<?php

namespace App\Actions;

use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Facades\DB;

/**
 * Store a new application (#928, ADR-0038 §8) as a New Applicant dated today. Entered by a holder
 * member (Add applicant), it records them and the mentor they chose; entered by the applicant
 * (the public form, #930), `$enteredBy` is null and the case starts unassigned.
 */
class RecordApplication
{
    /**
     * @param  array<string, mixed>  $fields  the validated application fields
     * @param  list<array{language_code: string, speaks?: bool, reads?: bool, writes?: bool}>  $languages
     */
    public function record(array $fields, array $languages, ?Member $enteredBy, ?int $mentorId = null): Applicant
    {
        return DB::transaction(function () use ($fields, $languages, $enteredBy, $mentorId): Applicant {
            $today = OrgTime::today()->toDateString();

            $applicant = new Applicant($fields);
            $applicant->forceFill([
                'status' => ApplicantStatus::New,
                'status_date' => $today,
                'applied_on' => $today,
                'mentor_id' => $enteredBy === null ? null : $mentorId,
                'entered_by_applicant' => $enteredBy === null,
                'created_by' => $enteredBy?->id,
                'updated_by' => $enteredBy?->id,
            ])->save();

            SyncApplicantLanguages::sync($applicant, $languages);

            return $applicant;
        });
    }
}
