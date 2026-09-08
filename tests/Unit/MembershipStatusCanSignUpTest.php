<?php

use App\Enums\MembershipStatus;

/*
 * The per-Group sign-up floor (#357, PRD #352, ADR-0021) — MembershipStatus::canSignUp().
 * A pure decision table worth pinning case by case (prior art: CategoryAccessTierTest),
 * mirroring Category::canSignUp()'s shape: the four standings that mean gone or paused
 * are false, the other seven true. Two entries surprise a reader and are called out:
 * `donor` is true (a Friends Group's roster *is* donors) and `emeritus` is true.
 */

it('resolves the per-Group sign-up floor for every standing', function (MembershipStatus $status, bool $canSignUp) {
    expect($status->canSignUp())->toBe($canSignUp);
})->with([
    'Full can sign up' => [MembershipStatus::Full, true],
    'Trainee can sign up' => [MembershipStatus::Trainee, true],
    'Transitional can sign up' => [MembershipStatus::Transitional, true],
    'Auxiliary can sign up' => [MembershipStatus::Auxiliary, true],
    'Projects can sign up' => [MembershipStatus::Projects, true],
    // Surprises a reader: an emeritus standing still permits signing up.
    'Emeritus can sign up' => [MembershipStatus::Emeritus, true],
    // Surprises a reader: donor is deliberate — a Friends Group's roster *is* donors,
    // and excluding them would leave a Friends Committee unable to staff its Schedule.
    'Donor can sign up' => [MembershipStatus::Donor, true],
    'LOA cannot sign up' => [MembershipStatus::Loa, false],
    'Inactive cannot sign up' => [MembershipStatus::Inactive, false],
    'Resigned cannot sign up' => [MembershipStatus::Resigned, false],
    'Deceased cannot sign up' => [MembershipStatus::Deceased, false],
]);
