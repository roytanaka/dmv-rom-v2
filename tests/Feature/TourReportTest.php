<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\ExhibitionRevenue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tour Summary and Tour Detail (#800, ADR-0032 §12). Per booking type: tours (one per Sign-up on a
 * Booking Shift), visitors (the Bookings' expected visitors) and Earned (correction wins). A
 * group-tour subtotal, then a grand total that adds the scheduled tours (Sign-ups on the Group's
 * other Shifts and their visitor counts) and the exhibition revenue. For one month or the fiscal
 * year to date, behind the hours-report gate.
 *
 * Frozen on 2026-01-15, inside Fiscal 2026 (2025-04 through 2026-03).
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00', config('app.org_timezone')));
});

/** A Member of $group carrying an optional role. */
function tourReportMember(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/**
 * A Group running bookings with two Tours and two types, and a fixed set of Bookings, scheduled
 * tours and exhibition revenue. The worked figures are in the tests below.
 *
 * @return array{Group, array<string, Booking>}
 */
function tourReportGroup(): array
{
    $group = Group::factory()->program()->create(['name' => 'Docents', 'has_bookings' => true, 'group_tour_label' => 'Group tours']);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    $group = $group->fresh();

    $egypt = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Ancient Egypt']);
    $dinos = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinosaurs']);
    $paid = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid', 'rate_per_visitor' => '2.50', 'rate_per_docent_hour' => '15.00', 'sort_order' => 0]);
    $free = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Free', 'rate_per_visitor' => '0.00', 'rate_per_docent_hour' => '0.00', 'sort_order' => 1]);

    $book = function (string $day, Tour $tour, BookingType $type, int $visitors, int $docents) use ($group): Booking {
        $booking = Booking::book($group, CarbonImmutable::parse("{$day} 15:00"), CarbonImmutable::parse("{$day} 16:00"), max($docents, 1), [
            'tour_id' => $tour->id,
            'booking_type_id' => $type->id,
            'client' => 'Client '.$day,
            'visitors' => $visitors,
        ]);
        for ($i = 0; $i < $docents; $i++) {
            SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => tourReportMember($group)->id]);
        }

        return $booking;
    };

    $bookings = [
        // January: 2.50 × 20 + 15.00 × 2 docent-hours = 80.00
        'jan_egypt_paid' => $book('2026-01-08', $egypt, $paid, 20, 2),
        // January: worked out 2.50 × 10 + 15.00 = 40.00, corrected to 35.00
        'jan_dinos_paid' => $book('2026-01-09', $dinos, $paid, 10, 1),
        // January: free, 0.00
        'jan_egypt_free' => $book('2026-01-10', $egypt, $free, 15, 1),
        // December: 2.50 × 30 + 15.00 = 90.00
        'dec_egypt_paid' => $book('2025-12-05', $egypt, $paid, 30, 1),
        // February, after "to date": 2.50 × 10 = 25.00, no Sign-ups
        'feb_egypt_paid' => $book('2026-02-05', $egypt, $paid, 10, 0),
    ];
    $bookings['jan_dinos_paid']->correctEarned('35.00');

    // Scheduled tours in January: one daily Shift with two Sign-ups, 12 and 8 visitors.
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    $daily = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-01-12 15:00'),
        'ends_at' => CarbonImmutable::parse('2026-01-12 16:00'),
        'capacity' => 2,
    ]);
    SignUp::factory()->create(['shift_id' => $daily->id, 'member_id' => tourReportMember($group)->id, 'visitor_count' => 12]);
    SignUp::factory()->create(['shift_id' => $daily->id, 'member_id' => tourReportMember($group)->id, 'visitor_count' => 8]);

    // Another Group's Shift in January stays out.
    $other = Shift::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-01-12 15:00'),
        'ends_at' => CarbonImmutable::parse('2026-01-12 16:00'),
    ]);
    SignUp::factory()->create(['shift_id' => $other->id, 'visitor_count' => 50]);

    return [$group, $bookings];
}

