<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Sign-up cancellation email (#358, PRD #352, ADR-0021 §Sign-up "Notification") — the
 * first transactional mail in the app, and the pattern the rest follow: a Mailable plus a
 * Blade view, its chrome translated through the lang files ({@see docs/conventions.md} § i18n)
 * and rendered in the recipient's locale (each Scheduler is a {@see Member}, which declares a
 * `HasLocalePreference`, so one send loop writes to a French and an English Scheduler each in
 * their own language).
 *
 * It is **unconditional** — no time threshold, no proximity window, no opt-out. It carries the
 * Shift it names (date, times, and kind where the Group uses kinds) and the Member who dropped;
 * the view converts the stored-UTC instants onto the org wall clock. It is one email, sent
 * synchronously — deliberately *not* queued, and *not* a reminder pipeline.
 */
class SignUpCancelled extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Shift  $shift  the Shift the dropped seat was on — named in the mail
     * @param  Member  $member  the Member who dropped the seat
     */
    public function __construct(
        public Shift $shift,
        public Member $member,
    ) {}

    /**
     * The subject, keyed so it resolves in the recipient's locale at render time.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
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
