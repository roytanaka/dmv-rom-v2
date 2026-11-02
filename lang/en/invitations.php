<?php

// Strings for Invitations (#902, ADR-0034). Chrome, translated like all chrome
// (ADR-0004); an Invitation's name, description and Options are as-authored content
// and never pass through here. Mirrors lang/fr/invitations.php.
return [
    'title' => 'Invitations',
    'tabs' => [
        'mine' => 'My invitations',
        'hosting' => 'Hosting',
    ],
    'mine' => [
        'empty' => 'You have no invitations.',
    ],
    'hosting' => [
        'empty' => 'You are not hosting any invitations.',
        'new' => 'New invitation',
        'date' => 'Date',
        'name' => 'Name',
        'host' => 'Host',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
    'state' => [
        'draft' => 'Draft',
        'open' => 'Open',
    ],
    'form' => [
        'create_title' => 'New invitation',
        'edit_title' => 'Edit invitation',
        'name' => 'Name',
        'date' => 'Date',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'description' => 'Description',
        'options' => 'Options',
        'option_name' => 'Option name',
        'add_option' => 'Add option',
        'remove_option' => 'Remove option',
    ],
    'guests' => [
        'title' => 'Guests',
        'title_for' => 'Guests: :name',
        'count' => ':count Guest|:count Guests',
        'empty' => 'No Guests yet.',
        'member' => 'Add a Member',
        'audience' => 'Add a group',
        'add' => 'Add',
        'remove' => 'Remove',
        'confirm_remove' => 'Remove this Guest? Their reply goes too.',
    ],
    'edit' => 'Edit',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'confirm_delete' => 'Delete this invitation?',
];
