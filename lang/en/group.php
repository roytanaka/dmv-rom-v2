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

    // Roster tab (#189) — the Group-scoped Directory surface.
    'roster' => [
        'search' => [
            'label' => 'Search by name',
            'placeholder' => 'Search by name',
        ],
        'column' => [
            'name' => 'Name',
            'roles' => 'Roles',
            'contact' => 'Contact',
            'standing' => 'Standing',
        ],
        // Shown in the contact cell when the viewer may not see contact details.
        'no_contact' => '—',
        'empty' => 'No members yet.',
        'no_matches' => 'No members match your search.',
    ],

    // Within-Group standing labels (App\Enums\MembershipStatus), distinct from a
    // Member's DMV-wide standing. Shown as a roster badge only when not Full.
    'standing' => [
        'full' => 'Full',
        'loa' => 'On leave',
        'trainee' => 'Trainee',
        'transitional' => 'Transitional',
        'auxiliary' => 'Auxiliary',
        'projects' => 'Projects',
        'emeritus' => 'Emeritus',
        'inactive' => 'Inactive',
        'resigned' => 'Resigned',
        'deceased' => 'Deceased',
        'donor' => 'Donor',
    ],

    // Meetings tab (#190) — the Group's first own-data, members-only surface.
    'meetings' => [
        'empty' => 'No meetings yet.',
        'video' => 'Join video call',
        'link' => [
            'agenda' => 'Agenda',
            'minutes' => 'Minutes',
            'report' => 'Report',
        ],
    ],

    // Officer edits on the Overview (#191) — inline About Us and banner selection.
    // Shown only to a Secretary / Chair / super-tier (server-gated via `can`).
    'edit' => [
        'about' => 'Edit',
        'about_title' => 'Edit About Us',
        'about_placeholder' => 'Describe this group…',
        'banner' => 'Change banner',
        'banner_title' => 'Choose a banner',
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],

    // The curated banner set (#191). The neutral default applies when none is set.
    'banner' => [
        'aria' => 'Group banner',
        'default' => 'Default',
        'option' => [
            'columns' => 'Columns',
            'quill' => 'Quill',
            'lattice' => 'Lattice',
            'ribbon' => 'Ribbon',
            'terrazzo' => 'Terrazzo',
        ],
    ],

    // Section panels not yet built in this slice.
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
