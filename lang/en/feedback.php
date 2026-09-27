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

    'comments' => [
        'title' => 'Comments',
        'empty' => 'No comments yet.',
        'name' => 'Your name',
        'body' => 'Comment',
        'add' => 'Add comment',
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
