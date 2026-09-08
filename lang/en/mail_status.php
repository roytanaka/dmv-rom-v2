<?php

// The Mail status page (#492, ADR-0024 §10) — super-tier only. Chrome strings, keyed so
// French drops in without touching the component. Mirrors lang/fr/mail_status.php key-for-key.
return [
    'title' => 'Mail status',
    'subtitle' => 'The mail queue and the cron that drains it.',
    'never' => 'Never',
    'scheduler_last_ran' => 'Scheduler last ran',
    'mail_last_sent' => 'Mail last sent',
    'pending' => 'Pending in queue',
    'last_error' => 'Last connection error',
    'no_error' => 'None',
    'dead' => [
        'title' => 'Cron looks dead',
        'body' => 'The scheduler has not checked in for over ten minutes. Mail is not going out.',
    ],
    'cannot_send' => [
        'title' => 'Cannot send',
        'body' => 'The last connection to the mail host failed after the last successful send.',
    ],
];
