<?php

// Tester feedback chrome (#676, ADR-0029): the send dialog, the Feedback page, and the
// labels of the Feedback type and status enums. Chrome, so it ships in both locales
// (ADR-0004, ADR-0029 §14). A Tester's message is content and is never translated.
return [
    'title' => 'Feedback',
    'empty' => 'No feedback yet.',

    'dialog' => [
        'title' => 'Send feedback',
        'name' => 'Your name',
        'type' => 'Type',
        'type_placeholder' => 'Choose a type',
        'message' => 'Message',
        'send' => 'Send',
        'cancel' => 'Cancel',
        'see_all' => 'See all feedback',
        'sent' => 'Thank you. Your feedback was sent.',
        'close' => 'Close',
    ],

    'column' => [
        'type' => 'Type',
        'status' => 'Status',
        'name' => 'Name',
        'message' => 'Message',
        'date' => 'Date',
        'comments' => 'Comments',
        'comments_count' => ':count comment|:count comments',
    ],

    // The Feedback page filters (#679, §13).
    'filter' => [
        'type' => 'Type',
        'status' => 'Status',
        'all_types' => 'All types',
        'all_statuses' => 'All statuses',
        'clear' => 'Clear filters',
        'empty' => 'No feedback matches these filters.',
    ],

    // The Support-operator's controls on an item's page (#679, §7).
    'manage' => [
        'title' => 'Manage',
        'status' => 'Status',
        'delete' => 'Delete item',
        'delete_title' => 'Delete Feedback #:id?',
        'delete_body' => 'This deletes the item, its screenshots, and its comments. You cannot undo this.',
        'cancel' => 'Cancel',
    ],

    // One item's page (#677, §13): the captured context, and the comments under it.
    'item' => [
        'title' => 'Feedback #:id',
        'sent_by' => 'Sent by :name',
        'context' => 'Context',
        'page' => 'Page',
        'route' => 'Page name',
        'locale' => 'Language',
        'browser' => 'Browser',
        'viewport' => 'Screen size',
        'member' => 'Logged in as',
        'impersonator' => 'Impersonated by',
        'version' => 'App version',
        'none' => 'None',
    ],

    // Screenshots on an item (#678, §9): the send dialog's drop zone and the item page.
    // The file errors name the file; the dialog and the server use the same words.
    'screenshots' => [
        'title' => 'Screenshots',
        'drop' => 'Drag or paste images here, or',
        'choose' => 'Choose files',
        'list' => 'Screenshots to send',
        'remove' => 'Remove :name',
        'pasted' => 'Pasted image',
        'size_kb' => ':size KB',
        'size_mb' => ':size MB',
        'error_type' => ':name was not added. Use a PNG, JPEG, WebP, or GIF image.',
        'error_size' => ':name was not added. It is larger than 5 MB.',
        'error_limit' => ':name was not added. You can add up to :max screenshots.',
        'error_count' => 'You can add up to :max screenshots.',
    ],

    'comments' => [
        'title' => 'Comments',
        'empty' => 'No comments yet.',
        'name' => 'Your name',
        'body' => 'Comment',
        'add' => 'Add comment',
        'delete' => 'Delete',
        'delete_label' => 'Delete the comment by :name',
        'delete_title' => 'Delete this comment?',
        'delete_body' => 'The comment by :name goes away for everyone. You cannot undo this.',
        'delete_confirm' => 'Delete comment',
    ],

    // FeedbackType (ADR-0029 §6). Plain words: Testers do not know the word "legacy".
    'type' => [
        'bug' => 'Bug',
        'feature-request' => 'Feature request',
        'translation' => 'Translation',
        'missing-from-new-site' => 'Missing from new site',
        'confusing' => 'Confusing',
        'other' => 'Other',
    ],

    // FeedbackStatus (ADR-0029 §7).
    'status' => [
        'new' => 'New',
        'confirmed' => 'Confirmed',
        'fixed' => 'Fixed',
        'wont-fix' => 'Won’t fix',
        'duplicate' => 'Duplicate',
    ],
];
