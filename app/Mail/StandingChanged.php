<?php

namespace App\Mail;

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use App\Support\Notices\StandingChangeNoticeWriter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The standing-change Notice (#485, spec #479, ADR-0024 §8). When a Member's DMV-wide Category
 * moves into a departure — Resigned or Deceased — every Chair of every Group where that Member's
 * Membership is not already departed is told, so the Chair can update their own roster. The
 * {@see StandingChangeNoticeWriter} writes one Notice {@see Delivery} per
 * Chair, and the Drain builds this Mailable from the row's payload snapshot and sends it.
 *
 * It renders in the recipient's saved locale. From is the app's one address carrying the Group's
 * name as the display name; there is no Reply-To (an automatic Notice is not a conversation —
 * ADR-0024 §3). The body names the Member, the Group, the new standing, and the effective date,
 * and links to the Group's roster, the link built for the render locale (ADR-0008) so a French
 * Chair gets a `/fr/` URL.
 *
 * The Member's record may be gone (or further changed) by drain time, so the Drain reconstructs
 * the Mailable from a snapshot via {@see fromSnapshot} rather than from live rows; the direct
 * constructor is kept for the body/subject render tests, which pass real models.
 */
class StandingChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The payload discriminator that tells the Drain a Notice Delivery is a standing change
     * rather than a Sign-up cancellation — both ride the {@see DeliveryKind::Notice}
     * kind (ADR-0024 §8), so the kind alone cannot pick the Mailable.
     */
    public const NOTICE_TYPE = 'standing_change';

    /**
     * @param  Member  $member  the Member whose standing changed — named in the mail
     * @param  Group  $group  the Group whose Chair is being told, and whose roster the mail links
     * @param  Category  $standing  the new DMV-wide standing (Resigned or Deceased)
     * @param  CarbonInterface  $effectiveDate  the date the change takes effect
     */
    public function __construct(
        public Member $member,
        public Group $group,
        public Category $standing,
        public CarbonInterface $effectiveDate,
    ) {}

    /**
     * Rebuild the Mailable from a Notice Delivery's payload snapshot. The Member's record may be
     * gone or further changed at drain time, so the row froze everything the mail names at write
     * time: the Member's name, the Group's name and slug, the new standing, and the effective
     * date. The reconstructed models are never saved; they exist only to feed the Mailable and
     * its view.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        $member = new Member([
            'first_name' => $payload['member']['first_name'],
            'last_name' => $payload['member']['last_name'],
        ]);

        $group = new Group(['name' => $payload['group']['name'], 'slug' => $payload['group']['slug']]);

        return new self(
            $member,
            $group,
            Category::from($payload['standing']),
            Carbon::parse($payload['effective_date']),
        );
    }

    /**
     * The recipient-facing envelope: the keyed subject (resolved in the render locale) and
     * From — the app's one address wearing the Group's name. No Reply-To.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->group->name),
            subject: __('notices.standing_change.subject', ['member' => trim($this->member->first_name.' '.$this->member->last_name)]),
        );
    }

    /**
     * The body — a Markdown view sharing the Mailable's Member, Group, standing, and date.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.standing-changed',
        );
    }
}
