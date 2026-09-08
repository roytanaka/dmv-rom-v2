<?php

return [
    // Le courriel d'annulation d'inscription (#358, PRD #352, ADR-0021 §Sign-up « Notification »)
    // — le premier courriel de l'application. Chrome uniquement : le nom du membre, le nom du
    // groupe et le libellé du type de quart sont du contenu tel que saisi, transmis et affiché
    // tel quel (ADR-0004).
    'cancellation_email' => [
        'subject' => 'Un quart a été annulé',
        'heading' => 'Un quart a été annulé',
        'intro' => ':member a annulé son inscription à un quart de :group :',
        'footer' => 'La place est de nouveau libre. Aucune action n’est requise, à moins que vous ne souhaitiez la combler.',
        'view_schedule' => 'Voir l’horaire',
    ],
    // Le courriel de rappel (#486, PRD #352, ADR-0024 §7) — l'unique chrome bilingue commun à
    // tous les groupes. Le nom du membre, le nom du groupe, le nom de l'horaire et le libellé du
    // type de quart sont du contenu tel que saisi, transmis et affiché tel quel (ADR-0004) ; la
    // date figure dans l'objet.
    'reminder_email' => [
        'subject' => 'Rappel : votre quart de :group le :date',
        'heading' => 'Vous avez un quart à venir',
        'intro' => 'Bonjour :member — ceci est un rappel de votre prochain quart de :group :',
        'footer' => 'Merci de votre bénévolat. Si vous ne pouvez plus vous présenter, veuillez annuler votre inscription afin que la place puisse être comblée.',
        'view_schedule' => 'Voir l’horaire',
    ],
];
