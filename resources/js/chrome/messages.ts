/**
 * Chrome nav i18n — placeholder shim.
 *
 * Every nav label is an i18n KEY, not final copy. This file maps those keys to
 * English strings and resolves them with {@link t}. It is a deliberate stand-in:
 * the real bilingual plumbing (server-driven Laravel translations surfaced to the
 * frontend, per ADR-0008 bilingual routing) replaces this map without touching the
 * fixture or components — they reference keys, never literals.
 *
 * TODO(i18n): replace this English-only map with the real translation source once
 * the frontend translation bridge exists. French is intentionally absent here.
 */

/** English copy keyed by nav i18n key. Keys — not these strings — are the contract. */
export const navMessages: Record<string, string> = {
    // Rail section headings (Zone B / Zone C)
    'nav.rail.my_groups': 'My Groups',
    'nav.rail.all_groups': 'All Groups',
    'nav.rail.officer': 'Officer Tools',

    // Zone B — sample Groups (representative; full catalogue → docs/nav-spec.md)
    'nav.group.docents': 'Docents',
    'nav.group.docents.school_visits': 'School Visits',
    'nav.group.docents.public_tours': 'Public Tours',
    'nav.group.gallery_interpreters': 'Gallery Interpreters',
    'nav.group.romwalks': 'ROMWalks',

    // Zone C — officer/admin cluster
    'nav.officer.members': 'Members',
    'nav.officer.communications': 'Communications',
    'nav.officer.reports': 'Reports',
    'nav.officer.flash_messages': 'Flash Messages',
    'nav.officer.dmv_settings': 'DMV Settings',

    // Zone A — personal/global (top-bar tab set on the Dashboard)
    'nav.personal.calendar': 'My Calendar',
    'nav.personal.hours': 'My Hours',
    'nav.personal.directory': 'Directory',
    'nav.personal.documents': 'Documents',
    'nav.personal.news': 'News',
    'nav.personal.profile': 'My Profile',
    'nav.personal.renew': 'Renew Membership',

    // Group Menu — capability slots. Labels are keyed off the program, so the same
    // slot reads differently per Group (e.g. Catalog → "Data Sheets" for Docents).
    // Representative for the sample Docents menu; full set → docs/nav-spec.md.
    'nav.section.about': 'About',
    'nav.section.docents.roster': "Who's Who",
    'nav.section.docents.schedule': 'Schedule',
    'nav.section.docents.catalog': 'Data Sheets',
    'nav.section.docents.publications': 'Publications',
    'nav.section.docents.meetings': 'Meetings',
    'nav.section.docents.statistics': 'Statistics',
    'nav.section.docents.schedule_admin': 'Schedule Admin',

    // Footer (institutional). The land acknowledgement and inclusion statement must
    // use the ROM's OFFICIAL wording and are NEVER machine-translated.
    // - land_ack: official ROM English, verbatim. TODO(copy): add the official French.
    // - inclusion: still a clearly-marked placeholder pending ROM-approved wording.
    'footer.org': 'Department of Museum Volunteers, Royal Ontario Museum',
    'footer.land_ack':
        'ROM acknowledges that this museum sits on the traditional ancestral lands of the Wendat, the Haudenosaunee Confederacy, and the Anishinabek Nation, including the Mississaugas of the Credit First Nation.',
    'footer.inclusion': '[Placeholder — the DMV’s official inclusion statement will appear here, pending the ROM-approved wording.]',
};

/**
 * Resolve a nav i18n key to display copy. Falls back to the key itself if unmapped,
 * so a missing translation is visible in the UI rather than rendering blank.
 */
export function t(key: string): string {
    return navMessages[key] ?? key;
}
