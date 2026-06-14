<?php

// Chrome navigation strings (Canadian French, fr-CA). Machine-translated baseline
// per ADR-0004; refine wording as needed. Mirrors lang/en/nav.php key-for-key.
//
// Tracer-bullet scope (#105): rail section headings only.
return [
    'rail' => [
        'my_groups' => 'Mes groupes',
        'all_groups' => 'Tous les groupes',
        'officer' => 'Outils des responsables',
    ],

    // Nom accessible du chevron qui ouvre/ferme les sous-groupes d'un groupe (#91).
    // Distinct du lien de navigation voisin. `:group` est le nom du groupe tel
    // qu'il est rédigé (contenu) ; il n'est jamais traduit.
    'toggle' => 'Basculer les sous-groupes de :group',
];