/** The Inertia props of a report page, as $viewer reads them. */
function tourReportProps(Member $viewer, string $route, Group $group, array $query = []): array
{
    $props = [];
    test()->actingAs($viewer)
        ->get(route($route, ['group' => $group, ...$query]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

// --- Tour Summary: one month ------------------------------------------------

it('totals a month by booking type, with the group-tour subtotal and the grand total', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $props = tourReportProps($statistician, 'groups.hours.tour-summary', $group, ['month' => '202601']);

    expect($props['types'])->toBe([
        ['id' => $props['types'][0]['id'], 'name' => 'Tour Paid', 'tours' => 3, 'visitors' => 30, 'earned' => '115.00'],
        ['id' => $props['types'][1]['id'], 'name' => 'Tour Free', 'tours' => 1, 'visitors' => 15, 'earned' => '0.00'],
    ])
        ->and($props['group_tours'])->toBe(['tours' => 4, 'visitors' => 45, 'earned' => '115.00'])
        ->and($props['scheduled'])->toBe(['tours' => 2, 'visitors' => 20])
        ->and($props['exhibition_revenue'])->toBe('0.00')
        ->and($props['grand_total'])->toBe(['tours' => 6, 'visitors' => 65, 'earned' => '115.00']);
});

it('uses the corrected Earned where one is set, and the worked-out figure once cleared', function () {
    [$group, $bookings] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $bookings['jan_dinos_paid']->correctEarned(null);

    // Tour Paid: 80.00 + 40.00 worked out
    expect(tourReportProps($statistician, 'groups.hours.tour-summary', $group, ['month' => '202601'])['types'][0]['earned'])
        ->toBe('120.00');
});

it('defaults to the current month', function () {
    [$group] = tourReportGroup();

    $props = tourReportProps(tourReportMember($group, Role::Chair), 'groups.hours.tour-summary', $group);

    expect($props['period'])->toMatchArray(['kind' => 'month', 'year_month' => '202601'])
        ->and($props['group_tours'])->toBe(['tours' => 4, 'visitors' => 45, 'earned' => '115.00']);
});

// --- Fiscal year to date ----------------------------------------------------

it('totals the fiscal year to date, leaving out the months still to come', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);
    ExhibitionRevenue::enter($group, '202601', '100.00');
    ExhibitionRevenue::enter($group, '202512', '50.00');
    ExhibitionRevenue::enter($group, '202504', '7.25');
    // Fiscal 2025, outside the window.
    ExhibitionRevenue::enter($group, '202503', '999.00');

    $props = tourReportProps($statistician, 'groups.hours.tour-summary', $group, ['fy' => '2026']);

    expect($props['period'])->toMatchArray(['kind' => 'year', 'fiscal_year' => 2026])
        ->and($props['types'][0])->toMatchArray(['name' => 'Tour Paid', 'tours' => 4, 'visitors' => 60, 'earned' => '205.00'])
        ->and($props['types'][1])->toMatchArray(['name' => 'Tour Free', 'tours' => 1, 'visitors' => 15, 'earned' => '0.00'])
        ->and($props['group_tours'])->toBe(['tours' => 5, 'visitors' => 75, 'earned' => '205.00'])
        ->and($props['scheduled'])->toBe(['tours' => 2, 'visitors' => 20])
        ->and($props['exhibition_revenue'])->toBe('157.25')
        ->and($props['grand_total'])->toBe(['tours' => 7, 'visitors' => 95, 'earned' => '362.25']);
});

// --- Tour Detail ------------------------------------------------------------

it('splits the figures by booking type and Tour', function () {
    [$group] = tourReportGroup();

    $props = tourReportProps(tourReportMember($group, Role::Statistician), 'groups.hours.tour-detail', $group, ['month' => '202601']);

    $strip = fn (array $rows) => array_map(fn (array $row) => array_diff_key($row, ['id' => true]), $rows);

    expect(array_column($props['detail'], 'name'))->toBe(['Tour Paid', 'Tour Free'])
        ->and($strip($props['detail'][0]['tours']))->toBe([
            ['name' => 'Ancient Egypt', 'tours' => 2, 'visitors' => 20, 'earned' => '80.00'],
            ['name' => 'Dinosaurs', 'tours' => 1, 'visitors' => 10, 'earned' => '35.00'],
        ])
        ->and($props['detail'][0]['total'])->toBe(['tours' => 3, 'visitors' => 30, 'earned' => '115.00'])
        ->and($strip($props['detail'][1]['tours']))->toBe([
            ['name' => 'Ancient Egypt', 'tours' => 1, 'visitors' => 15, 'earned' => '0.00'],
        ])
        ->and($props['grand_total'])->toBe(['tours' => 6, 'visitors' => 65, 'earned' => '115.00']);
});

