<?php

namespace App\Enums;

/**
 * A membership's within-Group standing — distinct from the Member's DMV-wide
 * Category (App\Enums\Category). A Member's standing is per-Group: on leave from
 * one Group doesn't touch another.
 *
 * Backed string enum: the stored value is the snake_case case value. `donor`
 * applies to Friends Groups only; enforcing that scoping is out of spine scope.
 */
enum MembershipStatus: string
{
    case Full = 'full';
    case Loa = 'loa';
    case Trainee = 'trainee';
    case Transitional = 'transitional';
    case Auxiliary = 'auxiliary';
    case Projects = 'projects';
    case Emeritus = 'emeritus';
    case Inactive = 'inactive';
    case Resigned = 'resigned';
    case Deceased = 'deceased';
    case Donor = 'donor';

    /**
     * Whether a membership in this within-Group standing may sign up for the Group's
     * Shifts — the per-Group floor the sign-up gate checks beside the DMV-wide
     * {@see Category::canSignUp()} (ADR-0021). Mirrors that method's shape: the four
     * standings that mean *gone or paused* — `loa`, `inactive`, `resigned`, `deceased`
     * — are barred; the other seven may sign up. `donor` is permitted deliberately: a
     * Friends Group's roster *is* donors, and excluding them would leave a Friends
     * Committee unable to staff its own Schedule.
     */
    public function canSignUp(): bool
    {
        return match ($this) {
            self::Full, self::Trainee, self::Transitional, self::Auxiliary,
            self::Projects, self::Emeritus, self::Donor => true,
            self::Loa, self::Inactive, self::Resigned, self::Deceased => false,
        };
    }
}
