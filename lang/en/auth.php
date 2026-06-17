<?php

// Authentication chrome (English). The top three keys are Laravel's framework
// defaults — they MUST stay (login-failure, wrong-password, and throttle messages
// resolve through them; LoginRequest reads auth.failed / auth.throttle). The
// `login` array is the ROM split-login screen copy (#157); French lives in
// lang/fr/auth.php, mirrored key-for-key. The department name stays English in
// both locales, consistent with institutional.php (PRD #104).
return [

    // Framework auth messages — do not remove or rename.
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // ROM split-login screen.
    'login' => [
        'heading' => 'Sign in',
        'welcome' => 'Welcome back to the Department of Museum Volunteers.',

        'email' => 'Email',
        'password' => 'Password',
        'forgot' => 'Forgot your password?',
        'submit' => 'Sign in',

        // Accessible names for the password show/hide toggle.
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',

        // Reception footer. `help_before` carries the phone (plain text, :phone)
        // and runs up to the mailto link; `help_email` is the link text; the
        // sentence-ending period is rendered in the template after the link.
        'help_before' => 'New to the Department of Museum Volunteers, or having trouble signing in? Call DMV Reception at :phone or',
        'help_email' => 'email the office',

        // Photo captions over the hero scrim.
        'photo_location' => 'Royal Ontario Museum · Toronto',
        'photo_credit' => 'Photo: Narciso Arellano / Unsplash',
    ],
];
