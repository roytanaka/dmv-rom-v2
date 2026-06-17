<?php

// Chaînes de l'annuaire des membres (#169, #170, PRD #167). Le bottin sous forme
// de tableau dense avec recherche par nom et filtre par groupe côté client; le tri
// viendra plus tard. Chaînes d'interface, traduites comme toute l'interface
// (ADR-0004). Reflète lang/en/directory.php.
return [
    'title' => 'Annuaire',
    'count' => '{1} :count membre|[2,*] :count membres',
    'column' => [
        'name' => 'Nom',
        'groups' => 'Groupes',
        'standing' => 'Statut',
    ],
    // Affiché dans la cellule Groupes lorsqu'un membre n'a aucune adhésion.
    'no_groups' => '—',
    // Recherche par nom en direct sur le bottin chargé (#170).
    'search' => [
        'label' => 'Rechercher par nom',
        'placeholder' => 'Rechercher par nom',
    ],
    // Filtre par groupe à sélection unique (#170). « all » est l'option par défaut.
    'filter' => [
        'group' => [
            'label' => 'Filtrer par groupe',
            'all' => 'Tous les groupes',
        ],
    ],
    // Ligne affichée lorsqu'aucun membre ne correspond à la recherche/au filtre (#170).
    'no_matches' => [
        'message' => 'Aucun membre ne correspond à votre recherche.',
        'clear' => 'Effacer les filtres',
    ],
];
