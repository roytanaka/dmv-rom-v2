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
    'choose_kind' => 'Choose a kind',
    'field' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'parent' => 'Parent',
        'kind' => 'Kind',
    ],
    'error' => [
        'slug_taken' => 'Another Group uses this slug.',
        'slug_format' => 'Use lowercase letters, digits and hyphens.',
        'member_admin_required' => 'Choose a Group. Member administration always has a holder.',
        'inactive_group' => 'Choose an active Group.',
    ],
    // The structure form (#843) fills in from here.
    'structure_soon' => 'The structure form is coming.',
    'save' => 'Save',
    'last_changed' => 'Last changed by :name on :date.',
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
        'held_by' => 'Held by :groups',
        'held_by_none' => 'Held by no Group',
        'group' => 'Group',
        'groups' => 'Groups',
        'gain' => 'Gain (:count): :names',
        'lose' => 'Lose (:count): :names',
    ],
];
