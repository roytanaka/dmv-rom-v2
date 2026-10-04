<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Sign-up cancellation Notice (#358, PRD #352, ADR-0021 §Sign-up "Notification"; moved
 * onto the Delivery queue in #481, ADR-0024). When a Member drops their own seat, every
 * Scheduler and Chair of the owning Group is told — but nothing sends in the web request any
 * more: the controller writes one Notice {@see Delivery} per recipient, and the
 * Drain builds this Mailable from the row's payload snapshot and sends it.
 *
 * It renders in the recipient's saved locale. From is the app's one address carrying the
 * Group's name as the display name; there is no Reply-To (an automatic Notice is not a
 * conversation). The body names the dropped Shift and links to its Schedule, the link built
 * for the render locale (ADR-0008) so a French recipient gets a `/fr/` URL.
 *
 * The Sign-up is gone by drain time, so the Drain reconstructs the Mailable from a snapshot
 * via {@see fromSnapshot} rather than from live rows; the direct constructor is kept for the
 * body/subject render tests, which pass real models.
 */
class SignUpCancelled extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Shift  $shift  the Shift the dropped seat was on — named in the mail, with its
     *                        Schedule and Group loaded (the From name and the link read them)
     * @param  Member  $member  the Member who dropped the seat
     */
    public function __construct(
        public Shift $shift,
        public Member $member,
    ) {}

    /**
     * Rebuild the Mailable from a Notice Delivery's payload snapshot. The Sign-up — and, in
     * the general Notice case, the Shift — may be gone at drain time, so the row froze
     * everything the mail needs at write time: the Member's name, the Group's name and slug,
     * the Schedule's id and name, and the Shift's times and kind. The reconstructed models are
     * never saved; they exist only to feed the Mailable and its view.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        $group = new Group(['name' => $payload['group']['name'], 'slug' => $payload['group']['slug']]);

        $schedule = new Schedule(['name' => $payload['schedule']['name']]);
        $schedule->id = $payload['schedule']['id'];
        $schedule->setRelation('group', $group);

        $shift = new Shift(['starts_at' => $payload['shift']['starts_at'], 'ends_at' => $payload['shift']['ends_at']]);
        $shift->setRelation('schedule', $schedule);
        $shift->setRelation('kind', $payload['shift']['kind'] === null
            ? null
            : new ShiftKind(['name' => $payload['shift']['kind']]));

        $member = new Member([
            'first_name' => $payload['member']['first_name'],
            'last_name' => $payload['member']['last_name'],
        ]);

        return new self($shift, $member);
    }

    /**
     * The recipient-facing envelope: the keyed subject (resolved in the render locale) and
     * From — the app's one address wearing the Group's name. No Reply-To.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->shift->schedule->group->name),
            subject: __('scheduling.cancellation_email.subject'),
        );
    }

    /**
     * The body — a Markdown view sharing the Mailable's Shift and Member.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sign-up-cancelled',
        );
    }
}
