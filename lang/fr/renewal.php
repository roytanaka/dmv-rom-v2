<?php

// Le renouvellement du membre (#939, ADR-0038 §9) : les étapes et le code de conduite. Le code de
// conduite est le texte de l'application d'origine, gardé en chaîne de traduction (non modifiable
// dans l'application). Reflète lang/en/renewal.php clé pour clé.
return [
    'title' => 'Renouveler votre adhésion',
    'period' => 'Du 1er avril :year au 31 mars :next',
    'next' => 'Suivant',
    'step' => [
        'form' => 'Votre formulaire de renouvellement',
        'skills' => 'Vos compétences',
        'payment' => 'Paiement',
    ],
    // Étape 2, le formulaire de renouvellement (#940). Les libellés des champs viennent de records.field.*.
    'form' => [
        'section' => [
            'rom' => 'Adhésion au ROM',
            'name' => 'Nom',
            'address' => 'Adresse',
            'phones' => 'Téléphones',
            'placements' => 'Vos affectations',
            'other' => 'Autres affectations',
            'emergency' => 'Personne à joindre en cas d’urgence',
            'category' => 'Catégorie de membre',
        ],
        'confirm_number' => 'Je confirme mon numéro de membre du ROM',
        'expired' => 'Votre adhésion au ROM est expirée. Veuillez la renouveler auprès du ROM.',
        'other_group' => 'Autre affectation :number',
        'other_position' => 'Poste :number',
        'other_text' => 'Autre affectation ou comité :number',
        'none' => 'Aucune',
        'no_placements' => 'Vous n’avez aucune affectation en cours.',
        'positions' => [
            'Member' => 'Membre',
            'Chair' => 'Présidence',
            'Co-Chair' => 'Coprésidence',
            'Vice-Chair' => 'Vice-présidence',
            'Auxiliary' => 'Auxiliaire',
        ],
        'verify_emergency' => 'Je confirme que ma personne à joindre est à jour',
        'category' => 'Catégorie',
        'choose' => 'Choisissez une catégorie',
        'resign' => 'Je souhaite quitter le DMV',
        'placement_required' => 'Confirmez au moins une affectation, ou choisissez Soutien, Congé ou Démission.',
        'save' => 'Enregistrer',
    ],
    // La fin d’un renouvellement que les Dossiers suivent en personne : démission, congé, actif à soutien (#940).
    'contact' => [
        'heading' => 'Merci',
        'body' => 'Un membre des Dossiers DMV communiquera avec vous. Ce renouvellement reste ouvert d’ici là.',
    ],
    'code_of_conduct' => [
        'title' => 'Code de conduite',
        'preamble_heading' => 'Préambule',
        'preamble' => 'Les membres du DMV ont des devoirs, des responsabilités et des occasions. Les bénévoles de tous les niveaux, qu’ils soient membres d’une section ou d’un comité ou qu’ils occupent un poste de direction, doivent connaître le code de conduite et s’y conformer.',
        'policy_heading' => 'Politique',
        'policy' => 'Le DMV valorise le travail d’équipe dans l’atteinte de ses objectifs. Il favorise donc un milieu de travail qui reconnaît l’intégrité individuelle, l’engagement, la coopération, la fiabilité, l’initiative, l’excellence et les normes éthiques les plus élevées. Pour créer un tel milieu, les membres doivent respecter le code de conduite suivant.',
        'duties_intro' => 'Dans l’exercice de vos fonctions de bénévole, vous devez :',
        'duties' => [
            'Traiter les visiteurs, les membres du DMV, le personnel du ROM, les autres bénévoles et les partenaires avec respect, tact, courtoisie, sensibilité et professionnalisme, sans abus, harcèlement, harcèlement sexuel, discrimination ni violence.',
            'Respecter la diversité des visiteurs, des membres du DMV, du personnel du ROM et des autres bénévoles.',
            'Mettre vos compétences, vos aptitudes et votre expérience au service du DMV et du ROM, en agissant de façon éthique et avec la plus grande honnêteté, intégrité, collégialité et le plus grand professionnalisme.',
            'Représenter le DMV et le ROM de manière positive et appuyer les décisions de la direction du DMV et du ROM.',
            'Accomplir vos tâches bénévoles en toute sécurité et prendre les mesures appropriées pour réduire au minimum tout risque, y compris communiquer avec la Sécurité du ROM au besoin.',
            'Vous présenter à vos tâches bénévoles sans être sous l’effet d’une substance, ni sembler l’être, et le demeurer pendant toute la durée de ces tâches.',
            'Respecter et protéger les biens et les installations du DMV et du ROM, et ne les utiliser que dans l’intérêt du DMV et du ROM.',
            'Vous abstenir de divulguer toute information confidentielle du DMV ou du ROM, sauf si vous devez le faire pour exercer vos fonctions ou si la loi l’exige.',
            'Vous abstenir d’utiliser toute information obtenue en tant que membre du DMV à l’avantage d’une personne ou d’une institution autre que le ROM.',
            'Respecter le règlement administratif, les politiques, les pratiques et les objectifs stratégiques du DMV établis par le conseil, et vous conformer à l’ensemble des lois, règles et règlements applicables au DMV et au ROM.',
            'Éviter autant que possible les conflits d’intérêts et les apparences de conflit d’intérêts. Si l’un ou l’autre survient, le reconnaître et le déclarer sans délai.',
            'Distinguer clairement les rôles des membres du DMV de ceux du personnel du ROM, et vous abstenir de toute ingérence, ou apparence d’ingérence, dans les activités de l’institution.',
            'Passer par la présidence, le conseil du DMV ou la présidence de la section ou du comité si vous avez besoin de l’aide du personnel du ROM, sauf si un contact direct avec le personnel est autorisé par la présidence, le conseil ou la présidence de la section ou du comité, ou constitue une pratique établie du comité.',
        ],
        'closing' => 'Le non-respect du présent code de conduite peut, à la discrétion du conseil du DMV, entraîner des mesures disciplinaires pouvant aller jusqu’à la révocation de l’adhésion au DMV.',
        'accept' => "J'ai lu le présent code de conduite et j'accepte de m'y conformer",
    ],
];
