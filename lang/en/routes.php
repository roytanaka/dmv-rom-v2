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

    // Zone C — officer/admin
    'officer.members' => 'officer/members',
    'officer.communications' => 'officer/communications',
    'officer.reports' => 'officer/reports',
    'officer.flash-messages' => 'officer/flash-messages',
    'officer.settings' => 'officer/settings',

    // Dynamic group route. The {group} slug is content and echoes back
    // as-authored — it is NOT resolved against a Group model (ADR-0008).
    'groups.show' => 'groups/{group}/{section?}',
];
