<?php

namespace App\Enums;

/**
 * A Member's DMV-wide standing — their organisation-level category, distinct
 * from the per-Group standing carried by a membership (see MembershipStatus,
 * landed in a later spine slice).
 *
 * Backed string enum: the stored value is the snake_case case value. Resolution
 * to a base access tier (Full / Limited / None) and the legacy-value mapping are
 * deliberately out of spine scope — that's authorization / migration work.
 */
enum Category: string
{
    case Active = 'active';
    case PreActive = 'pre_active';
    case Provisional = 'provisional';
    case Sustaining = 'sustaining';
    case Life = 'life';
    case Honourary = 'honourary';
    case Loa = 'loa';
    case Withdrawn = 'withdrawn';
    case Resigned = 'resigned';
    case Deceased = 'deceased';
}
