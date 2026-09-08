<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;
use App\Models\News;

/**
 * Authorization for the org-wide news feed (#155, ADR-0017 §5). Reading the feed
 * is open to every logged-in member and so has no method here — the route's `auth`
 * middleware is the only gate. Writes are capability-scoped: a member may post,
 * edit, or delete only as a news-editor of the announcements-on Group that owns
 * the item.
 *
 * The super-tier short-circuit lives in a single `Gate::before` (AppServiceProvider)
 * and is never re-checked here, so every method below reasons only about the
 * non-super-tier cases.
 */
class NewsPolicy
{
    /**
     * Who may post a new item on behalf of a Group: a member who can act as
     * news-editor of that Group ({@see Member::canActAs()}, which folds in
     * Chair-implication), and only while the Group's announcements capability is
     * on. The capability guard matters because Chair-implication would otherwise
     * grant news-editor on a non-announcements Group the member chairs.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $this->canEditNewsFor($actor, $group);
    }

    /**
     * Who may edit an item: a news-editor of the Group that posted it. Authority
     * never leaks across Groups — an editor of a different Group is denied.
     */
    public function update(Member $actor, News $news): bool
    {
        return $this->canEditNewsFor($actor, $news->postingGroup);
    }

    /**
     * Who may delete an item: same as edit — a news-editor of the posting Group.
     */
    public function delete(Member $actor, News $news): bool
    {
        return $this->canEditNewsFor($actor, $news->postingGroup);
    }

    /**
     * The shared predicate: the actor can act as news-editor of the Group, and the
     * Group's announcements capability is on. Both are required so a Chair of a
     * non-announcements Group (whom Chair-implication would report as news-editor)
     * cannot post — there is nothing to post to.
     */
    private function canEditNewsFor(Member $actor, Group $group): bool
    {
        return $group->has_announcements
            && $actor->canActAs(Role::NewsEditor, $group);
    }
}
