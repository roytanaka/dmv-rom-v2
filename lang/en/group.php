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
        // Reminders settings (#486, ADR-0024 §7) — the schedule-admin's on/off switch and lead
        // days for this Group's shift Reminders, shown on the list view.
        'reminders' => [
            'heading' => 'Shift reminders',
            'description' => 'Email members a reminder a few days before each shift they have signed up for.',
            'enabled_label' => 'Send shift reminders',
            'lead_days_label' => 'Days before the shift',
        ],
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
        // Shift authoring form (#356 front end, ADR-0021 §2) — the Scheduler's add / edit /
        // delete controls on an opened Schedule, gated by the server's `can` hints. `edit` /
        // `delete` above are reused; these name the form and its fields. The kind picker draws
        // from the Group's `shift_kinds`; the audience picker offers the two ShiftAudience cases.
        'new_shift' => 'New shift',
        'create_shift_title' => 'New shift',
        'edit_shift_title' => 'Edit shift',
        'confirm_delete_shift' => 'Delete this shift? This cannot be undone.',
        'shift_field' => [
            'starts_at' => 'Start',
            'ends_at' => 'End',
            'capacity' => 'Capacity',
            'kind' => 'Kind',
            'kind_none' => 'No kind',
            'audience' => 'Audience',
        ],
        'audience' => [
            'group' => 'Group members',
            'open' => 'Open to all',
        ],
        // Shift authoring (#356, ADR-0021 §2) — validation surfaced by the Form Requests
        // when the date range is enforced both ways: a Shift may not sit outside its
        // Schedule, and a Schedule may not shrink away from the Shifts already on it.
        'shift_outside_range' => 'This shift falls outside the schedule’s date range.',
        'schedule_range_conflict' => 'The schedule’s dates cannot leave out a shift already on it. Move or delete those shifts first.',
        // Bulk-create / bulk-delete (#362, ADR-0021 §2) — a bulk run is N single writes
        // plus a report. These name the rows a run skipped: they are useful output, not
        // errors, so they surface as a report on the page rather than a validation failure.
        'bulk' => [
            'skipped_outside_range' => 'Skipped — this day falls outside the schedule’s date range.',
            'skipped_has_sign_ups' => 'Skipped — this shift has members signed up. Remove them first.',
            // Bulk-place a Member's Sign-ups (#363, ADR-0021 §5) — a placed run honours
            // capacity and the one-seat rule per row, skipping and naming what it cannot write.
            'skipped_full' => 'Skipped — this shift is already full.',
            'skipped_already_signed_up' => 'Skipped — this member already has a seat on this shift.',
            // Bulk-create / bulk-delete Shifts form (#362 front end, ADR-0021 §2) — the
            // labour-saver that makes a month one form run. No interval: a Shift lands on
            // every matching weekday in the range. Delete matches the same filter and
            // confirms before firing, since it removes many rows at once.
            'open' => 'Bulk shifts',
            'title' => 'Bulk-create shifts',
            'description' => 'Create one shift on every chosen weekday across a date range. Delete removes every shift matching the same filter.',
            'create' => 'Create shifts',
            'delete' => 'Delete matching',
            'confirm_delete' => 'Delete every shift matching this filter? Shifts with members signed up are kept. This cannot be undone.',
            'field' => [
                'starts_time' => 'Start time',
                'ends_time' => 'End time',
                'capacity' => 'Capacity',
                'kind' => 'Kind',
                'kind_none' => 'No kind',
                'weekdays' => 'Days of the week',
                'from_date' => 'From date',
                'to_date' => 'To date',
            ],
            // The run report (#362 front end) — useful output, not an error: the counts
            // written or removed, and every skipped row with its reason.
            'report' => [
                'created' => '{0} No shifts created.|{1} :count shift created.|[2,*] :count shifts created.',
                'deleted' => '{0} No shifts removed.|{1} :count shift removed.|[2,*] :count shifts removed.',
                'skipped_heading' => '{1} :count skipped:|[2,*] :count skipped:',
                'dismiss' => 'Dismiss',
            ],
        ],
        // Bulk-place / bulk-remove a Member's Sign-ups (#363 front end, ADR-0021 §5) — the
        // Member-in-Schedule labour-saver that retires Reception's fortnight: one regular
        // placed across every Shift a filter names, weekly or biweekly, in one run — and
        // unwound the same way. Deliberately distinct from bulk-creating Shifts (#362): a
        // different actor's different moment, so it names its own entry point, form, and
        // report. The interval and its anchor live only in the form; no pattern is stored.
        'bulk_assign' => [
            'open' => 'Bulk sign-ups',
            'title' => 'Bulk-place a member’s sign-ups',
            'description' => 'Place one member on every shift matching this filter across a date range — weekly or biweekly. Remove clears that member from every matching shift.',
            'place' => 'Place member',
            'remove' => 'Remove matching',
            'confirm_remove' => 'Remove this member from every shift matching this filter? This clears their seats. This cannot be undone.',
            'field' => [
                'member' => 'Member',
                'member_none' => 'Choose a member…',
                'member_empty' => 'No placeable members in this group.',
                'weekdays' => 'Days of the week',
                'starts_time' => 'Start time',
                'ends_time' => 'End time',
                'from_date' => 'From date',
                'to_date' => 'To date',
                'interval' => 'Interval',
                'anchor_date' => 'Anchor date',
                'anchor_hint' => 'Biweekly weeks are counted from this date.',
            ],
            'interval' => [
                'weekly' => 'Every week',
                'biweekly' => 'Every other week',
            ],
            // The run report — useful output, not an error: the seats filled or cleared, and
            // every skipped row with its reason (a full shift, or a seat already held).
            'report' => [
                'placed' => '{0} No seats filled.|{1} :count seat filled.|[2,*] :count seats filled.',
                'removed' => '{0} No seats cleared.|{1} :count seat cleared.|[2,*] :count seats cleared.',
                'skipped_heading' => '{1} :count skipped:|[2,*] :count skipped:',
                'dismiss' => 'Dismiss',
            ],
        ],
        // Sign-up validation (#357, ADR-0021 §Sign-up) — surfaced by the Form Request when a
        // seat cannot be taken, and by the Shift edit when capacity would strand a member.
        'shift_full' => 'This shift is full.',
        'already_signed_up' => 'You are already signed up for this shift.',
        'capacity_below_signups' => 'Capacity cannot go below the number of members already signed up. Remove members first.',
        // Agenda (#355, #357) — the opened Schedule's Shifts, grouped by day. `seats` reads
        // the filled-seat count against capacity; `sign_up` is the take/drop affordance.
        'agenda' => [
            'aria_label' => 'Agenda',
            'empty' => 'No shifts on this schedule yet.',
            'time_range' => ':start – :end',
            'seats' => ':taken / :capacity taken',
            'sign_up' => [
                'take' => 'Sign up',
                'drop' => 'Drop',
                'full' => 'Full',
                'nobody' => 'No one signed up yet',
                'signed_up_label' => 'Signed up',
            ],
            // Officer assignment and removal (#359) — shown only to a schedule admin
            // (server-gated via `can.assign` and each seat's `signup_id`).
            'assign' => [
                'place' => 'Place a member',
                'title' => 'Place a member on this shift',
                'search' => 'Search members…',
                'empty' => 'No members to place.',
                'remove' => 'Remove from shift',
                'confirm_remove' => 'Remove this member from the shift?',
            ],
            // Sign-out (#445, PRD #443, ADR-0023 §5) — the seat-holder records how many
            // visitors they served, on their own Shift, from five minutes before it ends. The
            // Sign Out button stays disabled until a number is typed; the server enforces the
            // rest. `count` labels the box and its chip; the messages carry the server's refusals.
            'sign_out' => [
                'count_label' => 'Visitors served',
                'submit' => 'Sign out',
                'placeholder' => 'Number of visitors',
                'recorded' => ':count visitors',
                'whole_number' => 'Enter a whole number of visitors.',
                'not_negative' => 'The number of visitors cannot be negative.',
                // The Officer's correction (#450, ADR-0023 §5) — the pencil on every seat, with no
                // deadline. `correct` labels the pencil; `correcting` names whose seat is open so a
                // correction is never mistaken for a self sign-out; `save` and `cancel` are its
                // buttons (the own-seat sign-out keeps `submit` and has no cancel).
                'correct' => 'Correct visitor count',
                'correcting' => 'Correcting :name’s visitors',
                'save' => 'Save',
                'cancel' => 'Cancel',
                // The tour-leading second box (#447, ADR-0023 §2) — visitors served outside the
                // tour, optional. The label says "outside the tour" so it is never confused with
                // the count beside it; `extra_recorded` reads on the chip where both are recorded.
                'extra_label' => 'Visitors served outside the tour',
                'extra_placeholder' => 'Optional',
                'extra_recorded' => ':count outside the tour',
                'extra_whole_number' => 'Enter a whole number of extra interactions.',
                'extra_not_negative' => 'The number of extra interactions cannot be negative.',
                // GDR's five visitor origins (#448, ADR-0023 §3) — the provenance split beside the
                // count, GDR alone. The five must sum to the count; `provenance_sum` names both
                // totals so the volunteer sees where the numbers disagree. The five labels carry
                // the French wording legacy has shown at sign-out since 2020.
                'provenance_france_europe' => 'France and Europe',
                'provenance_quebec' => 'Quebec',
                'provenance_toronto' => 'Toronto',
                'provenance_rest_of_canada' => 'Rest of Canada',
                'provenance_other_countries' => 'Other countries',
                'provenance_sum' => 'The five origins add up to :sum, but the visitor count is :count.',
                'provenance_required' => 'Enter a number for every origin.',
                'provenance_whole_number' => 'Enter a whole number of visitors.',
                'provenance_not_negative' => 'The number of visitors cannot be negative.',
            ],
        ],
        // My sign-ups (#449, PRD #443, ADR-0023 §5) — the outstanding-shifts panel: the
        // viewer's own upcoming Shifts plus any past Shift inside the 28-day window still owed a
        // number. It crosses Schedules, so the subtitle names what it reaches; absent, not empty,
        // when there is nothing to show.
        'mine' => [
            'aria_label' => 'My sign-ups',
            'heading' => 'My sign-ups',
            'subtitle' => 'Your upcoming shifts, and any recent shift that still needs a visitor count.',
        ],
        // View toggle (#360, ADR-0021 §7) — the reader chooses Agenda or Calendar; the
        // choice lives in localStorage, never in the authoring form. Agenda is the default.
        'view' => [
            'aria_label' => 'Choose a view',
            'agenda' => 'Agenda',
            'calendar' => 'Calendar',
        ],
        // Calendar (#360) — the month-grid view and its day sheet.
        'calendar' => [
            'aria_label' => 'Calendar',
            'previous' => 'Previous month',
            'next' => 'Next month',
            'shift_count' => '{1} :count shift|[2,*] :count shifts',
        ],
        // Cross-Group open Shifts (#361, ADR-0021 §Sign-up) — other Groups' `open` Shifts the
        // reader discovers, always present but collapsed to a one-line band, always attributed
        // to their owning Group. `summary` heads the collapsed band; `show_all` / `hide_all` is
        // the master open/close-all; `chip` is the Calendar cell's compact count.
        'foreign' => [
            'summary' => '{1} :count more open to you — :group|[2,*] :count more open to you — :group',
            'show_all' => 'Open to me elsewhere',
            'hide_all' => 'Hide other groups’ shifts',
            'chip' => '+:count open',
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
