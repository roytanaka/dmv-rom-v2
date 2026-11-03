<?php

namespace App\Actions;

use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Facades\DB;

/**
 * Save the Applicant record (#928, ADR-0038 §8): the fields sent, the languages, and the rules the
 * Legacy app ran on save. A status change moves the status date to today; a departure reason
 * stands only beside Incomplete; a new note is prepended, dated and signed.
 */
class UpdateApplicant
{
    /**
     * @param  array<string, mixed>  $fields  the validated record fields, languages and note excluded
     * @param  list<array{language_code: string, speaks?: bool, reads?: bool, writes?: bool}>  $languages
     */
    public function update(Applicant $applicant, array $fields, array $languages, ?string $newNote, Member $actor): void
    {
        DB::transaction(function () use ($applicant, $fields, $languages, $newNote, $actor): void {
            $applicant->fill($fields);

            if ($applicant->status !== ApplicantStatus::Incomplete) {
                $applicant->departure_reason = null;
            }

            if ($applicant->isDirty('status')) {
                $applicant->status_date = OrgTime::today()->toDateString();
            }

            if (filled($newNote)) {
                $applicant->prependNote($newNote, $actor);
            }

            SyncApplicantLanguages::sync($applicant, $languages);

            $applicant->markUpdatedBy($actor);
        });
    }
}
