<?php

// Applicants and intake (#928, ADR-0038 §8). Chrome strings, translated like all chrome
// (ADR-0004). Field labels shared with the Member record come from records.php. Mirrors
// lang/fr/applicants.php.
return [
    'title' => 'Applicants',
    'add' => 'Add applicant',
    'new_title' => 'New applicant',
    'save' => 'Save',
    'saved' => 'Saved.',
    'none' => 'None',

    'status' => [
        'all' => 'All',
        'new' => 'New',
        'assigned_for_interview' => 'Assigned for interview',
        'accepted' => 'Accepted',
        'placed' => 'Placed',
        'yellow_card_completed' => 'Yellow card completed',
        'ready_to_go_active' => 'Ready to go active',
        'incomplete' => 'Incomplete',
    ],

    'filter' => 'Status',
    'search' => 'Search',
    'search_last_name' => 'Search by last name',
    'list_empty' => 'No Applicants with this status.',
    'list_no_match' => 'No Applicants match that last name.',
    'previous' => 'Previous',
    'next' => 'Next',
    'page' => 'Page :current of :last',

    'column' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'status' => 'Status',
        'applied_on' => 'Applied on',
        'last_update' => 'Last update',
        'email' => 'Email',
        'mentor' => 'Mentor',
        'actions' => 'Actions',
    ],
    'action' => [
        'edit' => 'Edit',
        'view' => 'View',
    ],

    'section' => [
        'intake' => 'Intake',
        'application' => 'Application',
    ],

    'field' => [
        'status' => 'Status',
        'status_date' => 'Status date',
        'departure_reason' => 'Reason',
        'placement' => 'Placement',
        'mentor' => 'Mentor',
        'applied_on' => 'Applied on',
        'entry_year' => 'Entry year',
        'override_duplicate_email' => 'Add the applicant anyway',
    ],

    'entered_by' => 'Entered by :name',
    'entered_by_applicant' => 'Entered by the applicant',
    'unassigned' => 'Unassigned',
    'duplicate_email' => 'A Member already has this email.',
    'email_taken' => 'Member :member already has this email. Match the name to theirs to update that Member, or change the email.',

    'languages' => [
        'title' => 'Languages',
        'uses' => 'Uses',
        'other' => 'Other language',
        'add_other' => 'Add another language',
    ],

    // Print, Delete and Send email (#929).
    'outputs' => [
        'print' => 'Print',
        'print_record' => 'Print record',
        'back' => 'Back',
        'list_title' => 'Applicants: :status',
        'search' => 'Last name starts with “:term”',
        'count' => ':count applicant|:count applicants',
        'phone' => 'Phone',
        'entered_by' => 'Entered by',
        'the_applicant' => 'The applicant',
        'last_updated' => 'Last updated',
        'by_on' => ':name on :date',
        'send_email' => 'Send email',
        'delete' => 'Delete',
        'delete_title' => 'Delete :name?',
        'delete_body' => 'The application, its languages and its notes are removed for good.',
        'cancel' => 'Cancel',
    ],
];
