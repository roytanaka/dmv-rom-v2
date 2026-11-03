<?php

namespace App\Http\Controllers;

use App\Http\Requests\Renewal\RenewalRequest;
use App\Http\Requests\Renewal\StoreCodeOfConductRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in Member's Renewal (#939, ADR-0038 §9), four steps: the code of conduct, the form,
 * skills, the payment page. Each step resolves a {@see RenewalRequest}, which refuses a Member who
 * need not renew; none takes a Member, so each acts on the signed-in one.
 */
class RenewalController extends Controller
{
    /**
     * Step 1: the code of conduct. Skipped once it is recorded for the year the Renewal serves.
     */
    public function codeOfConduct(RenewalRequest $request): Response|RedirectResponse
    {
        $member = $request->user();
        $year = $member->renewalServedYear();

        // Read raw: the signed-in Member made in memory (a test's actingAs) may lack the column.
        if ((int) ($member->getAttributes()['code_of_conduct_year'] ?? 0) === $year) {
            return to_route('renew.form');
        }

        // The text is a translation string (ADR-0038 §9), sent whole so its list of duties renders in order.
        return Inertia::render('renew/CodeOfConduct', [
            'year' => $year,
            'text' => __('renewal.code_of_conduct'),
        ]);
    }

    /** Save step 1: the year the Renewal serves, and on to the form. */
    public function storeCodeOfConduct(StoreCodeOfConductRequest $request): RedirectResponse
    {
        $member = $request->user();
        $member->forceFill(['code_of_conduct_year' => $member->renewalServedYear()])->save();

        return to_route('renew.form');
    }

    /** Step 2: the renewal form. A placeholder until #940. */
    public function form(RenewalRequest $request): Response
    {
        return $this->placeholder('form');
    }

    /** Step 3: skills. A placeholder until #941. */
    public function skills(RenewalRequest $request): Response
    {
        return $this->placeholder('skills');
    }

    /** Step 4: the payment page. A placeholder until #941. */
    public function payment(RenewalRequest $request): Response
    {
        return $this->placeholder('payment');
    }

    private function placeholder(string $step): Response
    {
        return Inertia::render('renew/Placeholder', ['step' => $step]);
    }
}
