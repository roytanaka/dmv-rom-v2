<?php

// Help centre chrome (#517, ADR-0025). The index title and intro, the breadcrumb
// root, and one label per Help section. Chrome, so it ships in both locales and is
// the single source of truth for both PHP __() and Vue trans(). Article prose is
// not here — it lives in resources/help/{locale}/<slug>.md.
return [
    'title' => 'Help',
    'intro' => 'Step-by-step guides for the tasks you do here.',

    // One label per HelpSection enum case, keyed by the section slug.
    'section' => [
        'getting-started' => 'Getting started',
        'dashboard' => 'Dashboard',
        'my-hours' => 'My Hours',
        'directory' => 'Directory',
        'news' => 'News',
        'groups' => 'Groups',
        'scheduling' => 'Scheduling',
        'hours-and-reports' => 'Hours and reports',
        'emailing' => 'Emailing',
        'settings' => 'Settings',
        'support' => 'Support',
    ],

    // Required-role badge (#518, ADR-0025 §6). The badge reads "Needs: Scheduler or
    // Chair": the prefix, one label per required role, joined by "or". An article
    // that needs no role shows no badge. One label per token in
    // HelpManifest::requirableRoles() — every Role, plus the two non-Group tiers.
    'required_role' => [
        'prefix' => 'Needs:',
        'or' => 'or',
        'role' => [
            'chair' => 'Chair',
            'secretary' => 'Secretary',
            'treasurer' => 'Treasurer',
            'statistician' => 'Statistician',
            'scheduler' => 'Scheduler',
            'vetting' => 'Vetting',
            'librarian' => 'Librarian',
            'content_maintainer' => 'Content Maintainer',
            'news_editor' => 'News Editor',
            'super_tier' => 'Super-tier',
            'support_operator' => 'Support operator',
        ],
    ],
];
