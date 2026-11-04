<?php

use App\Enums\Category;
use App\Models\Member;
use App\Models\RenewalSetting;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Renew link and the served year (#939, ADR-0038 §9). Who sees the link, with which text, is
 * the Legacy app's menu rule: Active, Pre-active or Sustaining; with the show flag on, a
 * paid-through year before the renewal year (or none); with it off, no paid-through year reads
 * Provisional to Active and a renewal status of LOA reads as a renewal.
 */

function renewalSettings(bool $show, ?int $year = 2027): RenewalSetting
{
    $row = RenewalSetting::current();
    $row->forceFill(['show_renewal' => $show, 'renewal_year' => $year])->save();

    return $row;
}

dataset('eligibility', [
    'Active, open, never paid' => [true, Category::Active, null, null, 'renew'],
    'Active, open, paid last year' => [true, Category::Active, 2026, 'Active', 'renew'],
    'Active, open, paid this year' => [true, Category::Active, 2027, 'Active', null],
    'Pre-active, open, paid last year' => [true, Category::PreActive, 2026, null, 'renew'],
    'Sustaining, open, paid last year' => [true, Category::Sustaining, 2026, null, 'renew'],
    'Active, open, LOA, paid this year' => [true, Category::Active, 2027, 'LOA', null],
    'Provisional, open' => [true, Category::Provisional, null, null, null],
    'Honourary, open' => [true, Category::Honourary, 2026, null, null],
    'LOA Category, open' => [true, Category::Loa, 2026, null, null],
    'Provisional, closed, never paid' => [false, Category::Provisional, null, null, null],
    'Active, closed, never paid' => [false, Category::Active, null, null, 'provisional_to_active'],
    'Pre-active, closed, never paid' => [false, Category::PreActive, null, null, 'provisional_to_active'],
    'Sustaining, closed, LOA' => [false, Category::Sustaining, 2025, 'LOA', 'loa'],
    'Active, closed, LOA' => [false, Category::Active, 2026, 'LOA', 'loa'],
    'Active, closed, paid last year' => [false, Category::Active, 2026, 'Active', null],
]);

it('shows the Renew link with the matching text, or none', function (bool $show, Category $category, ?int $paidThrough, ?string $status, ?string $expected) {
    renewalSettings($show);
    $member = Member::factory()->category($category)->create([
        'paid_through_year' => $paidThrough,
        'renewal_status' => $status,
    ]);

    $labels = ['renew' => 'Renew Membership', 'provisional_to_active' => 'Provisional to Active', 'loa' => 'Renew Membership'];

    $this->actingAs($member)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $expected === null
            ? $page->where('renewal', null)
            : $page->where('renewal.eligibility', $expected)
                ->where('renewal.labelKey', fn (string $key) => __($key, [], 'en') === $labels[$expected]));
})->with('eligibility');

it('gives the French link text', function () {
    renewalSettings(false);
    $member = Member::factory()->create(['paid_through_year' => null]);

    expect(__('user.renew_provisional', [], 'fr'))->toBe('De provisoire à actif');

    $this->actingAs($member)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('renewal.labelKey', 'user.renew_provisional'));
});

it('shows no link before the settings row is first saved', function () {
    $this->actingAs(Member::factory()->create(['paid_through_year' => 2026]))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('renewal', null));
});

it('serves the renewal year outside January to March', function () {
    $this->travelTo('2026-11-04 12:00');
    $settings = renewalSettings(true, 2027);

    expect(Member::factory()->make(['paid_through_year' => null])->renewalServedYear($settings))->toBe(2027)
        ->and(Member::factory()->make(['paid_through_year' => 2026, 'renewal_status' => 'LOA'])->renewalServedYear($settings))->toBe(2027);
});

it('serves the current year January to March with no paid-through year or LOA', function () {
    $this->travelTo('2027-02-15 12:00');
    $settings = renewalSettings(true, 2026);

    expect(Member::factory()->make(['paid_through_year' => null])->renewalServedYear($settings))->toBe(2027)
        ->and(Member::factory()->make(['paid_through_year' => 2025, 'renewal_status' => 'LOA'])->renewalServedYear($settings))->toBe(2027)
        ->and(Member::factory()->make(['paid_through_year' => 2025, 'renewal_status' => 'Active'])->renewalServedYear($settings))->toBe(2026);
});

it('leaves January to March at the renewal year from April', function () {
    $this->travelTo('2027-04-01 12:00');
    $settings = renewalSettings(false, 2026);

    expect(Member::factory()->make(['paid_through_year' => null])->renewalServedYear($settings))->toBe(2026);
});
