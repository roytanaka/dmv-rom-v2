<?php

namespace App\Models;

use App\Policies\DocumentTagPolicy;
use Database\Factories\DocumentTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Tag in a Group's Document library (#717, spec #290, ADR-0030 §4): a label the Group
 * defines and puts on any of its Documents, so readers can filter across Folders. The name
 * is content, as-authored (ADR-0004), unique within the Group. Writes go through
 * {@see DocumentTagPolicy}.
 */
class DocumentTag extends Model
{
    /** @use HasFactory<DocumentTagFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_tag');
    }
}
