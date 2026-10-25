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
    // Le formulaire de structure (#843) complète à partir d'ici.
    'structure_soon' => 'Le formulaire de structure arrive.',
    'save' => 'Enregistrer',
    'last_changed' => 'Dernière modification par :name le :date.',
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
        'held_by' => 'Détenue par :groups',
        'held_by_none' => 'Détenue par aucun groupe',
        'group' => 'Groupe',
        'groups' => 'Groupes',
        'gain' => 'Gagnent (:count) : :names',
        'lose' => 'Perdent (:count) : :names',
    ],
    'error' => [
        'member_admin_required' => 'Choisissez un groupe. L’administration des membres a toujours un détenteur.',
        'inactive_group' => 'Choisissez un groupe actif.',
    ],
];
