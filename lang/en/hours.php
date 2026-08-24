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

    // Recalculating a Group's scheduled hours from its Sign-ups (#410, ADR-0022 §2) — the
    // officer control on a scheduling Group and its closed-year refusal.
    'recalc' => [
        'button' => 'Recalculate scheduled hours',
        // Surfaced from the Form Request when a month outside the current fiscal year is asked.
        'closed_year' => 'Scheduled hours can only be recalculated for the current fiscal year.',
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

    // The My Hours destination (#409, ADR-0022 §8) — the Member's own hours across every
    // Group, by month across a fiscal year, with a year-to-date total.
    'mine' => [
        // The renewal question this surface answers.
        'lead' => 'Every group you have hours in this fiscal year.',
        // The fiscal-year picker. The label names the year the fiscal year ends in.
        'pick_year' => 'Fiscal year',
        'fiscal_year' => 'Fiscal :year',
        'column' => [
            'month' => 'Month',
            'scheduled' => 'Scheduled',
            'extra' => 'Extra',
            'total' => 'Total',
        ],
        'ytd' => 'Year to date',
        'empty' => [
            'heading' => 'No hours recorded yet',
            'body' => 'When you record hours on a group, they will appear here.',
        ],
    ],

    // The Group fiscal-year report (#411, ADR-0022 §5) — a Member × twelve-month matrix with
    // the Group's own hours and its subtree hours side by side, for a Chair or Statistician.
    'report' => [
        'title' => 'Hours report',
        // The officer-only link from the Group Hours tab to this report.
        'view' => 'View the group hours report',
        'lead' => 'Every member\'s hours this fiscal year, and how the group totals up.',
        'pick_year' => 'Fiscal year',
        'fiscal_year' => 'Fiscal :year',
        'column' => [
            'member' => 'Member',
            'ytd' => 'Year to date',
        ],
        // The two side-by-side rollups below the matrix.
        'own' => 'This group',
        'subtree' => 'Including sub-groups',
        // A fiscal year in which the group recorded nothing.
        'empty' => 'No hours recorded for this group in this fiscal year.',
    ],

    // The three officer surfaces after the fiscal-year matrix (#412, ADR-0022 §8): a month
    // picker, a Member History, and the two Member × twelve-month summaries.
    'detail' => [
        // The nav that links the sibling officer reports to one another.
        'nav' => [
            'report' => 'Fiscal year',
            'month' => 'By month',
            'member' => 'Member history',
            'extra' => 'Extra hours',
            'meetings' => 'Meeting hours',
        ],
        // The month picker — one month's entries across the group, by Member.
        'month' => [
            'title' => 'Hours by month',
            'lead' => 'One month\'s entries across the group, member by member.',
            'pick' => 'Month',
            'column' => [
                'member' => 'Member',
                'scheduled' => 'Scheduled',
                'extra' => 'Extra',
                'total' => 'Total',
            ],
            'empty' => 'No hours recorded for this group in this month.',
        ],
        // Member History — one person's hours in this group over time.
        'member' => [
            'title' => 'Member history',
            'lead' => 'One member\'s hours in this group over time.',
            'pick' => 'Member',
            'none' => 'Pick a member to see their history.',
            'column' => [
                'month' => 'Month',
                'scheduled' => 'Scheduled',
                'extra' => 'Extra',
                'total' => 'Total',
            ],
            'empty' => 'This member has no hours recorded in this group.',
        ],
        // Member Extra Hours — what people told us they did (rows carrying no meeting).
        'extra' => [
            'title' => 'Member extra hours',
            'lead' => 'Extra hours each member recorded this fiscal year — the work outside a shift.',
        ],
        // Member Meeting Hours — what people showed up to (rows carrying a meeting).
        'meetings' => [
            'title' => 'Member meeting hours',
            'lead' => 'Meeting hours each member attended this fiscal year.',
        ],
        // Shared by both summaries.
        'summary' => [
            'pick_year' => 'Fiscal year',
            'fiscal_year' => 'Fiscal :year',
            'column' => [
                'member' => 'Member',
                'ytd' => 'Year to date',
            ],
            'empty' => 'No hours recorded for this group in this fiscal year.',
        ],
    ],

    // The six DMV-wide fiscal-year reports (#413, ADR-0022 §8) — the fiscal-year statistics the
    // ROM asks the DMV for. Org-wide, gated to the DMV officers, Records, or the super-tier.
    'dmv' => [
        // The nav that links the six reports to one another.
        'nav_label' => 'DMV hours reports',
        'nav' => [
            'summary' => 'Summary',
            'detailed' => 'Detailed',
            'ranked' => 'Ranked hours',
            'zero_hours' => 'Zero hours',
            'zero_shift' => 'Zero shift hours',
            'zero_extra' => 'Zero extra hours',
        ],
        'pick_year' => 'Fiscal year',
        'fiscal_year' => 'Fiscal :year',

        // Summary Committee Statistics — scheduled hours by committee, then org-wide rows.
        'summary' => [
            'title' => 'Summary Committee Statistics',
            'lead' => 'Scheduled hours by committee across the fiscal year, with org-wide totals below.',
            'scheduled' => 'Scheduled hours',
            'meetings' => 'Meeting hours',
            'extra' => 'Extra hours',
            'total' => 'Grand total',
            'column' => [
                'committee' => 'Committee',
                'ytd' => 'Year to date',
            ],
            'empty' => 'No committee runs scheduling yet.',
        ],

        // Detailed Committee Statistics — each committee broken into shifts, meetings, and extra.
        'detailed' => [
            'title' => 'Detailed Committee Statistics',
            'lead' => 'Each committee\'s shifts, meetings, and extra hours across the fiscal year.',
            'kind' => [
                'shifts' => 'Shifts',
                'meetings' => 'Meetings',
                'extra' => 'Extra',
            ],
            'total' => 'DMV total — including every sub-group',
            'column' => [
                'committee' => 'Committee',
                'kind' => 'Kind',
                'ytd' => 'Year to date',
            ],
            'empty' => 'No hours recorded across the DMV in this fiscal year.',
        ],

        // Active Members Ranked Hours — every active and provisional Member by total, most first.
        'ranked' => [
            'title' => 'Active Members Ranked Hours',
            'lead' => 'Active and provisional members ordered by their total hours this fiscal year.',
            'no_hours' => 'Members with no hours recorded at all',
            'none_missing' => 'Every active and provisional member has hours this fiscal year.',
            'empty' => 'No active or provisional member has hours in this fiscal year.',
            'column' => [
                'member' => 'Member',
                'scheduled' => 'Scheduled',
                'extra' => 'Extra',
                'total' => 'Total',
            ],
        ],

        // The three zero-hours reports — one shared page, the variant naming the list.
        'zero' => [
            'lead' => 'Active and provisional members with none this fiscal year.',
            'empty' => 'No one — every active and provisional member has some this fiscal year.',
            'column' => [
                'member' => 'Member',
            ],
            'hours' => ['title' => 'Members with Zero Hours'],
            'shift' => ['title' => 'Members with Zero Shift Hours'],
            'extra' => ['title' => 'Members with Zero Extra Hours'],
        ],
    ],
];
