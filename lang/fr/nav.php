<?php

// Chrome navigation strings (Canadian French, fr-CA). Machine-translated baseline
// per ADR-0004; refine wording as needed. Mirrors lang/en/nav.php key-for-key.
//
// Rail headings, the Zone A personal tab set, and the Zone C officer cluster (#106).
// Group NAMES are content (ADR-0004) and are NOT keyed here.
return [
    'rail' => [
        'my_groups' => 'Mes groupes',
        'other_groups' => 'Autres groupes',
        'officer' => 'Outils des responsables',

        // Les quatre pairs conteneurs à l'échelle de l'organisation d'Autres groupes
        // (ADR-0020 §C) : de l'ossature structurelle, pas des groupes de contenu, donc
        // leurs libellés sont du chrome (clés traduites), distincts des noms de groupe
        // rendus verbatim en dessous. Indexés par le slug du conteneur (tirets → tirets bas).
        'peers' => [
            'governance_operations' => 'Gouvernance et opérations',
            'programs' => 'Programmes',
            'special_projects' => 'Projets spéciaux',
            'friends' => 'Amis',
        ],
    ],

    // Zone A — personnel/global (les onglets du tableau de bord).
    'personal' => [
        'calendar' => 'Mon calendrier',
        'hours' => 'Mes heures',
        'directory' => 'Répertoire',
        'documents' => 'Documents',
        'news' => 'Nouvelles',
        'profile' => 'Mon profil',
    ],

    // Utilitaire de la barre supérieure — la destination Aide (#194).
    'help' => 'Aide',

    // Zone C — responsables/administration (rail, épinglé en bas).
    'officer' => [
        'members' => 'Membres',
        'communications' => 'Communications',
        'reports' => 'Rapports',
        'flash_messages' => 'Messages éclair',
        'dmv_settings' => 'Paramètres du DMV',
    ],

    // Nom accessible du chevron qui ouvre/ferme les sous-groupes d'un groupe (#91).
    // Distinct du lien de navigation voisin. `:group` est le nom du groupe tel
    // qu'il est rédigé (contenu) ; il n'est jamais traduit.
    'toggle' => 'Basculer les sous-groupes de :group',
];
