<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\Member;
use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Reminder mail (#486, spec #479, ADR-0024 §7). A Member with an upcoming Sign-up is
 * reminded a few days before their Shift. The daily pass writes one Reminder {@see Delivery}
 * keyed to the (Shift, Member) pair, and the Drain builds this Mailable and sends it within the
 * hour.
 *
 * Unlike a Notice, a Reminder renders from the **live Shift**, not a payload snapshot: the row
 * carries a `shift_id`, and the Shift is still there at drain time because a Reminder expires at
 * its Shift's start and a deleted Shift cascades the row away (ADR-0024 §7, §4). So the Drain
 * hands this the loaded Shift (its Schedule, Group, and kind) and the recipient Member.
 *
 * One bilingual chrome template covers every Group. It renders in the recipient's saved locale.
 * From is the app's one address carrying the Group's name as the display name; there is no
 * Reply-To (an automatic Reminder is not a conversation). The body names the Shift and links to
 * its Schedule, the link built for the render locale (ADR-0008) so a French recipient gets a
 * `/fr/` URL.
 */
class ShiftReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Shift  $shift  the upcoming Shift — named in the mail, with its Schedule, Group,
     *                        and kind loaded (the subject, body, and link read them)
     * @param  Member  $member  the Member being reminded of their own seat
     */
    public function __construct(
        public Shift $shift,
        public Member $member,
    ) {}

    /**
     * The recipient-facing envelope: the keyed subject — "Reminder: your <Group> shift on
     * <date>", the Group as-authored and the date on the org wall clock in the render locale —
     * and From, the app's one address wearing the Group's name. No Reply-To.
     */
    public function envelope(): Envelope
    {
        $group = $this->shift->schedule->group;
        $date = $this->shift->starts_at->copy()
            ->setTimezone(config('app.org_timezone'))
            ->translatedFormat('j F Y');

        return new Envelope(
            from: new Address(config('mail.from.address'), $group->name),
            subject: __('scheduling.reminder_email.subject', ['group' => $group->name, 'date' => $date]),
        );
    }

    /**
     * The body — a Markdown view sharing the Mailable's Shift and Member.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shift-reminder',
        );
    }
}
