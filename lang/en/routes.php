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
    // The CSV export sibling of each report (#414, ADR-0022 §8). A '.csv' suffix on the report's
    // own path, so the export sits beside the page it exports; the extension is universal and
    // stays verbatim in the French twin.
    'groups.hours.report.csv' => 'groups/{group}/hours/report.csv',

    // The three officer surfaces after the fiscal-year matrix (#412, ADR-0022 §8): a month
    // picker, a Member History, and the two Member × twelve-month summaries. Each is its own
    // addressable route so a Chair can link it in an email. The words are translated in the
    // French twin; {group} stays verbatim.
    'groups.hours.month' => 'groups/{group}/hours/month',
    'groups.hours.member' => 'groups/{group}/hours/member',
    'groups.hours.extra' => 'groups/{group}/hours/extra',
    'groups.hours.meetings' => 'groups/{group}/hours/meetings',
    // Their CSV export siblings (#414) — a '.csv' suffix on each report's own path.
    'groups.hours.month.csv' => 'groups/{group}/hours/month.csv',
    'groups.hours.member.csv' => 'groups/{group}/hours/member.csv',
    'groups.hours.extra.csv' => 'groups/{group}/hours/extra.csv',
    'groups.hours.meetings.csv' => 'groups/{group}/hours/meetings.csv',

    // The six DMV-wide fiscal-year reports (#413, ADR-0022 §8). Org-wide, not scoped to a
    // {group} — always rooted at the DMV root Group. Flat dotted keys (like groups.hours.*);
    // Arr::get matches the literal key first, so they never collide with the bare 'hours' key.
    // The words are translated in the French twin.
    'hours.committee-summary' => 'hours/committee-summary',
    'hours.committee-detailed' => 'hours/committee-detailed',
    // Summary Visitor Interactions (#451, ADR-0023 §6). Org-wide like its siblings, but open to
    // any signed-in Member rather than officer-gated. The words are translated in the French twin.
    'hours.visitor-summary' => 'hours/visitor-interactions',
    'hours.ranked' => 'hours/ranked',
    'hours.zero-hours' => 'hours/zero-hours',
    'hours.zero-shift-hours' => 'hours/zero-shift-hours',
    'hours.zero-extra-hours' => 'hours/zero-extra-hours',
    // Their CSV export siblings (#414) — a '.csv' suffix on each report's own path.
    'hours.committee-summary.csv' => 'hours/committee-summary.csv',
    'hours.committee-detailed.csv' => 'hours/committee-detailed.csv',
    'hours.ranked.csv' => 'hours/ranked.csv',
    'hours.zero-hours.csv' => 'hours/zero-hours.csv',
    'hours.zero-shift-hours.csv' => 'hours/zero-shift-hours.csv',
    'hours.zero-extra-hours.csv' => 'hours/zero-extra-hours.csv',

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
