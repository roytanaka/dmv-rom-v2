<?php

namespace App\Enums;

/**
 * A role held on a membership — the closed spine catalog of 9 (PRD #126, slice 4;
 * news-editor added per ADR-0011 delta for the announcements capability).
 * Roles are stored as their own rows on the membership, never as boolean columns.
 *
 * Core roles (chair, secretary, treasurer, statistician) attach to any Group. The
 * rest each require their backing Group capability flag to be on — making, e.g., a
 * "Scheduler on a non-scheduling Group" unrepresentable. `requiredCapability()`
 * names that flag (null for core roles); the gating is enforced at write time.
 *
 * Statistician joined the core roles when ADR-0022 §3 made hours always-on and
 * withdrew the `has_hours_stats` capability: any Group can have someone who watches
 * its hours.
 */
enum Role: string
{
    // Core roles — attach to any Group regardless of capability flags.
    case Chair = 'chair';
    case Secretary = 'secretary';
    case Treasurer = 'treasurer';
    // Statistician is core since ADR-0022 §3 (hours is always-on, no flag to gate on).
    case Statistician = 'statistician';

    // Capability-backed roles — each requires the corresponding Group flag.
    case Scheduler = 'scheduler';
    case Vetting = 'vetting';
    case Librarian = 'librarian';
    case ContentMaintainer = 'content_maintainer';
    case NewsEditor = 'news_editor';

    /**
     * The Group capability flag this role requires to be on, or null for a core
     * role that attaches regardless of capabilities.
     */
    public function requiredCapability(): ?string
    {
        return match ($this) {
            self::Chair, self::Secretary, self::Treasurer, self::Statistician => null,
            self::Scheduler => 'has_scheduling',
            self::Vetting => 'has_vetting',
            self::Librarian => 'has_documents',
            self::ContentMaintainer => 'has_content_catalog',
            self::NewsEditor => 'has_announcements',
        };
    }
}
