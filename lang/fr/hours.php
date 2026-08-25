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

    // La barre d'impression et d'export partagée par chaque rapport (#414, ADR-0022 §8).
    'export' => [
        'print' => 'Imprimer',
        'csv' => 'Exporter en CSV',
    ],

    // Le rapport annuel du groupe (#411, ADR-0022 §5) — une matrice Membre × douze mois avec
    // les heures propres du groupe et celles de son sous-arbre côte à côte, pour un président
    // ou un statisticien.
    'report' => [
        'title' => 'Rapport des heures',
        // Le lien réservé aux officiers depuis l'onglet Heures du groupe vers ce rapport.
        'view' => 'Voir le rapport des heures du groupe',
        'lead' => 'Les heures de chaque membre pour cette année financière, et le total du groupe.',
        'pick_year' => 'Année financière',
        'fiscal_year' => 'Exercice :year',
        'column' => [
            'member' => 'Membre',
            'ytd' => 'Cumul annuel',
        ],
        'own' => 'Ce groupe',
        'subtree' => 'Sous-groupes compris',
        'empty' => 'Aucune heure enregistrée pour ce groupe durant cette année financière.',
    ],

    // Les trois surfaces d'officier après la matrice annuelle (#412, ADR-0022 §8) : un
    // sélecteur de mois, un historique par membre, et les deux sommaires Membre × douze mois.
    'detail' => [
        // La navigation reliant les rapports d'officier entre eux.
        'nav' => [
            'report' => 'Année financière',
            'month' => 'Par mois',
            'member' => 'Historique du membre',
            'extra' => 'Heures supplémentaires',
            'meetings' => 'Heures de réunion',
        ],
        // Le sélecteur de mois — les entrées d'un mois pour tout le groupe, par membre.
        'month' => [
            'title' => 'Heures par mois',
            'lead' => 'Les entrées d\'un mois pour tout le groupe, membre par membre.',
            'pick' => 'Mois',
            'column' => [
                'member' => 'Membre',
                'scheduled' => 'Planifiées',
                'extra' => 'Supplémentaires',
                'total' => 'Total',
            ],
            'empty' => 'Aucune heure enregistrée pour ce groupe durant ce mois.',
        ],
        // Historique du membre — les heures d'une personne dans ce groupe au fil du temps.
        'member' => [
            'title' => 'Historique du membre',
            'lead' => 'Les heures d\'un membre dans ce groupe au fil du temps.',
            'pick' => 'Membre',
            'none' => 'Choisissez un membre pour voir son historique.',
            'column' => [
                'month' => 'Mois',
                'scheduled' => 'Planifiées',
                'extra' => 'Supplémentaires',
                'total' => 'Total',
            ],
            'empty' => 'Ce membre n\'a aucune heure enregistrée dans ce groupe.',
        ],
        // Heures supplémentaires des membres — ce que les gens nous ont déclaré avoir fait.
        'extra' => [
            'title' => 'Heures supplémentaires des membres',
            'lead' => 'Les heures supplémentaires enregistrées par chaque membre cette année financière — le travail hors quart.',
        ],
        // Heures de réunion des membres — ce à quoi les gens ont assisté.
        'meetings' => [
            'title' => 'Heures de réunion des membres',
            'lead' => 'Les heures de réunion auxquelles chaque membre a assisté cette année financière.',
        ],
        // Partagé par les deux sommaires.
        'summary' => [
            'pick_year' => 'Année financière',
            'fiscal_year' => 'Exercice :year',
            'column' => [
                'member' => 'Membre',
                'ytd' => 'Cumul annuel',
            ],
            'empty' => 'Aucune heure enregistrée pour ce groupe durant cette année financière.',
        ],
    ],

    // Les six rapports annuels à l'échelle du DMV (#413, ADR-0022 §8) — les statistiques
    // annuelles que le ROM demande au DMV. À l'échelle de l'organisation, réservés aux officiers
    // du DMV, aux Archives (Records), ou à l'accès tout-DMV.
    'dmv' => [
        // La navigation qui relie les six rapports entre eux.
        'nav_label' => 'Rapports d\'heures du DMV',
        'nav' => [
            'summary' => 'Sommaire',
            'detailed' => 'Détaillé',
            'ranked' => 'Classement des heures',
            'zero_hours' => 'Zéro heure',
            'zero_shift' => 'Zéro heure de quart',
            'zero_extra' => 'Zéro heure supplémentaire',
        ],
        'pick_year' => 'Année financière',
        'fiscal_year' => 'Exercice :year',

        // Statistiques sommaires des comités — heures planifiées par comité, puis rangées globales.
        'summary' => [
            'title' => 'Statistiques sommaires des comités',
            'lead' => 'Les heures planifiées par comité durant l\'année financière, avec les totaux globaux dessous.',
            'scheduled' => 'Heures planifiées',
            'meetings' => 'Heures de réunion',
            'extra' => 'Heures supplémentaires',
            'total' => 'Total général',
            'column' => [
                'committee' => 'Comité',
                'ytd' => 'Cumul annuel',
            ],
            'empty' => 'Aucun comité ne gère d\'horaire pour l\'instant.',
        ],

        // Statistiques détaillées des comités — chaque comité ventilé en quarts, réunions, supplémentaires.
        'detailed' => [
            'title' => 'Statistiques détaillées des comités',
            'lead' => 'Les quarts, réunions et heures supplémentaires de chaque comité durant l\'année financière.',
            'kind' => [
                'shifts' => 'Quarts',
                'meetings' => 'Réunions',
                'extra' => 'Supplémentaires',
            ],
            'total' => 'Total du DMV — incluant chaque sous-groupe',
            'column' => [
                'committee' => 'Comité',
                'kind' => 'Type',
                'ytd' => 'Cumul annuel',
            ],
            'empty' => 'Aucune heure enregistrée dans tout le DMV durant cette année financière.',
        ],

        // Classement des heures des membres actifs — chaque membre actif et provisoire par total.
        'ranked' => [
            'title' => 'Classement des heures des membres actifs',
            'lead' => 'Les membres actifs et provisoires classés par total d\'heures cette année financière.',
            'no_hours' => 'Membres sans aucune heure enregistrée',
            'none_missing' => 'Chaque membre actif et provisoire a des heures cette année financière.',
            'empty' => 'Aucun membre actif ou provisoire n\'a d\'heures durant cette année financière.',
            'column' => [
                'member' => 'Membre',
                'scheduled' => 'Planifiées',
                'extra' => 'Supplémentaires',
                'total' => 'Total',
            ],
        ],

        // Les trois rapports « zéro heure » — une page partagée, la variante nomme la liste.
        'zero' => [
            'lead' => 'Les membres actifs et provisoires qui n\'en ont aucune cette année financière.',
            'empty' => 'Personne — chaque membre actif et provisoire en a durant cette année financière.',
            'column' => [
                'member' => 'Membre',
            ],
            'hours' => ['title' => 'Membres avec zéro heure'],
            'shift' => ['title' => 'Membres avec zéro heure de quart'],
            'extra' => ['title' => 'Membres avec zéro heure supplémentaire'],
        ],
    ],
];
