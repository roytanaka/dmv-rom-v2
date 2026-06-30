<?php

namespace App\Enums;

/**
 * The curated set of Group banner textures (#191, PRD #186). A Group's header
 * renders one full-bleed banner; an officer (Secretary / Chair / super-tier)
 * picks it from this closed set. There is no custom-upload path — that waits on
 * document storage and the Canadian data-residency review.
 *
 * Backed string enum: the case value is what's stored in `groups.banner_key`.
 * The matching image asset lives at `resources/images/groups/banners/{value}.svg`
 * and is mapped to its built URL on the client (see `resources/js/groups/banners.ts`);
 * the server only validates the key and resolves the per-Kind default. A null
 * `banner_key` is a valid state — the header falls back to a neutral default.
 */
enum GroupBanner: string
{
    case Columns = 'columns';
    case Quill = 'quill';
    case Lattice = 'lattice';
    case Ribbon = 'ribbon';
    case Terrazzo = 'terrazzo';

    /**
     * A sensible default banner for a Group of the given Kind — used to backfill
     * existing rows at migration time so every Group reads with intent, while new
     * rows may stay null and fall back to the neutral default at render.
     */
    public static function defaultFor(Kind $kind): self
    {
        return match ($kind) {
            Kind::StandingCommittee => self::Columns,
            Kind::Program => self::Quill,
            Kind::WorkingGroup => self::Lattice,
            Kind::Project => self::Ribbon,
            Kind::Cohort => self::Terrazzo,
        };
    }
}