it('splits the fiscal year to date by type and Tour', function () {
    [$group] = tourReportGroup();

    $props = tourReportProps(tourReportMember($group, Role::Statistician), 'groups.hours.tour-detail', $group, ['fy' => '2026']);

    // Egypt, paid: January 80.00 + December 90.00; February is still to come.
    expect($props['detail'][0]['tours'][0])->toMatchArray(['name' => 'Ancient Egypt', 'tours' => 3, 'visitors' => 50, 'earned' => '170.00']);
});

// --- Exhibition revenue -----------------------------------------------------

it('lets the Statistician enter the month\'s exhibition revenue, which joins the grand total', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $this->actingAs($statistician)
        ->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => '120.50'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $props = tourReportProps($statistician, 'groups.hours.tour-summary', $group, ['month' => '202601']);

    expect($props['exhibition'])->toBe(['can_enter' => true, 'amount' => '120.50'])
        ->and($props['exhibition_revenue'])->toBe('120.50')
        ->and($props['grand_total']['earned'])->toBe('235.50');
});

it('replaces the month\'s figure on a second entry and clears it with null', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $this->actingAs($statistician)->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => '10']);
    $this->actingAs($statistician)->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => '20']);

    expect(ExhibitionRevenue::where('group_id', $group->id)->sole()->amount)->toBe('20.00');

    $this->actingAs($statistician)->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => null]);

    expect(tourReportProps($statistician, 'groups.hours.tour-summary', $group, ['month' => '202601'])['exhibition'])
        ->toBe(['can_enter' => true, 'amount' => null]);
});

it('keeps exhibition revenue per Group', function () {
    [$group] = tourReportGroup();
    $other = Group::factory()->program()->create(['has_bookings' => true]);
    ExhibitionRevenue::enter($other, '202601', '500.00');

    expect(tourReportProps(tourReportMember($group, Role::Statistician), 'groups.hours.tour-summary', $group, ['month' => '202601'])['exhibition_revenue'])
        ->toBe('0.00');
});

it('lets the Chair enter exhibition revenue', function () {
    [$group] = tourReportGroup();

    $this->actingAs(tourReportMember($group, Role::Chair))
        ->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => '5'])
        ->assertSessionHasNoErrors();

    expect(ExhibitionRevenue::where('group_id', $group->id)->sole()->amount)->toBe('5.00');
});

it('refuses exhibition revenue from a Booker or an ordinary Member', function (?Role $role) {
    [$group] = tourReportGroup();

    $this->actingAs(tourReportMember($group, $role))
        ->put(route('groups.exhibition-revenue.update', $group), ['year_month' => '202601', 'amount' => '5'])
        ->assertForbidden();

    expect(ExhibitionRevenue::count())->toBe(0);
})->with([
    'Booker' => [Role::Booker],
    'Member' => [null],
]);

it('shows a parent Group\'s Chair the report without the exhibition revenue form', function () {
    [$group] = tourReportGroup();
    $parent = Group::factory()->create();
    $group->update(['parent_id' => $parent->id]);

    expect(tourReportProps(tourReportMember($parent, Role::Chair), 'groups.hours.tour-summary', $group->fresh(), ['month' => '202601'])['exhibition']['can_enter'])
        ->toBeFalse();
});

it('validates the exhibition revenue', function (array $input, string $field) {
    [$group] = tourReportGroup();

    $this->actingAs(tourReportMember($group, Role::Statistician))
        ->put(route('groups.exhibition-revenue.update', $group), $input)
        ->assertSessionHasErrors($field);
})->with([
    'negative' => [['year_month' => '202601', 'amount' => '-1'], 'amount'],
    'three decimals' => [['year_month' => '202601', 'amount' => '1.234'], 'amount'],
    'bad month' => [['year_month' => '202613', 'amount' => '1'], 'year_month'],
]);

// --- CSV --------------------------------------------------------------------

/** @return list<list<string>> */
function tourCsvRows(string $csv): array
{
    $csv = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;

    return collect(explode("\n", trim($csv)))
        ->map(fn (string $line) => str_getcsv($line, ',', '"', ''))
        ->all();
}

