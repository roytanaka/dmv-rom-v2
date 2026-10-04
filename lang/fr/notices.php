<?php

// Chaînes des avis automatiques (spec #479, ADR-0024 §8) — les courriels déclenchés par un
// événement, envoyés sans que personne ne les rédige. Du chrome, rendu dans la langue enregistrée
// de chaque destinataire (ADR-0004, ADR-0024 §3) ; les noms du membre et du groupe s'affichent
// tels qu'écrits et n'entrent jamais dans cette table. Reflète lang/en/notices.php clé pour clé.
return [
    // L'avis de changement de statut (#485) : le statut d'un membre au sein du DMV est passé à
    // Démissionnaire ou Décédé, annoncé à chaque président de chaque groupe où le membre n'avait
    // pas déjà quitté, afin que le président mette à jour sa propre liste. :member et :group
    // s'affichent tels qu'écrits ; :standing est le libellé member.standing.* rendu dans la langue
    // du destinataire ; :date est la date d'effet.
    'standing_change' => [
        'subject' => 'Changement de statut de membre : :member',
        'heading' => 'Un changement de statut de membre',
        'intro' => 'Le statut de :member au sein de :group est passé à :standing, en date du :date.',
        'footer' => 'Veuillez mettre à jour votre liste des membres au besoin. Ce message est fourni à titre informatif ; aucune réponse n’est attendue.',
        'view_roster' => 'Voir la liste des membres du groupe',
    ],
    // L'alerte de poste vacant (#487) : tous les trois jours du mois, un groupe indique à sa
    // liste de membres en règle quels quarts surveillés n'ont toujours personne. :group s'affiche
    // tel qu'écrit ; les dates, les heures et les types de quart s'affichent dans le corps, jamais
    // par cette table. « today » marque un quart daté du jour de l'envoi.
    'empty_desk' => [
        'subject' => 'Des quarts cherchent toujours quelqu’un — :group',
        'heading' => 'Ces quarts cherchent toujours quelqu’un',
        'intro' => 'Les quarts suivants du groupe :group n’ont toujours personne d’inscrit :',
        'today' => 'aujourd’hui',
        'footer' => 'Si vous pouvez en prendre un, veuillez vous inscrire afin que le poste soit couvert.',
        'view_schedules' => 'Voir l’horaire',
    ],
];
