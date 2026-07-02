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

    // Delete-account panel (components/DeleteUser.vue).
    'delete' => [
        'heading' => 'Delete account',
        'description' => 'Delete your account and all of its resources',
        'warning' => 'Warning',
        'warning_detail' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'confirm_title' => 'Are you sure you want to delete your account?',
        'confirm_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'password' => 'Password',
        'cancel' => 'Cancel',
    ],
];
