<?php

// Strings for the shared route-stub placeholder pages (#109): the "coming soon"
// page every stubbed route renders, and the locale-aware "not translated yet"
// boundary page for /fr/ URLs with no registered French route (ADR-0008).
// Chrome strings — translated like all chrome (ADR-0004). Mirrors lang/fr/placeholder.php.
return [
    'coming_soon' => [
        'title' => 'Coming soon',
        'body' => 'This part of the rebuild is not available yet.',
    ],
    'not_translated' => [
        'title' => 'Not translated yet',
        'body' => 'This page is not available in French yet.',
    ],
];
