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
    // La demande et la confirmation de visite de groupe (#799, ADR-0032 §9). La visite, le client,
    // le responsable et les commentaires sont du contenu et s'affichent tels qu'écrits ; :group
    // aussi. `date_format` est le format de date PHP du jour de la visite dans chaque langue.
    'booking' => [
        'date_format' => 'l j F Y',
        'client' => 'Client',
        'visitors' => 'Visiteurs attendus',
        'leader' => 'Responsable du groupe',
    ],
    'booking_request' => [
        'subject' => 'Demande de visite de groupe : :tour, :date',
        'heading' => 'Une visite de groupe cherche des guides',
        'intro' => ':group a une visite de groupe qui manque encore de guides. Si vous pouvez la donner, inscrivez-vous.',
        'seats_needed' => '{0} Plus aucun guide requis.|{1} Il manque encore :count guide.|[2,*] Il manque encore :count guides.',
        'view_schedule' => 'S’inscrire à l’horaire',
    ],
    'booking_confirmation' => [
        'subject' => 'Visite de groupe confirmée : :tour, :date',
        'heading' => 'Visite de groupe confirmée',
        'intro' => 'Cette visite de groupe de :group est confirmée.',
        'docents' => 'Guides de cette visite',
        'view_schedule' => 'Voir l’horaire',
    ],
    // L'avis de limite atteinte (#906, ADR-0034 §9). :name est le nom de l'invitation, tel qu'écrit.
    'invitation_full' => [
        'subject' => "Limite d'invités atteinte : :name",
        'heading' => 'Votre invitation est complète',
        'intro' => ":name a atteint sa limite d'invités.",
        'view_replies' => 'Voir les réponses',
    ],
    // L'avis au mentor (#920, ADR-0038 §4) : le bureau des dossiers a fait passer le mentoré
    // du statut provisoire ou préactif au statut actif. :member tel que saisi.
    'mentee_activated' => [
        'from' => 'Dossiers DMV',
        'subject' => 'Membre maintenant actif : :member',
        'heading' => 'Votre mentoré est maintenant actif',
        'intro' => 'Le bureau des dossiers a fait passer :member au statut actif.',
        'footer' => 'Ce message est fourni à titre informatif ; aucune réponse n’est attendue.',
    ],
    // Les résultats d'une requête enregistrée (#924, ADR-0038 §7).
    'saved_query_results' => [
        'from' => 'Dossiers DMV',
        'subject' => 'Résultats de la requête DMV : :query',
        'heading' => 'Résultats d’une requête enregistrée',
        'intro' => ':sender vous a envoyé les résultats de la requête enregistrée « :query ».',
        'view' => 'Ouvrir les résultats',
        'footer' => 'Le lien s’ouvre après connexion, pour les membres du bureau des dossiers.',
    ],
    // Les avis d'accueil (#931, ADR-0038 §8) : le statut d'un candidat a changé. :applicant,
    // :mentor et :group tels que saisis.
    'applicant' => [
        'from' => 'Adhésion DMV',
        'mentor' => 'Mentor : :mentor',
        'placement' => 'Affectation : :group',
        'reason' => 'Raison : :reason',
        'view_member' => 'Ouvrir le dossier du membre',
        'view_applicant' => 'Ouvrir le dossier du candidat',
        'footer' => 'Les liens s’ouvrent après connexion, pour les personnes qui peuvent voir les candidats.',
    ],
    'applicant_accepted' => [
        'subject' => 'Nouveau membre provisoire DMV : :applicant',
        'heading' => 'Nouveau membre provisoire',
        'intro' => ':applicant a été accepté et est maintenant membre provisoire.',
    ],
    'applicant_placed' => [
        'subject' => 'Affectation d’un candidat : :applicant',
        'heading' => 'Candidat affecté',
        'intro' => ':applicant a été affecté à :group.',
    ],
    'applicant_yellow_card' => [
        'subject' => 'Carte jaune complétée : :applicant',
        'heading' => 'Carte jaune complétée',
        'intro' => ':applicant a complété la carte jaune et est prêt à passer au statut actif.',
    ],
    'applicant_incomplete' => [
        'subject' => 'Candidat retiré : :applicant',
        'heading' => 'Candidat marqué incomplet',
        'intro' => ':applicant a été marqué incomplet. Son dossier de membre devrait être retiré.',
    ],
    // Le résumé des rappels de renouvellement (#942, ADR-0038 §9).
    'renewal_reminder_summary' => [
        'from' => 'Dossiers DMV',
        'subject' => 'Rappels de renouvellement envoyés aujourd’hui',
        'heading' => 'Rappels de renouvellement envoyés',
        'intro' => 'Le passage quotidien du courriel a écrit ces rappels pour le renouvellement :year.',
        'series' => [
            'active-1' => '1er rappel, membres actifs',
            'active-2' => '2e rappel, membres actifs',
            'sustaining-1' => '1er rappel, membres de soutien',
            'sustaining-2' => '2e rappel, membres de soutien',
        ],
    ],
];
