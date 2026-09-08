<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\Group;
use App\Models\Shift;
use App\Support\EmptyDesk\EmptyDeskAlertWriter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * The empty-desk alert mail (#487, spec #479, ADR-0024 §7). Every third day of the month, a
 * Group tells its roster which watched Shifts still have nobody. The
 * {@see EmptyDeskAlertWriter} writes one {@see Delivery} per
 * present-standing recipient, and the Drain builds this Mailable from the row's payload snapshot
 * and sends it within the hour.
 *
 * Like a Notice — and unlike a Reminder — it renders from a **snapshot**, not live rows: the
 * Shifts it names may be filled or gone by drain time, so the writer freezes the open-Shift list
 * (and the Group's name and slug) into the payload. It renders in the recipient's saved locale.
 * From is the app's one address carrying the Group's name as the display name; there is no
 * Reply-To (an automatic alert is not a conversation — ADR-0024 §3). The body names each open
 * Shift with its date, times, and kind, marks the ones dated today, and links to the Group's
 * schedules, the link built for the render locale (ADR-0008) so a French recipient gets a `/fr/`
 * URL.
 */
class EmptyDeskAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $groupName  the Group's name — the From display name and named in the body
     * @param  string  $groupSlug  the Group's slug — the schedules link is built from it
     * @param  CarbonInterface  $runDate  the run day, on the org wall clock — the Shifts dated
     *                                    this day are the ones marked "today"
     * @param  Collection<int, array{starts_at: CarbonInterface, ends_at: CarbonInterface, kind: string}>  $shifts
     *                                                                                                              the open Shifts, each with its instants and kind name
     */
    public function __construct(
        public string $groupName,
        public string $groupSlug,
        public CarbonInterface $runDate,
        public Collection $shifts,
    ) {}

    /**
     * Freeze what the alert names into a payload the Drain can render from — the Shifts may be
     * filled or gone by then. The Shift instants are stored ISO-8601 (UTC) and the kind name
     * as-authored; the run date is the org-wall-clock day, used to mark today's Shifts.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return array<string, mixed>
     */
    public static function snapshot(Group $group, Collection $shifts, CarbonInterface $runDate): array
    {
        return [
            'group' => ['name' => $group->name, 'slug' => $group->slug],
            'run_date' => $runDate->toDateString(),
            'shifts' => $shifts->map(fn (Shift $shift): array => [
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'kind' => $shift->kind?->name ?? '',
            ])->values()->all(),
        ];
    }

    /**
     * Rebuild the Mailable from a payload snapshot. The reconstructed values are never saved;
     * they exist only to feed the Mailable and its view.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        $shifts = collect($payload['shifts'])->map(fn (array $shift): array => [
            'starts_at' => Carbon::parse($shift['starts_at']),
            'ends_at' => Carbon::parse($shift['ends_at']),
            'kind' => $shift['kind'],
        ]);

        return new self(
            $payload['group']['name'],
            $payload['group']['slug'],
            Carbon::parse($payload['run_date']),
            $shifts,
        );
    }

    /**
     * The recipient-facing envelope: the keyed subject (resolved in the render locale) and From —
     * the app's one address wearing the Group's name. No Reply-To.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->groupName),
            subject: __('notices.empty_desk.subject', ['group' => $this->groupName]),
        );
    }

    /**
     * The body — a Markdown view sharing the Mailable's Group name, slug, run date, and Shifts.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.empty-desk-alert',
        );
    }
}
