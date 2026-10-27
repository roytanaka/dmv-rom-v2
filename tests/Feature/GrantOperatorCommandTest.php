<?php

use App\Models\Member;

/*
 * `members:grant-operator <email>` (#859) — the Support-operator runs it once on Beta
 * after the first import to set their own `support_operator` marker (ADR-0035 §4).
 */

it('makes the Member with that email a support operator', function () {
    $member = Member::factory()->create(['email' => 'operator@dmv.test']);

    $this->artisan('members:grant-operator', ['email' => 'operator@dmv.test'])
        ->expectsOutputToContain('operator@dmv.test')
        ->assertSuccessful();

    expect($member->fresh()->isSupportOperator())->toBeTrue();
});

it('is safe to run twice and says the Member is already an operator', function () {
    $member = Member::factory()->operator()->create(['email' => 'operator@dmv.test']);

    $this->artisan('members:grant-operator', ['email' => 'operator@dmv.test'])
        ->expectsOutputToContain('operator@dmv.test is already a support operator')
        ->assertSuccessful();

    expect($member->fresh()->isSupportOperator())->toBeTrue();
});

it('fails with a clear message when no Member has that email', function () {
    Member::factory()->create(['email' => 'someone@dmv.test']);

    $this->artisan('members:grant-operator', ['email' => 'nobody@dmv.test'])
        ->expectsOutputToContain('No Member with email "nobody@dmv.test"')
        ->assertFailed();

    expect(Member::where('support_operator', true)->exists())->toBeFalse();
});
