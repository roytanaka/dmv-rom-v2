<?php

// Chrome de la rétroaction des testeurs (#676, ADR-0029) — français canadien traduit à
// la machine (ADR-0004), à réviser. La boîte d'envoi, la page Rétroaction, et les
// étiquettes des énumérations de type et de statut. Le message d'un testeur est du
// contenu et n'est jamais traduit.
return [
    'title' => 'Rétroaction',
    'empty' => 'Aucune rétroaction pour le moment.',

    'dialog' => [
        'title' => 'Envoyer une rétroaction',
        'name' => 'Votre nom',
        'type' => 'Type',
        'type_placeholder' => 'Choisir un type',
        'message' => 'Message',
        'send' => 'Envoyer',
        'cancel' => 'Annuler',
        'see_all' => 'Voir toute la rétroaction',
        'sent' => 'Merci. Votre rétroaction a été envoyée.',
        'close' => 'Fermer',
    ],

    'column' => [
        'type' => 'Type',
        'status' => 'Statut',
        'name' => 'Nom',
        'message' => 'Message',
        'date' => 'Date',
    ],

    'type' => [
        'bug' => 'Bogue',
        'feature-request' => 'Demande de fonctionnalité',
        'translation' => 'Traduction',
        'missing-from-new-site' => 'Absent du nouveau site',
        'confusing' => 'Déroutant',
        'other' => 'Autre',
    ],

    'status' => [
        'new' => 'Nouveau',
        'confirmed' => 'Confirmé',
        'fixed' => 'Corrigé',
        'wont-fix' => 'Ne sera pas corrigé',
        'duplicate' => 'Doublon',
    ],
];
