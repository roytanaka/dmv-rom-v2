<?php

namespace App\Enums;

/**
 * Where an Applicant stands in intake (ADR-0038 §8): the seven Legacy stages, in list order.
 *
 * Backed string enum: the value is stored on `applicants.status`, is the Applicants list's
 * `status` filter word, and keys the `applicants.status` label.
 */
enum ApplicantStatus: string
{
    case New = 'new';
    case AssignedForInterview = 'assigned_for_interview';
    case Accepted = 'accepted';
    case Placed = 'placed';
    case YellowCardCompleted = 'yellow_card_completed';
    case ReadyToGoActive = 'ready_to_go_active';
    case Incomplete = 'incomplete';

    /**
     * The statuses the list's default All filter shows: every live case, as the Legacy app's
     * "All Applicants" left out the closed (Incomplete) and the finished (Ready to go active).
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => ! in_array($status, [self::Incomplete, self::ReadyToGoActive], true),
        ));
    }
}
