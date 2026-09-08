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

    // L'action de message direct (#491, ADR-0024 §6) : tout membre peut écrire à un autre depuis
    // son profil. Masquée sur son propre profil. Nommée par le prénom, comme le lit le bouton.
    'message' => 'Écrire à :name',

    // Contrôles d'administration des membres réservés aux Archives (#483, ADR-0024 §9).
    // Affichés uniquement lorsque le lecteur peut administrer les membres — le
    // MemberResource protège les données sous-jacentes.
    'administration' => 'Administration des membres',
    'no_email' => 'Aucun courriel',
    'no_email_help' => 'Faire taire tous les courriels destinés à ce membre — diffusions, messages directs, rappels et avis.',

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
