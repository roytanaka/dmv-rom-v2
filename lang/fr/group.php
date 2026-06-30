<?php

// Chaînes de la page de groupe (#188, PRD #186) — la coquille de comité commune à
// chaque groupe : une bannière, la barre d'onglets de section et l'onglet Aperçu.
// Chaînes d'interface, traduites comme toute l'interface (ADR-0004); le nom du
// groupe et le contenu rédigé (À propos) restent tels quels. Base traduite en
// français canadien (fr-CA). Reflète lang/en/group.php clé pour clé.
return [
    'tab' => [
        'overview' => 'Aperçu',
        'roster' => 'Membres',
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
        ],
        'no_contact' => '—',
        'empty' => 'Aucun membre pour le moment.',
        'no_matches' => 'Aucun membre ne correspond à votre recherche.',
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

    'coming_soon' => 'Cette section arrive bientôt.',

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
    ],
];
