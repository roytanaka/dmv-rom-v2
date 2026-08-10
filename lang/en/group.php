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
        'members' => 'Members',
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
            'actions' => 'Actions',
        ],
        // Shown in the contact cell when the viewer may not see contact details.
        'no_contact' => '—',
        'empty' => 'No members yet.',
        'no_matches' => 'No members match your search.',

        // Officer roster CRUD (#192) — shown only to a Secretary / Chair / super-tier
        // (server-gated via `can.manageRoster`). The validation strings surface from
        // the Form Requests when a bad role or a hard-remove with history is rejected.
        'role_unavailable' => 'That role is not available for this group.',
        'cannot_hard_remove' => 'This member has history and cannot be removed. Resign them instead.',
        'show_past' => 'Show past members',
        'manage' => 'Manage',
        'add' => 'Add member',
        'add_title' => 'Add member',
        'add_search' => 'Search all members',
        'add_submit' => 'Add to group',
        'no_candidates' => 'No members match your search.',
        'edit_title' => 'Manage membership',
        'field' => [
            'standing' => 'Standing',
            'roles' => 'Roles',
            'loa_start' => 'Leave starts',
            'loa_end' => 'Leave ends',
        ],
        'resign' => 'Resign member',
        'confirm_resign' => 'Resign this member? Their history is kept and they can be reinstated.',
        'remove' => 'Remove (added in error)',
        'confirm_remove' => 'Permanently remove this member? This cannot be undone.',
        'save' => 'Save',
        'cancel' => 'Cancel',
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
        // Headings over the two blocks the list is split into.
        'upcoming' => 'Upcoming',
        'past' => 'Past',
        'video' => 'Join video call',
        'link' => [
            'agenda' => 'Agenda',
            'minutes' => 'Minutes',
            'report' => 'Report',
        ],
        // Officer CRUD (#193) — shown only to a Secretary / Chair / super-tier
        // (server-gated via `can`). A hidden meeting carries the Draft badge.
        'draft' => 'Draft',
        'new' => 'New meeting',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'create_title' => 'New meeting',
        'edit_title' => 'Edit meeting',
        'confirm_delete' => 'Delete this meeting? This cannot be undone.',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'field' => [
            'title' => 'Title',
            'held_at' => 'Date and time',
            'description' => 'Description',
            'location' => 'Location',
            'video_url' => 'Video link',
            'published' => 'Published (visible to members)',
            'links' => 'Document links',
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

    // The curated banner set (#191) — full-bleed photos of ROM landmarks. The
    // neutral default applies when none is set.
    'banner' => [
        'aria' => 'Group banner',
        'option' => [
            'rotunda' => 'Rotunda',
            'crystal' => 'Crystal',
            'gallery' => 'Gallery',
            'mural' => 'Mural',
            'stained-glass' => 'Stained glass',
            'totem' => 'Totem',
        ],
    ],

    // Section panels not yet built in this slice.
    'coming_soon' => 'This section is coming soon.',

    // Scheduling tab (#353, ADR-0021 §1) — the Schedule read surface.
    'scheduling_panel' => [
        'empty' => 'No schedules yet.',
        'current_heading' => 'Current & upcoming',
        'past_heading' => 'Past',
        'draft_badge' => 'Draft',
        'date_range' => ':start – :end',
        'back_to_list' => 'All schedules',
        // Authoring (#354) — shown only to a Scheduler / Chair / super-tier
        // (server-gated via `can`). Publish / un-publish are the two state transitions.
        'new' => 'New schedule',
        'edit' => 'Edit',
        'publish' => 'Publish',
        'unpublish' => 'Un-publish',
        'delete' => 'Delete',
        'create_title' => 'New schedule',
        'edit_title' => 'Edit schedule',
        'confirm_delete' => 'Delete this schedule? This cannot be undone.',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'field' => [
            'name' => 'Name',
            'starts_on' => 'Start date',
            'ends_on' => 'End date',
            'description' => 'Description',
        ],
        // Shift authoring (#356, ADR-0021 §2) — validation surfaced by the Form Requests
        // when the date range is enforced both ways: a Shift may not sit outside its
        // Schedule, and a Schedule may not shrink away from the Shifts already on it.
        'shift_outside_range' => 'This shift falls outside the schedule’s date range.',
        'schedule_range_conflict' => 'The schedule’s dates cannot leave out a shift already on it. Move or delete those shifts first.',
        // Agenda (#355) — the opened Schedule's Shifts, grouped by day. `seats` reads
        // the filled-seat count against capacity; `taken` is 0 until Sign-ups (#357).
        'agenda' => [
            'aria_label' => 'Agenda',
            'empty' => 'No shifts on this schedule yet.',
            'time_range' => ':start – :end',
            'seats' => ':taken / :capacity taken',
        ],
    ],

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
        // Synthetic label for the root DMV Group's executive leadership (President /
        // VPs), which carry no per-Group role row — see GroupController::leadership.
        'executive' => 'Executive',
    ],
];
