<?php

// Strings for the composer's own chrome (spec #479, ADR-0024 §4, §6) — the little the app
// adds around a Broadcast that is otherwise content. A Broadcast body goes out as authored,
// in one language; only the sender copy's "who was not reached" footer is chrome, rendered in
// the sender's saved locale. Mirrors lang/fr/broadcasts.php key-for-key.
return [
    // The sender's copy, the done signal (ADR-0024 §4). Its footer names the recipients not
    // reached: :names is the list, up to twenty, with `and_more` appended for the overflow.
    'sender_copy' => [
        'undelivered' => 'Could not be delivered to: :names',
        'and_more' => 'and :count more',
    ],

    // The composer sheet's chrome (ADR-0024 §6) — the stepped Who → Message → Sent flow the
    // Email menu opens. Only these framing strings are translated; the subject and body a
    // sender writes are content and go out as authored.
    'composer' => [
        'menu' => 'Email',
        'title' => 'New email',
        // The Who step.
        'who_heading' => 'Who · :count member|Who · :count members',
        'hand_picked' => 'Hand-picked (:count)',
        'to' => 'To',
        'remove' => 'Remove :name',
        'add_people' => 'Add people',
        'search_placeholder' => 'Search by name',
        'select_all' => 'Select all',
        'empty_roster' => 'No one on this roster matches.',
        // The Message step.
        'from' => 'From :group · replies come to you · you get a copy',
        'subject' => 'Subject',
        'body' => 'Message',
        'attachments' => 'Attachments',
        'attach' => 'Add attachment',
        'attachment_remove' => 'Remove :name',
        // The editor toolbar.
        'bold' => 'Bold',
        'italic' => 'Italic',
        'bullet_list' => 'Bulleted list',
        'ordered_list' => 'Numbered list',
        'link' => 'Insert link',
        'link_prompt' => 'Link address',
        // Navigation and send.
        'back' => 'Back',
        'next' => 'Next',
        'send' => 'Send',
        'sending' => 'Sending…',
        // The Sent step.
        'sent_heading' => 'Queued for :count member.|Queued for :count members.',
        'sent_note' => 'Delivery takes up to an hour; you get a copy when it is done.',
        'skipped' => 'could not be reached (email switched off)',
        'done' => 'Done',
    ],
];
