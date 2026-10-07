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

    /** Whether the item still needs work: New or Confirmed. The rest are closed. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::New, self::Confirmed => true,
            self::Fixed, self::WontFix, self::Duplicate => false,
        };
    }

    /**
     * The open statuses, or the closed ones, for the Feedback page's Open and Closed
     * filters.
     *
     * @return list<self>
     */
    public static function grouped(bool $open): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen() === $open));
    }

    /** The lang key for this status's translated label (chrome, ADR-0004). */
    public function labelKey(): string
    {
        return "feedback.status.{$this->value}";
    }
}
