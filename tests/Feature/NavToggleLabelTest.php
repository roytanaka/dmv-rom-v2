<?php

// Seam B/D — catalogue assertion for the nav chevron toggle's accessible-name key
// (#91). The split rail row's chevron must announce WHICH Group it expands, so a
// screen-reader user no longer hears the Group name twice with no cue. The label
// is chrome (translated); the `:group` value is the as-authored Group name
// (content interpolated into chrome, ADR-0004) and never a translation key.

it('interpolates the toggle label with the group name in English', function () {
    expect(__('nav.toggle', ['group' => 'Docents'], 'en'))
        ->toBe('Toggle Docents subgroups');
});

it('interpolates the toggle label with the group name in French', function () {
    expect(__('nav.toggle', ['group' => 'Docents'], 'fr'))
        ->toBe('Basculer les sous-groupes de Docents');
});

it('leaves a French-authored group name as-authored under both locales', function () {
    expect(__('nav.toggle', ['group' => 'Guides du ROM'], 'en'))
        ->toBe('Toggle Guides du ROM subgroups')
        ->and(__('nav.toggle', ['group' => 'Guides du ROM'], 'fr'))
        ->toBe('Basculer les sous-groupes de Guides du ROM');
});
