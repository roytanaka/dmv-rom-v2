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
];
