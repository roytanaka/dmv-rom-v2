<?php

// Footer chrome (English). The institutional copy the footer shows — land
// acknowledgement, inclusion statement, department name — lives in institutional.php
// (its own namespace, excluded from machine translation). This file holds only the
// footer's own structural strings: the copyright line and the app version. Mirrors
// lang/fr/footer.php.
return [
    // :start/:current are the copyright year range (computed at render); :org is the
    // department name, pulled from institutional.php so it stays English in both locales.
    'copyright' => '© :start–:current :org',
    // The deployed version (#673): :commit is the short git commit id, :date the deploy time.
    'version' => 'Version :commit, :date',
    // Local development has no deployed version.
    'version_dev' => 'Version dev',
];
