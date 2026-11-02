<?php

namespace App\Enums;

/**
 * A Guest's answer to an Invitation (ADR-0034). Every Guest starts on No reply.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum Reply: string
{
    case NoReply = 'no_reply';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
