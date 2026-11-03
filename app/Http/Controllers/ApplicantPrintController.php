<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantLanguage;
use App\Models\MemberLanguage;
use App\Policies\ApplicantPolicy;
use App\Support\Applicants\ApplicantFilter;
use App\Support\OrgTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Print list and Print record on the Applicants pages (#929, ADR-0038 §8, §14): print-styled HTML
 * pages the browser prints, with no PDF package. Anyone who may read Applicants may print them
 * ({@see ApplicantPolicy}). The list prints every row the status filter and search show, unpaged;
 * the record prints every field, labelled on the server in the reader's language.
 */
class ApplicantPrintController extends Controller
{
    public function list(Request $request): Response
    {
        Gate::authorize('viewAny', Applicant::class);

        $filter = ApplicantFilter::fromRequest($request);

        return Inertia::render('officer/applicants/PrintList', [
            'status' => $filter->statusWord(),
            'search' => $filter->search,
            'applicants' => $filter->query()
                ->get()
                ->map(fn (Applicant $applicant) => [
                    'id' => $applicant->id,
                    'first_name' => $applicant->first_name,
                    'last_name' => $applicant->last_name,
                    'status' => $applicant->status->value,
                    'placement' => $applicant->placementGroup?->name,
                    'applied_on' => $applicant->applied_on->toDateString(),
                    'last_update' => OrgTime::toLocal($applicant->updated_at)->toDateString(),
                    'email' => $applicant->email,
                    'phone' => $applicant->phone,
                    'mentor' => $applicant->mentor?->fullName(),
                ])
                ->values(),
        ]);
    }

    public function record(Applicant $applicant): Response
    {
        Gate::authorize('view', $applicant);

        $applicant->load(['mentor', 'placementGroup', 'createdBy', 'updatedBy', 'languages' => fn ($query) => $query->orderBy('id')]);

        return Inertia::render('officer/applicants/PrintRecord', [
            'id' => $applicant->id,
            'name' => $applicant->fullName(),
            'sections' => $this->sections($applicant),
            'notes' => $applicant->notes,
        ]);
    }

    /**
     * The record as labelled sections, in the order of the Legacy app's printout: the intake facts
     * first (mentor, status, placement), then the application, then the languages and background.
     *
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private function sections(Applicant $applicant): array
    {
        $field = fn (string $name) => (string) __("records.field.{$name}");
        $date = fn ($value) => $value?->toDateString() ?? '';

        $intake = [
            [__('applicants.field.mentor'), $applicant->mentor?->fullName() ?? __('applicants.unassigned')],
            [__('applicants.field.status'), __("applicants.status.{$applicant->status->value}")],
            [__('applicants.field.status_date'), $date($applicant->status_date)],
        ];
        if ($applicant->departure_reason !== null) {
            $intake[] = [__('applicants.field.departure_reason'), __("member.departure_reason.{$applicant->departure_reason->value}")];
        }
        array_push(
            $intake,
            [__('applicants.field.placement'), $applicant->placementGroup?->name ?? ''],
            [__('applicants.field.applied_on'), $date($applicant->applied_on)],
            [__('applicants.field.entry_year'), (string) ($applicant->entry_year ?? '')],
            [__('applicants.outputs.entered_by'), $applicant->entered_by_applicant
                ? __('applicants.outputs.the_applicant')
                : ($applicant->createdBy?->fullName() ?? '')],
            [__('applicants.outputs.last_updated'), $applicant->updatedBy === null ? '' : __('applicants.outputs.by_on', [
                'name' => $applicant->updatedBy->fullName(),
                'date' => OrgTime::toLocal($applicant->updated_at)->toDateString(),
            ])],
        );

        $plain = fn (array $names) => array_map(fn (string $name) => [$field($name), (string) ($applicant->{$name} ?? '')], $names);

        $sections = [
            ['title' => __('applicants.section.intake'), 'rows' => $intake],
            ['title' => __('records.section.identity'), 'rows' => $plain(['first_name', 'last_name', 'email'])],
            ['title' => __('records.section.contact'), 'rows' => $plain(['phone', 'alternate_phone', 'business_phone'])],
            ['title' => __('records.section.address'), 'rows' => $plain(['address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country'])],
            ['title' => __('records.section.rom'), 'rows' => [
                [$field('membership_number'), (string) ($applicant->membership_number ?? '')],
                [$field('membership_expiry'), $applicant->membership_lifetime ? $field('membership_lifetime') : $date($applicant->membership_expiry)],
            ]],
            ['title' => __('records.section.emergency'), 'rows' => $plain(['emergency_contact', 'emergency_contact_number'])],
            ['title' => __('applicants.languages.title'), 'rows' => $applicant->languages
                ->map(fn (ApplicantLanguage $language) => [$this->languageName($language->language_code), $this->languageUses($language)])
                ->all()],
            ['title' => __('records.section.background'), 'rows' => $plain(['volunteer_experience', 'past_experience', 'education'])],
        ];

        return array_map(fn (array $section) => [
            'title' => (string) $section['title'],
            'rows' => array_map(fn (array $row) => ['label' => (string) $row[0], 'value' => (string) $row[1]], $section['rows']),
        ], $sections);
    }

    private function languageName(string $code): string
    {
        return in_array($code, MemberLanguage::KNOWN_CODES, true) ? (string) __("records.languages.name.{$code}") : $code;
    }

    private function languageUses(ApplicantLanguage $language): string
    {
        return collect(['speaks', 'reads', 'writes'])
            ->filter(fn (string $flag) => $language->{$flag})
            ->map(fn (string $flag) => (string) __("records.languages.{$flag}"))
            ->implode(', ');
    }
}
