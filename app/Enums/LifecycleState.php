<?php

namespace App\Enums;

/**
 * A Group's Lifecycle state — whether it is still alive (ADR-0010). Combined
 * with the time-boxed window, this is what makes "which Groups are dead?" a
 * standing query rather than a manual audit.
 *
 * Backed string enum: the stored value is the case value.
 */
enum LifecycleState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
