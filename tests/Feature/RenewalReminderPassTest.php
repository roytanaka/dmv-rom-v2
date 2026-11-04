<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\RenewalSetting;
use Illuminate\Support\Carbon;

/*
 * Renewal reminders in the daily pass (#942, ADR-0038 §9, §14.9). For each of Active and
 * Sustaining, on or after the send date plus six weeks (first) or ten weeks (second), every Member
 * of that Category whose paid-through year is below the renewal year and without the no-email flag
 * gets one Renewal reminder Delivery, keyed on (kind, series, series year, Member). A window with a
 * sent record, not legacy's exact-day match: a second run writes nothing, a missed day catches up.
 * Prior art: ReminderPassTest, EmptyDeskPassTest.
 */

/** The settings row: renewal on for 2027, both send dates on 2026-09-01. */
function renewalReminderSettings(array $overrides = []): RenewalSetting
{
    $row = RenewalSetting::current();
    $row->forceFill([
        'show_renewal' => true,
        'renewal_year' => 2027,
        'active_send_date' => '2026-09-01',
        'sustaining_send_date' => '2026-09-01',
        'active_message_1' => 'Active one',
        'active_message_2' => 'Active two',
        'sustaining_message_1' => 'Sustaining one',
        'sustaining_message_2' => 'Sustaining two',
        ...$overrides,
    ])->save();

    return $row;
}

/** Run the pass at noon org time on the given date. */
function runRenewalPassOn(string $date): void
{
    Carbon::setTestNow(Carbon::parse($date.' 12:00:00', 'America/Toronto'));
    test()->artisan('mail:daily-pass')->assertSuccessful();
}

function unpaidMember(Category $category = Category::Active, array $attributes = []): Member
{
    return Member::factory()->category($category)->create(['paid_through_year' => 2026, ...$attributes]);
}

/** A Member of the Group holding member administration (the Records Audience). */
function reminderRecordsMember(string $first, string $last): Member
{
    $records = Group::stewardOf(StewardshipFunction::MemberAdmin);

    if ($records === null) {
        $records = Group::factory()->create();
        GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    }

    // Paid up, so the Records Member gets no reminder of their own.
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last, 'paid_through_year' => 2027]);
    $records->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);

    return $member;
}

afterEach(fn () => Carbon::setTestNow());

it('writes nothing before the six-week mark', function () {
    renewalReminderSettings();
    unpaidMember();

    runRenewalPassOn('2026-10-12'); // six weeks is 2026-10-13

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->count())->toBe(0);
});

it('writes one first reminder per unpaid Active Member on the six-week mark', function () {
    renewalReminderSettings();
    $member = unpaidMember();

    runRenewalPassOn('2026-10-13');

    $row = Delivery::where('kind', DeliveryKind::RenewalReminder)->sole();
    expect($row->member_id)->toBe($member->id);
    expect($row->email)->toBe($member->email);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->series)->toBe('active-1');
    expect($row->series_year)->toBe(2027);
    expect($row->payload['message'])->toBe('Active one');
});

it('writes nothing on a second run the same day', function () {
    renewalReminderSettings();
    unpaidMember();

    runRenewalPassOn('2026-10-13');
    runRenewalPassOn('2026-10-13');

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->count())->toBe(1);
});

it('catches up days later for a Member missed earlier, without writing again for the others', function () {
    renewalReminderSettings();
    $early = unpaidMember();

    runRenewalPassOn('2026-10-13');
    $late = unpaidMember();
    runRenewalPassOn('2026-10-16');

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->pluck('member_id')->sort()->values()->all())
        ->toBe([$early->id, $late->id]);
});

it('writes nothing for a paid Member, a no-email Member, or another Category', function () {
    renewalReminderSettings();
    unpaidMember(attributes: ['paid_through_year' => 2027]);
    $flagged = unpaidMember();
    $flagged->forceFill(['no_email' => true])->save();
    unpaidMember(Category::Loa);
    unpaidMember(Category::Provisional);

    runRenewalPassOn('2026-10-13');

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->count())->toBe(0);
});

it('counts a Member with no paid-through year as unpaid', function () {
    renewalReminderSettings();
    unpaidMember(attributes: ['paid_through_year' => null]);

    runRenewalPassOn('2026-10-13');

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->count())->toBe(1);
});

