<?php

// Le journal des téléchargements de documents (#754, ADR-0030) — super-tier seulement.
// Chaînes de l'interface ; les noms de membres, de groupes et de fichiers sont du contenu.
// Reflète lang/en/document_downloads.php clé pour clé.
return [
    'title' => 'Journal des téléchargements',
    'subtitle' => 'Qui a ouvert chaque document, et quand.',
    'filter' => [
        'group' => 'Groupe',
        'all_groups' => 'Tous les groupes',
        'member' => 'Membre',
        'all_members' => 'Tous les membres',
        'from' => 'Du',
        'to' => 'Au',
        'search' => 'Nom du fichier',
        'apply' => 'Rechercher',
        'clear' => 'Effacer les filtres',
    ],
    'column' => [
        'when' => 'Date',
        'member' => 'Membre',
        'group' => 'Groupe',
        'file' => 'Nom du fichier',
    ],
    'deleted_member' => 'Membre supprimé',
    'deleted_group' => 'Groupe supprimé',
    'deleted_document' => 'Supprimé',
    'empty' => 'Aucun téléchargement pour l’instant.',
    'filtered_empty' => 'Aucun téléchargement ne correspond à ces filtres.',
    'page' => 'Page :current sur :last',
    'previous' => 'Précédent',
    'next' => 'Suivant',
];
