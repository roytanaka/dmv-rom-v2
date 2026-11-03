<?php

namespace App\Enums;

use App\Models\Member;
use App\Models\RenewalSetting;

/**
 * Whether a Member must act on the Renewal, and why (#939, ADR-0038 §9). The Legacy app's home-menu
 * rule, kept as it is: only Active, Pre-active and Sustaining Members qualify. With the show flag
 * on, a paid-through year before the renewal year (or none) means Renew. With it off, no
 * paid-through year means Provisional to Active (a first-year Member going Active), and a renewal
 * status of LOA means a Member coming back from leave.
 */
enum RenewalEligibility: string
{
    case None = 'none';
    case Renew = 'renew';
    case ProvisionalToActive = 'provisional_to_active';
    case Loa = 'loa';

    public static function for(Member $member, RenewalSetting $settings): self
    {
        if (! in_array($member->category, [Category::Active, Category::PreActive, Category::Sustaining], true)) {
            return self::None;
        }

        $paidThrough = $member->paidThroughYear();

        if ($settings->show_renewal) {
            $unpaid = $paidThrough === null
                || ($settings->renewal_year !== null && $paidThrough < $settings->renewal_year);

            return $unpaid ? self::Renew : self::None;
        }

        if ($paidThrough === null) {
            return self::ProvisionalToActive;
        }

        return $member->onRenewalLeave() ? self::Loa : self::None;
    }

    public function eligible(): bool
    {
        return $this !== self::None;
    }

    /** The link's text. A Member back from leave sees the ordinary Renew text, as in the Legacy app. */
    public function labelKey(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Renew, self::Loa => 'user.renew',
            self::ProvisionalToActive => 'user.renew_provisional',
        };
    }
}
