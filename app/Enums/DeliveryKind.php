<?php

namespace App\Enums;

use App\Models\Delivery;

/**
 * What a {@see Delivery} row is a copy of (spec #479, ADR-0024 §Delivery) — the
 * kind decides which Mailable the Drain builds and where it renders the body from: a
 * `Broadcast` or `SenderCopy` from its `broadcasts` row, a `Reminder` from the live Shift, a
 * `Notice` or `EmptyDesk` from the JSON snapshot in the row's payload (because the source may
 * be gone by drain time).
 *
 * Only `Notice` is wired this ticket — the Sign-up cancellation moved onto the queue. The
 * rest name the kinds later tickets fill in, so the column's domain is closed from the start.
 */
enum DeliveryKind: string
{
    case Broadcast = 'broadcast';
    case SenderCopy = 'sender_copy';
    case Reminder = 'reminder';
    case EmptyDesk = 'empty_desk';
    case Notice = 'notice';
    // One Guest's copy of a Host's Invitation mail, rendered from its `broadcasts` row.
    case Invitation = 'invitation';
    // A Reminder before an Invitation (#909, ADR-0034 §12), rendered from the live Invitation.
    case InvitationReminder = 'invitation_reminder';
    // A Renewal reminder (#942, ADR-0038 §9), keyed on `series` and `series_year` and rendered
    // from the settings text snapshotted in its payload.
    case RenewalReminder = 'renewal_reminder';
    // A ROM-expiry notice (#943, ADR-0038 §9), keyed on series `rom-expiry` and a YYYYMM
    // `series_year`, rendered from translated text alone.
    case RomExpiryNotice = 'rom_expiry_notice';
}
