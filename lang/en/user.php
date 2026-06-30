<?php

// Avatar (account) menu labels (English) — the top-bar right slot (ADR-0013). Chrome
// strings, keyed so French drops in without touching the component. The EN/FR locale
// badge in the menu is a pair of locale codes, not translatable copy, so it stays a
// literal in the component. Mirrors lang/fr/user.php key-for-key.
return [
    'profile' => 'My Profile',
    'renew' => 'Renew Membership', // account-menu only, not a primary nav destination (#196)
    'language' => 'Language',
    'logout' => 'Log out',
];
