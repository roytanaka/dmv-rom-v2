<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteApplicantRequest;
use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;

/**
 * Delete on the Applicant record (#929, ADR-0038 §8): a hard delete, as the Legacy app's, offered
 * only while no Member is linked so an accepted Applicant is never lost. Its languages cascade.
 */
class ApplicantDeletionController extends Controller
{
    public function __invoke(DeleteApplicantRequest $request, Applicant $applicant): RedirectResponse
    {
        $applicant->delete();

        return redirect()->route('officer.members.list.applicants');
    }
}
