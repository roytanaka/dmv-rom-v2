<?php

// Strings for the member directory (#169, #170, #171, PRD #167). The roster as a
// dense table with client-side name search, Group filter, a surname/given-name sort
// toggle, and an A–Z jump rail. Chrome strings, translated like all chrome
// (ADR-0004). Mirrors lang/fr/directory.php.
return [
    'title' => 'Directory',
    'count' => '{1} :count member|[2,*] :count members',
    'column' => [
        'name' => 'Name',
        'groups' => 'Groups',
        'other_groups' => 'Other Groups',
        'role' => 'Role',
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
        // Who's who list (#699): which Members the Directory lists. Old-site names and order.
        'list' => [
            'label' => 'Filter by list',
            'all_members' => 'All Members',
            'all_members_loa' => 'All Members +LOA',
            'active_provisional' => 'Active and Provisional Members',
            'active' => 'Active',
            'provisional' => 'Provisional',
            'pre_active' => 'PreActive',
            'sustaining' => 'Sustaining',
            'honourary' => 'Honourary',
            'loa' => 'Leave of Absence',
            'board_of_directors' => 'DMV Board of Directors',
            'committee_chairs' => 'Committee Chairs',
            'all_chairs' => 'Committee and DMV SubCommittee Chairs',
        ],
    ],
    // Surname / given-name sort toggle (#171). Display order and the jump rail both
    // follow the active sort.
    'sort' => [
        'label' => 'Sort by',
        'last_name' => 'Last name',
        'first_name' => 'First name',
    ],
    // A–Z jump rail (#171). Leaps to the first row under a letter; the letter it keys
    // on follows the active sort (surname vs given name).
    'jump' => [
        'label' => 'Jump to a letter',
        'letter' => 'Jump to :letter',
    ],
    // Empty-result row when search/filter matches nobody (#170).
    'no_matches' => [
        'message' => 'No members match your search.',
        'clear' => 'Clear filters',
    ],
];
