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
        'comments_and_attachments' => 'Commentaires et pièces jointes',
        'comments_count' => ':count commentaire|:count commentaires',
        'attachments_count' => ':count pièce jointe|:count pièces jointes',
    ],

    'filter' => [
        'type' => 'Type',
        'status' => 'Statut',
        'all_types' => 'Tous les types',
        'all_statuses' => 'Tous les statuts',
        'open' => 'Ouverts',
        'closed' => 'Fermés',
        'clear' => 'Effacer les filtres',
        'empty' => 'Aucune rétroaction ne correspond à ces filtres.',
    ],

    'manage' => [
        'title' => 'Gérer',
        'status' => 'Statut',
        'delete' => 'Supprimer la rétroaction',
        'delete_title' => 'Supprimer la rétroaction nº :id?',
        'delete_body' => 'Cette action supprime la rétroaction, ses captures d’écran et ses commentaires. Vous ne pouvez pas l’annuler.',
        'cancel' => 'Annuler',
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
        'error_empty' => 'Ajoutez un commentaire, une image ou les deux.',
        'images' => 'Images',
        'images_list' => 'Images à ajouter',
        'delete' => 'Supprimer',
        'delete_label' => 'Supprimer le commentaire de :name',
        'delete_title' => 'Supprimer ce commentaire?',
        'delete_body' => 'Le commentaire de :name disparaît pour tout le monde. Vous ne pouvez pas l’annuler.',
        'delete_confirm' => 'Supprimer le commentaire',
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
