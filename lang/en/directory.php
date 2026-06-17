<?php

// Strings for the member directory (#169, PRD #167). The roster as a dense table;
// search / filter / sort land in follow-up slices. Chrome strings, translated like
// all chrome (ADR-0004). Mirrors lang/fr/directory.php.
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
];
