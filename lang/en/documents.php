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
    ],

    'download' => 'Download :name',

    // Link Documents (#716): a title and a web address in place of a file.
    'link' => [
        'add' => 'Add link',
        'add_title' => 'Add a link',
        'edit_title' => 'Edit link',
        'edit' => 'Edit :name',
        'open' => 'Open :name',
        'type' => 'Link',
        'field' => [
            'title' => 'Title',
            'url' => 'Web address',
        ],
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],

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
];
