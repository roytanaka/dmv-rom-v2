<?php

// Chrome navigation strings (English). Source of truth for both PHP (__()) and
// Vue (laravel-vue-i18n `trans`). Only chrome is translated; Volunteer-authored
// Group names render as-authored and never enter this lookup (ADR-0004).
//
// Tracer-bullet scope (#105): rail section headings only. The remaining nav keys
// migrate from resources/js/chrome/messages.ts in #106.
return [
    'rail' => [
        'my_groups' => 'My Groups',
        'all_groups' => 'All Groups',
        'officer' => 'Officer Tools',
    ],
];
