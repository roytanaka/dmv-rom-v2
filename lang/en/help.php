<?php

// Help centre chrome (#517, ADR-0025). The index title and intro, the breadcrumb
// root, and one label per Help section. Chrome, so it ships in both locales and is
// the single source of truth for both PHP __() and Vue trans(). Article prose is
// not here — it lives in resources/help/{locale}/<slug>.md.
return [
    'title' => 'Help',
    'intro' => 'Step-by-step guides for the tasks you do here.',

    // The index (#623): the Start here box, and the closing link of each section card.
    'start_here' => [
        'heading' => 'New to the app? Start here.',
        'line' => 'This short guide shows you around the app.',
    ],
    'all_articles' => 'All :section articles (:count)',
    'read_about' => 'Read about :section',

    // "On this page" list of an article's headings (#621).
    'on_this_page' => 'On this page',

    // Previous and Next buttons at the end of an article (#620).
    'previous' => 'Previous',
    'next' => 'Next',

    // The linked list of a section's articles at the end of its overview (#622).
    'section_articles' => 'All :section articles',

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

    // Required-role badge (#518, ADR-0025 §6). The badge reads "Scheduler or
    // Chair": one label per required role, joined by "or". An article
    // that needs no role shows no badge. One label per token in
    // HelpManifest::requirableRoles() — every Role, plus the non-Group tiers.
    'required_role' => [
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
            'records' => 'Records',
        ],
    ],

    // The Help topics list beside an article (#619): its heading, the link back to
    // the index, and the label of each section's overview row.
    'topics' => [
        'title' => 'Help topics',
        'all' => 'All help topics',
        'overview' => 'Overview',
    ],
];
