<?php

// Chaînes pour l'onglet Heures d'un groupe (#408, PRD #406, ADR-0022) — la saisie des
// heures supplémentaires et la liste des enregistrements du membre. Chrome traduit comme
// tout le reste (ADR-0004) ; le nom du groupe reste tel qu'il a été saisi et n'entre jamais
// dans cette table. Reflète lang/en/hours.php clé pour clé.
return [
    // Le formulaire de saisie — la boîte de dialogue additive sur deux mois.
    'entry' => [
        'heading' => 'Enregistrer des heures supplémentaires',
        'counted_note' => 'Les quarts planifiés et les réunions sont déjà comptés. N’ajoutez que les heures effectuées en dehors de ceux-ci.',
        'help' => 'Les heures saisies s’ajoutent au total déjà enregistré. Saisissez un nombre négatif pour corriger une erreur.',
        'on_file' => 'Enregistré : :hours',
        'last_updated' => 'Dernière mise à jour :date',
        'never_updated' => 'Rien d’enregistré pour l’instant',
        'hours_label' => 'Heures à ajouter',
        'add' => 'Ajouter les heures',
        'whole_hours' => 'Saisissez des heures entières — les minutes ne nous intéressent pas.',
    ],

    // Recalcul des heures planifiées d’un groupe à partir des inscriptions (#410, ADR-0022 §2)
    // — la commande d’officier sur un groupe qui planifie et son refus des exercices clos.
    'recalc' => [
        'button' => 'Recalculer les heures planifiées',
        // Renvoyé par la requête de formulaire lorsqu’un mois hors de l’exercice courant est demandé.
        'closed_year' => 'Les heures planifiées ne peuvent être recalculées que pour l’exercice courant.',
    ],

    // La liste des enregistrements du membre, sous le formulaire.
    'records' => [
        'heading' => 'Vos heures dans ce groupe',
        'empty' => 'Vous n’avez encore enregistré aucune heure dans ce groupe.',
        'column' => [
            'month' => 'Mois',
            'scheduled' => 'Planifiées',
            'extra' => 'Supplémentaires',
            'total' => 'Total',
            'updated' => 'Dernière mise à jour',
        ],
    ],

    // La destination Mes heures (#409, ADR-0022 §8) — les heures du membre dans chaque
    // groupe, par mois sur une année financière, avec un cumul annuel.
    'mine' => [
        'lead' => 'Chaque groupe où vous avez des heures pour cette année financière.',
        'pick_year' => 'Année financière',
        'fiscal_year' => 'Exercice :year',
        'column' => [
            'month' => 'Mois',
            'scheduled' => 'Planifiées',
            'extra' => 'Supplémentaires',
            'total' => 'Total',
        ],
        'ytd' => 'Cumul annuel',
        'empty' => [
            'heading' => 'Aucune heure enregistrée pour l’instant',
            'body' => 'Lorsque vous enregistrez des heures dans un groupe, elles apparaissent ici.',
        ],
    ],
];
