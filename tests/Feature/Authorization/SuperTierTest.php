<?php

use App\Models\Member;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;

/*
 * super_tier hardening (#153, ADR-0017 §1). super-tier is the one org-wide grant;
 * it must never be client-assertable. These tests prove three things: the attribute
 * is not mass-assignable, strict-model mode is on app-wide so a silent drop throws
 * in development, and the only path that can flip it is the dedicated, separately
 * gated grant/revoke action — denied to every non-super-tier actor.
 */

// --- Mass-assignment hardening + strict-model mode --------------------------

it('does not allow super_tier to be mass-assigned', function () {
    $member = Member::factory()->create();

    expect(fn () => $member->fill(['super_tier' => true]))
        ->toThrow(MassAssignmentException::class);

    expect($member->fresh()->super_tier)->toBeFalse();
});

it('enables strict-model mode app-wide', function () {
    expect(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('ignores super_tier submitted through the self-service profile form', function () {
    $member = Member::factory()->create();

    $this->actingAs($member)
        ->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
            'super_tier' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->super_tier)->toBeFalse();
});

// --- Dedicated grant/revoke action ------------------------------------------

it('lets a super-tier member grant super-tier to another member', function () {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put(route('members.super-tier.update', $target), ['super_tier' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->super_tier)->toBeTrue();
});

it('lets a super-tier member revoke super-tier from another member', function () {
    $target = Member::factory()->superTier()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put(route('members.super-tier.update', $target), ['super_tier' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($target->fresh()->super_tier)->toBeFalse();
});

it('forbids a non-super-tier member from granting super-tier', function () {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->put(route('members.super-tier.update', $target), ['super_tier' => true])
        ->assertForbidden();

    expect($target->fresh()->super_tier)->toBeFalse();
});

it('redirects an unauthenticated grant request to login', function () {
    $target = Member::factory()->create();

    $this->put(route('members.super-tier.update', $target), ['super_tier' => true])
        ->assertRedirect(route('login'));
});
