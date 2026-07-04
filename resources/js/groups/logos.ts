// The curated Group logos (PRD #253) — a Group's identity mark, shown on the
// Dashboard launcher tiles. Mirrors the server-side App\Enums\GroupLogo closed
// set key-for-key: each key maps to its Vite-built asset URL here (the server
// carries the key; the client resolves the image). Unlike banners, a logo is
// IDENTITY, not decoration — it belongs to one specific Group and is never
// defaulted by Kind. A null/unknown logo_key falls back to a single generic
// mark (`genericLogo`), which is expected to dominate the grid; the Group name
// label carries the distinction.
//
// Assets are format-mixed (most SVG, some raster), so each key names its own
// file+extension — the resolver must not assume one extension.
import docentsLogo from '../../images/groups/logos/docents.svg';
import genericLogo from '../../images/groups/logos/generic.svg';

/** The curated Group logo keys. Matches the cases of App\Enums\GroupLogo. */
export const groupLogoKeys = ['docents'] as const;

export type GroupLogoKey = (typeof groupLogoKeys)[number];

/** Built asset URL for each curated Group logo key. */
export const groupLogos: Record<GroupLogoKey, string> = {
    docents: docentsLogo,
};

/**
 * The generic fallback mark, shown for any Group without its own logo. Mirrors
 * App\Enums\GroupLogo::Fallback ('generic').
 */
export const genericLogoSrc: string = genericLogo;

/**
 * Resolve a (possibly null/unknown) stored key to its built logo asset URL,
 * falling back to the generic mark. The launcher renders every tile with a mark
 * — the Group's own or the generic fallback — so this never returns null.
 */
export function logoSrc(key: string | null | undefined): string {
    return key && key in groupLogos ? groupLogos[key as GroupLogoKey] : genericLogoSrc;
}
