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
    // The structure form (#843) and the Stewardships cards (#846) fill in from here.
    'structure_soon' => 'The structure form is coming.',
    'stewardships_soon' => 'The Stewardships are coming.',
];
