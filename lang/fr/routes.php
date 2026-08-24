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

    // Rapport annuel des heures d'un groupe (#411, ADR-0022 §5). « hours/report » →
    // « heures/rapport » ; {group} reste verbatim.
    'groups.hours.report' => 'groupes/{group}/heures/rapport',
    // L'export CSV en pendant de chaque rapport (#414, ADR-0022 §8). Un suffixe « .csv » sur le
    // chemin du rapport ; l'extension est universelle et reste verbatim.
    'groups.hours.report.csv' => 'groupes/{group}/heures/rapport.csv',

    // Les trois surfaces d'officier après la matrice annuelle (#412, ADR-0022 §8) : un
    // sélecteur de mois, un historique par membre, et les deux sommaires Membre × douze
    // mois. « month/member/extra/meetings » → « mois/membre/supplementaires/reunions » ;
    // {group} reste verbatim.
    'groups.hours.month' => 'groupes/{group}/heures/mois',
    'groups.hours.member' => 'groupes/{group}/heures/membre',
    'groups.hours.extra' => 'groupes/{group}/heures/supplementaires',
    'groups.hours.meetings' => 'groupes/{group}/heures/reunions',
    // Les exports CSV en pendant de chaque rapport (#414) — un suffixe « .csv » verbatim.
    'groups.hours.month.csv' => 'groupes/{group}/heures/mois.csv',
    'groups.hours.member.csv' => 'groupes/{group}/heures/membre.csv',
    'groups.hours.extra.csv' => 'groupes/{group}/heures/supplementaires.csv',
    'groups.hours.meetings.csv' => 'groupes/{group}/heures/reunions.csv',

    // Les six rapports annuels à l'échelle du DMV (#413, ADR-0022 §8). À l'échelle de
    // l'organisation, sans {group} — toujours enracinés au groupe racine DMV. Les mots sont
    // traduits ; « hours » → « heures ».
    'hours.committee-summary' => 'heures/statistiques-sommaire',
    'hours.committee-detailed' => 'heures/statistiques-detaillees',
    'hours.ranked' => 'heures/classement',
    'hours.zero-hours' => 'heures/zero-heure',
    'hours.zero-shift-hours' => 'heures/zero-heure-quart',
    'hours.zero-extra-hours' => 'heures/zero-heure-supplementaire',
    // Les exports CSV en pendant de chaque rapport (#414) — un suffixe « .csv » verbatim.
    'hours.committee-summary.csv' => 'heures/statistiques-sommaire.csv',
    'hours.committee-detailed.csv' => 'heures/statistiques-detaillees.csv',
    'hours.ranked.csv' => 'heures/classement.csv',
    'hours.zero-hours.csv' => 'heures/zero-heure.csv',
    'hours.zero-shift-hours.csv' => 'heures/zero-heure-quart.csv',
    'hours.zero-extra-hours.csv' => 'heures/zero-heure-supplementaire.csv',

    // Paramètres → Profil / Mot de passe (#229, PRD #228). « settings » →
    // « parametres » (sans accent dans l'URL, comme officer.settings).
    'settings' => 'parametres',
    'settings.profile' => 'parametres/profil',
    'settings.profile.photo' => 'parametres/profil/photo',
    'settings.password' => 'parametres/mot-de-passe',
    'settings.skills' => 'parametres/competences',
];
