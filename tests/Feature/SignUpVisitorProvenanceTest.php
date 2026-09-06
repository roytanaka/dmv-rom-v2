<?php

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * GDR's five visitor origins, summed on the server (#448, PRD #443, ADR-0023 §3). Beside the
 * visitor count, GDR's Sign-up carries five nullable integers — France and Europe, Quebec,
 * Toronto, rest of Canada, other countries — and the five must sum to the visitor count, a rule
 * the server enforces (legacy enforced it in a JavaScript alert only). One Group in fifteen
 * years: five columns on the shared table, switched on per Group, no breakdown machinery. It
 * rides the same write seam (PATCH on a Sign-up, Form Request → SignUpPolicy) and the same read
 * prop as the count. Prior art, and the fixtures this file leans on: SignUpExtraInteractionsTest.
 *
 * A fixed clock so the five-minute sign-out window is exact; the Shift ends at 13:00 UTC and
 * `provNow()` sits an hour past it, inside the window.
 */
function provNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 14:00');
}

/** GDR: a Group that collects a visitor count and its five-origin provenance split beside it. */
function gdrGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorProvenance()->create();
}

/** A Group that collects a visitor count but not provenance — no origin boxes. */
function noProvenanceGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

/** A Member of the given Group in good standing. */
function provMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    return $member;
}

/** A Shift on the Schedule, 10:00–13:00 org time on 2026-09-10. */
function provShift(Schedule $schedule): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-10 13:00'),
    ]);
}

/** Seat the Member on the Shift, returning their Sign-up. */
function provSeatOn(Shift $shift, Member $member): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

/** Set up GDR with one seated Member, returning [member, signUp]. */
function seatedOnGdr(): array
{
    $group = gdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = provShift($schedule);
    $member = provMemberOf($group);

    return [$member, provSeatOn($shift, $member)];
}

/** The five origins keyed as the write seam expects them, summing to the passed total. */
function provenance(int $franceEurope, int $quebec, int $toronto, int $restOfCanada, int $otherCountries): array
{
    return [
        'visitors_france_europe' => $franceEurope,
        'visitors_quebec' => $quebec,
        'visitors_toronto' => $toronto,
        'visitors_rest_of_canada' => $restOfCanada,
        'visitors_other_countries' => $otherCountries,
    ];
}

// --- The five origins land in their own columns ---------------------------------

it('records five origins that sum to the visitor count', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 20,
            ...provenance(8, 4, 5, 2, 1),
        ])
        ->assertRedirect();

    $fresh = $signUp->fresh();
    expect($fresh->visitor_count)->toBe(20)
        ->and($fresh->visitors_france_europe)->toBe(8)
        ->and($fresh->visitors_quebec)->toBe(4)
        ->and($fresh->visitors_toronto)->toBe(5)
        ->and($fresh->visitors_rest_of_canada)->toBe(2)
        ->and($fresh->visitors_other_countries)->toBe(1);
});

it('accepts all five zeroes against a visitor count of zero', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 0,
            ...provenance(0, 0, 0, 0, 0),
        ])
        ->assertRedirect();

    expect($signUp->fresh()->visitors_france_europe)->toBe(0)
        ->and($signUp->fresh()->visitor_count)->toBe(0);
});

// --- The sum rule, on the server ------------------------------------------------

it('refuses five origins that do not sum to the visitor count, naming both totals', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 20,
            ...provenance(8, 4, 5, 2, 5), // sums to 24, not 20
        ])
        ->assertSessionHasErrors([
            'visitors_france_europe' => trans('group.scheduling_panel.agenda.sign_out.provenance_sum', ['sum' => 24, 'count' => 20]),
        ]);

    expect($signUp->fresh()->visitor_count)->toBeNull()
        ->and($signUp->fresh()->visitors_france_europe)->toBeNull();
});

