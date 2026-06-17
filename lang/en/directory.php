<?php

// Strings for the member directory (#169, #170, PRD #167). The roster as a dense
// table with client-side name search + Group filter; sort lands in a follow-up
// slice. Chrome strings, translated like all chrome (ADR-0004). Mirrors
// lang/fr/directory.php.
return [
    'title' => 'Directory',
    'count' => '{1} :count member|[2,*] :count members',
    'column' => [
        'name' => 'Name',
        'groups' => 'Groups',
        'standing' => 'Standing',
    ],
    // Shown in the Groups cell when a Member holds no memberships.
    'no_groups' => '—',
    // Live name search over the loaded roster (#170).
    'search' => [
        'label' => 'Search by name',
        'placeholder' => 'Search by name',
    ],
    // Single-select Group filter (#170). 'all' is the unfiltered default option.
    'filter' => [
        'group' => [
            'label' => 'Filter by Group',
            'all' => 'All Groups',
        ],
    ],
    // Empty-result row when search/filter matches nobody (#170).
    'no_matches' => [
        'message' => 'No members match your search.',
        'clear' => 'Clear filters',
    ],
];
