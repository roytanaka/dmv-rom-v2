<?php

namespace App\Enums;

/**
 * A role held on a membership — the closed spine catalog of 8 (PRD #126, slice 4).
 * Roles are stored as their own rows on the membership, never as boolean columns.
 *
 * Backed string enum: the stored value is the snake_case case value.
 *
 * Core roles (chair, secretary, treasurer) attach to any Group. The rest each
 * require their backing Group capability flag to be on — making, e.g., a
 * "Scheduler on a non-scheduling Group" unrepresentable. `requiredCapability()`
 * names that flag (null for core roles); the gating is enforced at write time.
 */
enum Role: string
{
    case Chair = 'chair';
    case Secretary = 'secretary';
    case Scheduler = 'scheduler';
    case Statistician = 'statistician';
    case Vetting = 'vetting';
    case Librarian = 'librarian';
    case ContentMaintainer = 'content_maintainer';
    case Treasurer = 'treasurer';

    /**
     * The Group capability flag this role requires to be on, or null for a core
     * role that attaches regardless of capabilities.
     */
    public function requiredCapability(): ?string
    {
        return match ($this) {
            self::Scheduler => 'has_scheduling',
            self::Statistician => 'has_hours_stats',
            self::Vetting => 'has_vetting',
            self::Librarian => 'has_documents',
            self::ContentMaintainer => 'has_content_catalog',
            self::Chair, self::Secretary, self::Treasurer => null,
        };
    }
}
