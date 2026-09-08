<?php

// Strings for the member record page (#154). The directory/profile UI is a later
// slice; this is the thin page over the centralized MemberResource. Chrome strings,
// translated like all chrome (ADR-0004). Mirrors lang/fr/member.php.
return [
    'title' => 'Member',
    'contact' => 'Contact details',
    'contact_restricted' => 'Contact details are not available to you.',
    'email' => 'Email',
    'phone' => 'Phone',
    'groups' => 'Groups & roles',
    'no_groups' => 'No group memberships.',

    // The Direct-message affordance (#491, ADR-0024 §6): any Member may write to one other from
    // their profile. Hidden on the viewer's own profile. Named by first name, as the button reads.
    'message' => 'Message :name',

    // Records-only member-administration controls (#483, ADR-0024 §9). Shown only when
    // the viewer may administer members — the MemberResource gates the underlying data.
    'administration' => 'Member administration',
    'no_email' => 'No email',
    'no_email_help' => 'Silence every email to this member — Broadcasts, Direct messages, Reminders, and Notices.',

    // DMV-wide standing labels (the Member's Category), keyed by the enum value the
    // MemberResource exposes as `standing`. Drives the directory/profile standing
    // badge. Mirrors lang/fr/member.php.
    'standing' => [
        'active' => 'Active',
        'pre_active' => 'Pre-active',
        'provisional' => 'Provisional',
        'sustaining' => 'Sustaining',
        'honourary' => 'Honourary',
        'loa' => 'On leave',
        'withdrawn' => 'Withdrawn',
        'resigned' => 'Resigned',
        'deceased' => 'Deceased',
    ],
];
