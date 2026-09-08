<?php

namespace App\Enums;

/**
 * A Group's listing visibility — who the Group is listed to in navigation and,
 * for the strictest tier, whether its page's existence is disclosed (ADR-0019).
 * A stored, first-class facet like Kind / Scope / Lifecycle, never derived from
 * tree position.
 *
 * Deliberately distinct from the per-Meeting `visibility` axis — same word, a
 * different concern.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum ListingVisibility: string
{
    // Listed to every logged-in Member; today's org-open behaviour.
    case Public = 'public';

    // Listed only to members of the parent Group.
    case Group = 'group';

    // Listed only to its own members; its page's existence stays hidden.
    case Private = 'private';
}
