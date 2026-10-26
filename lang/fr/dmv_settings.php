<?php

// Paramètres du DMV (#836, ADR-0036) — super-tier seulement. Chaînes de l'interface ; les noms
// de groupes sont du contenu. Reflète lang/en/dmv_settings.php clé pour clé.
return [
    'title' => 'Paramètres du DMV',
    'subtitle' => 'Les groupes et les intendances de l’organisation.',
    'section' => [
        'groups' => 'Groupes',
        'stewardships' => 'Intendances',
    ],
    'tree' => [
        'column' => [
            'name' => 'Nom',
            'kind' => 'Type',
            'status' => 'État',
        ],
        'no_settings' => 'Aucun paramètre ici',
        'archived' => 'Archivé',
        'create' => 'Créer un groupe',
    ],
    'kind' => [
        'standing_committee' => 'Comité permanent',
        'program' => 'Programme',
        'working_group' => 'Groupe de travail',
        'project' => 'Projet',
        'cohort' => 'Cohorte',
        'container' => 'Conteneur',
    ],
    // Créer un groupe (#842) : la boîte de dialogue de l'arborescence.
    'create' => [
        'title' => 'Créer un groupe',
        'submit' => 'Créer',
        'cancel' => 'Annuler',
    ],
    'choose_parent' => 'Choisir un parent',
    // Le parent actuel du formulaire de structure lorsqu’il est archivé ou terminé (gardé, non proposé).
    'inactive_parent' => ':name (inactif)',
    'choose_kind' => 'Choisir un type',
    'field' => [
        'name' => 'Nom',
        'slug' => 'Slug',
        'parent' => 'Parent',
        'kind' => 'Type',
        'kind_container_note' => 'Un conteneur n’a pas de page. Son adresse répond 404.',
        'visibility' => 'Visibilité dans la liste',
        'display_order' => 'Ordre d’affichage',
        'logo' => 'Logo',
        'time_boxed' => 'À durée limitée',
        'start_date' => 'Début',
        'end_date' => 'Fin',
        'hours_multiplier' => 'Multiplicateur d’heures',
    ],
    // Le formulaire de structure (#843) : ses sections, ses options et leurs libellés.
    'group' => [
        'identity' => 'Identité',
        'listing' => 'Liste',
        'dates' => 'Dates',
        'capabilities' => 'Fonctions',
        'visitor_figures' => 'Chiffres de fréquentation',
        'hours' => 'Heures',
        'archive' => 'Archivage',
    ],
    'capability' => [
        'has_meetings' => 'Réunions',
        'has_documents' => 'Documents',
        'has_scheduling' => 'Horaire',
        'has_vetting' => 'Vérification',
        'has_announcements' => 'Annonces',
        'has_bookings' => 'Réservations',
    ],
    'collection' => [
        'collects_visitor_count' => 'Nombre de visiteur·euses',
        'collects_extra_interactions' => 'Interactions supplémentaires',
        'collects_visitor_provenance' => 'Provenance des visiteur·euses',
        'visitor_figures_await_booking' => 'Les chiffres attendent la réservation',
    ],
    'visibility' => [
        'public' => 'Public',
        'group' => 'Membres du groupe parent',
        'private' => 'Membres seulement',
    ],
    // Les noms de logos sont les noms des groupes (du contenu), identiques dans les deux langues.
    'logo' => [
        'none' => 'Aucun',
        'docents' => 'Docents',
        'guides-du-rom' => 'Guides du ROM',
        'les-amis-francophiles' => 'Les Amis Francophiles',
        'dmv-hands-on-tours' => 'DMV Hands-on Tours',
        'gallery-interpreters' => 'Gallery Interpreters',
        'visitor-guides' => 'Visitor Guides',
        'visitor-wayfinders' => 'Visitor Wayfinders',
        'romwalks' => 'ROMWalks',
        'reception' => 'Reception',
        'rombus' => 'ROMBus',
        'romtravel' => 'ROMTravel',
        'bishop-white-fea' => 'Bishop White (FEA)',
        'friends-of-global-south-asia-fsa' => 'Friends of Global South Asia (FSA)',
        'friends-of-textiles-costume' => 'Friends of Textiles & Costume',
        'friends-of-palaeontology-fop' => 'Friends of Palaeontology (FOP)',
        'friends-of-earth-space-fes' => 'Friends of Earth & Space (FES)',
    ],
    // Les éléments derrière une fonction activée (#844), à côté de sa case.
    'count' => [
        'meetings' => '{1} :count réunion|[2,*] :count réunions',
        'documents' => '{1} :count document|[2,*] :count documents',
        'shifts' => '{1} :count quart|[2,*] :count quarts',
        'tours' => '{1} :count visite|[2,*] :count visites',
        'news' => '{1} :count annonce|[2,*] :count annonces',
        'bookings' => '{1} :count réservation|[2,*] :count réservations',
    ],
    'save' => 'Enregistrer',
    // Le dernier lien d’une liste de noms : « Responsable horaire et Bibliothécaire ».
    'and' => 'et',
    'last_changed' => 'Dernière modification par :name le :date.',
    'archived' => 'Archivé',
    'error' => [
        'slug_taken' => 'Un autre groupe utilise ce slug.',
        'slug_format' => 'Utilisez des lettres minuscules, des chiffres et des traits d’union.',
        'member_admin_required' => 'Choisissez un groupe. L’administration des membres a toujours un détenteur.',
        'inactive_group' => 'Choisissez un groupe actif.',
        'parent_cycle' => 'Un groupe ne peut pas se placer sous lui-même ni sous l’un de ses groupes.',
        'end_before_start' => 'La date de fin doit être égale ou postérieure à la date de début.',
        'not_editable' => 'La racine et les conteneurs n’ont pas de paramètres ici.',
        'archived' => 'Désarchivez ce groupe pour modifier ses paramètres.',
        'archive_steward' => ':group détient :stewardship. Déplacez d’abord l’intendance.',
        'capability_in_use' => '{1} :roles a besoin de cette fonction. Réattribuez d’abord le rôle.|[2,*] :roles ont besoin de cette fonction. Réattribuez d’abord les rôles.',
    ],
    // La section Intendances (#846, ADR-0036 §7).
    'stewardship' => [
        'member_admin' => [
            'title' => 'Administration des membres',
            'description' => 'Les membres de ce groupe modifient le dossier de chaque membre et ouvrent les rapports de tout le DMV. Un seul groupe la détient.',
        ],
        'org_mail' => [
            'title' => 'Expéditeur à l’échelle du DMV',
            'description' => 'Les membres de ces groupes peuvent écrire à tous les membres, à tous les président·es et au conseil.',
        ],
        // Pas encore affichées en cartes (ADR-0036 §7); nommées seulement par le garde d’archivage (#845).
        'statistics' => ['title' => 'Statistiques'],
        'website' => ['title' => 'Site Web'],
        'held_by' => 'Détenue par :groups',
        'held_by_none' => 'Détenue par aucun groupe',
        'group' => 'Groupe',
        'groups' => 'Groupes',
        'gain' => 'Gagnent (:count) : :names',
        'lose' => 'Perdent (:count) : :names',
    ],
    // Archiver et désarchiver un groupe (#845, ADR-0036 §2).
    'archive' => [
        'button' => 'Archiver ce groupe',
        'confirm_title' => 'Archiver :name ?',
        'confirm_body' => 'Les membres continuent de lire. Les responsables ne peuvent plus écrire. Vous pouvez désarchiver plus tard.',
        'confirm' => 'Archiver',
        'cancel' => 'Annuler',
        'unarchive' => 'Désarchiver',
    ],
];
