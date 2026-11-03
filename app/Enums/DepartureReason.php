<?php

namespace App\Enums;

/**
 * Why a Resigned or Withdrawn Member left (ADR-0038 §4). Replaces the Legacy app's
 * eighteen reason-coded categories with two Categories and one reason.
 *
 * Backed string enum: the value is stored on `members.departure_reason` and keys the
 * `member.departure_reason` label.
 */
enum DepartureReason: string
{
    case Family = 'family';
    case Job = 'job';
    case Health = 'health';
    case Moved = 'moved';
    case NoneGiven = 'none_given';
    case Age = 'age';
    case Schedule = 'schedule';
    case Stopped = 'stopped';
    case Other = 'other';
    case Involuntary = 'involuntary';
}
