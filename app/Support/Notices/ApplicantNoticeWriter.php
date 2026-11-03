<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\ApplicantNotice;
use App\Models\Applicant;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * The intake Notices (#931, ADR-0038 §8, §14.5), the Legacy app's mails to the Records mailbox and
 * the Treasurer on an Applicant's status change, sent in-app instead. Four Notices, each one Notice
 * {@see Delivery} per recipient, skipping the no-email flag; nothing ever goes to the applicant:
 *
 * - accepted: every member of the member-administration holder, and the mentor;
 * - placed: the mentor;
 * - yellow card completed: the Treasurer of the DMV Executive;
 * - incomplete, once a Member is linked: the mentor, with the reason.
 */
class ApplicantNoticeWriter
{
    public function accepted(Applicant $applicant, Member $member): void
    {
        $this->write(
            $this->payload(ApplicantNotice::ACCEPTED, $applicant, ['member' => ['id' => $member->id]]),
            RecordsRoster::members()->push($applicant->mentor),
        );
    }

    public function placed(Applicant $applicant): void
    {
        $this->write($this->payload(ApplicantNotice::PLACED, $applicant), collect([$applicant->mentor]));
    }

    public function yellowCardCompleted(Applicant $applicant): void
    {
        $this->write($this->payload(ApplicantNotice::YELLOW_CARD, $applicant), $this->treasurers());
    }

    public function incomplete(Applicant $applicant): void
    {
        $this->write(
            $this->payload(ApplicantNotice::INCOMPLETE, $applicant, ['reason' => $applicant->departure_reason?->value]),
            collect([$applicant->mentor]),
        );
    }

    /**
     * One row per distinct recipient, skipping the no-email flag.
     *
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, Member|null>  $recipients
     */
    private function write(array $payload, Collection $recipients): void
    {
        $recipients->filter()
            ->unique('id')
            ->reject(fn (Member $member): bool => $member->no_email)
            ->each(fn (Member $member) => $this->deliver($member, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(Member $member, array $payload): void
    {
        Delivery::create([
            'kind' => DeliveryKind::Notice,
            'member_id' => $member->getKey(),
            'email' => $member->email,
            'payload' => $payload,
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }

    /**
     * Freeze what the Notice names: the Applicant may change again before the Drain runs.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(string $type, Applicant $applicant, array $extra = []): array
    {
        return [
            'notice' => $type,
            'applicant' => [
                'id' => $applicant->id,
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
            ],
            'mentor' => $applicant->mentor?->fullName(),
            'placement' => $applicant->placementGroup?->name,
            ...$extra,
        ];
    }

    /**
     * The Treasurer-role holders of the DMV Executive.
     *
     * @return Collection<int, Member>
     */
    private function treasurers(): Collection
    {
        $executive = Group::executive();

        return $executive === null ? collect() : $this->roster($executive)
            ->filter(fn (GroupMember $m): bool => $m->roles->contains('role', Role::Treasurer))
            ->map(fn (GroupMember $m): Member => $m->member);
    }

    /**
     * @return Collection<int, GroupMember>
     */
    private function roster(Group $group): Collection
    {
        return $group->memberships()
            ->whereIn('status', MembershipStatus::belongingValues())
            ->with(['member', 'roles'])
            ->get();
    }
}
