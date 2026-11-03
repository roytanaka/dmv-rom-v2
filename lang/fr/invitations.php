<?php

// Chaînes des Invitations (#902, ADR-0034). Éléments d'interface, traduits comme toute
// l'interface (ADR-0004); le nom, la description et les options d'une invitation sont du
// contenu affiché tel quel et ne passent jamais par ici. Reflète lang/en/invitations.php.
// Français canadien.
return [
    'title' => 'Invitations',
    'tabs' => [
        'mine' => 'Mes invitations',
        'hosting' => 'Organisation',
    ],
    'mine' => [
        'empty' => "Vous n'avez aucune invitation.",
    ],
    'show' => [
        'hosted_by' => 'Organisée par :name',
        'your_reply' => 'Votre réponse',
        'closed' => 'Cette invitation est fermée.',
        'full' => 'Cette invitation est complète.',
    ],
    'reply' => [
        'no_reply' => 'Aucune réponse',
        'accepted' => 'Acceptée',
        'declined' => 'Refusée',
        'accept' => 'Accepter',
        'decline' => 'Refuser',
    ],
    'hosting' => [
        'empty' => "Vous n'organisez aucune invitation.",
        'new' => 'Nouvelle invitation',
        'date' => 'Date',
        'name' => 'Nom',
        'host' => 'Hôte',
        'status' => 'État',
        'actions' => 'Actions',
    ],
    'state' => [
        'draft' => 'Brouillon',
        'open' => 'Ouverte',
        'closed' => 'Fermée',
    ],
    'form' => [
        'create_title' => 'Nouvelle invitation',
        'edit_title' => "Modifier l'invitation",
        'name' => 'Nom',
        'date' => 'Date',
        'start_time' => 'Heure de début',
        'end_time' => 'Heure de fin',
        'description' => 'Description',
        'guest_limit' => "Limite d'invités",
        'options' => 'Options',
        'option_name' => "Nom de l'option",
        'add_option' => 'Ajouter une option',
        'remove_option' => "Retirer l'option",
    ],
    'guests' => [
        'title' => 'Invités',
        'title_for' => 'Invités : :name',
        'count' => ':count invité|:count invités',
        'empty' => 'Aucun invité pour le moment.',
        'member' => 'Ajouter un membre',
        'audience' => 'Ajouter un groupe',
        'add' => 'Ajouter',
        'remove' => 'Retirer',
        'confirm_remove' => 'Retirer cet invité ? Sa réponse sera aussi retirée.',
        'audience_refused' => 'Vous ne pouvez pas inviter ce public.',
    ],
    'send' => [
        'action' => 'Envoyer',
        're_send' => 'Renvoyer',
        'unreachable' => 'Aucun invité ne peut être joint par courriel.',
        'every_guest' => 'Le premier envoi va à tous les invités.',
    ],
    // Le texte prérempli appartient à l'hôte et reste dans sa langue. Le lien fait partie
    // de l'interface et suit la langue de chaque invité.
    'mail' => [
        'subject' => 'Invitation : :name',
        'when' => 'Vous êtes invité à :name le :date, de :start à :end.',
        'link' => "Voir l'invitation et répondre",
    ],
    'reminder_email' => [
        'subject' => 'Rappel : :name le :date',
        'heading' => 'Votre invitation approche',
        'intro' => 'Bonjour :member — ceci est un rappel d’une invitation que vous avez acceptée :',
        'your_options' => 'Vos options : :options',
        'view_invitation' => 'Voir l’invitation',
    ],
    'replies' => [
        'title' => 'Réponses',
        'title_for' => 'Réponses : :name',
        'print' => 'Imprimer',
        'email_accepted' => 'Écrire aux invités ayant accepté',
        'add' => 'Répondre pour un membre',
        'set' => 'Saisir la réponse',
        'guest' => 'Invité',
        'member' => 'Membre',
        'reply' => 'Réponse',
    ],
    'move_to_draft' => 'Remettre en brouillon',
    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'save' => 'Enregistrer',
    'cancel' => 'Annuler',
    'confirm_delete' => 'Supprimer cette invitation ?',
];
