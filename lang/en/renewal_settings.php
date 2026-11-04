<?php

// Renewal settings (#937, ADR-0038 §9): member administration's page for the year's Renewal.
// Chrome strings (ADR-0004); the message texts themselves are content. Mirrors
// lang/fr/renewal_settings.php.
return [
    'title' => 'Renewal settings',
    'counts' => [
        'renewed' => 'Renewed for :year: :count',
        'paid' => 'Paid through :year: :count',
    ],
    'section' => [
        'renewal' => 'Renewal',
        'active' => 'Active reminders',
        'sustaining' => 'Sustaining reminders',
        'external' => 'External addresses',
    ],
    'field' => [
        'show_renewal' => 'Show the renewal',
        'renewal_year' => 'Renewal year',
        'send_date' => 'Send date',
        'message_1' => 'First reminder',
        'message_2' => 'Second reminder',
        'attachment' => 'Attachment',
        'external_addresses' => 'Addresses, one per line',
    ],
];
