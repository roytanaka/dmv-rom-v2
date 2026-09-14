<?php

// Help centre chrome (#517, ADR-0025). The index title and intro, the breadcrumb
// root, and one label per Help section. Chrome, so it ships in both locales and is
// the single source of truth for both PHP __() and Vue trans(). Article prose is
// not here — it lives in resources/help/{locale}/<slug>.md.
return [
    'title' => 'Help',
    'intro' => 'Step-by-step guides for the tasks you do here.',

    // One label per HelpSection enum case, keyed by the section slug.
    'section' => [
        'getting-started' => 'Getting started',
    ],
];
