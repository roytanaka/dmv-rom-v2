<?php

/*
 * The Renewal reminder chrome (#942, ADR-0038 §9). The body is the member admin's text from
 * Renewal settings, as authored; only the sender and subject are translated.
 */
return [
    'from' => 'Dossiers DMV',
    'subject' => [
        'active-1' => '1er rappel : renouvellement de l’adhésion au DMV pour :year/:next - Actif',
        'active-2' => '2e rappel : renouvellement de l’adhésion au DMV pour :year/:next - Actif',
        'sustaining-1' => '1er rappel : renouvellement de l’adhésion au DMV pour :year/:next - De soutien',
        'sustaining-2' => '2e rappel : renouvellement de l’adhésion au DMV pour :year/:next - De soutien',
    ],
];
