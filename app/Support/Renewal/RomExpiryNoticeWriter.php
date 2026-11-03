<?php

namespace App\Support\Renewal;

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\RomExpirySummary;
use App\Models\Delivery;
use App\Models\Member;
use App\Support\Notices\RecordsRoster;
use App\Support\OrgTime;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ROM-expiry notice (#943, ADR-0038 §9), a step of the daily pass. It **only writes rows**;
 * the Drain sends them. The Legacy app calls this job "fees not paid"; it is about the ROM card,
 * not DMV dues.
 *
 * On the first of June, August, October, December and February (org time), every Active and
 * Sustaining Member whose ROM membership expiry is before today, who is not a lifetime member and
 * has no no-email flag, gets one {@see DeliveryKind::RomExpiryNotice} Delivery, written with
 * {@see Delivery::firstOrCreate} on the (kind, series `rom-expiry`, YYYYMM, Member) grain, so a
 * second run that day writes nothing; its payload carries the Member's name and expiry date. A run
 * that wrote any sends the Records Audience a summary. The show flag does not gate it: the Legacy
 * app and the ADR do not, and it runs June to February, mostly while the Renewal is closed. The
 * Beta mail-off switch is read by the pass before this step runs.
 */
class RomExpiryNoticeWriter
{
    public const SERIES = 'rom-expiry';

    /** The months whose first day the notice goes. */
    private const MONTHS = [6, 8, 10, 12, 2];

    public function write(): void
    {
        $today = OrgTime::today();

        if ($today->day !== 1 || ! in_array($today->month, self::MONTHS, true)) {
            return;
        }

        $seriesYear = (int) $today->format('Ym');

        $members = Member::query()
            ->whereIn('category', [Category::Active, Category::Sustaining])
            ->where('no_email', false)
            ->whereDate('membership_expiry', '<', $today->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNull('membership_lifetime')
                ->orWhere('membership_lifetime', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $names = [];

        foreach ($members as $member) {
            $row = Delivery::firstOrCreate(
                [
                    'kind' => DeliveryKind::RomExpiryNotice,
                    'series' => self::SERIES,
                    'series_year' => $seriesYear,
                    'member_id' => $member->getKey(),
                ],
                [
                    'email' => $member->email,
                    // The Legacy app's text greets by name and names the expiry date.
                    'payload' => [
                        'name' => $member->fullName(),
                        'membership_expiry' => $member->membership_expiry->toDateString(),
                    ],
                    'state' => DeliveryState::Pending,
                    'next_attempt_at' => now(),
                ],
            );

            if ($row->wasRecentlyCreated) {
                $names[] = $member->fullName();
            }
        }

        if ($names !== []) {
            RecordsRoster::writeNotice(RomExpirySummary::snapshot($names));
        }
    }
}
