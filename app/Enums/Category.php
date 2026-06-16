<?php

namespace App\Enums;

/**
 * A Member's DMV-wide standing — their organisation-level category, distinct
 * from the per-Group standing carried by a membership (see MembershipStatus,
 * landed in a later spine slice).
 *
 * Backed string enum: the stored value is the snake_case case value. The
 * legacy-value mapping is deliberately out of spine scope (migration work);
 * resolution to an access tier and the sign-up floor lands here per ADR-0017.
 */
enum Category: string
{
    case Active = 'active';
    case PreActive = 'pre_active';
    case Provisional = 'provisional';
    case Sustaining = 'sustaining';
    case Honourary = 'honourary';
    case Loa = 'loa';
    case Withdrawn = 'withdrawn';
    case Resigned = 'resigned';
    case Deceased = 'deceased';

    /**
     * The breadth-of-access tier this DMV-wide Category resolves to (ADR-0017).
     * Independent of the sign-up floor: LOA keeps Full view while barred from
     * signing up (see canSignUp()).
     */
    public function accessTier(): AccessTier
    {
        return match ($this) {
            self::Active, self::Honourary, self::Sustaining, self::Loa => AccessTier::Full,
            self::Provisional, self::PreActive => AccessTier::Limited,
            self::Withdrawn, self::Resigned, self::Deceased => AccessTier::None,
        };
    }

    /**
     * Whether members in this Category may sign up for shifts — the floor the
     * sign-up gate checks before any role check. Orthogonal to accessTier():
     * LOA has Full view but cannot sign up.
     */
    public function canSignUp(): bool
    {
        return match ($this) {
            self::Active, self::Honourary, self::Sustaining,
            self::Provisional, self::PreActive => true,
            self::Loa, self::Withdrawn, self::Resigned, self::Deceased => false,
        };
    }
}
