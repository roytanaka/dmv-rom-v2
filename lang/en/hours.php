<?php

// Strings for the Group Hours tab (#408, PRD #406, ADR-0022) — the extra-hours entry
// surface and the Member's own record list. Chrome strings, translated like all chrome
// (ADR-0004); the Group's own name renders as-authored and never enters this lookup.
// Mirrors lang/fr/hours.php key-for-key.
return [
    // The entry form — the two-month additive dialog.
    'entry' => [
        'heading' => 'Record extra hours',
        // The one thing a Member must not double-count: shifts and meetings are counted
        // for them already, so extra hours are only the work outside those.
        'counted_note' => 'Scheduled shifts and meetings are already counted. Only add hours worked outside them.',
        // Additive: the number entered is added to what is on file.
        'help' => 'Hours you enter are added to the total already on file. Enter a negative number to correct a mistake.',
        'on_file' => 'On file: :hours',
        'last_updated' => 'Last updated :date',
        'never_updated' => 'Nothing recorded yet',
        'hours_label' => 'Hours to add',
        'add' => 'Add hours',
        // Surfaced from the Form Request when a decimal is entered (story 17).
        'whole_hours' => 'Enter whole hours — we are not concerned with minutes.',
    ],

    // The Member's own record list below the form.
    'records' => [
        'heading' => 'Your hours in this group',
        'empty' => 'You have not recorded any hours in this group yet.',
        'column' => [
            'month' => 'Month',
            'scheduled' => 'Scheduled',
            'extra' => 'Extra',
            'total' => 'Total',
            'updated' => 'Last updated',
        ],
    ],
];
