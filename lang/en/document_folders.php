<?php

// Folders in a Group's Document library (#714, spec #290, ADR-0030 §3). Chrome only: Folder
// names are content, shown as written (ADR-0004). Kept apart from documents.php so the
// Folders strings stand on their own.
return [
    'root' => 'Documents',
    'breadcrumb' => 'Folders',
    'empty' => 'This folder is empty.',
    'open' => 'Open :name',
    'kind' => 'Folder',

    'new' => 'New folder',
    'actions' => 'Actions for :name',
    'move' => 'Move',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',

    'create_title' => 'New folder',
    'move_title' => 'Move :name',
    'delete_title' => 'Delete :name?',
    'delete_body' => 'Only an empty folder can be deleted.',

    'field' => [
        'name' => 'Name',
        'destination' => 'Move to',
        'visibility' => 'Who can read it',
    ],

    // Who reads a Folder (#715, ADR-0030 §5). Set on top-level Folders; the rest inherit.
    'edit' => 'Edit',
    'edit_title' => 'Edit folder',
    'visibility' => [
        'group' => 'Group members',
        'members' => 'All members',
    ],
    'readable_by' => 'Readable by: :who',

    // The move picker's top option: the library root.
    'top_level' => 'Top level of the library',

    'move_document' => 'Move',
    'move_document_title' => 'Move :name',

    'error' => [
        'name_required' => 'Enter a name.',
        'name_taken' => 'A folder with this name is already here.',
        'too_deep' => 'Folders can be at most :max levels deep.',
        'into_itself' => 'A folder cannot move inside itself.',
        'not_empty' => 'This folder still holds folders or documents. Move or delete them first.',
        'not_found' => 'That folder no longer exists.',
        'visibility_top_level' => 'Only a top-level folder sets who can read it.',
        'visibility_invalid' => 'Choose who can read this folder.',
    ],
];
