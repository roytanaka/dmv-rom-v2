<?php

// The Document library on a Group's Documents tab (#712, spec #290, ADR-0030). Chrome only:
// Document titles, descriptions and filenames are content, shown as written (ADR-0004).
return [
    'empty' => 'No documents yet.',

    'column' => [
        'name' => 'Name',
        'type' => 'Type',
        'size' => 'Size',
        'updated' => 'Updated',
        'uploader' => 'Uploaded by',
        'actions' => 'Actions',
    ],

    'download' => 'Download :name',

    'upload' => [
        'button' => 'Upload files',
        'drop' => 'Drop files here or',
        'choose' => 'Choose files',
        'uploading' => 'Uploading',
        'done' => 'Uploaded',
        'failed' => 'Not added',
        'error_type' => ':name was not added. This type of file is not allowed.',
        'error_size' => ':name was not added. It is larger than 1.5 GB.',
    ],

    // Edit, replace and delete one Document (#713).
    'manage' => [
        'menu' => 'Actions for :name',
        'edit' => 'Edit',
        'replace' => 'Replace file',
        'delete' => 'Delete',
        'edit_title' => 'Edit document',
        'replace_title' => 'Replace the file of :name',
        'replace_save' => 'Replace',
        'delete_title' => 'Delete this document?',
        'delete_body' => ':name and its file will be deleted. This cannot be undone.',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'field' => [
            'title' => 'Title',
            'description' => 'Description',
            'file' => 'New file',
        ],
    ],
];
