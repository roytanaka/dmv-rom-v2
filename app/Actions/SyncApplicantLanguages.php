<?php

namespace App\Actions;

use App\Models\Applicant;

/**
 * Make an Applicant's languages exactly the rows a form sent (#928, ADR-0038 §8). The forms send
 * the whole list, so a row left out is removed.
 */
class SyncApplicantLanguages
{
    /**
     * @param  list<array{language_code: string, speaks?: bool, reads?: bool, writes?: bool}>  $languages
     */
    public static function sync(Applicant $applicant, array $languages): void
    {
        $applicant->languages()->delete();

        foreach ($languages as $row) {
            $applicant->languages()->create([
                'language_code' => $row['language_code'],
                'speaks' => (bool) ($row['speaks'] ?? false),
                'reads' => (bool) ($row['reads'] ?? false),
                'writes' => (bool) ($row['writes'] ?? false),
            ]);
        }
    }
}
