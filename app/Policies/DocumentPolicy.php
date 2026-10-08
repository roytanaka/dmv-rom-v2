<?php

namespace App\Policies;

use App\Enums\DocumentVisibility;
use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for a Group's Document library (#712, spec #290, ADR-0030).
 *
 * Read: a member of the owning Group, or any Member when the Document's effective
 * visibility is `members`. A Private Group's library is closed to non-members (ADR-0019).
 * Parentage never grants a read (ADR-0030 §6): a parent Group's Chair is a non-member here.
 *
 * Manage: the Group's Librarian or Chair (Chair-implication via {@see Member::canActAs()}).
 *
 * Both hold only while the Group has the documents capability. The super-tier passes through
 * the `Gate::before` short-circuit (AppServiceProvider) and is never re-checked here; the
 * routes refuse a Group with the capability off for everyone, super-tier included.
 */
class DocumentPolicy
{
    /**
     * Who may download a Document (or open a link Document). Asked on every download, so a
     * role or membership lost since the page loaded no longer opens the file.
     */
    public function download(Member $actor, Document $document): bool
    {
        return self::reads($actor, $document->group, $document->visibility());
    }

    /**
     * Who may manage a Group's library: the named Group-level ability the Folder
     * policy and the `can.manageDocuments` hint defer to.
     */
    public function manage(Member $actor, Group $group): bool
    {
        return $this->managesLibraryOf($actor, $group);
    }

    /**
     * Who may add Documents to a Group's library.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $this->managesLibraryOf($actor, $group);
    }

    /**
     * Who may move a Document to another Folder of its Group, or to the root (#714).
     */
    public function move(Member $actor, Document $document): bool
    {
        return $this->managesLibraryOf($actor, $document->group);
    }

    /**
     * Who may edit a Document's title and description, or upload a new file over it (#713).
     */
    public function update(Member $actor, Document $document): bool
    {
        return $this->managesLibraryOf($actor, $document->group);
    }

    /**
     * Who may delete a Document (#713).
     */
    public function delete(Member $actor, Document $document): bool
    {
        return $this->managesLibraryOf($actor, $document->group);
    }

    /**
     * The shared manage predicate: the Group's Librarian (or Chair, folded in by
     * {@see Member::canActAs()}), while the documents capability is on. Edit, replace, move
     * and delete abilities reuse it.
     */
    private function managesLibraryOf(Member $actor, Group $group): bool
    {
        return $group->has_documents
            && $actor->canActAs(Role::Librarian, $group);
    }

    /**
     * The shared read rule, for a Document or a Folder ({@see DocumentFolderPolicy::view()})
     * of the given effective visibility: a member of the Group, or any Member when it is
     * shared with members and the Group is not Private, while the capability is on.
     */
    public static function reads(Member $actor, Group $group, DocumentVisibility $visibility): bool
    {
        if (! $group->has_documents) {
            return false;
        }

        if ($actor->membershipIn($group) !== null) {
            return true;
        }

        return $group->listing_visibility !== ListingVisibility::Private
            && $visibility === DocumentVisibility::Members;
    }
}
