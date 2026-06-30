<?php

// Chrome navigation strings (English). Source of truth for both PHP (__()) and
// Vue (laravel-vue-i18n `trans`). Only chrome is translated; Volunteer-authored
// Group names render as-authored and never enter this lookup (ADR-0004).
//
// Rail headings, the Zone A personal tab set, and the Zone C officer cluster all
// live here (#106, migrated from resources/js/chrome/messages.ts). Group NAMES are
// content and are NOT keyed here — they render as-authored from the nav fixture.
return [
    'rail' => [
        'my_groups' => 'My Groups',
        'all_groups' => 'All Groups',
        'officer' => 'Officer Tools',
    ],

    // Zone A — personal/global (the Dashboard top-bar tab set). Renew Membership is
    // not here: it is an account/utility action, so its label lives in user.php (#196).
    'personal' => [
        'calendar' => 'My Calendar',
        'hours' => 'My Hours',
        'directory' => 'Directory',
        'documents' => 'Documents',
        'news' => 'News',
        'profile' => 'My Profile',
    ],

    // Top-bar utility — the Help destination in the right cluster (#194).
    'help' => 'Help',

    // Zone C — officer/admin cluster (rail, pinned bottom, officer-only).
    'officer' => [
        'members' => 'Members',
        'communications' => 'Communications',
        'reports' => 'Reports',
        'flash_messages' => 'Flash Messages',
        'dmv_settings' => 'DMV Settings',
    ],

    // Accessible name for the split-rail chevron that expands/collapses a Group's
    // subgroups (#91). Distinct from the sibling nav link so a screen reader does
    // not announce the Group name twice. `:group` is the as-authored Group name
    // (content), interpolated into chrome — never a translation key.
    'toggle' => 'Toggle :group subgroups',
];
