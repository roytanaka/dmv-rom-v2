<?php

// Chrome du centre d'aide (#517, ADR-0025) — français canadien traduit à la machine
// (ADR-0004), à réviser. Le titre et l'intro de l'index, la racine du fil
// d'Ariane, et une étiquette par section d'aide. La prose des articles n'est pas
// ici : elle vit dans resources/help/{locale}/<slug>.md.
return [
    'title' => 'Aide',
    'intro' => 'Des guides étape par étape pour les tâches que vous faites ici.',

    // Une étiquette par cas de l'énumération HelpSection, indexée par le slug.
    'section' => [
        'getting-started' => 'Pour commencer',
    ],
];