it('writes the second series at ten weeks with the second text', function () {
    renewalReminderSettings();
    $member = unpaidMember();

    runRenewalPassOn('2026-10-13');
    runRenewalPassOn('2026-11-09'); // ten weeks is 2026-11-10
    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->count())->toBe(1);

    runRenewalPassOn('2026-11-10');

    $second = Delivery::where('kind', DeliveryKind::RenewalReminder)->where('series', 'active-2')->sole();
    expect($second->member_id)->toBe($member->id);
    expect($second->payload['message'])->toBe('Active two');
});

it('closes the first series when the second opens, so nobody gets both on one day', function () {
    renewalReminderSettings();
    $member = unpaidMember();

    runRenewalPassOn('2026-11-10'); // a first pass past ten weeks

    $row = Delivery::where('kind', DeliveryKind::RenewalReminder)->sole();
    expect($row->member_id)->toBe($member->id);
    expect($row->series)->toBe('active-2');
});

it('closes the second series seven days after it opens, so a Member first eligible later gets nothing', function () {
    renewalReminderSettings();
    $early = unpaidMember();

    runRenewalPassOn('2026-11-16'); // the last day of the second series (opened 2026-11-10)
    $late = unpaidMember();
    runRenewalPassOn('2026-11-17');

    expect(Delivery::where('kind', DeliveryKind::RenewalReminder)->pluck('member_id')->all())->toBe([$early->id]);
});

it('writes nothing when the renewal year is bumped but the send dates are last year\'s', function () {
    renewalReminderSettings(['renewal_year' => 2028]);
    unpaidMember(attributes: ['paid_through_year' => 2027]);

    runRenewalPassOn('2027-09-01');

    expect(Delivery::count())->toBe(0);
});

it('runs the Sustaining series from its own send date', function () {
    renewalReminderSettings(['active_send_date' => '2026-12-01', 'sustaining_send_date' => '2026-09-01']);
    unpaidMember();
    $sustaining = unpaidMember(Category::Sustaining);

    runRenewalPassOn('2026-10-13');

    $row = Delivery::where('kind', DeliveryKind::RenewalReminder)->sole();
    expect($row->member_id)->toBe($sustaining->id);
    expect($row->series)->toBe('sustaining-1');
    expect($row->payload['message'])->toBe('Sustaining one');
});

it('writes nothing when the show flag is off or no renewal year is set', function () {
    unpaidMember();

    renewalReminderSettings(['show_renewal' => false]);
    runRenewalPassOn('2026-10-13');

    renewalReminderSettings(['renewal_year' => null]);
    runRenewalPassOn('2026-10-13');

    expect(Delivery::count())->toBe(0);
});

it('writes one summary Notice per Records Member for a run that wrote, listing who was mailed by series', function () {
    renewalReminderSettings(['sustaining_send_date' => '2026-08-01']);
    $rita = reminderRecordsMember('Rita', 'Records');
    $flagged = reminderRecordsMember('Nora', 'Noemail');
    $flagged->forceFill(['no_email' => true])->save();
    unpaidMember(attributes: ['first_name' => 'Ann', 'last_name' => 'Active']);
    unpaidMember(Category::Sustaining, ['first_name' => 'Sam', 'last_name' => 'Sustaining']);

    runRenewalPassOn('2026-10-13'); // Active at six weeks, Sustaining past ten

    $summary = Delivery::where('kind', DeliveryKind::Notice)->sole();
    expect($summary->member_id)->toBe($rita->id);
    expect($summary->payload['notice'])->toBe('renewal_reminder_summary');
    expect($summary->payload['series_year'])->toBe(2027);
    expect($summary->payload['mailed'])->toBe([
        'active-1' => ['Ann Active'],
        'sustaining-2' => ['Sam Sustaining'],
    ]);
});

it('writes no summary Notice for a run that wrote nothing', function () {
    renewalReminderSettings();
    reminderRecordsMember('Rita', 'Records');
    unpaidMember();

    runRenewalPassOn('2026-10-12');
    expect(Delivery::count())->toBe(0);

    runRenewalPassOn('2026-10-13');
    runRenewalPassOn('2026-10-14');

    expect(Delivery::where('kind', DeliveryKind::Notice)->count())->toBe(1);
});

it('writes nothing when the Beta mail-off switch is on', function () {
    renewalReminderSettings();
    unpaidMember();
    config(['mail.daily_pass_off' => true]);

    runRenewalPassOn('2026-10-13');

    expect(Delivery::count())->toBe(0);
});
