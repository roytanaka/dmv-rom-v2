<?php

namespace App\Support\Audiences;

use App\Models\Applicant;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * A resolved Audience (ADR-0024 §5, §9): the label as the record will store it, the
 * recipients de-duplicated by Member, and the Members skipped for the no-email flag
 * — checked once, here, so a flagged Member never becomes a Delivery row. `edited`
 * is set when the picker removed or added anyone; the label carries the "N removed"
 * suffix when anyone was removed. The Applicants Audience (#929) holds Applicants
 * instead, which have no no-email flag and are never skipped.
 */
final class ResolvedAudience
{
    /**
     * @param  Collection<int, Member|Applicant>  $recipients
     * @param  Collection<int, Member>  $skipped
     */
    public function __construct(
        public readonly string $label,
        public readonly bool $edited,
        public readonly Collection $recipients,
        public readonly Collection $skipped,
    ) {}
}
