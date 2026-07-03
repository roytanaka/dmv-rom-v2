<?php

// Settings chrome strings (English) — the Settings → Profile / Password surface
// (#229, PRD #228). Migrated out of the Vue components so the pages translate and
// a French Member stays in French (ADR-0008). Source of truth for both PHP (__())
// and Vue (laravel-vue-i18n `trans`). Mirrors lang/fr/settings.php key-for-key.
return [
    // Settings shell (layouts/settings/Layout.vue).
    'title' => 'Settings',
    'description' => 'Manage your profile and account settings',
    'nav' => [
        'profile' => 'Profile',
        'password' => 'Password',
    ],

    // Settings → Profile page.
    'profile' => [
        'title' => 'Profile settings',
        'heading' => 'Profile information',
        'description' => 'Update your name and email address',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email address',
        // Contact record (#232) — three phones and a structured home address.
        'contact_heading' => 'Contact details',
        'contact_description' => 'Phone numbers are shown to your Group officers; your home address is visible only to you and DMV Records.',
        'phone' => 'Primary phone',
        'alternate_phone' => 'Alternate phone',
        'business_phone' => 'Business phone',
        'address_heading' => 'Home address',
        'address_street' => 'Street address',
        'address_city' => 'City',
        'address_province' => 'Province',
        'address_postal_code' => 'Postal code',
        'address_country' => 'Country',
        // Profile photo (#233). Public once uploaded; optional (no photo = initials).
        'photo' => 'Profile photo',
        'photo_hint' => 'JPG, PNG, or WebP, up to 5 MB. Cropped to a square automatically.',
        'photo_error_heic' => 'HEIC photos (the iPhone default) aren’t supported. On your phone, re-save or export the picture as JPG and upload that.',
        'photo_error_unsupported' => 'That file type isn’t supported. Please upload a JPG, PNG, or WebP image.',
        'photo_error_too_large' => 'That image is too large. Please upload a photo under :max MB.',
        'photo_remove' => 'Remove photo',
        'current_password' => 'Current password',
        'current_password_hint' => 'Confirm your current password to change your email address.',
        'unverified' => 'Your email address is unverified.',
        'resend' => 'Click here to re-send the verification email.',
        'verification_sent' => 'A new verification link has been sent to your email address.',
        'save' => 'Save',
        'saved' => 'Saved.',
    ],

    // Settings → Password page.
    'password' => [
        'title' => 'Password settings',
        'heading' => 'Update password',
        'description' => 'Ensure your account is using a long, random password to stay secure',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'save' => 'Save password',
        'saved' => 'Saved',
    ],
];
