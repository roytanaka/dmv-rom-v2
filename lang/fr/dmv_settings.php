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
    // L’arborescence des groupes (#841, restylée dans #848) : barre d’outils, colonnes, notes et faits.
    'tree' => [
        'column' => [
            'name' => 'Groupe',
            'kind' => 'Type',
            'capabilities' => 'Fonctions',
            'status' => 'État',
        ],
        'search' => 'Trouver un groupe',
        'show_archived' => 'Afficher les groupes archivés',
        'expand' => 'Déplier :name',
        'collapse' => 'Replier :name',
        // Les paramètres de la racine ne sont pas ici ; un conteneur n’a pas de page.
        'root_note' => 'Défini par les responsables',
        'container_note' => 'Aucune page',
        // Une lettre par fonction ; la légende sous le tableau les explique. Les mêmes codes
        // qu’en anglais, pour que l’arborescence se lise pareil dans les deux langues.
        'letter' => [
            'has_meetings' => 'M',
            'has_documents' => 'D',
            'has_scheduling' => 'S',
            'has_vetting' => 'V',
            'has_announcements' => 'N',
            'has_bookings' => 'B',
        ],
        'capability_on' => ':capability : activée',
        'capability_off' => ':capability : désactivée',
        // Noms courts des intendances pour la colonne État.
        'stewardship' => [
            'member_admin' => 'Admin. des membres',
            'org_mail' => 'Expéditeur DMV',
        ],
        'hours' => 'Heures ×:multiplier',
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
    'choose_section' => 'Choisir une section',
    'choose_parent' => 'Choisir un parent',
    // Le parent actuel du formulaire de structure lorsqu’il est archivé ou terminé (gardé, non proposé).
    'inactive_parent' => ':name (inactif)',
    'choose_kind' => 'Choisir un type',
    'field' => [
        'name' => 'Nom',
        'slug' => 'Adresse',
        // L’enfant de la racine dont le sous-arbre fournit le parent (#850).
        'section' => 'Section',
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
        // Le formulaire restylé (#848) : lien d’en-tête, index des sections, note d’archivage et barre d’enregistrement.
        'open_page' => 'Ouvrir la page du groupe',
        'sections' => 'Sections',
        'has_problem' => 'Contient un problème',
        'archive_note' => 'Un groupe archivé quitte la barre latérale. Les membres peuvent toujours le lire. Personne ne peut le modifier tant que vous ne le désarchivez pas.',
        'save_changes' => 'Enregistrer les modifications',
        'discard' => 'Annuler les modifications',
        'problems' => '{1} :count problème à corriger avant d’enregistrer|[2,*] :count problèmes à corriger avant d’enregistrer',
    ],
    // La bannière du formulaire d’un groupe archivé (#848).
    'archived_banner' => [
        'title' => 'Ce groupe est archivé.',
        'body' => 'Les membres peuvent toujours lire sa page, ses documents, ses réunions et ses heures. Personne ne peut le modifier. Désarchivez-le pour modifier ces paramètres.',
    ],
    'capability' => [
        'has_meetings' => 'Réunions',
        'has_documents' => 'Documents',
        'has_scheduling' => 'Horaire',
        'has_vetting' => 'Vérification',
        'has_announcements' => 'Nouvelles',
        'has_bookings' => 'Réservations',
    ],
    // Une ligne sous chaque interrupteur de fonction (#854).
    'capability_help' => [
        'has_meetings' => 'Les membres voient les réunions, ordres du jour et procès-verbaux du groupe.',
        'has_documents' => 'Le groupe tient une bibliothèque de documents.',
        'has_scheduling' => 'Le groupe publie des quarts et les membres s’y inscrivent.',
        'has_vetting' => 'Le groupe note les visites que chaque membre peut donner.',
        'has_announcements' => 'Le groupe publie dans le fil de nouvelles du DMV.',
        'has_bookings' => 'Le groupe accepte les réservations de visites de clients externes.',
    ],
    'collection' => [
        'collects_visitor_count' => 'Nombre de visiteur·euses',
        'collects_extra_interactions' => 'Interactions supplémentaires',
        'collects_visitor_provenance' => 'Provenance des visiteur·euses',
        'visitor_figures_await_booking' => 'Les chiffres attendent la réservation',
    ],
    // Une ligne sous chaque interrupteur de chiffres de fréquentation (#854).
    'collection_help' => [
        'collects_visitor_count' => 'Les membres indiquent le nombre de visiteur·euses servi·es à chaque quart.',
        'collects_extra_interactions' => 'Les membres indiquent aussi les visiteur·euses servi·es hors de leur visite.',
        'collects_visitor_provenance' => 'Les membres répartissent les visiteur·euses de chaque quart selon leur provenance.',
        'visitor_figures_await_booking' => 'Le rapport de fréquentation indique que les chiffres du groupe sont incomplets tant que les réservations ne sont pas comptées.',
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
    // Les mêmes éléments une fois la fonction désactivée dans le brouillon (#848) : masqués, pas supprimés.
    'hidden' => [
        'meetings' => '{1} :count réunion devient masquée|[2,*] :count réunions deviennent masquées',
        'documents' => '{1} :count document devient masqué|[2,*] :count documents deviennent masqués',
        'shifts' => '{1} :count quart devient masqué|[2,*] :count quarts deviennent masqués',
        'tours' => '{1} :count visite devient masquée|[2,*] :count visites deviennent masquées',
        'news' => '{1} :count annonce devient masquée|[2,*] :count annonces deviennent masquées',
        'bookings' => '{1} :count réservation devient masquée|[2,*] :count réservations deviennent masquées',
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
        'root_parent' => 'Choisissez une section, pas la racine.',
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
        // Le panneau d’aperçu (#848) : qui gagne et qui perd selon le brouillon, ou aucun changement.
        'gain' => 'Gagnent l’autorité (:count)',
        'lose' => 'Perdent l’autorité (:count)',
        'no_change' => 'Aucun changement pour l’instant. Cochez ou décochez un groupe pour voir qui gagne ou perd l’autorité.',
        'undo' => 'Annuler le changement',
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
