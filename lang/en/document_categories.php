<?php

// Document categories in a Group's Document library (#724, spec #721, ADR-0030 §4). Chrome
// only: Document category names are content, shown as written (ADR-0004).
return [
    'manage' => 'Manage categories',
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

    // The Category filter beside the breadcrumb (#725).
    'filter' => [
        'label' => 'Filter by category',
        'all' => 'All categories',
        'unavailable' => 'Unavailable category',
        'empty' => 'Nothing in this category.',
    ],

    // The section for items with no Document category, and the select's choice for it.
    'other' => 'Other',
    'none' => 'No category',

    // What a section or the open Folder's header (#726) holds.
    'count' => [
        'categories' => '{1} :count category|[2,*] :count categories',
        'folders' => '{1} :count folder|[2,*] :count folders',
        'files' => '{0} :count files|{1} :count file|[2,*] :count files',
    ],

    'error' => [
        'name_required' => 'Enter a name.',
        'name_taken' => 'A category with this name is already here.',
        'not_here' => 'Choose a category of this folder.',
    ],
];
