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
import genericLogo from '../../images/groups/logos/generic.svg';
// Member-facing programs.
import docentsLogo from '../../images/groups/logos/docents.svg';
import guidesDuRomLogo from '../../images/groups/logos/guides-du-rom.svg';
import lesAmisFrancophilesLogo from '../../images/groups/logos/les-amis-francophiles.svg';
// HoT is the one raster in the set — the resolver must not assume ".svg".
import dmvHandsOnToursLogo from '../../images/groups/logos/dmv-hands-on-tours.png';
import galleryInterpretersLogo from '../../images/groups/logos/gallery-interpreters.svg';
import receptionLogo from '../../images/groups/logos/reception.svg';
import rombusLogo from '../../images/groups/logos/rombus.svg';
import romtravelLogo from '../../images/groups/logos/romtravel.svg';
import romwalksLogo from '../../images/groups/logos/romwalks.svg';
import visitorGuidesLogo from '../../images/groups/logos/visitor-guides.svg';
import visitorWayfindersLogo from '../../images/groups/logos/visitor-wayfinders.svg';
// Friends-of standing committees.
import bishopWhiteFeaLogo from '../../images/groups/logos/bishop-white-fea.svg';
import friendsOfEarthSpaceFesLogo from '../../images/groups/logos/friends-of-earth-space-fes.svg';
import friendsOfGlobalSouthAsiaFsaLogo from '../../images/groups/logos/friends-of-global-south-asia-fsa.svg';
import friendsOfPalaeontologyFopLogo from '../../images/groups/logos/friends-of-palaeontology-fop.svg';
import friendsOfTextilesCostumeLogo from '../../images/groups/logos/friends-of-textiles-costume.svg';

/** The curated Group logo keys. Matches the cases of App\Enums\GroupLogo. */
export const groupLogoKeys = [
    'docents',
    'guides-du-rom',
    'les-amis-francophiles',
    'dmv-hands-on-tours',
    'gallery-interpreters',
    'visitor-guides',
    'visitor-wayfinders',
    'romwalks',
    'reception',
    'rombus',
    'romtravel',
    'bishop-white-fea',
    'friends-of-global-south-asia-fsa',
    'friends-of-textiles-costume',
    'friends-of-palaeontology-fop',
    'friends-of-earth-space-fes',
] as const;

export type GroupLogoKey = (typeof groupLogoKeys)[number];

/** Built asset URL for each curated Group logo key. */
export const groupLogos: Record<GroupLogoKey, string> = {
    docents: docentsLogo,
    'guides-du-rom': guidesDuRomLogo,
    'les-amis-francophiles': lesAmisFrancophilesLogo,
    'dmv-hands-on-tours': dmvHandsOnToursLogo,
    'gallery-interpreters': galleryInterpretersLogo,
    'visitor-guides': visitorGuidesLogo,
    'visitor-wayfinders': visitorWayfindersLogo,
    romwalks: romwalksLogo,
    reception: receptionLogo,
    rombus: rombusLogo,
    romtravel: romtravelLogo,
    'bishop-white-fea': bishopWhiteFeaLogo,
    'friends-of-global-south-asia-fsa': friendsOfGlobalSouthAsiaFsaLogo,
    'friends-of-textiles-costume': friendsOfTextilesCostumeLogo,
    'friends-of-palaeontology-fop': friendsOfPalaeontologyFopLogo,
    'friends-of-earth-space-fes': friendsOfEarthSpaceFesLogo,
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
