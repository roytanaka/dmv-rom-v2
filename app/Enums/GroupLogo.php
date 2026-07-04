<?php

namespace App\Enums;

/**
 * The curated set of Group logos (PRD #253) — a Group's identity mark, shown on
 * the Dashboard launcher tiles. Unlike a {@see GroupBanner} (interchangeable
 * decoration on the Group header, defaulted by Kind), a logo belongs to one
 * specific Group: the Docents logo means Docents. So there is deliberately NO
 * `defaultFor(Kind)` and no per-Kind backfill — a null `logo_key` is a valid,
 * common state that resolves to a single generic fallback mark at render.
 *
 * Backed string enum: the case value is what's stored in `groups.logo_key`, and
 * is also the asset filename stem. Assets are format-mixed (most SVG, some
 * raster), so the client maps each key to its actual file+extension (see
 * `resources/js/groups/logos.ts`); the server only carries the key. The images
 * live at `resources/images/groups/logos/{value}.{ext}`, alongside the generic
 * fallback at `{Fallback}.{ext}`.
 *
 * Expanding coverage before cutover is cheap by design: add one case here, ship
 * one asset, wire it into logos.ts. Reskinning existing art is a drop-in file
 * swap with no code change. The current art is provisional.
 */
enum GroupLogo: string
{
    /**
     * The generic mark shown for any Group without its own logo (a null
     * `logo_key`). Not a real Group's identity, so it is not a case — it is the
     * well-defined fallback stem the launcher resolves to. Expected to dominate
     * the grid; the Group name label carries the distinction.
     */
    public const Fallback = 'generic';

    // Member-facing programs with their own mark. The case value is the asset
    // filename stem and the stored `logo_key`; it mirrors the Group slug so the
    // seeder assignment reads plainly, but the two are deliberately decoupled
    // (a logo is identity, not derived from the slug). ROMForYou ships no mark
    // and stays on the generic fallback (PRD #253 / #257).
    case Docents = 'docents';
    case GuidesDuRom = 'guides-du-rom';
    case LesAmisFrancophiles = 'les-amis-francophiles';
    case DmvHandsOnTours = 'dmv-hands-on-tours';
    case GalleryInterpreters = 'gallery-interpreters';
    case VisitorGuides = 'visitor-guides';
    case VisitorWayfinders = 'visitor-wayfinders';
    case Romwalks = 'romwalks';
    case Reception = 'reception';
    case Rombus = 'rombus';
    case Romtravel = 'romtravel';

    // Friends-of standing committees with their own mark.
    case BishopWhiteFea = 'bishop-white-fea';
    case FriendsOfGlobalSouthAsiaFsa = 'friends-of-global-south-asia-fsa';
    case FriendsOfTextilesCostume = 'friends-of-textiles-costume';
    case FriendsOfPalaeontologyFop = 'friends-of-palaeontology-fop';
    case FriendsOfEarthSpaceFes = 'friends-of-earth-space-fes';
}
