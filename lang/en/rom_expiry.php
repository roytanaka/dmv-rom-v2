<?php

/*
 * The ROM-expiry notice and its summary (#943, ADR-0038 §9). The body is a placeholder until the
 * Records Chair confirms the wording.
 */
return [
    'from' => 'DMV Records',
    'subject' => 'Update your ROM membership expiry date',
    'greeting' => 'Hello :name,',
    'body' => 'Your record shows that your ROM membership recently expired on :date. Please update your ROM membership expiry date in Account settings.',
    'summary' => [
        'subject' => 'ROM-expiry notices sent today',
        'heading' => 'ROM-expiry notices sent',
        'intro' => 'Today’s daily mail pass wrote the ROM-expiry notice for these :count Members.',
    ],
];
