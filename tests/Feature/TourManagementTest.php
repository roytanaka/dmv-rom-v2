<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Maintaining a Group's Tour list and its kind mapping (#788, ADR-0033 §1, §4). A Vetting
 * officer, the Chair or super-tier adds, renames, retires, restores, reorders and deletes Tours,
 * sets open to all, and maps each Tour onto shift kinds — only while the Group runs vetting.
 * Deleting is refused while a qualification or Sign-up points at the Tour.
 */

/** A vetting Group (scheduling on, so it has shift kinds). */
function tourGroup(): Group
{
    return Group::factory()->program()->create(['has_vetting' => true]);
}

/** A Member of $group carrying an optional role. */
function tourMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Vetting officer add a Tour at the end of the sort order', function () {
    $group = tourGroup();
    Tour::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->post(route('groups.tours.store', ['group' => $group]), ['name' => 'Museum Highlights'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $added = $group->tours()->where('name', 'Museum Highlights')->sole();
    expect($added->active)->toBeTrue();
    expect($added->open_to_all)->toBeFalse();
    expect($added->sort_order)->toBe(1);
});

it('lets the Chair and super-tier add a Tour', function () {
    $group = tourGroup();
    $chair = tourMemberOf($group, Role::Chair);
    $super = Member::factory()->superTier()->create();

    $this->actingAs($chair)
        ->post(route('groups.tours.store', ['group' => $group]), ['name' => 'Family'])
        ->assertRedirect();
    $this->actingAs($super)
        ->post(route('groups.tours.store', ['group' => $group]), ['name' => 'ROMCAP'])
        ->assertRedirect();

    expect($group->tours()->pluck('name')->sort()->values()->all())->toBe(['Family', 'ROMCAP']);
});

it('lets a Vetting officer rename, retire and restore a Tour', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinos']);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['name' => 'Dinosaurs'])
        ->assertRedirect();
    expect($tour->refresh()->name)->toBe('Dinosaurs');

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['active' => false])
        ->assertRedirect();
    expect($tour->refresh()->active)->toBeFalse();

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['active' => true])
        ->assertRedirect();
    expect($tour->refresh()->active)->toBeTrue();
});

it('lets a Vetting officer set and clear open to all', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['open_to_all' => true])
        ->assertRedirect();
    expect($tour->refresh()->open_to_all)->toBeTrue();

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['open_to_all' => false])
        ->assertRedirect();
    expect($tour->refresh()->open_to_all)->toBeFalse();
});

it('lets a Vetting officer reorder Tours', function () {
    $group = tourGroup();
    $first = Tour::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = Tour::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('groups.tours.reorder', ['group' => $group]), ['ids' => [$second->id, $first->id]])
        ->assertRedirect();

    expect($second->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
});

it('rejects reordering a Tour of another Group', function () {
    $group = tourGroup();
    $foreign = Tour::factory()->create();
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('groups.tours.reorder', ['group' => $group]), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');
});

it('rejects a duplicate name in the same Group but allows it in another Group', function () {
    $group = tourGroup();
    Tour::factory()->create(['group_id' => $group->id, 'name' => 'Museum Highlights']);
    $other = tourGroup();
    $vetting = tourMemberOf($group, Role::Vetting);
    $otherVetting = tourMemberOf($other, Role::Vetting);

    $this->actingAs($vetting)
        ->post(route('groups.tours.store', ['group' => $group]), ['name' => 'Museum Highlights'])
        ->assertSessionHasErrors('name');

    $this->actingAs($otherVetting)
        ->post(route('groups.tours.store', ['group' => $other]), ['name' => 'Museum Highlights'])
        ->assertSessionHasNoErrors();
});

it('lets a rename keep the same name without a self-collision', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Family']);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['name' => 'Family'])
        ->assertSessionHasNoErrors();
});

it('lets a Vetting officer delete an unused Tour', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);
    $kind->tours()->attach($tour);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->delete(route('tours.destroy', ['tour' => $tour]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Tour::find($tour->id))->toBeNull();
    expect($kind->tours()->count())->toBe(0);
});

it('refuses to delete a Tour a qualification points at, even for super-tier', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);
    Qualification::factory()->create(['group_member_id' => $membership->id, 'tour_id' => $tour->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('tours.destroy', ['tour' => $tour]))
        ->assertSessionHasErrors('tour');

    expect(Tour::find($tour->id))->not->toBeNull();
});

it('refuses to delete a Tour a Sign-up records', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    SignUp::factory()->create(['tour_id' => $tour->id]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->delete(route('tours.destroy', ['tour' => $tour]))
        ->assertSessionHasErrors('tour');

    expect(Tour::find($tour->id))->not->toBeNull();
});

