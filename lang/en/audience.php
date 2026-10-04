<?php

return [
    // Broadcast/Direct-message Audience names (ADR-0024 §5) — the label the composer
    // menu shows and the sent record keeps. The parameterised Audiences borrow their
    // label from elsewhere: One status from group.standing.*, One Category from
    // member.standing.*, a child Group's roster from the child's own name, and One
    // Member from the Member's name — all content, passed through as authored.
    // Mirrors lang/fr/audience.php.

    // Group-context roster and officer Audiences.
    'whole_group' => 'Whole group',
    'whole_group_on_leave' => 'Whole group and on leave',
    'group_active' => 'Active',
    'group_officers' => 'The group’s officers',
    'hand_picked' => 'Pick people…',

    // Scheduling Audiences.
    'sign_ups_schedule' => 'Sign-ups on :schedule',
    'sign_ups_shift' => 'Sign-ups on this shift',

    // Org-wide Audiences.
    'all_members' => 'All members',
    'all_members_on_leave' => 'All members and on leave',
    'active_provisional' => 'Active and provisional',

    // Leadership Audiences.
    'board_of_directors' => 'Board of Directors',
    'committee_chairs' => 'Committee Chairs',
    'all_chairs' => 'All Chairs',

    // The edited-Audience suffix, appended when the picker removed anyone
    // (ADR-0024 §6): the Audience name stays and the count of removals is named.
    'edited' => ':label, :count removed',

    // The Email control's empty-state reasons (#513, ADR-0024 §6) — shown in the greyed
    // button's hover tooltip and as the menu's one disabled line, so the reason nothing is
    // pickable is named rather than left blank. 'not_member' on a non-root Group the viewer
    // has not joined; 'not_org_wide_sender' on the root, whose Audiences are org-wide.
    'not_member' => 'Join this group to email its members.',
    'not_org_wide_sender' => 'Only Chairs and members of the Executive, Records, and Awards groups can email all of the DMV.',
];
