<?php

// Chaînes de l'annuaire des membres (#169, PRD #167). Le bottin sous forme de
// tableau dense; la recherche / le filtre / le tri viendront plus tard. Chaînes
// d'interface, traduites comme toute l'interface (ADR-0004). Reflète
// lang/en/directory.php.
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
];
