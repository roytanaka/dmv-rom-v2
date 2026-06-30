// The curated Group banners (#191, PRD #186) — full-bleed photos of ROM
// landmarks. Mirrors the server-side App\Enums\GroupBanner closed set
// key-for-key: each key maps to its Vite-built AVIF + JPG asset URLs here (the
// server validates the key; the client resolves the images). A null/unknown
// banner_key falls back to the default banner (`defaultBannerKey`, Rotunda) —
// the header's heritage gradient only shows through underneath while the image
// loads. Display labels are translated chrome and live in lang/{en,fr}/group.php
// under `banner.option.*`.
//
// Each banner ships as an AVIF (served first) with a JPG fallback for browsers
// without AVIF support; the header renders them through a <picture> element.
import crystalAvif from '../../images/groups/banners/crystal.avif';
import crystalJpg from '../../images/groups/banners/crystal.jpg';
import galleryAvif from '../../images/groups/banners/gallery.avif';
import galleryJpg from '../../images/groups/banners/gallery.jpg';
import muralAvif from '../../images/groups/banners/mural.avif';
import muralJpg from '../../images/groups/banners/mural.jpg';
import rotundaAvif from '../../images/groups/banners/rotunda.avif';
import rotundaJpg from '../../images/groups/banners/rotunda.jpg';
import stainedGlassAvif from '../../images/groups/banners/stained-glass.avif';
import stainedGlassJpg from '../../images/groups/banners/stained-glass.jpg';
import totemAvif from '../../images/groups/banners/totem.avif';
import totemJpg from '../../images/groups/banners/totem.jpg';

/** The curated banner keys, in pick-list order. Matches App\Enums\GroupBanner. */
export const groupBannerKeys = ['rotunda', 'crystal', 'gallery', 'mural', 'stained-glass', 'totem'] as const;

export type GroupBannerKey = (typeof groupBannerKeys)[number];

/** The banner shown when a Group has no explicit pick (banner_key is null). */
export const defaultBannerKey: GroupBannerKey = 'rotunda';

/** The built AVIF + JPG asset URLs for a banner. */
export interface BannerSources {
    avif: string;
    jpg: string;
}

/** Built asset URLs for each curated banner key. */
export const groupBanners: Record<GroupBannerKey, BannerSources> = {
    rotunda: { avif: rotundaAvif, jpg: rotundaJpg },
    crystal: { avif: crystalAvif, jpg: crystalJpg },
    gallery: { avif: galleryAvif, jpg: galleryJpg },
    mural: { avif: muralAvif, jpg: muralJpg },
    'stained-glass': { avif: stainedGlassAvif, jpg: stainedGlassJpg },
    totem: { avif: totemAvif, jpg: totemJpg },
};

/** Resolve a (possibly null/unknown) stored key to its asset sources, or null to fall back. */
export function bannerSources(key: string | null | undefined): BannerSources | null {
    return key && key in groupBanners ? groupBanners[key as GroupBannerKey] : null;
}
