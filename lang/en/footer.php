<?php

// Footer chrome (English). The institutional copy the footer shows — land
// acknowledgement, inclusion statement, department name — lives in institutional.php
// (its own namespace, excluded from machine translation). This file holds only the
// footer's own structural string: the copyright line format. Mirrors lang/fr/footer.php.
return [
    // :start/:current are the copyright year range (computed at render); :org is the
    // department name, pulled from institutional.php so it stays English in both locales.
    'copyright' => '© :start–:current :org',
];
