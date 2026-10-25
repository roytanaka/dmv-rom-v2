<?php

// DMV Settings (#836, ADR-0036) — super-tier only. Chrome strings; Group names are content.
// Mirrors lang/fr/dmv_settings.php key-for-key.
return [
    'title' => 'DMV Settings',
    'subtitle' => 'The organisation\'s Groups and Stewardships.',
    'section' => [
        'groups' => 'Groups',
        'stewardships' => 'Stewardships',
    ],
    'tree' => [
        'column' => [
            'name' => 'Name',
            'kind' => 'Kind',
            'status' => 'Status',
        ],
        'no_settings' => 'No settings here',
        'archived' => 'Archived',
        'create' => 'Create a Group',
    ],
    'kind' => [
        'standing_committee' => 'Standing committee',
        'program' => 'Program',
        'working_group' => 'Working group',
        'project' => 'Project',
        'cohort' => 'Cohort',
        'container' => 'Container',
    ],
    // Create a Group (#842): the dialog on the tree.
    'create' => [
        'title' => 'Create a Group',
        'submit' => 'Create',
        'cancel' => 'Cancel',
    ],
    'choose_parent' => 'Choose a parent',
    // The structure form's current parent when it is archived or ended (kept, not offered).
    'inactive_parent' => ':name (inactive)',
    'choose_kind' => 'Choose a kind',
    'field' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'parent' => 'Parent',
        'kind' => 'Kind',
        'kind_container_note' => 'A Container has no page. Its address answers 404.',
        'visibility' => 'Listing visibility',
        'display_order' => 'Display order',
        'logo' => 'Logo',
        'time_boxed' => 'Time-boxed',
        'start_date' => 'Start',
        'end_date' => 'End',
        'hours_multiplier' => 'Hours multiplier',
    ],
    // The structure form (#843): its fieldsets, flags and option labels.
    'group' => [
        'identity' => 'Identity',
        'listing' => 'Listing',
        'dates' => 'Dates',
        'capabilities' => 'Capabilities',
        'visitor_figures' => 'Visitor figures',
        'hours' => 'Hours',
        'archive' => 'Archive',
    ],
    'capability' => [
        'has_meetings' => 'Meetings',
        'has_documents' => 'Documents',
        'has_scheduling' => 'Scheduling',
        'has_vetting' => 'Vetting',
        'has_announcements' => 'Announcements',
        'has_bookings' => 'Bookings',
    ],
    'collection' => [
        'collects_visitor_count' => 'Visitor count',
        'collects_extra_interactions' => 'Extra interactions',
        'collects_visitor_provenance' => 'Visitor origins',
        'visitor_figures_await_booking' => 'Figures wait for the booking',
    ],
    'visibility' => [
        'public' => 'Public',
        'group' => 'Parent Group members',
        'private' => 'Members only',
    ],
    // Logo names are the Groups' display names (content), the same in both languages.
    'logo' => [
        'none' => 'None',
        'docents' => 'Docents',
        'guides-du-rom' => 'Guides du ROM',
        'les-amis-francophiles' => 'Les Amis Francophiles',
        'dmv-hands-on-tours' => 'DMV Hands-on Tours',
        'gallery-interpreters' => 'Gallery Interpreters',
        'visitor-guides' => 'Visitor Guides',
        'visitor-wayfinders' => 'Visitor Wayfinders',
        'romwalks' => 'ROMWalks',
        'reception' => 'Reception',
        'rombus' => 'ROMBus',
        'romtravel' => 'ROMTravel',
        'bishop-white-fea' => 'Bishop White (FEA)',
        'friends-of-global-south-asia-fsa' => 'Friends of Global South Asia (FSA)',
        'friends-of-textiles-costume' => 'Friends of Textiles & Costume',
        'friends-of-palaeontology-fop' => 'Friends of Palaeontology (FOP)',
        'friends-of-earth-space-fes' => 'Friends of Earth & Space (FES)',
    ],
    // The records behind a capability that is on (#844), beside its checkbox.
    'count' => [
        'meetings' => '{1} :count meeting|[2,*] :count meetings',
        'documents' => '{1} :count document|[2,*] :count documents',
        'shifts' => '{1} :count shift|[2,*] :count shifts',
        'tours' => '{1} :count tour|[2,*] :count tours',
        'news' => '{1} :count post|[2,*] :count posts',
        'bookings' => '{1} :count booking|[2,*] :count bookings',
    ],
    'save' => 'Save',
    // The last glue of a list of names: "Scheduler and Booker".
    'and' => 'and',
    'last_changed' => 'Last changed by :name on :date.',
    'archived' => 'Archived',
    'error' => [
        'slug_taken' => 'Another Group uses this slug.',
        'slug_format' => 'Use lowercase letters, digits and hyphens.',
        'member_admin_required' => 'Choose a Group. Member administration always has a holder.',
        'inactive_group' => 'Choose an active Group.',
        'parent_cycle' => 'A Group cannot sit under itself or one of its own Groups.',
        'end_before_start' => 'The end date must be on or after the start date.',
        'not_editable' => 'The root and Containers have no settings here.',
        'archived' => 'Unarchive this Group to change its settings.',
        'archive_steward' => ':group holds :stewardship. Move the Stewardship first.',
        'capability_in_use' => '{1} :roles needs this capability. Reassign the role first.|[2,*] :roles need this capability. Reassign the roles first.',
    ],
    // The Stewardships section (#846, ADR-0036 §7).
    'stewardship' => [
        'member_admin' => [
            'title' => 'Member administration',
            'description' => 'Members of this Group edit every Member\'s record and open the all-DMV reports. Exactly one Group holds it.',
        ],
        'org_mail' => [
            'title' => 'Org-wide sender',
            'description' => 'Members of these Groups can email every Member, every Chair and the Board.',
        ],
        // Not shown as cards yet (ADR-0036 §7); named only by the archive guard (#845).
        'statistics' => ['title' => 'Statistics'],
        'website' => ['title' => 'Website'],
        'held_by' => 'Held by :groups',
        'held_by_none' => 'Held by no Group',
        'group' => 'Group',
        'groups' => 'Groups',
        'gain' => 'Gain (:count): :names',
        'lose' => 'Lose (:count): :names',
    ],
    // Archive and unarchive a Group (#845, ADR-0036 §2).
    'archive' => [
        'button' => 'Archive this Group',
        'confirm_title' => 'Archive :name?',
        'confirm_body' => 'Members keep reading. Officers can no longer write. You can unarchive later.',
        'confirm' => 'Archive',
        'cancel' => 'Cancel',
        'unarchive' => 'Unarchive',
    ],
];
