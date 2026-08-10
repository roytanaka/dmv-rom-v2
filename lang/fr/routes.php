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
    // Fiche d'un membre (#172, PRD #167). Le mot anglais « members » se traduit ici
    // par « benevoles » (et non « membres » comme pour officer.members) — voir la
    // note dans lang/en/routes.php sur la collision de segment.
    'members.show' => 'benevoles/{member}',
    'documents' => 'documents',
    'news' => 'nouvelles',
    'profile' => 'profil',
    'renew' => 'renouveler',
    // Utility — the top bar's Help destination (#194).
    'help' => 'aide',

    // Zone C — officer/admin
    'officer.members' => 'officier/membres',
    'officer.communications' => 'officier/communications',
    'officer.reports' => 'officier/rapports',
    'officer.flash-messages' => 'officier/messages-eclair',
    'officer.settings' => 'officier/parametres',

    // Dynamic group route. The {group} slug echoes back as-authored even under
    // /fr/ (/fr/groupes/docents) — no per-record slug translation (ADR-0008).
    'groups.show' => 'groupes/{group}/{section?}',

    // Permalien d'un horaire (#353, ADR-0021 §1). « scheduling » → « horaire »
    // (comme l'onglet group.tab.scheduling) ; {group} et {schedule} restent verbatim.
    'groups.scheduling.show' => 'groupes/{group}/horaire/{schedule}',

    // Paramètres → Profil / Mot de passe (#229, PRD #228). « settings » →
    // « parametres » (sans accent dans l'URL, comme officer.settings).
    'settings' => 'parametres',
    'settings.profile' => 'parametres/profil',
    'settings.profile.photo' => 'parametres/profil/photo',
    'settings.password' => 'parametres/mot-de-passe',
    'settings.skills' => 'parametres/competences',
];
