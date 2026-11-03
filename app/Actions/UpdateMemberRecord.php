<?php

namespace App\Actions;

use App\Models\Member;

/**
 * Save the Member record (#917, ADR-0038 §3, §10): the fields member administration sent, plus
 * the rules the Legacy app ran on save.
 */
class UpdateMemberRecord
{
    /**
     * @param  array<string, mixed>  $fields  the validated record fields
     * @param  bool  $nextAwardYearSent  whether the form sent a next award year
     */
    public function update(Member $member, array $fields, bool $nextAwardYearSent, ?string $newNote, Member $actor): void
    {
        $member->fill($fields);

        // A new last award year plans the next one (ADR-0038 §10). A year sent alongside it is
        // the admin's override, even when it is the year already stored.
        if ($member->isDirty('last_award_year') && $member->last_award_year !== null && ! $nextAwardYearSent) {
            $member->next_award_year = $member->last_award_year + 5;
        }

        if ($member->isDirty('email')) {
            $member->email_verified_at = null;
        }

        if (filled($newNote)) {
            $member->prependNote($newNote, $actor);
        }

        $member->markUpdatedBy($actor);
    }
}
