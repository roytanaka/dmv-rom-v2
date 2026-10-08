<?php

namespace App\Models;

use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Document category (#724, spec #721, ADR-0030 §4): a heading that groups the Folders and
 * Documents directly inside one Folder, or at the library root (`folder_id` null). Never a
 * Folder level and never a read rule: who reads an item stays its top-level Folder's setting.
 * Managed under the DocumentPolicy's `manage` ability. `name` is content, as-authored
 * (ADR-0004).
 */
class DocumentCategory extends Model
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'folder_id',
        'name',
    ];

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Folder whose list this is; null for the library root's.
     *
     * @return BelongsTo<DocumentFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }
}
