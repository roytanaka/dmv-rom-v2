<?php

// La bibliothèque de documents de l'onglet Documents d'un groupe (#712, spec #290, ADR-0030).
// Interface seulement : les titres, descriptions et noms de fichiers sont du contenu,
// affichés tels qu'écrits (ADR-0004).
return [
    'empty' => 'Aucun document pour l’instant.',

    'column' => [
        'name' => 'Nom',
        'type' => 'Type',
        'size' => 'Taille',
        'updated' => 'Mis à jour',
        'uploader' => 'Téléversé par',
        'actions' => 'Actions',
    ],

    'download' => 'Télécharger :name',

    'upload' => [
        'button' => 'Téléverser des fichiers',
        'drop' => 'Déposez des fichiers ici ou',
        'choose' => 'Choisir des fichiers',
        'uploading' => 'Téléversement',
        'done' => 'Téléversé',
        'failed' => 'Non ajouté',
        'error_type' => ':name n’a pas été ajouté. Ce type de fichier n’est pas permis.',
        'error_size' => ':name n’a pas été ajouté. Il dépasse 1,5 Go.',
    ],

    // Modifier, remplacer et supprimer un document (#713).
    'manage' => [
        'menu' => 'Actions pour :name',
        'edit' => 'Modifier',
        'replace' => 'Remplacer le fichier',
        'delete' => 'Supprimer',
        'edit_title' => 'Modifier le document',
        'replace_title' => 'Remplacer le fichier de :name',
        'replace_save' => 'Remplacer',
        'delete_title' => 'Supprimer ce document?',
        'delete_body' => ':name et son fichier seront supprimés. Cette action est définitive.',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'field' => [
            'title' => 'Titre',
            'description' => 'Description',
            'file' => 'Nouveau fichier',
        ],
    ],
];
