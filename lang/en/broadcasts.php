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
        // A Direct message has one recipient, so its sender copy names the one who was missed.
        'undelivered_direct' => 'Could not deliver to :name.',
    ],

    // A Direct message (ADR-0024 §6) — any Member to one other. The refusal shown when the one
    // recipient has email switched off, so nothing is written and the sender is told at once.
    'direct' => [
        'unreachable' => ':name cannot be reached by email.',
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
        // The Who step of a Direct message: one fixed recipient, no picking. The note warns the
        // sender their reply address is shown, since a reply comes to them (ADR-0024 §6).
        'direct_reply_note' => 'A reply comes to your own address, so :name will see it.',
        // The Message step. Its read-only recipient chips collapse past a cap, with a
        // "+N more" control that reveals the rest in place ("All Members" is ~500 people).
        'more' => '+:count more',
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
