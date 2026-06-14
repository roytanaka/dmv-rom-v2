<?php

// Institutional voice (English) — ROM's official statements (land acknowledgement,
// inclusion statement) and the department name. Rendered in the footer chrome, but
// kept in their own namespace because they are reusable institutional copy AND
// because the machine-translation step excludes THIS file by name so an automated
// pass can never overwrite the official French (enforced + hardened in #111). The
// French baseline in lang/fr/institutional.php is provisional until ROM's approved
// wording is hand-authored. Mirrors lang/fr/institutional.php key-for-key.
return [
    // ROM's official English land acknowledgement (corrected working-tree wording).
    'land_acknowledgement' => 'ROM acknowledges that this museum sits on the ancestral lands of the Wendat, the Haudenosaunee Confederacy, and the Anishinaabek Nation, which includes the Mississaugas of the Credit First Nation, since time immemorial to today.',

    // The DMV inclusion statement (official English wording).
    'inclusion' => 'The DMV values all its members and recognizes the right of each to be treated with respect and courtesy without abuse, harassment or discrimination.',

    // Department name — stays English in BOTH locales until ROM supplies approved
    // French (PRD #104). Never machine-translated, never guessed.
    'department' => 'ROM Department of Museum Volunteers',
];
