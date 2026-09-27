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

    'item' => [
        'title' => 'Rétroaction nº :id',
        'sent_by' => 'Envoyée par :name',
        'context' => 'Contexte',
        'page' => 'Page',
        'route' => 'Nom de la page',
        'locale' => 'Langue',
        'browser' => 'Navigateur',
        'viewport' => 'Taille de l’écran',
        'member' => 'Connecté en tant que',
        'impersonator' => 'Identité empruntée par',
        'version' => 'Version de l’application',
        'none' => 'Aucun',
    ],

    'screenshots' => [
        'title' => 'Captures d’écran',
        'drop' => 'Glissez ou collez des images ici, ou',
        'choose' => 'Choisir des fichiers',
        'list' => 'Captures d’écran à envoyer',
        'remove' => 'Retirer :name',
        'pasted' => 'Image collée',
        'size_kb' => ':size Ko',
        'size_mb' => ':size Mo',
        'error_type' => ':name n’a pas été ajouté. Utilisez une image PNG, JPEG, WebP ou GIF.',
        'error_size' => ':name n’a pas été ajouté. Il dépasse 5 Mo.',
        'error_limit' => ':name n’a pas été ajouté. Vous pouvez ajouter jusqu’à :max captures d’écran.',
        'error_count' => 'Vous pouvez ajouter jusqu’à :max captures d’écran.',
    ],

    'comments' => [
        'title' => 'Commentaires',
        'empty' => 'Aucun commentaire pour le moment.',
        'name' => 'Votre nom',
        'body' => 'Commentaire',
        'add' => 'Ajouter le commentaire',
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
