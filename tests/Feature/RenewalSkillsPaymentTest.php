<?php

use App\Models\Member;
use App\Models\RenewalSetting;
use App\Models\Skill;
use App\Models\SkillCategory;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Steps 3 and 4 of the Renewal (#941, ADR-0038 §9): skills, through the Settings skills request,
 * skipped for a Sustaining choice; the payment page, with the fee, the Akaraisin link carrying the
 * amount and the cheque text. Prior art: RenewalFormTest, SkillsUpdateTest.
 */

function openRenewalStep(int $year = 2027): void
{
    RenewalSetting::current()->forceFill(['show_renewal' => true, 'renewal_year' => $year])->save();
}

/** A Member who saved the form for 2027 as Active, at $25. */
function renewedMember(array $attributes = []): Member
{
    return Member::factory()->create([
        'paid_through_year' => 2026,
        'code_of_conduct_year' => 2027,
        'renewal_year' => 2027,
        'renewal_status' => 'Active',
        'amount_paid' => 25,
        'fee_category' => 'Active',
        ...$attributes,
    ]);
}

it('shows the skills step with the Member\'s skills checked', function () {
    openRenewalStep();
    $member = renewedMember();
    $category = SkillCategory::factory()->create();
    $held = Skill::factory()->create(['category_id' => $category->id]);
    Skill::factory()->create(['category_id' => $category->id]);
    $member->skills()->attach($held);

    $this->actingAs($member)->get('/renew/skills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/Skills')
            ->where('selected', [$held->id])
            ->has('catalog', 1)
            ->has('catalog.0.skills', 2));
});

it('skips the skills step for a Member renewing as Sustaining', function (string $code) {
    openRenewalStep();
    $member = renewedMember(['renewal_status' => $code]);

    $this->actingAs($member)->get('/renew/skills')->assertRedirect('/renew/payment');
})->with(['Sust', 'Sust-SR']);

it('saves the skills and moves on to the payment page', function () {
    openRenewalStep();
    $member = renewedMember();
    $category = SkillCategory::factory()->create();
    [$kept, $added] = Skill::factory()->count(2)->create(['category_id' => $category->id])->all();
    $dropped = Skill::factory()->create(['category_id' => $category->id]);
    $member->skills()->attach([$kept->id, $dropped->id]);

    $this->actingAs($member)->post('/renew/skills', ['skills' => [$kept->id, $added->id]])
        ->assertRedirect('/renew/payment');

    expect($member->skills()->pluck('skills.id')->sort()->values()->all())->toBe([$kept->id, $added->id]);
});

it('rejects a retired skill, as Settings does', function () {
    openRenewalStep();
    $member = renewedMember();
    $retired = Skill::factory()->retired()->create(['category_id' => SkillCategory::factory()->create()->id]);

    $this->actingAs($member)->post('/renew/skills', ['skills' => [$retired->id]])
        ->assertSessionHasErrors('skills.0');

    expect($member->skills()->count())->toBe(0);
});

it('shows the fee and the Akaraisin link carrying only the amount', function () {
    config(['services.akaraisin.url' => 'https://rom.akaraisin.com/ui/dmv2/donations/start?it=1']);
    openRenewalStep();
    $member = renewedMember(['amount_paid' => 20, 'fee_category' => 'Active Student']);

    $this->actingAs($member)->get('/renew/payment')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/Payment')
            ->where('year', 2027)
            ->where('amount', '20.00')
            ->where('link', 'https://rom.akaraisin.com/ui/dmv2/donations/start?it=1&amt=20'));
});

it('keeps the cents in the link when the fee has them', function () {
    config(['services.akaraisin.url' => 'https://example.test/donate']);
    openRenewalStep();
    $member = renewedMember(['amount_paid' => 22.5]);

    $this->actingAs($member)->get('/renew/payment')
        ->assertInertia(fn (Assert $page) => $page->where('link', 'https://example.test/donate?amt=22.50'));
});

it('shows no fee and no link while Records has not set one', function () {
    openRenewalStep();
    $member = renewedMember(['amount_paid' => null, 'fee_category' => null]);

    $this->actingAs($member)->get('/renew/payment')
        ->assertInertia(fn (Assert $page) => $page->component('renew/Payment')
            ->where('amount', null)
            ->where('link', null));
});

it('sends a Member who has not saved the form back to it', function (string $step) {
    openRenewalStep();
    $member = renewedMember(['renewal_year' => 2026]);

    $this->actingAs($member)->get("/renew/{$step}")->assertRedirect('/renew/form');
})->with(['skills', 'payment']);

it('carries the cheque instructions in both languages', function () {
    expect(__('renewal.payment.cheque', [], 'en'))->toContain('ROM DMV')->toContain('100 Queens Park')
        ->and(__('renewal.payment.cheque', [], 'fr'))->toContain('ROM DMV')->toContain('100 Queens Park');
});

it('shows "renewed, payment pending" in Account settings while the renewal year is ahead', function (?int $paidThrough, int $renewalYear, bool $pending) {
    $member = renewedMember(['paid_through_year' => $paidThrough, 'renewal_year' => $renewalYear]);

    $this->actingAs($member)->get('/settings/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('membership.paid_through_year', $paidThrough)
            ->where('membership.renewal_year', $renewalYear)
            ->where('membership.payment_pending', $pending));
})->with([
    'renewed, not paid' => [2026, 2027, true],
    'renewed and paid' => [2027, 2027, false],
    'first renewal, not paid' => [null, 2027, true],
]);

it('refuses the skills save to a Member who need not renew', function () {
    openRenewalStep();
    $member = renewedMember(['paid_through_year' => 2027]);

    $this->actingAs($member)->post('/renew/skills', ['skills' => []])->assertForbidden();
});
