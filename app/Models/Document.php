<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\DocumentVisibility;
use App\Policies\DocumentPolicy;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Document in a Group's Document library (#712, spec #290, ADR-0030): a file or a link,
 * at the library root or in one Folder. Reads and writes go through {@see DocumentPolicy}.
 *
 * `title` and `description` are content, as-authored (ADR-0004). A file's bytes live on the
 * private disk under `storage_path`; `original_filename` is the name a download hands back.
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'folder_id',
        'title',
        'description',
        'kind',
        'url',
        'original_filename',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
        'uploaded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Who reads this Document (ADR-0030 §5): its Folder's effective setting (read from the
     * top-level Folder, {@see DocumentFolder::visibility()}), or `Group` at the library root.
     * A list that already holds the Folder sets the `folder` relation to skip the query.
     */
    public function visibility(): DocumentVisibility
    {
        if ($this->folder_id === null) {
            return DocumentVisibility::Group;
        }

        $folder = $this->relationLoaded('folder') ? $this->folder : DocumentFolder::query()->findOrFail($this->folder_id);

        return $folder->visibility();
    }

    /**
     * The name a reader sees: the title, or the filename when there is none.
     */
    public function displayName(): string
    {
        return $this->title ?? $this->original_filename ?? '';
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Folder this Document sits in; null at the library root.
     *
     * @return BelongsTo<DocumentFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'uploaded_by_id');
    }

    /**
     * The Group's Tags this Document carries (#717, ADR-0030 §4).
     *
     * @return BelongsToMany<DocumentTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'document_tag');
    }

    /**
     * The access log: one row per download.
     *
     * @return HasMany<DocumentDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(DocumentDownload::class);
    }
}
