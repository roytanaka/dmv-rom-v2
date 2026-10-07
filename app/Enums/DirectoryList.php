<?php

namespace App\Enums;

use App\Models\Member;

/**
 * The Directory's Who's who list (#699) — the old site's select that picks which
 * Members the Directory lists. Same names and order as the old site. Each list other
 * than the default reuses an email Audience set (ADR-0024 §5), so the Directory and
 * email agree on who is in each.
 *
 * Backed string enum: the value is the Directory's `list` query parameter.
 */
enum DirectoryList: string
{
    case AllMembers = 'all_members';
    case AllMembersLoa = 'all_members_loa';
    case ActiveProvisional = 'active_provisional';
    case Active = 'active';
    case Provisional = 'provisional';
    case PreActive = 'pre_active';
    case Sustaining = 'sustaining';
    case Honourary = 'honourary';
    case Loa = 'loa';
    case BoardOfDirectors = 'board_of_directors';
    case CommitteeChairs = 'committee_chairs';
    case AllChairs = 'all_chairs';

    /**
     * Whether every Member sees this list. The rest are for Records, Officers, and
     * super-tier ({@see Member::seesEveryDirectoryList()}), as on the old site.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::AllMembers, self::BoardOfDirectors, self::CommitteeChairs, self::AllChairs => true,
            default => false,
        };
    }

    /**
     * The email Audience this list reads, with its parameter. Null for the default,
     * which stays the Directory's own list ({@see Member::scopeInDirectory()}):
     * the All members Audience holds Provisional and PreActive Members, whom only the
     * closed lists show.
     *
     * @return array{AudienceKey, ?string}|null
     */
    public function audience(): ?array
    {
        return match ($this) {
            self::AllMembers => null,
            self::AllMembersLoa => [AudienceKey::AllMembersOnLeave, null],
            self::ActiveProvisional => [AudienceKey::ActiveProvisional, null],
            self::Active => [AudienceKey::OneCategory, Category::Active->value],
            self::Provisional => [AudienceKey::OneCategory, Category::Provisional->value],
            self::PreActive => [AudienceKey::OneCategory, Category::PreActive->value],
            self::Sustaining => [AudienceKey::OneCategory, Category::Sustaining->value],
            self::Honourary => [AudienceKey::OneCategory, Category::Honourary->value],
            self::Loa => [AudienceKey::OneCategory, Category::Loa->value],
            self::BoardOfDirectors => [AudienceKey::BoardOfDirectors, null],
            self::CommitteeChairs => [AudienceKey::CommitteeChairs, null],
            self::AllChairs => [AudienceKey::AllChairs, null],
        };
    }
}
