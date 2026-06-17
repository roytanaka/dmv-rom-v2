<?php

// Chaînes de la page de fiche membre (#154). L'interface de l'annuaire/profil
// viendra plus tard; ceci est la page mince au-dessus du MemberResource centralisé.
// Chaînes d'interface, traduites comme toute l'interface (ADR-0004). Reflète
// lang/en/member.php.
return [
    'title' => 'Membre',
    'contact' => 'Coordonnées',
    'contact_restricted' => 'Les coordonnées ne vous sont pas accessibles.',
    'email' => 'Courriel',
    'phone' => 'Téléphone',
    'groups' => 'Groupes et rôles',
    'no_groups' => 'Aucune adhésion à un groupe.',

    // Libellés de statut au sein du DMV (la Catégorie du membre), indexés par la
    // valeur d'énumération exposée par MemberResource sous `standing`. Alimente le
    // badge de statut de l'annuaire/profil. Reflète lang/en/member.php.
    'standing' => [
        'active' => 'Actif',
        'pre_active' => 'Pré-actif',
        'provisional' => 'Provisoire',
        'sustaining' => 'De soutien',
        'honourary' => 'Honoraire',
        'loa' => 'En congé',
        'withdrawn' => 'Retiré',
        'resigned' => 'Démissionnaire',
        'deceased' => 'Décédé',
    ],
];
