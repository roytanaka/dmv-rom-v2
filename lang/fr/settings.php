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

    // Panneau de suppression de compte (components/DeleteUser.vue).
    'delete' => [
        'heading' => 'Supprimer le compte',
        'description' => 'Supprimez votre compte et toutes ses ressources',
        'warning' => 'Avertissement',
        'warning_detail' => 'Veuillez procéder avec prudence, cette action est irréversible.',
        'button' => 'Supprimer le compte',
        'confirm_title' => 'Êtes-vous sûr de vouloir supprimer votre compte ?',
        'confirm_description' => 'Une fois votre compte supprimé, toutes ses ressources et données seront également supprimées définitivement. Veuillez saisir votre mot de passe pour confirmer que vous souhaitez supprimer définitivement votre compte.',
        'password' => 'Mot de passe',
        'cancel' => 'Annuler',
    ],
];
