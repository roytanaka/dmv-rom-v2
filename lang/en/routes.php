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
