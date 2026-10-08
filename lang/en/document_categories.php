<?php

// Document categories in a Group's Document library (#724, spec #721, ADR-0030 §4). Chrome
// only: Document category names are content, shown as written (ADR-0004).
return [
    'manage' => 'Categories',
    'title' => 'Categories',
    'empty' => 'No categories yet.',
    'add' => 'Add',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'rename_label' => 'Rename :name',
    'delete_label' => 'Delete :name',
    'delete_title' => 'Delete :name?',
    'delete_body' => 'Its folders and files move to Other.',

    'field' => [
        'name' => 'Name',
        'new' => 'New category',
        'category' => 'Category',
    ],

    // The section for items with no Document category, and the select's choice for it.
    'other' => 'Other',
    'none' => 'No category',

    // A section heading's count.
    'count' => [
        'folders' => '{1} :count folder|[2,*] :count folders',
        'files' => '{0} :count files|{1} :count file|[2,*] :count files',
    ],

    'error' => [
        'name_required' => 'Enter a name.',
        'name_taken' => 'A category with this name is already here.',
        'not_here' => 'Choose a category of this folder.',
    ],
];
