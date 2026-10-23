<?php

// The Document download log (#754, ADR-0030) — super-tier only. Chrome strings; Member,
// Group and file names are content. Mirrors lang/fr/document_downloads.php key-for-key.
return [
    'title' => 'Download log',
    'subtitle' => 'Who opened each Document, and when.',
    'filter' => [
        'group' => 'Group',
        'all_groups' => 'All Groups',
        'member' => 'Member',
        'all_members' => 'All Members',
        'from' => 'From',
        'to' => 'To',
        'search' => 'File name',
        'apply' => 'Search',
        'clear' => 'Clear filters',
    ],
    'column' => [
        'when' => 'When',
        'member' => 'Member',
        'group' => 'Group',
        'file' => 'File name',
    ],
    'deleted_member' => 'Deleted Member',
    'deleted_group' => 'Deleted Group',
    'deleted_document' => 'Deleted',
    'empty' => 'No downloads yet.',
    'filtered_empty' => 'No downloads match these filters.',
    'page' => 'Page :current of :last',
    'previous' => 'Previous',
    'next' => 'Next',
];
