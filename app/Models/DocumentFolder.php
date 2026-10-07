<?php

namespace App\Models;

use App\Enums\DocumentVisibility;
use App\Policies\DocumentFolderPolicy;
use Database\Factories\DocumentFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A Folder in a Group's Document library (#714, spec #290, ADR-0030 §3). Folders form a tree
 * per Group, at most {@see self::MAX_DEPTH} levels deep; `parent_id` null is a top-level
 * Folder. A Document sits in one Folder or at the library root. Reads and writes go through
 * {@see DocumentFolderPolicy}. `name` is content, as-authored (ADR-0004).
 *
 * The tree helpers walk by explicit queries (strict mode forbids lazy loads). Trees are
 * small (a few dozen Folders, 5 levels), so a walk is a handful of queries at most.
 */
class DocumentFolder extends Model
{
    /** @use HasFactory<DocumentFolderFactory> */
    use HasFactory;

    /** The deepest a Folder may sit: a top-level Folder is depth 1 (ADR-0030 §3). */
    public const MAX_DEPTH = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
    ];

    /**
     * The Folders above this one, top-level first, this Folder excluded. The breadcrumb.
     *
     * @return Collection<int, DocumentFolder>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $parent = self::query()->findOrFail($parentId);
            $ancestors->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    /**
     * The top-level Folder this one sits under, or itself when it is top-level. Effective
     * visibility (ADR-0030 §5) is read from it.
     */
    public function topLevel(): DocumentFolder
    {
        return $this->ancestors()->first() ?? $this;
    }

    /**
     * Who reads this Folder and everything in it (ADR-0030 §5): its top-level Folder's setting.
     * The visibility ticket (#715) stores the setting on top-level Folders and reads it from
     * {@see topLevel()}; until then every Folder is `Group`.
     */
    public function visibility(): DocumentVisibility
    {
        return DocumentVisibility::Group;
    }

    /** How deep this Folder sits: 1 for a top-level Folder. */
    public function depth(): int
    {
        return $this->ancestors()->count() + 1;
    }

    /**
     * The ids of every Folder below this one, at any depth.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        return array_merge(...array_values($this->descendantLevels()));
    }

    /**
     * How many levels this Folder's subtree spans: 1 for a Folder with no subfolders. A move
     * adds it to the target's depth to check the limit.
     */
    public function subtreeHeight(): int
    {
        return count($this->descendantLevels()) + 1;
    }

    /**
     * The subtree below this Folder, one list of ids per level, nearest first.
     *
     * @return array<int, list<int>>
     */
    private function descendantLevels(): array
    {
        $levels = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();

            if ($frontier !== []) {
                $levels[] = $frontier;
            }
        }

        return $levels;
    }

    /**
     * True when the Folder holds no subfolders and no Documents: the only state it may be
     * deleted in.
     */
    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->documents()->exists();
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<DocumentFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<DocumentFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }
}
