<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\DocumentTag;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's Tags (#717, spec #290, ADR-0030 §4). Every Tag write (create,
 * rename, delete, and setting a Document's Tags) is a library manage action, so each defers
 * to the DocumentPolicy's manage rule: the Group's Librarian or Chair, while the documents
 * capability is on. Reading a Tag rides on reading its Documents.
 *
 * The super-tier passes through `Gate::before`; the Form Requests refuse a Group with the
 * capability off for everyone, super-tier included.
 */
class DocumentTagPolicy
{
    public function create(Member $actor, Group $group): bool
    {
        return $actor->can('manage', [Document::class, $group]);
    }

    public function update(Member $actor, DocumentTag $tag): bool
    {
        return $actor->can('manage', [Document::class, $tag->group]);
    }

    public function delete(Member $actor, DocumentTag $tag): bool
    {
        return $actor->can('manage', [Document::class, $tag->group]);
    }

    /**
     * Who may set which Tags a Document carries.
     */
    public function assign(Member $actor, Document $document): bool
    {
        return $actor->can('manage', [Document::class, $document->group]);
    }
}
