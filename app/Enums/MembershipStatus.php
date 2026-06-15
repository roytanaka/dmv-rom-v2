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
}
