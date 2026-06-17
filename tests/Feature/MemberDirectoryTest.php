<?php

use App\Enums\Category;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Directory index (#169, PRD #167). The living roster as an Inertia payload: who
 * appears (the in-directory Category scope), what each row carries (no contact PII,
 * ever — not even for a viewer who would pass `viewContact` on a profile), and that
 * the page is behind auth. Asserted at the payload seam.
 */

it('redirects an unauthenticated request to login', function () {
    $this->get(route('directory'))->assertRedirect(route('login'));
});

it('lists only Members whose Category grants a directory listing', function () {
    $included = collect([Category::Active, Category::Honourary, Category::Sustaining, Category::Loa])
        ->mapWithKeys(fn (Category $c) => [$c->value => Member::factory()->category($c)->create()]);
    $excluded = collect([
        Category::Resigned, Category::Withdrawn, Category::Deceased,
        Category::PreActive, Category::Provisional,
    ])->mapWithKeys(fn (Category $c) => [$c->value => Member::factory()->category($c)->create()]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('directory'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->where('members', function (Collection $members) use ($included, $excluded) {
                $ids = $members->pluck('id')->all();

                return $included->every(fn (Member $m) => in_array($m->id, $ids, true))
                    && $excluded->every(fn (Member $m) => ! in_array($m->id, $ids, true));
            }));
});

it('carries name, standing, and Groups for each row', function () {
    $group = Group::factory()->create();
    $member = Member::factory()->category(Category::Loa)->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('directory'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', function (Collection $members) use ($member, $group) {
                $row = $members->firstWhere('id', $member->id);

                return $row['first_name'] === $member->first_name
                    && $row['last_name'] === $member->last_name
                    && $row['standing'] === Category::Loa->value
                    && $row['groups'][0]['name'] === $group->name;
            }));
});

it('never carries contact PII in the list, even for a viewer who could see it', function () {
    Member::factory()->count(2)->create();

    // Super-tier passes `viewContact` on any profile — yet the list still omits it.
    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('directory'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn (Collection $members) => $members->every(
                fn (array $m) => ! array_key_exists('email', $m) && ! array_key_exists('phone', $m)
            )));
});
