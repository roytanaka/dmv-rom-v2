<?php

namespace App\Enums;

use App\Models\Broadcast;

/**
 * What a {@see Broadcast} sent record is (spec #479, ADR-0024 §6) — a hand-written
 * mail an officer sends to an Audience, or a Direct message any Member sends to one
 * other. The two share one record, one composer, one queue, and one no-email rule;
 * they differ only in who may send and how the From display name is chosen (the
 * Group's name for a Broadcast, the sender's for a Direct message — ADR-0024 §3).
 *
 * Only `Broadcast` is wired this ticket (#488); `DirectMessage` names the peer a
 * later ticket (#491) fills in, so the column's domain is closed from the start.
 */
enum BroadcastKind: string
{
    case Broadcast = 'broadcast';
    case DirectMessage = 'direct_message';
}
