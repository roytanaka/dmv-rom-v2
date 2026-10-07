<?php

namespace App\Policies;

use App\Enums\DocumentVisibility;
use App\Enums\ListingVisibility;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for the Folders of a Group's Document library (#714, spec #290, ADR-0030 §3).
 *
 * View: the same rule as a Document download ({@see DocumentPolicy::download()}), on the
 * Folder's effective visibility. Manage (create, rename, move, delete): the Document library's
 * manage predicate, reused through the DocumentPolicy's `create` ability so the two never drift.
 *
 * The super-tier passes through the `Gate::before` short-circuit; the routes and Form Requests
 * refuse a Group with the documents capability off for everyone.
 */
class DocumentFolderPolicy
{
    /**
     * Who may open a Folder and see it listed.
     */
    public function view(Member $actor, DocumentFolder $folder): bool
    {
        $group = $folder->group;

        if (! $group->has_documents) {
            return false;
        }

        if ($actor->membershipIn($group) !== null) {
            return true;
        }

        return $group->listing_visibility !== ListingVisibility::Private
            && $folder->visibility() === DocumentVisibility::Members;
    }

    /**
     * Who may add a Folder to a Group's library.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $actor->can('create', [Document::class, $group]);
    }

    /**
     * Who may rename or move a Folder.
     */
    public function update(Member $actor, DocumentFolder $folder): bool
    {
        return $actor->can('create', [Document::class, $folder->group]);
    }

    /**
     * Who may delete a Folder. Emptiness is a separate refusal with its own message.
     */
    public function delete(Member $actor, DocumentFolder $folder): bool
    {
        return $actor->can('create', [Document::class, $folder->group]);
    }
}
