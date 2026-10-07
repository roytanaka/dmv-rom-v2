<?php

// Les dossiers de la bibliothèque de documents d'un groupe (#714, spec #290, ADR-0030 §3).
// Interface seulement : les noms de dossiers sont du contenu, affichés tels qu'écrits (ADR-0004).
return [
    'root' => 'Documents',
    'breadcrumb' => 'Dossiers',
    'empty' => 'Ce dossier est vide.',
    'open' => 'Ouvrir :name',
    'kind' => 'Dossier',

    'new' => 'Nouveau dossier',
    'actions' => 'Actions pour :name',
    'rename' => 'Renommer',
    'move' => 'Déplacer',
    'delete' => 'Supprimer',
    'save' => 'Enregistrer',
    'cancel' => 'Annuler',

    'create_title' => 'Nouveau dossier',
    'rename_title' => 'Renommer le dossier',
    'move_title' => 'Déplacer :name',
    'delete_title' => 'Supprimer :name?',
    'delete_body' => 'Seul un dossier vide peut être supprimé.',

    'field' => [
        'name' => 'Nom',
        'destination' => 'Déplacer vers',
    ],

    'top_level' => 'Premier niveau de la bibliothèque',

    'move_document' => 'Déplacer',
    'move_document_title' => 'Déplacer :name',

    'error' => [
        'name_required' => 'Saisissez un nom.',
        'name_taken' => 'Un dossier porte déjà ce nom ici.',
        'too_deep' => 'Les dossiers peuvent compter au plus :max niveaux.',
        'into_itself' => 'Un dossier ne peut pas être déplacé dans lui-même.',
        'not_empty' => 'Ce dossier contient encore des dossiers ou des documents. Déplacez-les ou supprimez-les d’abord.',
        'not_found' => 'Ce dossier n’existe plus.',
    ],
];
