<?php

// Strings for automatic Notice mail (spec #479, ADR-0024 §8) — event-triggered mail the app
// sends without anyone composing it. Chrome, rendered in each recipient's saved locale (ADR-0004,
// ADR-0024 §3); the Member's and the Group's names render as-authored and never enter this lookup.
// Mirrors lang/fr/notices.php key-for-key.
return [
    // The standing-change Notice (#485): a Member's DMV-wide standing moved to Resigned or
    // Deceased, told to every Chair of every Group where the Member had not already departed, so
    // the Chair can update their own roster. :member and :group are as-authored; :standing is the
    // member.standing.* label resolved in the render locale; :date is the effective date.
    'standing_change' => [
        'subject' => 'Membership standing change: :member',
        'heading' => 'A change in membership standing',
        'intro' => 'The standing of :member in :group has changed to :standing, effective :date.',
        'footer' => 'Please update your roster as needed. This message is for your information; no reply is expected.',
        'view_roster' => 'View the group roster',
    ],
    // The empty-desk alert (#487): every third day of the month, a Group tells its present-
    // standing roster which watched Shifts still have nobody. :group is as-authored; the Shift
    // dates, times, and kind names render in the body, never through this lookup. `today` marks a
    // Shift dated the run day.
    'empty_desk' => [
        'subject' => 'Open shifts still need someone — :group',
        'heading' => 'These shifts still need someone',
        'intro' => 'The following :group shifts still have no one signed up:',
        'today' => 'today',
        'footer' => 'If you can take one of these, please sign up so the desk is covered.',
        'view_schedules' => 'View the schedule',
    ],
    // The Booking Request and Confirmation (#799, ADR-0032 §9). The Tour, client, leader and
    // comments are Booking content and render as written, never through this lookup; :group is
    // as-authored. `date_format` is the PHP date format for the tour's day in each language.
    'booking' => [
        'date_format' => 'l, j F Y',
        'client' => 'Client',
        'visitors' => 'Expected visitors',
        'leader' => 'Group leader',
    ],
    'booking_request' => [
        'subject' => 'Group tour request: :tour, :date',
        'heading' => 'A group tour needs docents',
        'intro' => ':group has a group tour that still needs docents. If you can give it, please sign up.',
        'seats_needed' => '{0} No more docents needed.|{1} :count docent still needed.|[2,*] :count docents still needed.',
        'view_schedule' => 'Sign up on the schedule',
    ],
    'booking_confirmation' => [
        'subject' => 'Group tour confirmed: :tour, :date',
        'heading' => 'Group tour confirmed',
        'intro' => 'This :group group tour is confirmed.',
        'docents' => 'Docents on this tour',
        'view_schedule' => 'View the schedule',
    ],
];
