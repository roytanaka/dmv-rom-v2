<?php

use App\Enums\Category;
use App\Models\Member;
use App\Models\RenewalSetting;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Renewal's steps (#939, ADR-0038 §9): the `renew` route refuses a Member who need not renew
 * and only ever acts on the signed-in Member. Step 1 is the code of conduct; steps 2 to 4 are
 * placeholders until the form, skills and payment land.
 */

function openRenewal(int $year = 2027): void
{
    RenewalSetting::current()->forceFill(['show_renewal' => true, 'renewal_year' => $year])->save();
}

function renewingMember(array $attributes = []): Member
{
    return Member::factory()->create(['paid_through_year' => 2026, 'code_of_conduct_year' => 2026, ...$attributes]);
}

it('opens the code of conduct for a Member who must renew', function () {
    openRenewal();

    $this->actingAs(renewingMember())->get('/renew')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/CodeOfConduct')->where('year', 2027));
});

it('opens the French twin', function () {
    openRenewal();

    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(renewingMember())->get('/fr/renouveler')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('renew/CodeOfConduct'));
    });

    expect(__('renewal.code_of_conduct.accept', [], 'fr'))->toBe("J'ai lu le présent code de conduite et j'accepte de m'y conformer")
        ->and(__('renewal.code_of_conduct.accept', [], 'en'))->toBe('I have read and agree to adhere to this Code of Conduct');
});

it('refuses a Member who need not renew', function () {
    openRenewal();
    $paid = renewingMember(['paid_through_year' => 2027]);

    $this->actingAs($paid)->get('/renew')->assertForbidden();
    $this->actingAs($paid)->get('/renew/form')->assertForbidden();
    $this->actingAs($paid)->post('/renew/code-of-conduct', ['accept' => true])->assertForbidden();
    expect($paid->fresh()->code_of_conduct_year)->toBe(2026);
});

it('refuses a Category outside the Renewal, even super-tier', function () {
    openRenewal();
    $president = renewingMember(['category' => Category::Honourary, 'super_tier' => true]);

    $this->actingAs($president)->get('/renew')->assertForbidden();
});

it('skips to the form when the code of conduct is already recorded for the year', function () {
    openRenewal();

    $this->actingAs(renewingMember(['code_of_conduct_year' => 2027]))->get('/renew')
        ->assertRedirect('/renew/form');
});

it('requires the checkbox', function () {
    openRenewal();
    $member = renewingMember();

    $this->actingAs($member)->post('/renew/code-of-conduct', [])->assertSessionHasErrors('accept');
    $this->actingAs($member)->post('/renew/code-of-conduct', ['accept' => false])->assertSessionHasErrors('accept');
    expect($member->fresh()->code_of_conduct_year)->toBe(2026);
});

it('writes the served year and moves to the form', function () {
    openRenewal();
    $member = renewingMember();

    $this->actingAs($member)->post('/renew/code-of-conduct', ['accept' => true])
        ->assertRedirect('/renew/form');

    expect($member->fresh()->code_of_conduct_year)->toBe(2027);
});

it('writes the current year from January to March for a first-year Member', function () {
    $this->travelTo('2027-02-10 12:00');
    RenewalSetting::current()->forceFill(['show_renewal' => false, 'renewal_year' => 2026])->save();
    $member = renewingMember(['paid_through_year' => null, 'code_of_conduct_year' => null]);

    $this->actingAs($member)->post('/renew/code-of-conduct', ['accept' => true])->assertRedirect('/renew/form');

    expect($member->fresh()->code_of_conduct_year)->toBe(2027);
});

it('acts only on the signed-in Member', function () {
    openRenewal();
    $member = renewingMember();
    $other = renewingMember();

    $this->actingAs($member)->post('/renew/code-of-conduct', ['accept' => true, 'member_id' => $other->id, 'member' => $other->id]);

    expect($other->fresh()->code_of_conduct_year)->toBe(2026)
        ->and($member->fresh()->code_of_conduct_year)->toBe(2027);
});

it('renders steps 3 and 4 as placeholders', function (string $path, string $step) {
    openRenewal();

    $this->actingAs(renewingMember())->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/Placeholder')->where('step', $step));
})->with([
    ['/renew/skills', 'skills'],
    ['/renew/payment', 'payment'],
]);

it('opens the French twins of steps 2 to 4', function () {
    openRenewal();
    $member = renewingMember();

    $this->withLocaleRoutes('fr', function () use ($member) {
        foreach (['/fr/renouveler/formulaire', '/fr/renouveler/competences', '/fr/renouveler/paiement'] as $path) {
            $this->actingAs($member)->get($path)->assertOk();
        }
    });
});