it('always lets a used Tour be retired', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    SignUp::factory()->create(['tour_id' => $tour->id]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['active' => false])
        ->assertSessionHasNoErrors();

    expect($tour->refresh()->active)->toBeFalse();
});

it('lets a Vetting officer map a Tour onto shift kinds, and clear the mapping', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $gallery = ShiftKind::factory()->create(['group_id' => $group->id]);
    $group_tour = ShiftKind::factory()->create(['group_id' => $group->id]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.shift-kinds.update', ['tour' => $tour]), ['shift_kinds' => [$gallery->id, $group_tour->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($tour->shiftKinds()->pluck('shift_kinds.id')->sort()->values()->all())->toBe([$gallery->id, $group_tour->id]);

    $this->actingAs($vetting)
        ->patch(route('tours.shift-kinds.update', ['tour' => $tour]), ['shift_kinds' => []])
        ->assertSessionHasNoErrors();
    expect($tour->shiftKinds()->count())->toBe(0);
});

it('rejects mapping a Tour onto another Group\'s shift kind', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $foreignKind = ShiftKind::factory()->create();
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->patch(route('tours.shift-kinds.update', ['tour' => $tour]), ['shift_kinds' => [$foreignKind->id]])
        ->assertSessionHasErrors('shift_kinds.0');
});

it('forbids a Scheduler and a plain member from every Tour write', function (?Role $role) {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);
    $actor = tourMemberOf($group, $role);

    $this->actingAs($actor)->post(route('groups.tours.store', ['group' => $group]), ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($actor)->patch(route('tours.update', ['tour' => $tour]), ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($actor)->patch(route('groups.tours.reorder', ['group' => $group]), ['ids' => [$tour->id]])->assertForbidden();
    $this->actingAs($actor)->patch(route('tours.shift-kinds.update', ['tour' => $tour]), ['shift_kinds' => [$kind->id]])->assertForbidden();
    $this->actingAs($actor)->delete(route('tours.destroy', ['tour' => $tour]))->assertForbidden();
})->with([
    'Scheduler' => [Role::Scheduler],
    'plain member' => [null],
]);

it('forbids a Vetting officer of another Group', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id]);
    $foreignVetting = tourMemberOf(tourGroup(), Role::Vetting);

    $this->actingAs($foreignVetting)
        ->patch(route('tours.update', ['tour' => $tour]), ['name' => 'Nope'])
        ->assertForbidden();
});

it('forbids managing Tours on a Group without vetting, even for its Chair', function () {
    $group = Group::factory()->program()->create(['has_vetting' => false]);
    $chair = tourMemberOf($group, Role::Chair);

    $this->actingAs($chair)
        ->post(route('groups.tours.store', ['group' => $group]), ['name' => 'Nope'])
        ->assertForbidden();
});

it('shows the Tours card to a Vetting officer, with each Tour\'s mapped kinds', function () {
    $group = tourGroup();
    $highlights = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Museum Highlights', 'open_to_all' => true, 'sort_order' => 0]);
    Tour::factory()->inactive()->create(['group_id' => $group->id, 'name' => 'Old tour', 'sort_order' => 1]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights slot']);
    $kind->tours()->attach($highlights);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTours', true)
            ->where('can.manageSettings', true)
            ->where('settings.tours.tours.0.name', 'Museum Highlights')
            ->where('settings.tours.tours.0.openToAll', true)
            ->where('settings.tours.tours.0.shiftKindIds', [$kind->id])
            ->where('settings.tours.tours.1.active', false)
            ->where('settings.tours.shiftKinds.0.name', 'Highlights slot')
            // A Vetting officer who is not a Scheduler gets no scheduling cards.
            ->where('settings.shiftKinds', null)
        );
});

it('shows a Scheduler each kind\'s mapped Tours on the Shift kinds card, but no Tours card', function () {
    $group = tourGroup();
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinosaurs']);
    $mapped = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Gallery/Theme', 'sort_order' => 0]);
    ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk', 'sort_order' => 1]);
    $mapped->tours()->attach($tour);
    $scheduler = tourMemberOf($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTours', false)
            ->where('settings.tours', null)
            ->where('settings.shiftKinds.0.tours', ['Dinosaurs'])
            ->where('settings.shiftKinds.1.tours', [])
        );
});

it('gives no Tours card on a Group without vetting', function () {
    $group = Group::factory()->program()->create(['has_vetting' => false]);
    $chair = tourMemberOf($group, Role::Chair);

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTours', false)
            ->where('settings.tours', null)
        );
});

it('opens the Settings tab to a Vetting officer of a Group without scheduling', function () {
    $group = Group::factory()->create(['has_vetting' => true, 'has_scheduling' => false]);
    $vetting = tourMemberOf($group, Role::Vetting);

    $this->actingAs($vetting)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('settings.tours.shiftKinds', []));
});
