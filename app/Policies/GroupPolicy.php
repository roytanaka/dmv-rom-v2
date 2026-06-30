<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for officer edits on a Group (#191, PRD #186) — the first
 * group-officer write path, establishing the Form Request → policy → `can`-prop
 * convention for Groups (mirrors the NewsPolicy seam). Reading a Group's Overview
 * is org-open and so has no method here; the route's `auth` middleware is the only
 * gate for the read.
 *
 * Unlike the News and Meeting policies, the officer edits gated here (About Us and
 * banner selection) touch core Group attributes that exist for every Group
 * regardless of its capability flags, so there is deliberately no capability guard.
 *
 * The super-tier short-circuit lives in a single `Gate::before`
 * (AppServiceProvider) and is never re-checked here, so the method below reasons
 * only about the non-super-tier cases.
 */
class GroupPolicy
{
    /**
     * Who may edit a Group's About Us and pick its banner: a Secretary or Chair of
     * that Group. `canActAs` folds in Chair-implication (a Chair implies the
     * Group's officer roles except Treasurer), so a Chair without an explicit
     * Secretary role still qualifies. Authority never leaks across Groups — an
     * officer of a different Group is denied.
     */
    public function update(Member $actor, Group $group): bool
    {
        return $actor->canActAs(Role::Secretary, $group)
            || $actor->canActAs(Role::Chair, $group);
    }
}
