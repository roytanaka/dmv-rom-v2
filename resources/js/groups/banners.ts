// The curated Group banner textures (#191, PRD #186). Mirrors the server-side
// App\Enums\GroupBanner closed set key-for-key: each key maps to its Vite-built
// asset URL here (the server validates the key; the client resolves the image).
// A null/unknown banner_key falls back to no texture — the header's heritage
// gradient shows through as the neutral default. Display labels are translated
// chrome and live in lang/{en,fr}/group.php under `banner.option.*`.
import columns from '../../images/groups/banners/columns.svg';
import lattice from '../../images/groups/banners/lattice.svg';
import quill from '../../images/groups/banners/quill.svg';
import ribbon from '../../images/groups/banners/ribbon.svg';
import terrazzo from '../../images/groups/banners/terrazzo.svg';

/** The curated banner keys, in pick-list order. Matches App\Enums\GroupBanner. */
export const groupBannerKeys = ['columns', 'quill', 'lattice', 'ribbon', 'terrazzo'] as const;

export type GroupBannerKey = (typeof groupBannerKeys)[number];

/** Built asset URL for each curated banner key. */
export const groupBanners: Record<GroupBannerKey, string> = {
    columns,
    quill,
    lattice,
    ribbon,
    terrazzo,
};

/** Resolve a (possibly null/unknown) stored key to its asset URL, or null to fall back. */
export function bannerUrl(key: string | null | undefined): string | null {
    return key && key in groupBanners ? groupBanners[key as GroupBannerKey] : null;
}
