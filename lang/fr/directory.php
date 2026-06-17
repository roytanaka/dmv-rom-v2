<?php

// Chaînes de l'annuaire des membres (#169, #170, #171, PRD #167). Le bottin sous
// forme de tableau dense avec recherche par nom, filtre par groupe, bascule de tri
// nom/prénom et rail de saut A–Z côté client. Chaînes d'interface, traduites comme
// toute l'interface (ADR-0004). Reflète lang/en/directory.php.
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
    // Bascule de tri par nom / prénom (#171). L'ordre d'affichage et le rail de saut
    // suivent tous deux le tri actif.
    'sort' => [
        'label' => 'Trier par',
        'last_name' => 'Nom',
        'first_name' => 'Prénom',
    ],
    // Rail de saut A–Z (#171). Saute à la première ligne sous une lettre; la lettre
    // utilisée suit le tri actif (nom ou prénom).
    'jump' => [
        'label' => 'Aller à une lettre',
        'letter' => 'Aller à :letter',
    ],
    // Ligne affichée lorsqu'aucun membre ne correspond à la recherche/au filtre (#170).
    'no_matches' => [
        'message' => 'Aucun membre ne correspond à votre recherche.',
        'clear' => 'Effacer les filtres',
    ],
];
