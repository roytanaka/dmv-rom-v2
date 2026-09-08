<?php

// La page d'état du courriel (#492, ADR-0024 §10) — réservée au super-palier. Chaîne de
// chrome. Reflète lang/en/mail_status.php clé pour clé.
return [
    'title' => 'État du courriel',
    'subtitle' => "La file d'attente du courriel et le cron qui la vide.",
    'never' => 'Jamais',
    'scheduler_last_ran' => 'Dernière exécution du planificateur',
    'mail_last_sent' => 'Dernier courriel envoyé',
    'pending' => 'En attente dans la file',
    'last_error' => 'Dernière erreur de connexion',
    'no_error' => 'Aucune',
    'dead' => [
        'title' => 'Le cron semble mort',
        'body' => "Le planificateur ne s'est pas manifesté depuis plus de dix minutes. Le courriel ne part pas.",
    ],
    'cannot_send' => [
        'title' => 'Envoi impossible',
        'body' => 'La dernière connexion au serveur de courriel a échoué après le dernier envoi réussi.',
    ],
];
