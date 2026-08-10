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
    ],
];
