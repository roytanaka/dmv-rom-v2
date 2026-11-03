<?php

namespace App\Actions;

use App\Enums\Category;
use App\Models\Applicant;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Accept an Applicant (#931, ADR-0038 §8): the Applicant becomes a Member and stays, linked through
 * `member_id`. A Member with the Applicant's email and name (a Donor, a resigned Member) is updated
 * and keeps its Category; otherwise a new Member starts Provisional through {@see ChangeCategory},
 * so the Category history entry and status date are written, with this year as its entry year.
 *
 * The Applicant's fields are copied as the Legacy app copied every shared column. On a matched
 * Member a blank Applicant field leaves the Member's value, and the Applicant's notes are prepended
 * to the Member's log. Either way the entry year is this year (spec story 39). A Member under another name holding the email is refused: the email is
 * unique, and the office decides which person this is.
 */
class AcceptApplicant
{
    /** The columns an Applicant and a Member share, copied one for one. */
    private const COPIED = [
        'first_name', 'last_name', 'email', 'phone', 'alternate_phone', 'business_phone',
        'address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country',
        'membership_number', 'membership_expiry', 'membership_lifetime',
        'emergency_contact', 'emergency_contact_number',
        'volunteer_experience', 'past_experience', 'education',
    ];

    public function __construct(private ChangeCategory $changeCategory) {}

    public function accept(Applicant $applicant, Member $actor): Member
    {
        $member = $this->match($applicant);

        return $member === null ? $this->create($applicant, $actor) : $this->update($member, $applicant, $actor);
    }

    /**
     * The Member holding the Applicant's email, when the name matches too.
     *
     * @throws ValidationException when the email belongs to a Member under another name
     */
    private function match(Applicant $applicant): ?Member
    {
        if (blank($applicant->email)) {
            return null;
        }

        $member = Member::where('email', $applicant->email)->first();

        if ($member !== null && ! $this->sameName($member, $applicant)) {
            throw ValidationException::withMessages([
                'email' => __('applicants.email_taken', ['member' => $member->fullName()]),
            ]);
        }

        return $member;
    }

    private function create(Applicant $applicant, Member $actor): Member
    {
        $year = OrgTime::today()->year;

        $member = new Member;
        $member->forceFill([
            ...$applicant->only(self::COPIED),
            'password' => Str::random(40),
            'mentor_name' => $applicant->mentor?->fullName(),
            'notes' => $applicant->notes,
            'entry_year' => $year,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();
        // The row starts on the column default; the Category is ChangeCategory's alone to set.
        $member->refresh();

        $this->copyLanguages($applicant, $member);
        $this->changeCategory->change($member, Category::Provisional, OrgTime::today(), null, $actor);

        $applicant->forceFill(['member_id' => $member->id, 'entry_year' => $year])->save();

        return $member;
    }

    private function update(Member $member, Applicant $applicant, Member $actor): Member
    {
        $copied = array_filter(
            [...$applicant->only(self::COPIED), 'mentor_name' => $applicant->mentor?->fullName()],
            fn ($value) => filled($value),
        );
        $year = OrgTime::today()->year;
        $member->forceFill([...$copied, 'entry_year' => $year]);

        if (filled($applicant->notes)) {
            $member->prependNote($applicant->notes, $actor);
        }

        $this->copyLanguages($applicant, $member);
        $member->markUpdatedBy($actor);

        $applicant->forceFill(['member_id' => $member->id, 'entry_year' => $year])->save();

        return $member;
    }

    /** Each of the Applicant's languages onto the Member, replacing the Member's row for that language. */
    private function copyLanguages(Applicant $applicant, Member $member): void
    {
        foreach ($applicant->languages as $language) {
            $member->languages()->updateOrCreate(
                ['language_code' => $language->language_code],
                $language->only(['speaks', 'reads', 'writes']),
            );
        }
    }

    private function sameName(Member $member, Applicant $applicant): bool
    {
        $normalize = fn (?string $name): string => mb_strtolower(trim((string) $name));

        return $normalize($member->first_name) === $normalize($applicant->first_name)
            && $normalize($member->last_name) === $normalize($applicant->last_name);
    }
}
