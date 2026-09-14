<?php

// Chrome du centre d'aide (#517, ADR-0025) — français canadien traduit à la machine
// (ADR-0004), à réviser. Le titre et l'intro de l'index, la racine du fil
// d'Ariane, et une étiquette par section d'aide. La prose des articles n'est pas
// ici : elle vit dans resources/help/{locale}/<slug>.md.
return [
    'title' => 'Aide',
    'intro' => 'Des guides étape par étape pour les tâches que vous faites ici.',

    // Une étiquette par cas de l'énumération HelpSection, indexée par le slug.
    'section' => [
        'getting-started' => 'Pour commencer',
        'dashboard' => 'Tableau de bord',
        'my-hours' => 'Mes heures',
        'directory' => 'Répertoire',
        'news' => 'Nouvelles',
        'groups' => 'Groupes',
        'scheduling' => 'Horaire',
        'hours-and-reports' => 'Heures et rapports',
        'settings' => 'Paramètres',
    ],

    // Badge du rôle requis (#518, ADR-0025 §6). Le badge affiche « Requis :
    // Responsable horaire ou Président·e » : le préfixe, une étiquette par rôle
    // requis, jointes par « ou ». Un article sans rôle requis n'affiche aucun badge.
    // Une étiquette par jeton de HelpManifest::requirableRoles().
    'required_role' => [
        'prefix' => 'Requis :',
        'or' => 'ou',
        'role' => [
            'chair' => 'Président·e',
            'secretary' => 'Secrétaire',
            'treasurer' => 'Trésorier·ère',
            'statistician' => 'Statisticien·ne',
            'scheduler' => 'Responsable horaire',
            'vetting' => 'Vérification',
            'librarian' => 'Bibliothécaire',
            'content_maintainer' => 'Responsable du contenu',
            'news_editor' => 'Responsable des nouvelles',
            'super_tier' => 'Super-niveau',
            'support_operator' => 'Opérateur de soutien',
        ],
    ],
];
