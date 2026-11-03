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
    // The limit-reached Notice (#906, ADR-0034 §9). :name is the Invitation's name, as written.
    'invitation_full' => [
        'subject' => 'Guest limit reached: :name',
        'heading' => 'Your invitation is full',
        'intro' => ':name has reached its Guest limit.',
        'view_replies' => 'View the replies',
    ],
    // The mentor Notice (#920, ADR-0038 §4): the Records office moved a mentor's mentee from
    // Provisional or Pre-active to Active. :member is as-authored.
    'mentee_activated' => [
        'from' => 'DMV Records',
        'subject' => 'Member now Active: :member',
        'heading' => 'Your mentee is now Active',
        'intro' => 'The Records office has changed :member to Active.',
        'footer' => 'This message is for your information; no reply is expected.',
    ],
    // A Saved query's results (#924, ADR-0038 §7): the Legacy app's "Results of DMV Query".
    'saved_query_results' => [
        'from' => 'DMV Records',
        'subject' => 'Results of DMV query: :query',
        'heading' => 'Saved query results',
        'intro' => ':sender has sent you the results of the saved query “:query”.',
        'view' => 'Open the results',
        'footer' => 'The link opens after you sign in, for members of the Records office.',
    ],
    // The intake Notices (#931, ADR-0038 §8): an Applicant's status moved. :applicant, :mentor and
    // :group are as-authored.
    'applicant' => [
        'from' => 'DMV Membership',
        'mentor' => 'Mentor: :mentor',
        'placement' => 'Placement: :group',
        'reason' => 'Reason: :reason',
        'view_member' => 'Open the Member record',
        'view_applicant' => 'Open the Applicant record',
        'footer' => 'The links open after you sign in, for those who may see Applicants.',
    ],
    'applicant_accepted' => [
        'subject' => 'New DMV Provisional Member: :applicant',
        'heading' => 'New Provisional Member',
        'intro' => ':applicant has been accepted and is now a Provisional Member.',
    ],
    'applicant_placed' => [
        'subject' => 'Applicant placement: :applicant',
        'heading' => 'Applicant placed',
        'intro' => ':applicant has been placed in :group.',
    ],
    'applicant_yellow_card' => [
        'subject' => 'Yellow card completed: :applicant',
        'heading' => 'Yellow card completed',
        'intro' => ':applicant has completed the yellow card and is ready to go Active.',
    ],
    'applicant_incomplete' => [
        'subject' => 'Applicant withdrawn: :applicant',
        'heading' => 'Applicant marked Incomplete',
        'intro' => ':applicant has been marked Incomplete. Their Member record should be withdrawn.',
    ],
    // The Renewal reminder summary (#942, ADR-0038 §9): who one daily-pass run mailed, by series.
    // The names are as-authored.
    'renewal_reminder_summary' => [
        'from' => 'DMV Records',
        'subject' => 'Renewal reminder emails sent today',
        'heading' => 'Renewal reminders sent',
        'intro' => 'Today’s daily mail pass wrote these reminders for the :year renewal.',
        'series' => [
            'active-1' => '1st reminder, Active Members',
            'active-2' => '2nd reminder, Active Members',
            'sustaining-1' => '1st reminder, Sustaining Members',
            'sustaining-2' => '2nd reminder, Sustaining Members',
        ],
    ],
];
