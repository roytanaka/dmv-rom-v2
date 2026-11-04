<?php

/*
 * The Renewal reminder chrome (#942, ADR-0038 §9). The body is the member admin's text from
 * Renewal settings, as authored; only the sender and subject are translated. The subject
 * follows the Legacy app's: "1st Reminder to complete DMV Membership Renewal for 2027/2028".
 */
return [
    'from' => 'DMV Records',
    'subject' => [
        'active-1' => '1st Reminder to complete DMV Membership Renewal for :year/:next - Active',
        'active-2' => '2nd Reminder to complete DMV Membership Renewal for :year/:next - Active',
        'sustaining-1' => '1st Reminder to complete DMV Membership Renewal for :year/:next - Sustaining',
        'sustaining-2' => '2nd Reminder to complete DMV Membership Renewal for :year/:next - Sustaining',
    ],
];
