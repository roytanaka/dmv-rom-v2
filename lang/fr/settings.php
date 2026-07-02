<?php

// Chaînes des paramètres (français canadien, fr-CA). Baseline machine-translated
// par ADR-0004 ; affiner au besoin. Migrées hors des composants Vue (#229, PRD
// #228) pour que la page se traduise et qu'un membre francophone reste en français
// (ADR-0008). Miroir clé pour clé de lang/en/settings.php.
return [
    // Coquille des paramètres (layouts/settings/Layout.vue).
    'title' => 'Paramètres',
    'description' => 'Gérez votre profil et les paramètres de votre compte',
    'nav' => [
        'profile' => 'Profil',
        'password' => 'Mot de passe',
    ],

    // Page Paramètres → Profil.
    'profile' => [
        'title' => 'Paramètres du profil',
        'heading' => 'Renseignements du profil',
        'description' => 'Mettez à jour votre nom et votre adresse courriel',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'Adresse courriel',
        // Coordonnées (#232) — trois numéros de téléphone et une adresse domiciliaire structurée.
        'contact_heading' => 'Coordonnées',
        'contact_description' => 'Vos numéros de téléphone sont visibles par les dirigeants de votre groupe ; votre adresse domiciliaire n’est visible que par vous et par les Archives du DMV.',
        'phone' => 'Téléphone principal',
        'alternate_phone' => 'Autre téléphone',
        'business_phone' => 'Téléphone professionnel',
        'address_heading' => 'Adresse domiciliaire',
        'address_street' => 'Adresse municipale',
        'address_city' => 'Ville',
        'address_province' => 'Province',
        'address_postal_code' => 'Code postal',
        'address_country' => 'Pays',
        // Photo de profil (#233). Publique une fois téléversée ; facultative (aucune photo = initiales).
        'photo' => 'Photo de profil',
        'photo_hint' => 'JPG, PNG ou WebP, jusqu’à 5 Mo. Recadrée automatiquement en carré.',
        'photo_error_heic' => 'Les photos HEIC (le format par défaut de l’iPhone) ne sont pas prises en charge. Sur votre téléphone, réenregistrez ou exportez l’image en JPG, puis téléversez ce fichier.',
        'photo_error_unsupported' => 'Ce type de fichier n’est pas pris en charge. Veuillez téléverser une image JPG, PNG ou WebP.',
        'photo_error_too_large' => 'Cette image est trop volumineuse. Veuillez téléverser une photo de moins de :max Mo.',
        'current_password' => 'Mot de passe actuel',
        'current_password_hint' => 'Confirmez votre mot de passe actuel pour changer votre adresse courriel.',
        'unverified' => "Votre adresse courriel n'est pas vérifiée.",
        'resend' => 'Cliquez ici pour renvoyer le courriel de vérification.',
        'verification_sent' => 'Un nouveau lien de vérification a été envoyé à votre adresse courriel.',
        'save' => 'Enregistrer',
        'saved' => 'Enregistré.',
    ],

    // Page Paramètres → Mot de passe.
    'password' => [
        'title' => 'Paramètres du mot de passe',
        'heading' => 'Mettre à jour le mot de passe',
        'description' => 'Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester sécurisé',
        'current_password' => 'Mot de passe actuel',
        'new_password' => 'Nouveau mot de passe',
        'confirm_password' => 'Confirmer le mot de passe',
        'save' => 'Enregistrer le mot de passe',
        'saved' => 'Enregistré',
    ],
];
