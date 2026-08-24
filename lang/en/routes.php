<?php

// URI-segment translation table (English) for mcamara/laravel-localization,
// resolved per-locale via LaravelLocalization::transRoute('routes.<key>').
// English is canonical at the root (ADR-0008). Mirrors lang/fr/routes.php
// key-for-key. Values are whole URI patterns, so dynamic params ({group}) and
// fixed prefixes (officer/…) live here verbatim and only the words change in fr.
return [
    'dashboard' => 'dashboard',

    // Zone A — personal
    'calendar' => 'calendar',
    'hours' => 'hours',
    'directory' => 'directory',
    // Member profile (#172, PRD #167). Canonical at /members/{member}; the French
    // twin is /benevoles/{member}. Its leading word collides with officer.members
    // ('members' → 'membres'): both share the English segment "members" but resolve
    // to different French words. The routeSegments table (HandleInertiaRequests) is
    // keyed by English segment, so only one French value can win there — and
    // officer.members is declared LAST, so the table keeps 'members' → 'membres'.
    // That table only localises relative chrome-nav hrefs; profile links are
    // server-generated via route(), so they get /benevoles from this entry directly.
    'members.show' => 'members/{member}',
    'documents' => 'documents',
    'news' => 'news',
    'profile' => 'profile',
    'renew' => 'renew',
    // Utility — the top bar's Help destination (#194).
    'help' => 'help',

    // Zone C — officer/admin
    'officer.members' => 'officer/members',
    'officer.communications' => 'officer/communications',
    'officer.reports' => 'officer/reports',
    'officer.flash-messages' => 'officer/flash-messages',
    'officer.settings' => 'officer/settings',

    // Dynamic group route. The {group} slug is content and echoes back
    // as-authored — it is NOT resolved against a Group model (ADR-0008).
    'groups.show' => 'groups/{group}/{section?}',

    // A Schedule permalink (#353, ADR-0021 §1). Addresses a Schedule by id — it has
    // no slug — under the owning Group. The 'scheduling' segment is translated in the
    // French twin (ADR-0008); {group} and {schedule} stay verbatim.
    'groups.scheduling.show' => 'groups/{group}/scheduling/{schedule}',

    // A Group's fiscal-year hours report (#411, ADR-0022 §5). A separate addressable route
    // rather than a mode of the Hours tab, so it is linkable and the CSV export can be its
    // sibling (#414). The 'hours/report' segments are translated in the French twin; {group}
    // stays verbatim.
    'groups.hours.report' => 'groups/{group}/hours/report',

    // Settings → Profile / Password (#229, PRD #228). The self-service account
    // surface, brought into the localized group so each page has a French twin.
    // 'settings' is the bare redirect target (→ settings.profile); the two child
    // keys carry the pages themselves. The route NAME mirrors the key suffix so the
    // language switcher resolves the twin (HandleInertiaRequests::twinUrl).
    'settings' => 'settings',
    'settings.profile' => 'settings/profile',
    'settings.profile.photo' => 'settings/profile/photo',
    'settings.password' => 'settings/password',
    'settings.skills' => 'settings/skills',
];
