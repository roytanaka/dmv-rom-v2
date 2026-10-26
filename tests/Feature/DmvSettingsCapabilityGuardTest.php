<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\News;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Tour;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Turn a capability off from DMV Settings (#844, PRD #836, ADR-0036 §5): the save is blocked
 * while a Member of the Group holds a role that needs the capability, naming the role. With no
 * such role it saves, and nothing behind the flag is deleted: turning it back on makes the
 * records reachable again. The form counts the records behind each capability that is on.
 *
 * Asserted from outside: the HTTP response, the session errors, the Inertia props and the
 * stored rows.
 */

/** A Group under a fresh root with every capability on, at slug `youth-tours`. */
function guardGroup(): Group
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);

    return Group::factory()->program()->create([
        'parent_id' => $root->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours',
        'has_meetings' => true, 'has_documents' => true, 'has_scheduling' => true,
        'has_vetting' => true, 'has_announcements' => true, 'has_bookings' => true,
    ]);
}

/** A Member of $group holding $role. */
function guardRoleHolder(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** The structure form as saved, with $overrides. */
function guardPayload(Group $group, array $overrides = []): array
{
    return [
        'name' => $group->name,
        'parent_id' => $group->parent_id,
        'kind' => $group->kind->value,
        'listing_visibility' => $group->listing_visibility->value,
        'display_order' => $group->display_order,
        'logo_key' => null,
        'time_boxed' => false,
        'has_meetings' => $group->has_meetings,
        'has_documents' => $group->has_documents,
        'has_scheduling' => $group->has_scheduling,
        'has_vetting' => $group->has_vetting,
        'has_announcements' => $group->has_announcements,
        'has_bookings' => $group->has_bookings,
        'collects_visitor_count' => false,
        'collects_extra_interactions' => false,
        'collects_visitor_provenance' => false,
        'visitor_figures_await_booking' => false,
        'hours_multiplier' => '1.000',
        ...$overrides,
    ];
}

it('blocks turning a capability off while a Member holds a role that needs it, naming the role', function (string $flag, Role $role, string $label) {
    $group = guardGroup();
    guardRoleHolder($group, $role);

    $this->actingAs(Member::factory()->superTier()->create())
        ->from('/officer/settings/groups/youth-tours')
        ->patch('/officer/settings/groups/youth-tours', guardPayload($group, [$flag => false]))
        ->assertRedirect('/officer/settings/groups/youth-tours')
        ->assertSessionHasErrors([$flag => "{$label} needs this capability. Reassign the role first."]);

    expect($group->fresh()->{$flag})->toBeTrue();
})->with([
    'scheduling' => ['has_scheduling', Role::Scheduler, 'Scheduler'],
    'vetting' => ['has_vetting', Role::Vetting, 'Vetting'],
    'documents' => ['has_documents', Role::Librarian, 'Librarian'],
    'announcements' => ['has_announcements', Role::NewsEditor, 'News Editor'],
    'bookings' => ['has_bookings', Role::Booker, 'Booker'],
]);

it('names the role in French', function () {
    $group = guardGroup();
    guardRoleHolder($group, Role::Scheduler);
    app()->setLocale('fr');

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', guardPayload($group, ['has_scheduling' => false]))
        ->assertSessionHasErrors(['has_scheduling' => 'Responsable horaire a besoin de cette fonction. Réattribuez d’abord le rôle.']);
});

it('ignores roles held on other Groups, core roles and flags left on', function () {
    $group = guardGroup();
    guardRoleHolder($group, Role::Chair);
    guardRoleHolder($group, Role::Booker);
    guardRoleHolder(Group::factory()->program()->create(), Role::Scheduler);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', guardPayload($group, ['has_scheduling' => false, 'has_meetings' => false]))
        ->assertSessionHasNoErrors();

    $group->refresh();
    expect($group->has_scheduling)->toBeFalse()
        ->and($group->has_meetings)->toBeFalse()
        ->and($group->has_bookings)->toBeTrue();
});

it('keeps the records behind a capability turned off, and reaches them again once it is back on', function () {
    $group = guardGroup();
    $document = Document::factory()->create(['group_id' => $group->id]);
    $documentsTab = route('groups.show', ['group' => $group, 'section' => 'documents']);

    $this->actingAs(Member::factory()->superTier()->create())->get($documentsTab)->assertOk();

    $this->patch('/officer/settings/groups/youth-tours', guardPayload($group, ['has_documents' => false]))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('documents', ['id' => $document->id, 'group_id' => $group->id]);
    $this->get($documentsTab)->assertNotFound();

    $this->patch('/officer/settings/groups/youth-tours', guardPayload($group->fresh(), ['has_documents' => true]))
        ->assertSessionHasNoErrors();
    $this->get($documentsTab)->assertOk();
});

it('counts the records behind each capability that is on', function () {
    $group = guardGroup();
    $group->update(['has_vetting' => false]);
    Meeting::factory()->count(2)->create(['group_id' => $group->id]);
    Document::factory()->count(3)->create(['group_id' => $group->id]);
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    Shift::factory()->count(4)->create(['schedule_id' => $schedule->id]);
    News::factory()->count(5)->create(['posting_group_id' => $group->id]);
    Booking::factory()->create(['booking_type_id' => BookingType::factory()->create(['group_id' => $group->id])->id]);
    // Another Group's records count for that Group only.
    Document::factory()->create(['group_id' => Group::factory()->create(['has_documents' => true])->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/youth-tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', [
                'has_meetings' => ['count' => 2, 'key' => 'dmv_settings.count.meetings'],
                'has_documents' => ['count' => 3, 'key' => 'dmv_settings.count.documents'],
                'has_scheduling' => ['count' => 4, 'key' => 'dmv_settings.count.shifts'],
                'has_announcements' => ['count' => 5, 'key' => 'dmv_settings.count.news'],
                'has_bookings' => ['count' => 1, 'key' => 'dmv_settings.count.bookings'],
            ]));
});

it('counts the Tours behind vetting', function () {
    $group = guardGroup();
    Tour::factory()->count(2)->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/youth-tours')
        ->assertInertia(fn (Assert $page) => $page->where('counts.has_vetting', ['count' => 2, 'key' => 'dmv_settings.count.tours']));
});
