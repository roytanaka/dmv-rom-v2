<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApplicantCodeRequest;
use App\Models\ApplicantCode;
use Illuminate\Http\RedirectResponse;

/**
 * The Applicant code dialog's save (#930, ADR-0038 §8), on the Applicants page. The Applicant
 * Chair only ({@see UpdateApplicantCodeRequest}).
 */
class ApplicantCodeController extends Controller
{
    public function update(UpdateApplicantCodeRequest $request): RedirectResponse
    {
        $row = ApplicantCode::current();
        $row->fill([
            'code' => $request->validated('code'),
            'published' => $request->boolean('published'),
            'expires_on' => $request->validated('expires_on'),
        ]);
        $row->updated_by = $request->user()->id;
        $row->save();

        return back();
    }
}
