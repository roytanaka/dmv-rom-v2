<?php

namespace App\Enums;

/**
 * The type a Tester picks for a Feedback item (ADR-0029 §6). "Missing from new site" is a
 * feature the old app had that the new one does not; the label avoids the word "legacy".
 *
 * Backed string enum: the stored value is the case's slug, and its label is chrome — a key
 * in the `feedback` lang file (ADR-0004).
 */
enum FeedbackType: string
{
    case Bug = 'bug';
    case FeatureRequest = 'feature-request';
    case Translation = 'translation';
    case MissingFromNewSite = 'missing-from-new-site';
    case Confusing = 'confusing';
    case Other = 'other';

    /** The lang key for this type's translated label (chrome, ADR-0004). */
    public function labelKey(): string
    {
        return "feedback.type.{$this->value}";
    }
}
