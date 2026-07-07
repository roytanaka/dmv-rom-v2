<?php

// Seam B/D — nav + section translation catalogue (#106). The nav chrome strings
// (rail / personal / officer headings and the Group-Menu section tabs) migrate out
// of the JS `messages.ts` shim into the Laravel lang files, the single source of
// truth for both PHP (__()) and Vue (laravel-vue-i18n `trans`). Group NAMES are
// content (ADR-0004): they render as-authored and must NOT appear as keys here.

it('resolves the personal (Zone A) nav keys under both locales', function () {
    expect(__('nav.personal.calendar', [], 'en'))->toBe('My Calendar')
        ->and(__('nav.personal.calendar', [], 'fr'))->toBe('Mon calendrier')
        ->and(__('nav.personal.news', [], 'en'))->toBe('News')
        ->and(__('nav.personal.news', [], 'fr'))->toBe('Nouvelles');
});

it('carries no Renew key in the personal nav set — it moved to the account menu (#196)', function () {
    expect(__('nav.personal', [], 'en'))->not->toHaveKey('renew')
        ->and(__('nav.personal', [], 'fr'))->not->toHaveKey('renew');
});

it('resolves the rail (Zone B) heading + container-peer keys under both locales', function () {
    // Browse Groups is the renamed browse zone (ADR-0020 §C — "All Groups" retired; label
    // finalized from the "Other Groups" working label to name the browse/discover function).
    // The `other_groups` key is retained; only the display string changed. Its four container
    // peers are chrome (i18n keys), distinct from the verbatim Group names nested beneath them.
    expect(__('nav.rail.other_groups', [], 'en'))->toBe('Browse Groups')
        ->and(__('nav.rail.other_groups', [], 'fr'))->toBe('Parcourir les groupes')
        ->and(__('nav.rail.peers.governance_operations', [], 'en'))->toBe('Governance & Operations')
        ->and(__('nav.rail.peers.programs', [], 'fr'))->toBe('Programmes')
        ->and(__('nav.rail.peers.friends', [], 'en'))->toBe('Friends')
        ->and(__('nav.rail.peers.special_projects', [], 'fr'))->toBe('Projets spéciaux');
});

it('retires the old All Groups rail key under both locales (ADR-0020)', function () {
    expect(__('nav.rail', [], 'en'))->not->toHaveKey('all_groups')
        ->and(__('nav.rail', [], 'fr'))->not->toHaveKey('all_groups');
});

it('resolves the officer (Zone C) nav keys under both locales', function () {
    expect(__('nav.officer.members', [], 'en'))->toBe('Members')
        ->and(__('nav.officer.members', [], 'fr'))->toBe('Membres')
        ->and(__('nav.officer.dmv_settings', [], 'en'))->toBe('DMV Settings')
        ->and(__('nav.officer.dmv_settings', [], 'fr'))->toBe('Paramètres du DMV');
});

it('resolves the Group-Menu section tab keys under both locales', function () {
    expect(__('section.about', [], 'en'))->toBe('About')
        ->and(__('section.about', [], 'fr'))->toBe('À propos')
        ->and(__('section.docents.schedule', [], 'en'))->toBe('Schedule')
        ->and(__('section.docents.schedule', [], 'fr'))->toBe('Horaire');
});

it('carries no Group-name translation keys in the nav catalogue (chrome/content split)', function () {
    // Group names are content — never a translation key (ADR-0004). The fixture's
    // old `nav.group.*` stand-in keys are removed; assert the catalogue has no
    // `group` namespace in either locale.
    expect(__('nav', [], 'en'))->not->toHaveKey('group')
        ->and(__('nav', [], 'fr'))->not->toHaveKey('group');
});
