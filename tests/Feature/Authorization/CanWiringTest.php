<?php

use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Two-tier `can` wiring (#151, ADR-0017 §9): a coarse app-wide `auth.can` shared
 * to every page for chrome/nav, plus a fine-grained per-resource `can` computed
 * by policy on the page that needs it. Both are UI hints — the server enforces
 * every action — so these tests only assert the hints are surfaced correctly.
 */

it('shares a coarse auth.can with the member-admin hint off for an ordinary member', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.administerMembers', false));
});

it('turns the coarse member-admin hint on for a Records officer', function () {
    $records = Group::factory()->create();
    GroupStewardship::factory()
        ->stewarding(StewardshipFunction::MemberAdmin)
        ->create(['group_id' => $records->id]);

    $officer = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $officer->id]);

    $this->actingAs($officer)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.administerMembers', true));
});

it('turns the coarse member-admin hint on for a super-tier member', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.administerMembers', true));
});

it('passes a fine-grained per-resource can to the profile page', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/settings/profile')
        ->assertInertia(fn (Assert $page) => $page->where('can.update', true));
});
