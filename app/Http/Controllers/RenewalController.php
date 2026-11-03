<?php

namespace App\Http\Controllers;

use App\Actions\SaveRenewalForm;
use App\Http\Requests\Renewal\RenewalRequest;
use App\Http\Requests\Renewal\StoreCodeOfConductRequest;
use App\Http\Requests\Renewal\StoreRenewalFormRequest;
use App\Http\Requests\Renewal\StoreRenewalSkillsRequest;
use App\Models\FeeCategory;
use App\Models\SkillCategory;
use App\Support\Renewal\RenewalPlacements;
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

    /**
     * Step 2: the renewal form (#940), pre-filled from the record; in edit mode, from what the
     * Member saved for the year.
     */
    public function form(RenewalRequest $request, SaveRenewalForm $form): Response
    {
        // Every column, read fresh: the form shows the Records fields the session's copy may lack.
        $member = $request->user()->refresh();

        return Inertia::render('renew/Form', [
            'year' => $member->renewalServedYear(),
            'editing' => $form->editing($member),
            'fields' => $form->fields($member),
            'placements' => $form->checklist($member),
            'groups' => $form->groups(),
            'positions' => RenewalPlacements::POSITIONS,
            'categories' => $form->categories()->map(fn (FeeCategory $fee): array => [
                'code' => $fee->code,
                'name' => $fee->name,
                'extra' => $fee->extra,
                'amount' => $fee->amount,
            ])->values(),
        ]);
    }

    /**
     * Save step 2. Resign, LOA and Active to Sustaining end the flow on a page that says Records
     * will be in touch; everything else moves on to skills.
     */
    public function storeForm(StoreRenewalFormRequest $request, SaveRenewalForm $form): RedirectResponse
    {
        $ends = $form->save($request->user()->refresh(), $request->validated());

        return to_route($ends ? 'renew.contact' : 'renew.skills');
    }

    /** The end of a Renewal that Records follows up in person (#940). */
    public function contact(RenewalRequest $request): Response
    {
        return Inertia::render('renew/RecordsInTouch');
    }

    /**
     * Step 3: skills (#941), the Settings skills picker inside the Renewal. A Member who chose
     * Sustaining goes straight to payment, as in the Legacy app.
     */
    public function skills(RenewalRequest $request, SaveRenewalForm $form): Response|RedirectResponse
    {
        $member = $request->user()->refresh();

        if (! $form->editing($member)) {
            return to_route('renew.form');
        }

        if ($form->skipsSkills($member)) {
            return to_route('renew.payment');
        }

        return Inertia::render('renew/Skills', [
            'year' => $member->renewalServedYear(),
            'catalog' => SkillCategory::catalog(),
            'selected' => $member->skills()->pluck('skills.id'),
        ]);
    }

    /** Save step 3: the whole skill set replaced, as Settings → Skills does, and on to payment. */
    public function storeSkills(StoreRenewalSkillsRequest $request): RedirectResponse
    {
        $request->user()->skills()->sync($request->validated('skills') ?? []);

        return to_route('renew.payment');
    }

    /**
     * Step 4: the payment page (#941). The fee is the amount paid the form set; the link is the
     * ROM's Akaraisin page with only the amount, as the Legacy app builds it. Nothing comes back:
     * Records types the paid-through year when the money arrives. With no amount (a Provisional
     * Member, whose fee Records sets), the page shows neither fee nor link.
     */
    public function payment(RenewalRequest $request, SaveRenewalForm $form): Response|RedirectResponse
    {
        $member = $request->user()->refresh();

        if (! $form->editing($member)) {
            return to_route('renew.form');
        }

        $amount = $member->amount_paid;

        return Inertia::render('renew/Payment', [
            'year' => $member->renewalServedYear(),
            'amount' => $amount,
            'link' => $amount === null ? null : $this->akaraisinLink($amount),
        ]);
    }

    /** The Akaraisin base URL with `amt` appended: whole dollars bare, as the Legacy app sends. */
    private function akaraisinLink(string $amount): string
    {
        $base = (string) config('services.akaraisin.url');
        $amt = fmod((float) $amount, 1.0) === 0.0 ? (string) (int) $amount : $amount;

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['amt' => $amt]);
    }
}