it('holds the sum rule against a request that bypasses the browser entirely', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    // A raw PATCH with no client validation in front of it — the server is the enforcer.
    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 10,
            ...provenance(1, 1, 1, 1, 1), // sums to 5
        ])
        ->assertSessionHasErrors('visitors_france_europe');

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('refuses four of five — nothing partial is saved', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 20,
            'visitors_france_europe' => 8,
            'visitors_quebec' => 4,
            'visitors_toronto' => 5,
            'visitors_rest_of_canada' => 3,
            // other_countries omitted
        ])
        ->assertSessionHasErrors('visitors_other_countries');

    expect($signUp->fresh()->visitors_france_europe)->toBeNull()
        ->and($signUp->fresh()->visitor_count)->toBeNull();
});

it('refuses a decimal in any of the five', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 20,
            ...provenance(8, 4, 5, 2, 1),
            'visitors_toronto' => 5.5,
        ])
        ->assertSessionHasErrors('visitors_toronto');

    expect($signUp->fresh()->visitors_toronto)->toBeNull();
});

it('refuses a negative in any of the five', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 20,
            ...provenance(8, 4, 5, 2, 1),
            'visitors_quebec' => -1,
        ])
        ->assertSessionHasErrors('visitors_quebec');

    expect($signUp->fresh()->visitors_quebec)->toBeNull();
});

// --- Provenance is GDR-only -----------------------------------------------------

it('refuses a provenance value on a Group that does not collect provenance', function () {
    $this->travelTo(provNow());

    $group = noProvenanceGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = provShift($schedule);
    $member = provMemberOf($group);
    $signUp = provSeatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'visitors_france_europe' => 9,
        ])
        ->assertSessionHasErrors('visitors_france_europe');

    expect($signUp->fresh()->visitors_france_europe)->toBeNull()
        ->and($signUp->fresh()->visitor_count)->toBeNull();
});

// --- Re-filing overwrites all five together ------------------------------------

it('overwrites all five origins together on a re-file', function () {
    $this->travelTo(provNow());
    [$member, $signUp] = seatedOnGdr();
    $signUp->update([
        'visitor_count' => 20,
        ...provenance(8, 4, 5, 2, 1),
    ]);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 12,
            ...provenance(3, 3, 3, 2, 1),
        ])
        ->assertRedirect();

    $fresh = $signUp->fresh();
    expect($fresh->visitor_count)->toBe(12)
        ->and($fresh->visitors_france_europe)->toBe(3)
        ->and($fresh->visitors_quebec)->toBe(3)
        ->and($fresh->visitors_toronto)->toBe(3)
        ->and($fresh->visitors_rest_of_canada)->toBe(2)
        ->and($fresh->visitors_other_countries)->toBe(1);
});

// --- The read surface: the switch and the seat-holder's own origins -------------

it('exposes the provenance switch as a Group capability', function () {
    $this->travelTo(provNow());

    $group = gdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $member = provMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.collectsVisitorProvenance', true));
});

it('leaves the provenance switch off for a Group that does not collect it', function () {
    $this->travelTo(provNow());

    $group = noProvenanceGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $member = provMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.collectsVisitorProvenance', false));
});

it('carries the seat-holder’s own origins on their Shift', function () {
    $this->travelTo(provNow());

    $group = gdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = provShift($schedule);
    $member = provMemberOf($group);
    $signUp = provSeatOn($shift, $member);
    $signUp->update(['visitor_count' => 20, ...provenance(8, 4, 5, 2, 1)]);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.visitors_france_europe', 8)
            ->where('scheduling.open.shifts.0.visitors_other_countries', 1));
});

it('withholds the origins from a reader holding no seat', function () {
    $this->travelTo(provNow());

    $group = gdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = provShift($schedule);
    $seated = provMemberOf($group);
    $signUp = provSeatOn($shift, $seated);
    $signUp->update(['visitor_count' => 20, ...provenance(8, 4, 5, 2, 1)]);

    $this->actingAs(provMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.visitors_france_europe', null));
});
