<?php

return [
    // Noms des audiences de diffusion / message direct (ADR-0024 §5) — le libellé
    // affiché dans le menu du composeur et conservé dans l'enregistrement d'envoi.
    // Les audiences paramétrées empruntent leur libellé ailleurs : Un statut à
    // group.standing.*, Une catégorie à member.standing.*, le groupe enfant à son
    // propre nom, et Un membre au nom du membre — du contenu, restitué tel quel.
    // Reflète lang/en/audience.php.

    // Audiences de groupe — effectif et responsables.
    'whole_group' => 'Tout le groupe',
    'whole_group_on_leave' => 'Tout le groupe, congés compris',
    'group_active' => 'Actifs',
    'group_officers' => 'Les responsables du groupe',
    'hand_picked' => 'Choisir des personnes…',

    // Audiences de planification.
    'sign_ups_schedule' => 'Inscriptions à :schedule',
    'sign_ups_shift' => 'Inscriptions à ce quart',

    // Audiences à l'échelle de l'organisme.
    'all_members' => 'Tous les membres',
    'all_members_on_leave' => 'Tous les membres, congés compris',
    'active_provisional' => 'Actifs et provisoires',

    // Audiences de direction.
    'board_of_directors' => 'Conseil d’administration',
    'committee_chairs' => 'Présidences de comité',
    'all_chairs' => 'Toutes les présidences',

    // Suffixe d'audience modifiée, ajouté lorsque le sélecteur a retiré des
    // personnes (ADR-0024 §6) : le nom de l'audience demeure et le nombre de
    // retraits est indiqué.
    'edited' => ':label, :count retiré(s)',

    // État vide du menu Courriel — une seule ligne désactivée affichée lorsque
    // l'utilisateur ne peut choisir aucune audience ici, au lieu d'un menu vide (#506).
    'empty' => 'Rien à envoyer par courriel d’ici',
];
