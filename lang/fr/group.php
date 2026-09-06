<?php

// Chaînes de la page de groupe (#188, PRD #186) — la coquille de comité commune à
// chaque groupe : une bannière, la barre d'onglets de section et l'onglet Aperçu.
// Chaînes d'interface, traduites comme toute l'interface (ADR-0004); le nom du
// groupe et le contenu rédigé (À propos) restent tels quels. Base traduite en
// français canadien (fr-CA). Reflète lang/en/group.php clé pour clé.
return [
    'tab' => [
        'overview' => 'Aperçu',
        'members' => 'Membres',
        'meetings' => 'Réunions',
        'documents' => 'Documents',
        'scheduling' => 'Horaire',
        'content' => 'Contenu',
        'hours' => 'Heures',
    ],
    'soon' => 'Bientôt',

    'archived' => 'Archivé',
    'ended' => 'Terminé le :date',

    'about' => 'À propos',
    'about_empty' => 'Aucune description pour le moment.',
    'children' => 'Groupes',
    'leadership' => 'Direction',
    'leadership_empty' => 'Aucun officier inscrit.',
    'facts' => 'En bref',
    'facts_members' => '{1} :count membre|[2,*] :count membres',
    'facts_meets' => 'Tient des réunions',
    'facts_dates' => ':start – :end',
    'facts_starts' => 'Débute le :date',
    'facts_ends' => 'Se termine le :date',

    'roster' => [
        'search' => [
            'label' => 'Rechercher par nom',
            'placeholder' => 'Rechercher par nom',
        ],
        'column' => [
            'name' => 'Nom',
            'roles' => 'Rôles',
            'contact' => 'Coordonnées',
            'standing' => 'Statut',
            'actions' => 'Actions',
        ],
        'no_contact' => '—',
        'empty' => 'Aucun membre pour le moment.',
        'no_matches' => 'Aucun membre ne correspond à votre recherche.',

        // CRUD du registre par les officiers (#192) — visible uniquement pour un·e
        // secrétaire / président·e / super-palier (contrôlé côté serveur via
        // `can.manageRoster`). Les messages de validation proviennent des Form Requests.
        'role_unavailable' => 'Ce rôle n’est pas disponible pour ce groupe.',
        'cannot_hard_remove' => 'Ce membre a un historique et ne peut être retiré. Faites-le démissionner.',
        'show_past' => 'Afficher les anciens membres',
        'manage' => 'Gérer',
        'add' => 'Ajouter un membre',
        'add_title' => 'Ajouter un membre',
        'add_search' => 'Rechercher parmi tous les membres',
        'add_submit' => 'Ajouter au groupe',
        'no_candidates' => 'Aucun membre ne correspond à votre recherche.',
        'edit_title' => 'Gérer l’adhésion',
        'field' => [
            'standing' => 'Statut',
            'roles' => 'Rôles',
            'loa_start' => 'Début du congé',
            'loa_end' => 'Fin du congé',
        ],
        'resign' => 'Faire démissionner',
        'confirm_resign' => 'Faire démissionner ce membre? Son historique est conservé et il peut être réintégré.',
        'remove' => 'Retirer (ajouté par erreur)',
        'confirm_remove' => 'Retirer définitivement ce membre? Cette action est irréversible.',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
    ],

    'standing' => [
        'full' => 'Régulier·ère',
        'loa' => 'En congé',
        'trainee' => 'Stagiaire',
        'transitional' => 'En transition',
        'auxiliary' => 'Auxiliaire',
        'projects' => 'Projets',
        'emeritus' => 'Émérite',
        'inactive' => 'Inactif·ve',
        'resigned' => 'Démissionnaire',
        'deceased' => 'Décédé·e',
        'donor' => 'Donateur·rice',
    ],

    'meetings' => [
        'empty' => 'Aucune réunion pour le moment.',
        'upcoming' => 'À venir',
        'past' => 'Passées',
        'video' => 'Joindre l’appel vidéo',
        'link' => [
            'agenda' => 'Ordre du jour',
            'minutes' => 'Procès-verbal',
            'report' => 'Rapport',
        ],
        'draft' => 'Brouillon',
        'new' => 'Nouvelle réunion',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'create_title' => 'Nouvelle réunion',
        'edit_title' => 'Modifier la réunion',
        'confirm_delete' => 'Supprimer cette réunion? Cette action est irréversible.',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'field' => [
            'title' => 'Titre',
            'held_at' => 'Date et heure',
            'description' => 'Description',
            'location' => 'Lieu',
            'video_url' => 'Lien vidéo',
            'published' => 'Publiée (visible aux membres)',
            'links' => 'Liens de documents',
        ],
    ],

    'edit' => [
        'about' => 'Modifier',
        'about_title' => 'Modifier la section À propos',
        'about_placeholder' => 'Décrivez ce groupe…',
        'banner' => 'Changer la bannière',
        'banner_title' => 'Choisir une bannière',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
    ],

    'banner' => [
        'aria' => 'Bannière du groupe',
        'option' => [
            'rotunda' => 'Rotonde',
            'crystal' => 'Cristal',
            'gallery' => 'Galerie',
            'mural' => 'Murale',
            'stained-glass' => 'Vitrail',
            'totem' => 'Totem',
        ],
    ],

    'coming_soon' => 'Cette section arrive bientôt.',

    // Onglet Horaire (#353, ADR-0021 §1) — la surface de lecture des horaires.
    'scheduling_panel' => [
        'empty' => 'Aucun horaire pour l’instant.',
        'current_heading' => 'Actuels et à venir',
        'past_heading' => 'Passés',
        'draft_badge' => 'Brouillon',
        'date_range' => 'Du :start au :end',
        'back_to_list' => 'Tous les horaires',
        // Rédaction (#354) — visible seulement pour un·e responsable horaire /
        // président·e / super-palier (contrôlé côté serveur via `can`). Publier /
        // dépublier sont les deux transitions d’état.
        'new' => 'Nouvel horaire',
        'edit' => 'Modifier',
        'publish' => 'Publier',
        'unpublish' => 'Dépublier',
        'delete' => 'Supprimer',
        'create_title' => 'Nouvel horaire',
        'edit_title' => 'Modifier l’horaire',
        'confirm_delete' => 'Supprimer cet horaire ? Cette action est irréversible.',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'field' => [
            'name' => 'Nom',
            'starts_on' => 'Date de début',
            'ends_on' => 'Date de fin',
            'description' => 'Description',
        ],
        // Formulaire de rédaction des créneaux (#356 front end, ADR-0021 §2) — les commandes
        // ajouter / modifier / supprimer de la ou du responsable horaire sur un horaire ouvert,
        // contrôlées par les indices `can` du serveur. « edit » / « delete » ci-dessus sont
        // réutilisés ; ces clés nomment le formulaire et ses champs. Le sélecteur de type puise
        // dans les `shift_kinds` du groupe ; le sélecteur d’audience offre les deux cas ShiftAudience.
        'new_shift' => 'Nouveau créneau',
        'create_shift_title' => 'Nouveau créneau',
        'edit_shift_title' => 'Modifier le créneau',
        'confirm_delete_shift' => 'Supprimer ce créneau ? Cette action est irréversible.',
        'shift_field' => [
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'capacity' => 'Capacité',
            'kind' => 'Type',
            'kind_none' => 'Aucun type',
            'audience' => 'Audience',
        ],
        'audience' => [
            'group' => 'Membres du groupe',
            'open' => 'Ouvert à tous',
        ],
        // Création de créneaux (#356, ADR-0021 §2) — messages de validation affichés par
        // les Form Requests lorsque la plage de dates est appliquée dans les deux sens :
        // un créneau ne peut sortir de son horaire, et un horaire ne peut se resserrer en
        // excluant les créneaux qui s’y trouvent déjà.
        'shift_outside_range' => 'Ce créneau se situe en dehors de la plage de dates de l’horaire.',
        'schedule_range_conflict' => 'Les dates de l’horaire ne peuvent exclure un créneau qui s’y trouve déjà. Déplacez ou supprimez ces créneaux d’abord.',
        // Création / suppression en lot (#362, ADR-0021 §2) — un lot est N écritures
        // simples plus un rapport. Ces messages nomment les lignes ignorées par une
        // exécution : ce sont des résultats utiles, non des erreurs, affichés comme un
        // rapport sur la page plutôt qu’un échec de validation.
        'bulk' => [
            'skipped_outside_range' => 'Ignoré — cette journée se situe en dehors de la plage de dates de l’horaire.',
            'skipped_has_sign_ups' => 'Ignoré — ce créneau compte des membres inscrits. Retirez-les d’abord.',
            // Placement groupé des inscriptions d’un·e membre (#363, ADR-0021 §5) — une
            // exécution respecte la capacité et la règle d’une seule place par rangée,
            // en ignorant et nommant ce qu’elle ne peut inscrire.
            'skipped_full' => 'Ignoré — ce créneau est déjà complet.',
            'skipped_already_signed_up' => 'Ignoré — cette·ce membre a déjà une place sur ce créneau.',
            // Formulaire de création / suppression en lot des créneaux (#362 front end,
            // ADR-0021 §2) — l’outil qui fait d’un mois une seule exécution. Aucun
            // intervalle : un créneau tombe sur chaque jour de semaine correspondant de la
            // plage. La suppression cible le même filtre et se confirme avant de s’exécuter.
            'open' => 'Créneaux en lot',
            'title' => 'Créer des créneaux en lot',
            'description' => 'Créez un créneau sur chaque jour de semaine choisi d’une plage de dates. La suppression retire tous les créneaux correspondant au même filtre.',
            'create' => 'Créer les créneaux',
            'delete' => 'Supprimer les correspondants',
            'confirm_delete' => 'Supprimer tous les créneaux correspondant à ce filtre ? Les créneaux comptant des membres inscrits sont conservés. Cette action est irréversible.',
            'field' => [
                'starts_time' => 'Heure de début',
                'ends_time' => 'Heure de fin',
                'capacity' => 'Capacité',
                'kind' => 'Type',
                'kind_none' => 'Aucun type',
                'weekdays' => 'Jours de la semaine',
                'from_date' => 'Date de début',
                'to_date' => 'Date de fin',
            ],
            // Le rapport d’exécution (#362 front end) — un résultat utile, non une erreur :
            // le nombre créé ou retiré, et chaque ligne ignorée avec sa raison.
            'report' => [
                'created' => '{0} Aucun créneau créé.|{1} :count créneau créé.|[2,*] :count créneaux créés.',
                'deleted' => '{0} Aucun créneau retiré.|{1} :count créneau retiré.|[2,*] :count créneaux retirés.',
                'skipped_heading' => '{1} :count ignoré :|[2,*] :count ignorés :',
                'dismiss' => 'Fermer',
            ],
        ],
        // Placement / retrait groupé des inscriptions d’un·e membre (#363 front end,
        // ADR-0021 §5) — l’outil « membre dans l’horaire » qui retire la quinzaine de la
        // Réception : un·e habitué·e placé·e sur chaque créneau que nomme un filtre, chaque
        // semaine ou aux deux semaines, en une exécution — et défait de la même façon.
        // Volontairement distinct de la création de créneaux en lot (#362) : un autre acteur,
        // un autre moment. L’intervalle et son point d’ancrage vivent uniquement dans le
        // formulaire ; aucun motif n’est stocké.
        'bulk_assign' => [
            'open' => 'Inscriptions en lot',
            'title' => 'Placer en lot les inscriptions d’un·e membre',
            'description' => 'Placez un·e membre sur chaque créneau correspondant à ce filtre d’une plage de dates — chaque semaine ou aux deux semaines. Le retrait libère ce·tte membre de chaque créneau correspondant.',
            'place' => 'Placer le·la membre',
            'remove' => 'Retirer les correspondants',
            'confirm_remove' => 'Retirer ce·tte membre de chaque créneau correspondant à ce filtre ? Cela libère ses places. Cette action est irréversible.',
            'field' => [
                'member' => 'Membre',
                'member_none' => 'Choisir un·e membre…',
                'member_empty' => 'Aucun·e membre plaçable dans ce groupe.',
                'weekdays' => 'Jours de la semaine',
                'starts_time' => 'Heure de début',
                'ends_time' => 'Heure de fin',
                'from_date' => 'Date de début',
                'to_date' => 'Date de fin',
                'interval' => 'Intervalle',
                'anchor_date' => 'Date d’ancrage',
                'anchor_hint' => 'Les semaines aux deux semaines sont comptées à partir de cette date.',
            ],
            'interval' => [
                'weekly' => 'Chaque semaine',
                'biweekly' => 'Une semaine sur deux',
            ],
            // Le rapport d’exécution — un résultat utile, non une erreur : les places
            // occupées ou libérées, et chaque ligne ignorée avec sa raison (un créneau
            // complet, ou une place déjà détenue).
            'report' => [
                'placed' => '{0} Aucune place occupée.|{1} :count place occupée.|[2,*] :count places occupées.',
                'removed' => '{0} Aucune place libérée.|{1} :count place libérée.|[2,*] :count places libérées.',
                'skipped_heading' => '{1} :count ignoré :|[2,*] :count ignorés :',
                'dismiss' => 'Fermer',
            ],
        ],
        // Validation des inscriptions (#357, ADR-0021 §Sign-up) — affichée par le Form
        // Request lorsqu’une place ne peut être prise, et par la modification du créneau
        // lorsque la capacité laisserait un·e membre sans place.
        'shift_full' => 'Ce créneau est complet.',
        'already_signed_up' => 'Vous êtes déjà inscrit·e à ce créneau.',
        'capacity_below_signups' => 'La capacité ne peut être inférieure au nombre de membres déjà inscrit·es. Retirez d’abord des membres.',
        // Agenda (#355, #357) — les créneaux de l’horaire ouvert, groupés par jour.
        // « seats » indique les places occupées sur la capacité ; « sign_up » est
        // l’action s’inscrire / se désister.
        'agenda' => [
            'aria_label' => 'Agenda',
            'empty' => 'Aucun créneau pour cet horaire pour l’instant.',
            'time_range' => 'De :start à :end',
            'seats' => ':taken / :capacity occupées',
            'sign_up' => [
                'take' => 'S’inscrire',
                'drop' => 'Se désister',
                'full' => 'Complet',
                'nobody' => 'Personne inscrit·e pour l’instant',
                'signed_up_label' => 'Inscrit·es',
            ],
            // Affectation et retrait par la ou le responsable (#359) — visible
            // uniquement pour une ou un gestionnaire d’horaire.
            'assign' => [
                'place' => 'Affecter un membre',
                'title' => 'Affecter un membre à ce quart',
                'search' => 'Rechercher des membres…',
                'empty' => 'Aucun membre à affecter.',
                'remove' => 'Retirer du quart',
                'confirm_remove' => 'Retirer ce membre du quart ?',
            ],
            // Fin de quart (#445, PRD #443, ADR-0023 §5) — la ou le titulaire du quart
            // enregistre le nombre de visiteur·euses servi·es, sur son propre quart, à partir
            // de cinq minutes avant la fin. Le bouton reste désactivé tant qu’aucun nombre
            // n’est saisi ; le serveur applique le reste.
            'sign_out' => [
                'count_label' => 'Visiteur·euses servi·es',
                'submit' => 'Terminer le quart',
                'placeholder' => 'Nombre de visiteur·euses',
                'recorded' => ':count visiteur·euses',
                'whole_number' => 'Saisissez un nombre entier de visiteur·euses.',
                'not_negative' => 'Le nombre de visiteur·euses ne peut pas être négatif.',
            ],
        ],
        // Bascule de vue (#360, ADR-0021 §7) — la ou le lecteur choisit Agenda ou
        // Calendrier ; le choix vit dans localStorage, jamais dans le formulaire de
        // rédaction. L’Agenda est la vue par défaut.
        'view' => [
            'aria_label' => 'Choisir une vue',
            'agenda' => 'Agenda',
            'calendar' => 'Calendrier',
        ],
        // Calendrier (#360) — la vue en grille mensuelle et sa fiche du jour.
        'calendar' => [
            'aria_label' => 'Calendrier',
            'previous' => 'Mois précédent',
            'next' => 'Mois suivant',
            'shift_count' => '{1} :count créneau|[2,*] :count créneaux',
        ],
        // Créneaux ouverts inter-groupes (#361, ADR-0021 §Inscription) — les créneaux « open »
        // d'autres groupes que la lectrice découvre, toujours présents mais réduits à une ligne,
        // toujours attribués à leur groupe propriétaire. `summary` coiffe la bande repliée ;
        // `show_all` / `hide_all` est le tout ouvrir / tout fermer ; `chip` est le compte compact
        // dans la case du calendrier.
        'foreign' => [
            'summary' => '{1} :count autre ouvert à vous — :group|[2,*] :count autres ouverts à vous — :group',
            'show_all' => 'Ouverts à moi ailleurs',
            'hide_all' => 'Masquer les créneaux des autres groupes',
            'chip' => '+:count ouvert(s)',
        ],
    ],

    'role' => [
        'chair' => 'Président·e',
        'secretary' => 'Secrétaire',
        'treasurer' => 'Trésorier·ère',
        'scheduler' => 'Responsable horaire',
        'statistician' => 'Statisticien·ne',
        'vetting' => 'Vérification',
        'librarian' => 'Bibliothécaire',
        'content_maintainer' => 'Responsable du contenu',
        'news_editor' => 'Responsable des nouvelles',
        'executive' => 'Direction générale',
    ],
];
