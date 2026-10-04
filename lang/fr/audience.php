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

    // Raisons de l'état vide du contrôle Courriel (#513, ADR-0024 §6) — affichées dans
    // l'infobulle du bouton grisé et comme unique ligne désactivée du menu, pour nommer
    // pourquoi rien n'est sélectionnable plutôt que de laisser vide. « not_member » sur un
    // groupe non racine que l'utilisateur n'a pas rejoint ; « not_org_wide_sender » sur la
    // racine, dont les audiences visent l'ensemble de l'organisme.
    'not_member' => 'Joignez-vous à ce groupe pour écrire à ses membres.',
    'not_org_wide_sender' => 'Seules les présidences et les membres des groupes Direction, Archives et Prix peuvent écrire à l’ensemble du DMV.',
];
