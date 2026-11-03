<?php

namespace App\Actions;

use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use App\Models\Member;
use App\Support\Notices\ApplicantNoticeWriter;
use App\Support\OrgTime;
use Illuminate\Support\Facades\DB;

/**
 * Save the Applicant record (#928, ADR-0038 §8): the fields sent, the languages, and the rules the
 * Legacy app ran on save. A status change moves the status date to today; a departure reason
 * stands only beside Incomplete; a new note is prepended, dated and signed.
 *
 * Then intake moves on (#931): a move to Accepted or Placed with no Member linked accepts the
 * Applicant (spec story 38: a move, not any save), and a status change writes its Notice. All in one transaction, so a refused accept saves nothing.
 */
class UpdateApplicant
{
    public function __construct(
        private AcceptApplicant $acceptApplicant,
        private ApplicantNoticeWriter $notices,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  the validated record fields, languages and note excluded
     * @param  list<array{language_code: string, speaks?: bool, reads?: bool, writes?: bool}>  $languages
     */
    public function update(Applicant $applicant, array $fields, array $languages, ?string $newNote, Member $actor): void
    {
        DB::transaction(function () use ($applicant, $fields, $languages, $newNote, $actor): void {
            $applicant->fill($fields);
            $statusChanged = $applicant->isDirty('status');

            if ($applicant->status !== ApplicantStatus::Incomplete) {
                $applicant->departure_reason = null;
            }

            if ($statusChanged) {
                $applicant->status_date = OrgTime::today()->toDateString();
            }

            if (filled($newNote)) {
                $applicant->prependNote($newNote, $actor);
            }

            SyncApplicantLanguages::sync($applicant, $languages);

            $applicant->markUpdatedBy($actor);

            $this->moveIntake($applicant, $statusChanged, $actor);
        });
    }

    private function moveIntake(Applicant $applicant, bool $statusChanged, Member $actor): void
    {
        if (! $statusChanged) {
            return;
        }

        $accepting = $applicant->member_id === null
            && in_array($applicant->status, [ApplicantStatus::Accepted, ApplicantStatus::Placed], true);

        if ($accepting) {
            // Accepting tells the mentor already, the placement named, so Placed adds no second Notice.
            $this->notices->accepted($applicant, $this->acceptApplicant->accept($applicant, $actor));

            return;
        }

        match ($applicant->status) {
            ApplicantStatus::Placed => $this->notices->placed($applicant),
            ApplicantStatus::YellowCardCompleted => $this->notices->yellowCardCompleted($applicant),
            ApplicantStatus::Incomplete => $applicant->member_id === null ? null : $this->notices->incomplete($applicant),
            default => null,
        };
    }
}
