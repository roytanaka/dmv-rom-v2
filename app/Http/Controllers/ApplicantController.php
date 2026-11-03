<?php

namespace App\Http\Controllers;

use App\Actions\RecordApplication;
use App\Actions\UpdateApplicant;
use App\Enums\ApplicantStatus;
use App\Enums\DepartureReason;
use App\Enums\MemberList;
use App\Http\Requests\StoreApplicantRequest;
use App\Http\Requests\UpdateApplicantRequest;
use App\Models\Applicant;
use App\Models\ApplicantLanguage;
use App\Policies\ApplicantPolicy;
use App\Support\Applicants\ApplicantOptions;
use App\Support\OrgTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Applicants pages (#928, ADR-0038 §8) under Officer Tools → Members: the list, Add applicant
 * and the Applicant record. Run by the holder of the `applicants` Stewardship, read by any Chair of
 * an active Group and member administration ({@see ApplicantPolicy}). The list is
 * also the seventh Member list, at its URL word.
 */
class ApplicantController extends Controller
{
    private const PER_PAGE = 50;

    /** The list's default filter: every live case ({@see ApplicantStatus::live()}). */
    public const ALL = 'all';

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Applicant::class);

        $status = $request->query('status');
        $filter = is_string($status) ? ApplicantStatus::tryFrom($status) : null;
        $query = $request->query('q');
        $search = is_string($query) && trim($query) !== '' ? trim($query) : null;
        $actor = $request->user();

        return Inertia::render('officer/applicants/Index', [
            'status' => $filter?->value ?? self::ALL,
            'statuses' => array_map(fn (ApplicantStatus $case) => $case->value, ApplicantStatus::cases()),
            'search' => $search,
            'canCreate' => $actor->can('create', Applicant::class),
            // Member administration reaches this page as the seventh Member list, so it keeps the tabs.
            'memberListTabs' => $actor->can('administer-members') ? MemberList::tabs() : null,
            'applicants' => self::listed($filter)
                ->when($search, fn (Builder $rows, string $term) => $rows->whereLike('applicants.last_name', addcslashes($term, '%_\\').'%'))
                ->with(['mentor', 'placementGroup'])
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Applicant $applicant) => [
                    'id' => $applicant->id,
                    'first_name' => $applicant->first_name,
                    'last_name' => $applicant->last_name,
                    'status' => $applicant->status->value,
                    'placement' => $applicant->placementGroup?->name,
                    'applied_on' => $applicant->applied_on->toDateString(),
                    'last_update' => OrgTime::toLocal($applicant->updated_at)->toDateString(),
                    'email' => $applicant->email,
                    'mentor' => $applicant->mentor?->fullName(),
                    'can_edit' => $actor->can('update', $applicant),
                ]),
        ]);
    }

    /**
     * The Applicants a filter shows, sorted by mentor then last name: one status, or every live
     * one. The unassigned sort first, as the Legacy app's blank Mentor did. Public so Send email
     * (#929) can resolve the same rows.
     *
     * @return Builder<Applicant>
     */
    public static function listed(?ApplicantStatus $status): Builder
    {
        return Applicant::query()
            ->select('applicants.*')
            ->when(
                $status,
                fn (Builder $rows, ApplicantStatus $one) => $rows->where('status', $one),
                fn (Builder $rows) => $rows->whereIn('status', ApplicantStatus::live()),
            )
            ->leftJoin('members as mentors', 'mentors.id', '=', 'applicants.mentor_id')
            ->orderBy('mentors.last_name')
            ->orderBy('mentors.first_name')
            ->orderBy('applicants.last_name')
            ->orderBy('applicants.first_name')
            ->orderBy('applicants.id');
    }

    public function create(): Response
    {
        Gate::authorize('create', Applicant::class);

        return Inertia::render('officer/applicants/Create', [
            'mentors' => ApplicantOptions::mentors(),
        ]);
    }

    public function store(StoreApplicantRequest $request, RecordApplication $action): RedirectResponse
    {
        $applicant = $action->record(
            $request->safe()->except(['languages', 'mentor_id', 'override_duplicate_email']),
            $request->validated('languages') ?? [],
            $request->user(),
            $request->validated('mentor_id'),
        );

        return redirect()->route('officer.members.applicants.show', ['applicant' => $applicant->id]);
    }

    public function show(Request $request, Applicant $applicant): Response
    {
        Gate::authorize('view', $applicant);

        $applicant->load([
            'mentor',
            'placementGroup',
            'member',
            'createdBy',
            'updatedBy',
            'languages' => fn ($query) => $query->orderBy('id'),
        ]);

        return Inertia::render('officer/applicants/Record', [
            'applicant' => [
                ...$applicant->only([
                    'id', 'first_name', 'last_name', 'email', 'phone', 'alternate_phone', 'business_phone',
                    'address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country',
                    'membership_number', 'membership_lifetime', 'emergency_contact', 'emergency_contact_number',
                    'placement_group_id', 'mentor_id', 'entered_by_applicant', 'entry_year', 'member_id', 'notes',
                    'volunteer_experience', 'past_experience', 'education',
                ]),
                'membership_expiry' => $applicant->membership_expiry?->toDateString(),
                'status' => $applicant->status->value,
                'status_date' => $applicant->status_date?->toDateString(),
                'departure_reason' => $applicant->departure_reason?->value,
                'applied_on' => $applicant->applied_on->toDateString(),
                'languages' => $applicant->languages->map(fn (ApplicantLanguage $language) => $language->only([
                    'language_code', 'speaks', 'reads', 'writes',
                ]))->values(),
            ],
            'canEdit' => $request->user()->can('update', $applicant),
            'statuses' => array_map(fn (ApplicantStatus $case) => $case->value, ApplicantStatus::cases()),
            'departureReasons' => array_map(fn (DepartureReason $reason) => $reason->value, DepartureReason::cases()),
            'mentors' => self::withCurrent(ApplicantOptions::mentors(), $applicant->mentor_id, $applicant->mentor?->fullName()),
            'placements' => self::withCurrent(ApplicantOptions::placements(), $applicant->placement_group_id, $applicant->placementGroup?->name),
            'enteredBy' => $applicant->createdBy?->fullName(),
            'lastUpdated' => $applicant->updatedBy === null ? null : [
                'name' => $applicant->updatedBy->fullName(),
                'date' => OrgTime::toLocal($applicant->updated_at)->toDateString(),
            ],
        ]);
    }

    public function update(UpdateApplicantRequest $request, UpdateApplicant $action, Applicant $applicant): RedirectResponse
    {
        $action->update(
            $applicant,
            $request->safe()->except(['languages', 'new_note']),
            $request->validated('languages') ?? [],
            $request->validated('new_note'),
            $request->user(),
        );

        return back();
    }

    /**
     * The options, with the Applicant's own choice kept on offer once it has left them (a mentor who
     * left the holder, a placement Group since archived).
     *
     * @param  Collection<int, array{id: int, name: string}>  $options
     * @return Collection<int, array{id: int, name: string}>
     */
    private static function withCurrent(Collection $options, ?int $id, ?string $name): Collection
    {
        if ($id !== null && ! $options->contains('id', $id)) {
            $options->push(['id' => $id, 'name' => (string) $name]);
        }

        return $options->values();
    }
}
