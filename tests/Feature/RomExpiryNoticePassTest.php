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
 * The ROM-expiry notice in the daily pass (#943, ADR-0038 §9). On the first of June, August,
 * October, December and February, every Active and Sustaining Member whose ROM membership expiry
 * is before today, who is not a lifetime member and has no no-email flag, gets one Delivery on the
 * (kind, series `rom-expiry`, YYYYMM, Member) grain. Off when the show flag is off or the Beta
 * switch is on. Prior art: RenewalReminderPassTest.
 */

/** Renewal shown, but no renewal year, so the Renewal reminders stay quiet. */
function romExpirySettings(bool $show = true): void
{
    $row = RenewalSetting::current();
    $row->forceFill(['show_renewal' => $show, 'renewal_year' => null])->save();
}

function runRomExpiryPassOn(string $date): void
{
    Carbon::setTestNow(Carbon::parse($date.' 12:00:00', 'America/Toronto'));
    test()->artisan('mail:daily-pass')->assertSuccessful();
}

function expiredMember(Category $category = Category::Active, array $attributes = []): Member
{
    return Member::factory()->category($category)->create(['membership_expiry' => '2026-03-31', ...$attributes]);
}

function romExpiryRows()
{
    return Delivery::where('kind', DeliveryKind::RomExpiryNotice);
}

afterEach(fn () => Carbon::setTestNow());

it('writes one notice per expired Active or Sustaining Member on the first of a listed month', function () {
    romExpirySettings();
    $active = expiredMember();
    $sustaining = expiredMember(Category::Sustaining);

    runRomExpiryPassOn('2026-06-01');

    expect(romExpiryRows()->pluck('member_id')->sort()->values()->all())->toBe([$active->id, $sustaining->id]);
    $row = romExpiryRows()->where('member_id', $active->id)->sole();
    expect($row->email)->toBe($active->email);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->series)->toBe('rom-expiry');
    expect($row->series_year)->toBe(202606);
});

it('writes nothing on other days or in the months not listed', function () {
    romExpirySettings();
    expiredMember();

    runRomExpiryPassOn('2026-06-02');
    runRomExpiryPassOn('2026-07-01');
    runRomExpiryPassOn('2027-03-01');

    expect(Delivery::count())->toBe(0);
});

it('writes for each listed month, February included', function () {
    romExpirySettings();
    expiredMember();

    foreach (['2026-08-01', '2026-10-01', '2026-12-01', '2027-02-01'] as $date) {
        runRomExpiryPassOn($date);
    }

    expect(romExpiryRows()->orderBy('series_year')->pluck('series_year')->all())->toBe([202608, 202610, 202612, 202702]);
});

it('writes nothing on a second run the same day, and again the next listed month', function () {
    romExpirySettings();
    expiredMember();

    runRomExpiryPassOn('2026-06-01');
    runRomExpiryPassOn('2026-06-01');
    expect(romExpiryRows()->count())->toBe(1);

    runRomExpiryPassOn('2026-08-01');
    expect(romExpiryRows()->count())->toBe(2);
});

it('skips lifetime, no-email, unexpired, unset and other-Category Members', function () {
    romExpirySettings();
    expiredMember(attributes: ['membership_lifetime' => true]);
    expiredMember()->forceFill(['no_email' => true])->save();
    expiredMember(attributes: ['membership_expiry' => '2026-06-01']); // today is not before today
    expiredMember(attributes: ['membership_expiry' => null]);
    expiredMember(Category::Loa);
    expiredMember(Category::Provisional);
    $counted = expiredMember(attributes: ['membership_lifetime' => false]);

    runRomExpiryPassOn('2026-06-01');

    expect(romExpiryRows()->pluck('member_id')->all())->toBe([$counted->id]);
});

it('writes one summary Notice per Records Member for a run that wrote, and none for a run that did not', function () {
    romExpirySettings();
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $rita = Member::factory()->create();
    $nora = Member::factory()->noEmail()->create();
    foreach ([$rita, $nora] as $member) {
        $records->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    }
    expiredMember(attributes: ['first_name' => 'Ann', 'last_name' => 'Active']);
    expiredMember(Category::Sustaining, ['first_name' => 'Sam', 'last_name' => 'Sustaining']);

    runRomExpiryPassOn('2026-06-01');
    runRomExpiryPassOn('2026-06-01');

    $summary = Delivery::where('kind', DeliveryKind::Notice)->sole();
    expect($summary->member_id)->toBe($rita->id);
    expect($summary->payload)->toBe(['notice' => 'rom_expiry_summary', 'mailed' => ['Ann Active', 'Sam Sustaining']]);
});

it('writes nothing when the show flag is off or the Beta mail-off switch is on', function () {
    expiredMember();

    romExpirySettings(show: false);
    runRomExpiryPassOn('2026-06-01');

    romExpirySettings();
    config(['mail.daily_pass_off' => true]);
    runRomExpiryPassOn('2026-06-01');

    expect(Delivery::count())->toBe(0);
});
