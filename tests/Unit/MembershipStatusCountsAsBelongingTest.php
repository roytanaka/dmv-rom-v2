<?php

use App\Enums\MembershipStatus;

/*
 * The "belongs to the Group" rule (#635, ADR-0020) — MembershipStatus::countsAsBelonging().
 * My Groups and the Other Groups prune both read it. A present standing (canSignUp() true)
 * or LOA belongs; the three departed standings — Inactive, Resigned, Deceased — do not.
 * LOA is the one entry that differs from canSignUp(): a Member on leave cannot sign up,
 * but the Group is still theirs.
 */

it('resolves whether each standing counts as belonging to the Group', function (MembershipStatus $status, bool $belongs) {
    expect($status->countsAsBelonging())->toBe($belongs);
})->with([
    'Full belongs' => [MembershipStatus::Full, true],
    'Trainee belongs' => [MembershipStatus::Trainee, true],
    'Transitional belongs' => [MembershipStatus::Transitional, true],
    'Auxiliary belongs' => [MembershipStatus::Auxiliary, true],
    'Projects belongs' => [MembershipStatus::Projects, true],
    'Emeritus belongs' => [MembershipStatus::Emeritus, true],
    'Donor belongs' => [MembershipStatus::Donor, true],
    // Differs from canSignUp(): on leave, the Group is still the Member's.
    'LOA belongs' => [MembershipStatus::Loa, true],
    'Inactive does not belong' => [MembershipStatus::Inactive, false],
    'Resigned does not belong' => [MembershipStatus::Resigned, false],
    'Deceased does not belong' => [MembershipStatus::Deceased, false],
]);
