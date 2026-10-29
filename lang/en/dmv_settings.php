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
    // The Groups tree (#841, restyled in #848): its toolbar, columns, row notes and facts.
    'tree' => [
        'column' => [
            'name' => 'Group',
            'kind' => 'Kind',
            'capabilities' => 'Capabilities',
            'status' => 'Status',
        ],
        'search' => 'Find a Group',
        'show_archived' => 'Show archived Groups',
        'expand' => 'Expand :name',
        'collapse' => 'Collapse :name',
        // The root's settings are not here; a Container has no page of its own.
        'root_note' => 'Set by officers',
        'container_note' => 'No page',
        // A parent officer's own Group, heading their subtree (#883): listed, not opened.
        'own_note' => 'Your Group',
        // One letter per capability slot; the legend under the table spells them out.
        'letter' => [
            'has_meetings' => 'M',
            'has_documents' => 'D',
            'has_scheduling' => 'S',
            'has_vetting' => 'V',
            'has_announcements' => 'N',
            'has_bookings' => 'B',
        ],
        'capability_on' => ':capability: on',
        'capability_off' => ':capability: off',
        // Short Stewardship names for the Status column.
        'stewardship' => [
            'member_admin' => 'Member admin',
            'org_mail' => 'Org-wide sender',
        ],
        'hours' => 'Hours ×:multiplier',
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
    'choose_section' => 'Choose a section',
    'choose_parent' => 'Choose a parent',
    // The structure form's current parent when it is archived or ended (kept, not offered).
    'inactive_parent' => ':name (inactive)',
    'choose_kind' => 'Choose a kind',
    'field' => [
        'name' => 'Name',
        'slug' => 'Address',
        // The root's child whose subtree the parent comes from (#850).
        'section' => 'Section',
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
        // The restyled form (#848): header link, section index, Archive note and save bar.
        'open_page' => 'Open the Group page',
        'sections' => 'Sections',
        'has_problem' => 'Has a problem',
        'archive_note' => 'An archived Group leaves the rail. Members can still read it. No one can change it until you unarchive it.',
        'save_changes' => 'Save changes',
        'discard' => 'Discard',
        'problems' => '{1} :count problem to fix before you save|[2,*] :count problems to fix before you save',
    ],
    // The banner on an archived Group's form (#848).
    'archived_banner' => [
        'title' => 'This Group is archived.',
        'body' => 'Members can still read its page, documents, meetings and hours. No one can change it. Unarchive it to edit these settings.',
    ],
    'capability' => [
        'has_meetings' => 'Meetings',
        'has_documents' => 'Documents',
        'has_scheduling' => 'Scheduling',
        'has_vetting' => 'Vetting',
        'has_announcements' => 'News',
        'has_bookings' => 'Bookings',
    ],
    // One line under each capability switch (#854).
    'capability_help' => [
        'has_meetings' => 'Members see the Group\'s meetings, agendas and minutes.',
        'has_documents' => 'The Group keeps a document library.',
        'has_scheduling' => 'The Group posts shifts and members sign up.',
        'has_vetting' => 'The Group records which tours each member may give.',
        'has_announcements' => 'The Group posts to the DMV-wide News feed.',
        'has_bookings' => 'The Group takes tour bookings from outside clients.',
    ],
    'collection' => [
        'collects_visitor_count' => 'Visitor count',
        'collects_extra_interactions' => 'Extra interactions',
        'collects_visitor_provenance' => 'Visitor origins',
        'visitor_figures_await_booking' => 'Figures wait for the booking',
    ],
    // One line under each visitor-figure switch (#854).
    'collection_help' => [
        'collects_visitor_count' => 'Members enter the number of visitors they served on each shift.',
        'collects_extra_interactions' => 'Members also enter visitors they served outside their tour.',
        'collects_visitor_provenance' => 'Members split each shift\'s visitors by where they came from.',
        'visitor_figures_await_booking' => 'The visitor report marks this Group\'s figures as incomplete until bookings are counted.',
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
    // The same records once the capability is switched off in the draft (#848): hidden, not deleted.
    'hidden' => [
        'meetings' => '{1} :count meeting becomes hidden|[2,*] :count meetings become hidden',
        'documents' => '{1} :count document becomes hidden|[2,*] :count documents become hidden',
        'shifts' => '{1} :count shift becomes hidden|[2,*] :count shifts become hidden',
        'tours' => '{1} :count tour becomes hidden|[2,*] :count tours become hidden',
        'news' => '{1} :count post becomes hidden|[2,*] :count posts become hidden',
        'bookings' => '{1} :count booking becomes hidden|[2,*] :count bookings become hidden',
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
        'root_parent' => 'Choose a section, not the root.',
        'end_before_start' => 'The end date must be on or after the start date.',
        'not_editable' => 'The root and Containers have no settings here.',
        'archived' => 'Unarchive this Group to change its settings.',
        'archive_steward' => ':group holds :stewardship. Move the Stewardship first.',
        'sibling_name_taken' => 'Another Group under this parent has this name.',
        'outside_subtree' => 'Choose one of your Groups or a Group below it.',
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
        // The preview panel (#848): who gains and loses under the draft, or no change yet.
        'gain' => 'Gain the authority (:count)',
        'lose' => 'Lose the authority (:count)',
        'no_change' => 'No change yet. Tick or clear a Group to see who gains or loses the authority.',
        'undo' => 'Undo change',
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
