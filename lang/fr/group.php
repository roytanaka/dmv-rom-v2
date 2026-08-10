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
        // Création de créneaux (#356, ADR-0021 §2) — messages de validation affichés par
        // les Form Requests lorsque la plage de dates est appliquée dans les deux sens :
        // un créneau ne peut sortir de son horaire, et un horaire ne peut se resserrer en
        // excluant les créneaux qui s’y trouvent déjà.
        'shift_outside_range' => 'Ce créneau se situe en dehors de la plage de dates de l’horaire.',
        'schedule_range_conflict' => 'Les dates de l’horaire ne peuvent exclure un créneau qui s’y trouve déjà. Déplacez ou supprimez ces créneaux d’abord.',
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
