<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\SignUp;
use Illuminate\Support\Collection;

/**
 * The Booking Request and Confirmation writer (#799, ADR-0032 §9). A *Request* asks the Members
 * who may give the Booking's Tour to sign up; a *Confirmation* tells the Members on the Booking
 * who else is on it. Adding a Booking sends one ({@see onCreate}); a Booker, the Chair or
 * super-tier sends either again from the Booking.
 *
 * It writes one Notice {@see Delivery} per recipient, skipping the no-email flag, plus one copy to
 * the Group's copy address ({@see GroupCopyWriter}). The Booking may change by drain time, so each
 * row carries a snapshot of what the mail names, and the sending Booker's name and address for
 * Reply-To. Shape mirrors {@see BookingMail::fromSnapshot}.
 */
class BookingMailWriter
{
    public function __construct(private GroupCopyWriter $copy) {}

    /**
     * On create: a Request while seats are open, a Confirmation once the Booking is full.
     */
    public function onCreate(Booking $booking, Member $sender): void
    {
        $shift = $booking->shift;

        if ($shift->signUps()->count() >= $shift->capacity) {
            $this->confirmation($booking, $sender);
        } else {
            $this->request($booking, $sender);
        }
    }

    /**
     * The Request: to every current Member of the Group with an active qualification on the
     * Booking's Tour, or the whole current roster when the Tour is open to all. "Current" is the
     * sign-up floor ({@see MembershipStatus::canSignUp}): asking someone who cannot sign up is noise.
     */
    public function request(Booking $booking, Member $sender): void
    {
        $tour = $booking->tour;

        $recipients = $booking->group->memberships()
            ->with(['member', 'qualifications'])
            ->get()
            ->filter(fn (GroupMember $membership): bool => $membership->status->canSignUp())
            ->filter(fn (GroupMember $membership): bool => $tour->open_to_all
                || $membership->qualifications->contains(fn ($held) => $held->active && $held->tour_id === $tour->id))
            ->map(fn (GroupMember $membership): Member => $membership->member);

        $this->write($booking, $sender, BookingMail::REQUEST, $recipients);
    }

    /**
     * The Confirmation: to every Member signed up on the Booking, listing them all.
     */
    public function confirmation(Booking $booking, Member $sender): void
    {
        $recipients = $booking->shift->signUps()->with('member')->get()
            ->map(fn (SignUp $signUp): Member => $signUp->member);

        $this->write($booking, $sender, BookingMail::CONFIRMATION, $recipients);
    }

    /**
     * One row per recipient with an address and without the no-email flag — checked once here,
     * the sole point a Booking mail Delivery is written (ADR-0024 §9) — then the Group copy.
     *
     * @param  Collection<int, Member>  $recipients
     */
    private function write(Booking $booking, Member $sender, string $notice, Collection $recipients): void
    {
        $payload = $this->snapshot($booking, $sender, $notice);

        $recipients
            ->unique(fn (Member $member): int => $member->getKey())
            ->reject(fn (Member $member): bool => (bool) $member->no_email || blank($member->email))
            ->each(fn (Member $member) => Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $member->getKey(),
                'email' => $member->email,
                'payload' => $payload,
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]));

        $this->copy->write($booking->group, $payload, $sender->preferredLocale());
    }

    /**
     * Freeze what the mail names. Tour, client, leader and comments are content, kept as written.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Booking $booking, Member $sender, string $notice): array
    {
        $shift = $booking->shift;
        $signUps = $shift->signUps()->with('member')->get();

        return [
            'notice' => $notice,
            'group' => [
                'name' => $booking->group->name,
                'slug' => $booking->group->slug,
            ],
            'schedule_id' => $shift->schedule_id,
            'reply_to' => [
                'name' => $sender->fullName(),
                'email' => $sender->email,
            ],
            'booking' => [
                'tour' => $booking->tour->name,
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'visitors' => $booking->visitors,
                'client' => $booking->client,
                'leader' => $booking->leader,
                'comments' => $booking->comments,
                'seats_needed' => max(0, $shift->capacity - $signUps->count()),
                'docents' => $signUps
                    ->map(fn (SignUp $signUp): string => $signUp->member->fullName())
                    ->sort()
                    ->values()
                    ->all(),
            ],
        ];
    }
}
