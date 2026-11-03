<?php

namespace App\Policies;

use App\Models\Applicant;
use App\Models\Member;

/**
 * Authorization for Applicants (#928, ADR-0038 §2, §8). Intake belongs to the Group holding the
 * `applicants` Stewardship: its Chair edits every Applicant, its members edit the ones they mentor
 * and read the rest. Any Chair of an active Group and member administration read. The super-tier
 * passes everything through the Gate::before short-circuit (AppServiceProvider), so a rule that
 * must bind it too is re-checked in the Form Request (see {@see deletable()}).
 */
class ApplicantPolicy
{
    public function viewAny(Member $actor): bool
    {
        return $actor->hasApplicantAuthority()
            || $actor->hasMemberAdminAuthority()
            || $actor->chairsAnActiveGroup();
    }

    public function view(Member $actor, Applicant $applicant): bool
    {
        return $this->viewAny($actor);
    }

    /** Add applicant: any member of the holder. */
    public function create(Member $actor): bool
    {
        return $actor->hasApplicantAuthority();
    }

    /** The Applicant Chair edits all; a holder member edits the cases they mentor. */
    public function update(Member $actor, Applicant $applicant): bool
    {
        return $actor->isApplicantChair()
            || ($applicant->mentor_id === $actor->id && $actor->hasApplicantAuthority());
    }

    /**
     * Delete: whoever may edit, and only while no Member is linked. Super-tier skips this method
     * via Gate::before, so the Form Request checks {@see deletable()} again.
     */
    public function delete(Member $actor, Applicant $applicant): bool
    {
        return $this->update($actor, $applicant) && self::deletable($applicant);
    }

    /** Whether an Applicant may be deleted: never once Accept has linked a Member. */
    public static function deletable(Applicant $applicant): bool
    {
        return $applicant->member_id === null;
    }

    /** Publish the Applicant code: the Applicant Chair. */
    public function setCode(Member $actor): bool
    {
        return $actor->isApplicantChair();
    }
}
