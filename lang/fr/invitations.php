<?php

// Chaînes des Invitations (#902, ADR-0034). Éléments d'interface, traduits comme toute
// l'interface (ADR-0004); le nom, la description et les options d'une invitation sont du
// contenu affiché tel quel et ne passent jamais par ici. Reflète lang/en/invitations.php.
// Français canadien.
return [
    'title' => 'Invitations',
    'tabs' => [
        'mine' => 'Mes invitations',
        'hosting' => 'Organisation',
    ],
    'mine' => [
        'empty' => "Vous n'avez aucune invitation.",
    ],
    'hosting' => [
        'empty' => "Vous n'organisez aucune invitation.",
        'new' => 'Nouvelle invitation',
        'date' => 'Date',
        'name' => 'Nom',
        'host' => 'Hôte',
        'status' => 'État',
        'actions' => 'Actions',
    ],
    'state' => [
        'draft' => 'Brouillon',
        'open' => 'Ouverte',
    ],
    'form' => [
        'create_title' => 'Nouvelle invitation',
        'edit_title' => "Modifier l'invitation",
        'name' => 'Nom',
        'date' => 'Date',
        'start_time' => 'Heure de début',
        'end_time' => 'Heure de fin',
        'description' => 'Description',
        'options' => 'Options',
        'option_name' => "Nom de l'option",
        'add_option' => 'Ajouter une option',
        'remove_option' => "Retirer l'option",
    ],
    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'save' => 'Enregistrer',
    'cancel' => 'Annuler',
    'confirm_delete' => 'Supprimer cette invitation ?',
];
