<?php

// Les candidats et l'accueil (#928, ADR-0038 §8). Textes de l'interface, traduits comme toute
// l'interface (ADR-0004). Les libellés communs au dossier de membre viennent de records.php.
// Reflète lang/en/applicants.php.
return [
    'title' => 'Candidats',
    'add' => 'Ajouter un candidat',
    'new_title' => 'Nouveau candidat',
    'save' => 'Enregistrer',
    'saved' => 'Enregistré.',
    'none' => 'Aucun',

    'status' => [
        'all' => 'Tous',
        'new' => 'Nouveau',
        'assigned_for_interview' => 'Assigné pour entrevue',
        'accepted' => 'Accepté',
        'placed' => 'Placé',
        'yellow_card_completed' => 'Carte jaune remplie',
        'ready_to_go_active' => 'Prêt à devenir actif',
        'incomplete' => 'Incomplet',
    ],

    'filter' => 'Statut',
    'search' => 'Rechercher',
    'search_last_name' => 'Rechercher par nom de famille',
    'list_empty' => 'Aucun candidat avec ce statut.',
    'list_no_match' => 'Aucun candidat ne correspond à ce nom de famille.',
    'previous' => 'Précédent',
    'next' => 'Suivant',
    'page' => 'Page :current de :last',

    'column' => [
        'first_name' => 'Prénom',
        'last_name' => 'Nom de famille',
        'status' => 'Statut',
        'applied_on' => 'Date de candidature',
        'last_update' => 'Dernière mise à jour',
        'email' => 'Courriel',
        'mentor' => 'Mentor',
        'actions' => 'Actions',
    ],
    'action' => [
        'edit' => 'Modifier',
        'view' => 'Consulter',
    ],

    'section' => [
        'intake' => 'Accueil',
        'application' => 'Candidature',
    ],

    'field' => [
        'status' => 'Statut',
        'status_date' => 'Date du statut',
        'departure_reason' => 'Motif',
        'placement' => 'Affectation',
        'mentor' => 'Mentor',
        'applied_on' => 'Date de candidature',
        'entry_year' => "Année d'entrée",
        'override_duplicate_email' => 'Ajouter le candidat quand même',
    ],

    'entered_by' => 'Saisi par :name',
    'entered_by_applicant' => 'Saisi par le candidat',
    'unassigned' => 'Non assigné',
    'duplicate_email' => 'Un membre a déjà ce courriel.',

    'languages' => [
        'title' => 'Langues',
        'uses' => 'Utilise',
        'other' => 'Autre langue',
        'add_other' => 'Ajouter une autre langue',
    ],

    // Imprimer, Supprimer et Envoyer un courriel (#929).
    'outputs' => [
        'print' => 'Imprimer',
        'print_record' => 'Imprimer le dossier',
        'back' => 'Retour',
        'list_title' => 'Candidats : :status',
        'search' => 'Nom de famille commençant par « :term »',
        'count' => ':count candidat|:count candidats',
        'phone' => 'Téléphone',
        'entered_by' => 'Saisi par',
        'the_applicant' => 'Le candidat',
        'last_updated' => 'Dernière mise à jour',
        'by_on' => ':name le :date',
        'send_email' => 'Envoyer un courriel',
        'delete' => 'Supprimer',
        'delete_title' => 'Supprimer :name ?',
        'delete_body' => 'La candidature, ses langues et ses notes sont supprimées définitivement.',
        'cancel' => 'Annuler',
    ],
];
