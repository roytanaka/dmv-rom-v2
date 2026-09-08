<?php

// Chaînes du châssis du compositeur (spec #479, ADR-0024 §4, §6) — le peu que l'application
// ajoute autour d'un message qui est par ailleurs du contenu. Le corps d'un message part tel
// qu'il a été rédigé, dans une seule langue; seul le pied de page « personnes non jointes » de
// la copie de l'expéditeur est du châssis, rendu dans la langue de l'expéditeur. Reflète
// lang/en/broadcasts.php clé pour clé.
return [
    // La copie de l'expéditeur, le signal de fin (ADR-0024 §4). Son pied de page nomme les
    // destinataires non joints : :names est la liste, jusqu'à vingt, avec `and_more` ajouté
    // pour le surplus.
    'sender_copy' => [
        'undelivered' => 'Impossible de livrer à : :names',
        'and_more' => 'et :count de plus',
    ],

    // Le châssis de la feuille du compositeur (ADR-0024 §6) — le flux échelonné Qui → Message →
    // Envoyé qu'ouvre le menu Courriel. Seules ces chaînes d'encadrement sont traduites; l'objet
    // et le corps rédigés par l'expéditeur sont du contenu et partent tels qu'ils ont été écrits.
    'composer' => [
        'menu' => 'Courriel',
        'title' => 'Nouveau courriel',
        // L'étape Qui.
        'who_heading' => 'Qui · :count membre|Qui · :count membres',
        'hand_picked' => 'Choix manuel (:count)',
        'to' => 'À',
        'remove' => 'Retirer :name',
        'add_people' => 'Ajouter des personnes',
        'search_placeholder' => 'Rechercher par nom',
        'select_all' => 'Tout sélectionner',
        'empty_roster' => 'Personne sur cette liste ne correspond.',
        // L'étape Message.
        'from' => 'De :group · les réponses vous parviennent · vous en recevez une copie',
        'subject' => 'Objet',
        'body' => 'Message',
        'attachments' => 'Pièces jointes',
        'attach' => 'Ajouter une pièce jointe',
        'attachment_remove' => 'Retirer :name',
        // La barre d'outils de l'éditeur.
        'bold' => 'Gras',
        'italic' => 'Italique',
        'bullet_list' => 'Liste à puces',
        'ordered_list' => 'Liste numérotée',
        'link' => 'Insérer un lien',
        'link_prompt' => 'Adresse du lien',
        // Navigation et envoi.
        'back' => 'Retour',
        'next' => 'Suivant',
        'send' => 'Envoyer',
        'sending' => 'Envoi…',
        // L'étape Envoyé.
        'sent_heading' => 'En file pour :count membre.|En file pour :count membres.',
        'sent_note' => 'La livraison prend jusqu\'à une heure; vous recevez une copie une fois terminée.',
        'skipped' => 'n\'ont pu être joints (courriel désactivé)',
        'done' => 'Terminé',
    ],
];
