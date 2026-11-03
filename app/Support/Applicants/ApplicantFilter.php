<?php

namespace App\Support\Applicants;

use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * What the Applicants list shows (#928, #929, ADR-0038 §8): one status or All, every live one
 * ({@see ApplicantStatus::live()}), and an optional last-name search. One filter for the list,
 * Print list and the Send email Audience, so the three always hold the same rows.
 */
final class ApplicantFilter
{
    /** The list's default status word: every live case. */
    public const ALL = 'all';

    public function __construct(
        public readonly ?ApplicantStatus $status = null,
        public readonly ?string $search = null,
    ) {}

    /** The list's query parameters, `status` and `q`; an unknown status is All. */
    public static function fromRequest(Request $request): self
    {
        $status = $request->query('status');
        $search = $request->query('q');

        return new self(
            is_string($status) ? ApplicantStatus::tryFrom($status) : null,
            is_string($search) && trim($search) !== '' ? trim($search) : null,
        );
    }

    /**
     * The filter as Send email names it (`context_subject`): the status word, then `:` and the
     * search when there is one, as `new:Smi`. Null for an unknown status word.
     */
    public static function fromSubject(mixed $subject): ?self
    {
        if (! is_string($subject)) {
            return null;
        }

        [$word, $search] = array_pad(explode(':', $subject, 2), 2, null);
        $status = $word === self::ALL ? null : ApplicantStatus::tryFrom($word);

        if ($word !== self::ALL && $status === null) {
            return null;
        }

        return new self($status, is_string($search) && trim($search) !== '' ? trim($search) : null);
    }

    /** The Send email subject for this filter; {@see fromSubject()} reads it back. */
    public function subject(): string
    {
        return $this->search === null ? $this->statusWord() : $this->statusWord().':'.$this->search;
    }

    /** The status word: a status value, or `all`. */
    public function statusWord(): string
    {
        return $this->status?->value ?? self::ALL;
    }

    /**
     * The Applicants this filter shows, sorted by mentor then last name, with their mentor and
     * placement. The unassigned sort first, as the Legacy app's blank Mentor did.
     *
     * @return Builder<Applicant>
     */
    public function query(): Builder
    {
        return Applicant::query()
            ->select('applicants.*')
            ->when(
                $this->status,
                fn (Builder $rows, ApplicantStatus $one) => $rows->where('status', $one),
                fn (Builder $rows) => $rows->whereIn('status', ApplicantStatus::live()),
            )
            ->when($this->search, fn (Builder $rows, string $term) => $rows->whereLike('applicants.last_name', addcslashes($term, '%_\\').'%'))
            ->leftJoin('members as mentors', 'mentors.id', '=', 'applicants.mentor_id')
            ->orderBy('mentors.last_name')
            ->orderBy('mentors.first_name')
            ->orderBy('applicants.last_name')
            ->orderBy('applicants.first_name')
            ->orderBy('applicants.id')
            ->with(['mentor', 'placementGroup']);
    }
}
