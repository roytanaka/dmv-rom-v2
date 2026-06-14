<?php

// Seam B — footer / user-menu / institutional translation catalogue (#107). These
// chrome strings migrate out of the JS `messages.ts` shim into namespaced Laravel
// lang files (footer.php, user.php, institutional.php), the single source of truth
// for both PHP (__()) and Vue (laravel-vue-i18n `trans`). The institutional file is
// hand-authored and excluded from the machine-translation step (hardened in #111);
// here we assert the migrated keys resolve and the institutional invariants hold.

it('resolves the avatar-menu (user) keys under both locales', function () {
    expect(__('user.profile', [], 'en'))->toBe('My Profile')
        ->and(__('user.profile', [], 'fr'))->toBe('Mon profil')
        ->and(__('user.language', [], 'en'))->toBe('Language')
        ->and(__('user.language', [], 'fr'))->toBe('Langue')
        ->and(__('user.logout', [], 'en'))->toBe('Log out')
        ->and(__('user.logout', [], 'fr'))->toBe('Se déconnecter');
});

it('renders the footer copyright line with the year range and department', function () {
    $replacements = ['start' => 2011, 'current' => 2026, 'org' => __('institutional.department', [], 'en')];

    expect(__('footer.copyright', $replacements, 'en'))
        ->toBe('© 2011–2026 ROM Department of Museum Volunteers');
});

it('carries the official English institutional statements verbatim', function () {
    expect(__('institutional.land_acknowledgement', [], 'en'))
        ->toBe('ROM acknowledges that this museum sits on the ancestral lands of the Wendat, the Haudenosaunee Confederacy, and the Anishinaabek Nation, which includes the Mississaugas of the Credit First Nation, since time immemorial to today.')
        ->and(__('institutional.inclusion', [], 'en'))
        ->toBe('The DMV values all its members and recognizes the right of each to be treated with respect and courtesy without abuse, harassment or discrimination.');
});

it('keeps the department name in English under both locales until ROM approves French', function () {
    expect(__('institutional.department', [], 'en'))->toBe('ROM Department of Museum Volunteers')
        ->and(__('institutional.department', [], 'fr'))->toBe('ROM Department of Museum Volunteers');
});

it('ships a French baseline for the institutional statements (hardened to official verbatim in #111)', function () {
    expect(__('institutional.land_acknowledgement', [], 'fr'))
        ->not->toBe('institutional.land_acknowledgement')
        ->and(__('institutional.inclusion', [], 'fr'))
        ->not->toBe('institutional.inclusion');
});