it('exports the Tour Summary as CSV', function () {
    [$group] = tourReportGroup();
    ExhibitionRevenue::enter($group, '202601', '100.00');

    $response = $this->actingAs(tourReportMember($group, Role::Statistician))
        ->get(route('groups.hours.tour-summary.csv', ['group' => $group, 'month' => '202601']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain("{$group->slug}-tour-summary-202601.csv")
        ->and(tourCsvRows($response->streamedContent()))->toBe([
            ['Type', 'Tours', 'Visitors', 'Earned'],
            ['Tour Paid', '3', '30', '115.00'],
            ['Tour Free', '1', '15', '0.00'],
            ['Group tours', '4', '45', '115.00'],
            ['Scheduled tours', '2', '20', ''],
            ['Exhibition revenue', '', '', '100.00'],
            ['Grand total', '6', '65', '215.00'],
        ]);
});

it('exports the Tour Detail for the fiscal year to date as CSV', function () {
    [$group] = tourReportGroup();

    $response = $this->actingAs(tourReportMember($group, Role::Statistician))
        ->get(route('groups.hours.tour-detail.csv', ['group' => $group, 'fy' => '2026']))
        ->assertOk();

    expect($response->headers->get('content-disposition'))->toContain("{$group->slug}-tour-detail-fiscal-2026.csv")
        ->and(tourCsvRows($response->streamedContent()))->toBe([
            ['Type', 'Tour', 'Tours', 'Visitors', 'Earned'],
            ['Tour Paid', 'Ancient Egypt', '3', '50', '170.00'],
            ['Tour Paid', 'Dinosaurs', '1', '10', '35.00'],
            ['Tour Paid', 'Total', '4', '60', '205.00'],
            ['Tour Free', 'Ancient Egypt', '1', '15', '0.00'],
            ['Tour Free', 'Total', '1', '15', '0.00'],
            ['Group tours', '', '5', '75', '205.00'],
            ['Scheduled tours', '', '2', '20', ''],
            ['Exhibition revenue', '', '', '', '0.00'],
            ['Grand total', '', '7', '95', '205.00'],
        ]);
});

it('translates the CSV headings under /fr/', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $this->withLocaleRoutes('fr', function () use ($statistician, $group) {
        $csv = $this->actingAs($statistician)
            ->get("/fr/groupes/{$group->slug}/heures/sommaire-visites.csv?month=202601")
            ->assertOk()
            ->streamedContent();

        expect(tourCsvRows($csv)[0])->toBe(['Type', 'Visites', 'Visiteurs', 'Montant gagné']);
    });
});

// --- The gate ---------------------------------------------------------------

it('opens both reports and their CSVs to a Chair, a Statistician, a parent Chair and super-tier', function (string $route) {
    [$group] = tourReportGroup();
    $parent = Group::factory()->create();
    $group->update(['parent_id' => $parent->id]);

    foreach ([tourReportMember($group, Role::Chair), tourReportMember($group, Role::Statistician), tourReportMember($parent, Role::Chair), Member::factory()->superTier()->create()] as $viewer) {
        $this->actingAs($viewer)->get(route($route, ['group' => $group->fresh(), 'month' => '202601']))->assertOk();
    }
})->with(['groups.hours.tour-summary', 'groups.hours.tour-detail', 'groups.hours.tour-summary.csv', 'groups.hours.tour-detail.csv']);

it('refuses both reports and their CSVs to a Booker, an ordinary Member and an outsider', function (string $route) {
    [$group] = tourReportGroup();

    foreach ([tourReportMember($group, Role::Booker), tourReportMember($group), Member::factory()->create()] as $viewer) {
        $this->actingAs($viewer)->get(route($route, ['group' => $group, 'month' => '202601']))->assertForbidden();
    }
})->with(['groups.hours.tour-summary', 'groups.hours.tour-detail', 'groups.hours.tour-summary.csv', 'groups.hours.tour-detail.csv']);

it('has no tour reports on a Group that runs no bookings', function () {
    $group = Group::factory()->program()->create(['has_bookings' => false]);

    $this->actingAs(tourReportMember($group, Role::Chair))
        ->get(route('groups.hours.tour-summary', $group))
        ->assertNotFound();
});

it('resolves the reports under the French /fr/ path', function () {
    [$group] = tourReportGroup();
    $statistician = tourReportMember($group, Role::Statistician);

    $this->withLocaleRoutes('fr', function () use ($statistician, $group) {
        $this->actingAs($statistician)->get("/fr/groupes/{$group->slug}/heures/sommaire-visites")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('groups/TourSummary'));
        $this->actingAs($statistician)->get("/fr/groupes/{$group->slug}/heures/detail-visites")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('groups/TourDetail'));
    });
});
