<?php

// Paramètres du DMV (#836, ADR-0036) — super-tier seulement. Chaînes de l'interface ; les noms
// de groupes sont du contenu. Reflète lang/en/dmv_settings.php clé pour clé.
return [
    'title' => 'Paramètres du DMV',
    'subtitle' => 'Les groupes et les intendances de l’organisation.',
    'section' => [
        'groups' => 'Groupes',
        'stewardships' => 'Intendances',
    ],
    'tree' => [
        'column' => [
            'name' => 'Nom',
            'kind' => 'Type',
            'status' => 'État',
        ],
        'no_settings' => 'Aucun paramètre ici',
        'archived' => 'Archivé',
        'create' => 'Créer un groupe',
    ],
    'kind' => [
        'standing_committee' => 'Comité permanent',
        'program' => 'Programme',
        'working_group' => 'Groupe de travail',
        'project' => 'Projet',
        'cohort' => 'Cohorte',
        'container' => 'Conteneur',
    ],
    // Créer un groupe (#842) : la boîte de dialogue de l'arborescence.
    'create' => [
        'title' => 'Créer un groupe',
        'submit' => 'Créer',
        'cancel' => 'Annuler',
    ],
    'choose_parent' => 'Choisir un parent',
    'choose_kind' => 'Choisir un type',
    'field' => [
        'name' => 'Nom',
        'slug' => 'Slug',
        'parent' => 'Parent',
        'kind' => 'Type',
    ],
    'error' => [
        'slug_taken' => 'Un autre groupe utilise ce slug.',
        'slug_format' => 'Utilisez des lettres minuscules, des chiffres et des traits d’union.',
        'inactive_group' => 'Choisissez un groupe actif.',
    ],
    // Le formulaire de structure (#843) et les cartes d'intendances (#846) complètent à partir d'ici.
    'structure_soon' => 'Le formulaire de structure arrive.',
    'stewardships_soon' => 'Les intendances arrivent.',
];
