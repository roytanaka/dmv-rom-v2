<?php

namespace App\Enums;

/**
 * An Invitation's stored state (ADR-0034 §6). Closed is not stored: it is an Open
 * Invitation whose date has passed.
 *
 * - `draft` — seen only by its Host and the super-tier.
 * - `open` — Guests see it and reply.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum InvitationState: string
{
    case Draft = 'draft';
    case Open = 'open';
}
