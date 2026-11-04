<?php

/*
 * The ROM-expiry notice and its summary (#943, ADR-0038 §9). The body is a placeholder until the
 * Records Chair confirms the wording.
 */
return [
    'from' => 'Dossiers DMV',
    'subject' => 'Mettez à jour la date d’expiration de votre adhésion au ROM',
    'greeting' => 'Bonjour :name,',
    'body' => 'Votre dossier indique que votre adhésion au ROM a expiré le :date. Veuillez mettre à jour la date d’expiration de votre adhésion au ROM dans les paramètres du compte.',
    'summary' => [
        'subject' => 'Avis d’expiration ROM envoyés aujourd’hui',
        'heading' => 'Avis d’expiration ROM envoyés',
        'intro' => 'Le passage quotidien du courriel a écrit l’avis d’expiration ROM pour ces :count membres.',
    ],
];
