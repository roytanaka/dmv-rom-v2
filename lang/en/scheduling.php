<?php

return [
    // The Sign-up cancellation email (#358, PRD #352, ADR-0021 §Sign-up "Notification") — the
    // first mail in the app. Chrome only: the Member name, Group name, and ShiftKind label are
    // as-authored content, passed in and rendered as-is (ADR-0004).
    'cancellation_email' => [
        'subject' => 'A shift has been dropped',
        'heading' => 'A shift has been dropped',
        'intro' => ':member has dropped their sign-up for a :group shift:',
        'footer' => 'The seat is now open again. No action is required unless you want to fill it.',
        'view_schedule' => 'View the schedule',
    ],
];
