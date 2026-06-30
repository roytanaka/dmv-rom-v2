<?php

// Avatar (account) menu labels (English) — the top-bar right slot (ADR-0013). Chrome
// strings, keyed so French drops in without touching the component. The EN/FR locale
// badge in the menu is a pair of locale codes, not translatable copy, so it stays a
// literal in the component. Mirrors lang/fr/user.php key-for-key.
return [
    'profile' => 'My Profile',
    // Renew Membership — an account/utility action in the avatar menu (#196), not a
    // primary top-bar destination. An outbound renewal action; its href is locale-aware.
    'renew' => 'Renew Membership',
    'language' => 'Language',
    'logout' => 'Log out',
];
