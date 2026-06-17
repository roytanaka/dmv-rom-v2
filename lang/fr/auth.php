<?php

// Chrome d'authentification (fr-CA), registre institutionnel du ROM. Les trois
// premières clés sont les messages-cadres de Laravel (échec de connexion, mauvais
// mot de passe, limitation) — elles DOIVENT rester. Le tableau `login` reprend la
// maquette de connexion fractionnée du ROM (#157), miroir clé pour clé de
// lang/en/auth.php. Le nom du département reste en ANGLAIS dans les deux langues,
// conformément à institutional.php (PRD #104).
return [

    // Messages-cadres de Laravel — ne pas retirer ni renommer.
    'failed' => 'Ces identifiants ne correspondent pas à nos dossiers.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',

    // Écran de connexion fractionné du ROM.
    'login' => [
        'heading' => 'Connexion',
        'welcome' => 'Bon retour au Department of Museum Volunteers.',

        'email' => 'Courriel',
        'password' => 'Mot de passe',
        'forgot' => 'Mot de passe oublié?',
        'submit' => 'Connexion',

        // Noms accessibles pour la bascule afficher/masquer le mot de passe.
        'show_password' => 'Afficher le mot de passe',
        'hide_password' => 'Masquer le mot de passe',

        // Pied de page de la réception. `help_before` contient le numéro (texte
        // simple, :phone) et précède le lien mailto ; `help_email` est le texte
        // du lien ; le point final est rendu après le lien dans le gabarit.
        'help_before' => 'Nouveau au sein du Department of Museum Volunteers ou vous éprouvez des difficultés à vous connecter? Composez le :phone pour joindre la réception du DMV ou',
        'help_email' => 'écrivez au bureau',

        // Légendes de la photo sur le dégradé.
        'photo_location' => 'Royal Ontario Museum · Toronto',
        'photo_credit' => 'Photo : Narciso Arellano / Unsplash',
    ],
];
