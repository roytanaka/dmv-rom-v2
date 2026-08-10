<?php

namespace App\Enums;

/**
 * A Schedule's lifecycle state (ADR-0021 §1) — two states, one read audience each.
 * Legacy's five states (`edit → signup → freeze → confirm → final` and the separate
 * `visible` flag) collapse to this pair: publication is a visibility switch, not a
 * freeze.
 *
 * - `draft` — visible only to the Group's schedule admins (its Scheduler / Chair,
 *   plus the super-tier).
 * - `published` — visible to the Group's `listing_visibility` audience.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum ScheduleState: string
{
    case Draft = 'draft';
    case Published = 'published';
}
