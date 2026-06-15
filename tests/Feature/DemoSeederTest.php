<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Database\Seeders\DemoSeeder;

/*
 * The curated demo data (PRD #139, slice 1 / #140): the faker-free, idempotent
 * spine seeded into staging by hand before a board pitch. These assertions check
 * external, observable invariants — the tree resolves, no membership is orphaned,
 * and re-seeding heals rather than duplicates — rather than specific curated
 * content, so the suite survives the tree growing in later slices.
 */

beforeEach(fn () => $this->seed(DemoSeeder::class));

it('builds a tree whose relationships resolve from the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();

    expect($root->parent)->toBeNull()
        ->and($root->children)->not->toBeEmpty()
        ->and($root->children->every(fn (Group $child) => $child->parent->is($root)))->toBeTrue();
});

it('seeds the spine: standing committee, program, and members under the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $committee = Group::where('slug', DemoSeeder::COMMITTEE)->firstOrFail();
    $program = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();

    expect($committee->parent->is($root))->toBeTrue()
        ->and($program->parent->is($root))->toBeTrue()
        ->and(Member::count())->toBeGreaterThanOrEqual(2)
        ->and(GroupMember::count())->toBeGreaterThanOrEqual(2);
});

it('leaves no membership orphaned — every one references a real Group and Member', function () {
    GroupMember::with(['group', 'member'])->get()->each(function (GroupMember $membership) {
        expect($membership->group)->not->toBeNull()
            ->and($membership->member)->not->toBeNull();
    });
});

it('is idempotent — re-seeding leaves row counts unchanged', function () {
    $counts = fn () => [
        'groups' => Group::count(),
        'members' => Member::count(),
        'memberships' => GroupMember::count(),
    ];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});
