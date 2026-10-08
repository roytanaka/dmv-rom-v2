<?php

// Les catégories de documents de la bibliothèque d'un groupe (#724, spec #721, ADR-0030 §4).
// Interface seulement : les noms de catégories sont du contenu, affichés tels qu'écrits (ADR-0004).
return [
    'manage' => 'Catégories',
    'title' => 'Catégories',
    'empty' => 'Aucune catégorie pour l’instant.',
    'add' => 'Ajouter',
    'delete' => 'Supprimer',
    'save' => 'Enregistrer',
    'cancel' => 'Annuler',
    'rename_label' => 'Renommer :name',
    'delete_label' => 'Supprimer :name',
    'delete_title' => 'Supprimer :name?',
    'delete_body' => 'Ses dossiers et fichiers passent dans Autres.',

    'field' => [
        'name' => 'Nom',
        'new' => 'Nouvelle catégorie',
        'category' => 'Catégorie',
    ],

    'filter' => [
        'label' => 'Filtrer par catégorie',
        'all' => 'Toutes les catégories',
        'empty' => 'Rien dans cette catégorie.',
    ],

    'other' => 'Autres',
    'none' => 'Aucune catégorie',

    'count' => [
        'folders' => '{1} :count dossier|[2,*] :count dossiers',
        'files' => '{0} :count fichier|{1} :count fichier|[2,*] :count fichiers',
    ],

    'error' => [
        'name_required' => 'Saisissez un nom.',
        'name_taken' => 'Une catégorie porte déjà ce nom ici.',
        'not_here' => 'Choisissez une catégorie de ce dossier.',
    ],
];
