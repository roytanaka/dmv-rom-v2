<?php

namespace App\Enums;

use App\Models\Delivery;

/**
 * The lifecycle of one {@see Delivery} row — the queue's four terminal-or-not
 * states (spec #479, ADR-0024 §Delivery). A row is born `Pending`; the Drain moves it to
 * `Sent` on a successful send, to `Failed` when a send gives up, or to `Expired` when the
 * thing it would announce is gone before it goes out. `Sent`, `Failed`, and `Expired` are
 * terminal — the Drain never touches a row again once it lands there.
 */
enum DeliveryState: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Expired = 'expired';
}
