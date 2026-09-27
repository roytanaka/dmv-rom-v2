<?php

namespace App\Enums;

/**
 * Where a Feedback item stands (ADR-0029 §7). Every item starts as New; only the
 * Support-operator changes it.
 *
 * Backed string enum: the stored value is the case's slug, and its label is chrome — a key
 * in the `feedback` lang file (ADR-0004).
 */
enum FeedbackStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Fixed = 'fixed';
    case WontFix = 'wont-fix';
    case Duplicate = 'duplicate';

    /** The lang key for this status's translated label (chrome, ADR-0004). */
    public function labelKey(): string
    {
        return "feedback.status.{$this->value}";
    }
}
