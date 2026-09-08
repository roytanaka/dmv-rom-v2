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
];
