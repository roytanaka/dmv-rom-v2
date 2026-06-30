<?php

namespace App\Enums;

/**
 * The curated set of Group banners (#191, PRD #186) — full-bleed photos of ROM
 * landmarks. A Group's header renders one; an officer (Secretary / Chair /
 * super-tier) picks it from this closed set. There is no custom-upload path —
 * that waits on document storage and the Canadian data-residency review.
 *
 * Backed string enum: the case value is what's stored in `groups.banner_key`.
 * The matching images live at `resources/images/groups/banners/{value}.{avif,jpg}`
 * (AVIF with a JPG fallback) and are mapped to their built URLs on the client
 * (see `resources/js/groups/banners.ts`); the server only validates the key and
 * resolves the per-Kind default. A null `banner_key` is a valid state — the
 * header falls back to the default banner (Rotunda).
 */
enum GroupBanner: string
{
    case Rotunda = 'rotunda';
    case Crystal = 'crystal';
    case Gallery = 'gallery';
    case Mural = 'mural';
    case StainedGlass = 'stained-glass';
    case Totem = 'totem';

    /**
     * A sensible default banner for a Group of the given Kind — used to backfill
     * existing rows at migration time so every Group reads with intent, while new
     * rows may stay null and fall back to the neutral default at render.
     */
    public static function defaultFor(Kind $kind): self
    {
        return match ($kind) {
            Kind::StandingCommittee => self::Rotunda,
            Kind::Program => self::Gallery,
            Kind::WorkingGroup => self::Crystal,
            Kind::Project => self::StainedGlass,
            Kind::Cohort => self::Mural,
        };
    }
}
