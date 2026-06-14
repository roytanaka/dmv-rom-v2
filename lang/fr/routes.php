<?php

// URI-segment translation table (Canadian French) for mcamara/laravel-localization.
// Translated segments, not a bare prefix: /dashboard ↔ /fr/tableau-de-bord
// (ADR-0008). Mirrors lang/en/routes.php key-for-key. Only the words change —
// dynamic params ({group}) stay verbatim because group slugs are as-authored
// content, not translated.
return [
    'dashboard' => 'tableau-de-bord',

    // Zone A — personal
    'calendar' => 'calendrier',
    'hours' => 'heures',
    'directory' => 'annuaire',
    'documents' => 'documents',
    'news' => 'nouvelles',
    'profile' => 'profil',
    'renew' => 'renouveler',

    // Zone C — officer/admin
    'officer.members' => 'officier/membres',
    'officer.communications' => 'officier/communications',
    'officer.reports' => 'officier/rapports',
    'officer.flash-messages' => 'officier/messages-eclair',
    'officer.settings' => 'officier/parametres',

    // Dynamic group route. The {group} slug echoes back as-authored even under
    // /fr/ (/fr/groupes/docents) — no per-record slug translation (ADR-0008).
    'groups.show' => 'groupes/{group}/{section?}',
];
