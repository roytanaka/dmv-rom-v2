<?php

// Chrome top-bar section tabs — the Group-Menu layer (#106, migrated from
// resources/js/chrome/messages.ts). The SAME capability slot reads differently per
// Group (Catalog → "Data Sheets" for Docents), so labels are keyed per program.
// Chrome strings (ADR-0004); Group names themselves are content and stay as-authored.
// Mirrors lang/fr/section.php key-for-key.
return [
    // Always-present slot across every Group Menu.
    'about' => 'About',

    // Docents — the one fully-populated sample menu (docs/nav-spec.md is the eventual
    // exhaustive source). Other Groups resolve their own keyed labels as modelled.
    'docents' => [
        'roster' => "Who's Who",
        'schedule' => 'Schedule',
        'catalog' => 'Data Sheets',
        'publications' => 'Publications',
        'meetings' => 'Meetings',
        'statistics' => 'Statistics',
        'schedule_admin' => 'Schedule Admin',
    ],
];
