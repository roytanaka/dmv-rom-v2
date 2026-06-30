<?php

// Strings for the Group detail page (#188, PRD #186) — the committee shell every
// Group runs on: a banner header, the section-tab strip, and the Overview tab.
// Chrome strings, translated like all chrome (ADR-0004); the Group's own name and
// member-entered content (About Us) render as-authored and never enter this lookup.
// Mirrors lang/fr/group.php key-for-key.
return [
    // In-body section tabs (sticky under the banner). The base triad plus the muted
    // "soon" stubs that appear only for a capability the Group actually runs.
    'tab' => [
        'overview' => 'Overview',
        'roster' => 'Roster',
        'meetings' => 'Meetings',
        'documents' => 'Documents',
        'scheduling' => 'Scheduling',
        'content' => 'Content',
        'hours' => 'Hours',
    ],
    // Marker on a capability tab whose feature has not shipped yet.
    'soon' => 'Soon',

    // Banner / header chrome.
    'archived' => 'Archived',
    'ended' => 'Ended :date',

    // Overview tab.
    'about' => 'About Us',
    'about_empty' => 'No description yet.',
    'children' => 'Groups',
    'leadership' => 'Leadership',
    'leadership_empty' => 'No officers recorded.',
    'facts' => 'At a glance',
    'facts_members' => '{1} :count member|[2,*] :count members',
    'facts_meets' => 'Holds meetings',
    'facts_dates' => ':start – :end',
    'facts_starts' => 'Starts :date',
    'facts_ends' => 'Ends :date',

    // Section panels not yet built in this slice (Roster #189, Meetings #190).
    'coming_soon' => 'This section is coming soon.',

    // Role labels — the Group's officers, by role (leadership at a glance).
    'role' => [
        'chair' => 'Chair',
        'secretary' => 'Secretary',
        'treasurer' => 'Treasurer',
        'scheduler' => 'Scheduler',
        'statistician' => 'Statistician',
        'vetting' => 'Vetting',
        'librarian' => 'Librarian',
        'content_maintainer' => 'Content Maintainer',
        'news_editor' => 'News Editor',
    ],
];
