<?php

use App\Enums\LifecycleState;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Http\Controllers\DmvSettingsController;
use App\Http\Controllers\GroupController;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Archive and unarchive a Group from DMV Settings (#845, PRD #836, ADR-0036 §2): the
 * super-tier archives a Group from its structure form and unarchives it the same way. An
 * archived Group leaves the rail and refuses writes. A Group holding a stewardship cannot be
 * archived. Nothing deletes a Group.
 *
 * Asserted from outside: the HTTP response, the session errors, the stored Group, the rail
 * and a representative write on the next request.
 */

/** The DMV root, inline, as the tree has it. */
function archiveRoot(): Group
{
    return Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
}

it('archives a Group and records who did it', function () {
    $root = archiveRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)
        ->from('/officer/settings/groups/youth-tours')
        ->patch('/officer/settings/groups/youth-tours/archive')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/youth-tours');

    $group->refresh();
    expect($group->lifecycle_state)->toBe(LifecycleState::Archived)
        ->and($group->updatedBy->is($actor))->toBeTrue();
});

it('unarchives a Group and records who did it', function () {
    $root = archiveRoot();
    $group = Group::factory()->program()->archived()->create(['parent_id' => $root->id, 'slug' => 'old-tours']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)
        ->from('/officer/settings/groups/old-tours')
        ->patch('/officer/settings/groups/old-tours/unarchive')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/old-tours');

    $group->refresh();
    expect($group->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($group->updatedBy->is($actor))->toBeTrue();
});

it('refuses to archive a Group that holds a stewardship, naming it', function () {
    $root = archiveRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records']);
    $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    $group->stewardships()->create(['function' => StewardshipFunction::OrgMail]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/records/archive')
        ->assertSessionHasErrors(['archive' => 'Records holds Member administration and Org-wide sender. Move the Stewardship first.']);

    expect($group->fresh()->lifecycle_state)->toBe(LifecycleState::Active);
});

it('names the stewardship in French', function () {
    $root = archiveRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records']);
    $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

    app()->setLocale('fr');
    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/records/archive')
        ->assertSessionHasErrors(['archive' => 'Records détient Administration des membres. Déplacez d’abord l’intendance.']);
});

it('refuses to archive or unarchive the root or a Container', function (string $action) {
    $root = archiveRoot();
    Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs']);
    $actor = Member::factory()->superTier()->create();
    $error = ['archive' => 'The root and Containers have no settings here.'];

    $this->actingAs($actor)->patch("/officer/settings/groups/programs/{$action}")->assertSessionHasErrors($error);
    $this->actingAs($actor)->patch("/officer/settings/groups/dmv/{$action}")->assertSessionHasErrors($error);

    expect(Group::where('slug', 'programs')->sole()->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($root->fresh()->lifecycle_state)->toBe(LifecycleState::Active);
})->with(['archive', 'unarchive']);

it('forbids archiving and unarchiving to anyone below the super-tier, the Group\'s own Chair included', function (string $action) {
    $root = archiveRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);
    $chair = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $chair->id]);
    GroupMemberRole::factory()->role(Role::Chair)->create(['group_member_id' => $membership->id]);

    $this->actingAs(Member::factory()->create())->patch("/officer/settings/groups/youth-tours/{$action}")->assertForbidden();
    $this->actingAs($chair)->patch("/officer/settings/groups/youth-tours/{$action}")->assertForbidden();

    expect($group->fresh()->lifecycle_state)->toBe(LifecycleState::Active);
})->with(['archive', 'unarchive']);

it('takes an archived Group off My Groups and Browse Groups, and puts it back on unarchive', function () {
    $root = archiveRoot();
    $programs = Group::factory()->container()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'display_order' => 1]);
    $group = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    $actor = Member::factory()->superTier()->create();
    $listed = fn (Assert $page) => $page->where('rail.otherGroups.items.0.children.0.groupId', 'youth-tours');
    $mine = fn (Collection $items) => $items->pluck('groupId')->contains('youth-tours');
    $notMine = fn (Collection $items) => ! $items->pluck('groupId')->contains('youth-tours');

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('rail.myGroups.items', $mine));
    $this->actingAs($actor)->get('/dashboard')->assertInertia($listed);

    $this->actingAs($actor)->patch('/officer/settings/groups/youth-tours/archive')->assertSessionHasNoErrors();

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('rail.myGroups.items', $notMine));
    $this->actingAs($actor)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->missing('rail.otherGroups.items.0.children'));

    $this->actingAs($actor)->patch('/officer/settings/groups/youth-tours/unarchive')->assertSessionHasNoErrors();

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('rail.myGroups.items', $mine));
    $this->actingAs($actor)->get('/dashboard')->assertInertia($listed);
});

it('keeps an expired time-boxed Group off My Groups, as it does an archived one', function () {
    $root = archiveRoot();
    $group = Group::factory()->project()->timeBoxed()->create([
        'parent_id' => $root->id, 'slug' => 'gala-2025', 'end_date' => now()->subDay()->toDateString(),
    ]);
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where(
        'rail.myGroups',
        fn ($myGroups) => ! collect($myGroups['items'] ?? [])->pluck('groupId')->contains('gala-2025'),
    ));
});

it('refuses writes to an archived Group and takes them again once unarchived', function () {
    $root = archiveRoot();
    $group = Group::factory()->program()->publicListing()->create([
        'parent_id' => $root->id, 'slug' => 'youth-tours', 'has_scheduling' => true, 'reminders_enabled' => true, 'reminder_lead_days' => 5,
    ]);
    $chair = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $chair->id]);
    GroupMemberRole::factory()->role(Role::Chair)->create(['group_member_id' => $membership->id]);
    $actor = Member::factory()->superTier()->create();
    $write = fn (int $days) => $this->actingAs($chair)
        ->patch(route('groups.reminders.update', ['group' => $group]), ['reminders_enabled' => true, 'reminder_lead_days' => $days]);

    $this->actingAs($actor)->patch('/officer/settings/groups/youth-tours/archive')->assertSessionHasNoErrors();
    $write(3)->assertForbidden();
    expect($group->fresh()->reminder_lead_days)->toBe(5);

    $this->actingAs($actor)->patch('/officer/settings/groups/youth-tours/unarchive')->assertSessionHasNoErrors();
    $write(3)->assertSessionHasNoErrors();
    expect($group->fresh()->reminder_lead_days)->toBe(3);
});

it('has no way to delete a Group', function () {
    $groupDeletes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('DELETE', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => preg_match('#(^|/)groups/\{group(:[a-z]+)?\}$#', $uri) === 1);

    expect($groupDeletes->all())->toBe([])
        ->and(method_exists(DmvSettingsController::class, 'destroy'))->toBeFalse()
        ->and(method_exists(GroupController::class, 'destroy'))->toBeFalse();
});
