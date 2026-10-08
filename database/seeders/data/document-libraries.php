<?php

/*
 * Demo Document libraries sampled from the legacy site (#727, spec #721, ADR-0030 §4),
 * keyed by Group slug. A sample, not a copy: real Folder and Document category names from
 * legacy, and a few small generated files under some of them. Titles that named a person
 * were dropped or renamed. A Group missing here gets the standard library.
 *
 * Each level (the root, or a Folder) has:
 * - `sections`: Document category name => its items. An empty list seeds an empty heading.
 * - `items`: items with no Document category, which show under Other.
 *
 * An item is a Folder (`name`, optional `visibility` on a top-level Folder, then its own
 * `sections` and `items`), a file (a plain filename string, or `file` with `lines` of text),
 * or a link (`link` and `title`). Legacy members-only Topics seed as `group` Folders; a
 * top-level Folder with no `visibility` is Group-only too. Every library also gets a
 * "Visiting the ROM" link at its root, with no Document category.
 *
 * Docents mirrors the legacy Data Sheets: a legacy Category is a top-level Folder, a Section
 * is a Document category, and a Tour is a Folder. Committee and Program libraries sample
 * the legacy general library: a Topic is a top-level Folder, a Sub-topic a Folder below it.
 */

return [
    'docents' => [
        'sections' => [
            'Data Sheets' => [
                [
                    'name' => 'Museum/Theme',
                    'visibility' => 'group',
                    'sections' => [
                        'Museum Tours' => [
                            ['name' => 'Museum Highlights', 'items' => [
                                ['file' => 'General - ROM Rotunda Ceiling.pdf', 'lines' => [
                                    'Stop: the Rotunda, ground floor.',
                                    'The mosaic ceiling uses Venetian glass tiles.',
                                ]],
                                'General - ROM Brief Building History.pdf',
                            ]],
                            ['name' => 'Family Museum'],
                            ['name' => 'Tactile'],
                            ['name' => 'Museum Highlights-ASL'],
                        ],
                        'Theme Tours' => [
                            ['name' => 'Architecture: 100 Years in the Making'],
                            ['name' => 'Queens, Goddesses & Femmes Fatales'],
                            ['name' => 'Super Powers of the Ancient Mediterranean World'],
                            ['name' => 'Fashion, Form and Function'],
                        ],
                        'Floor Highlights Tour' => [
                            ['name' => 'Floor Highlights Tour'],
                        ],
                        'Climate History, Climate Hope' => [],
                    ],
                ],
                [
                    'name' => 'Natural History',
                    'visibility' => 'members',
                    'sections' => [
                        'Earth Sciences' => [
                            ['name' => 'Gems and Gold'],
                            ['name' => "Earth's Treasures"],
                        ],
                        'Life Sciences' => [
                            ['name' => 'Dinosaurs', 'items' => [
                                ['file' => 'Dinosaurs - Gallery data sheet.pdf', 'lines' => [
                                    'Gallery: Dinosaurs, Level 2.',
                                    'Key objects: Barosaurus, Parasaurolophus, the Allosaurus skull.',
                                ]],
                            ]],
                            ['name' => 'Dinosaurs/Mammals'],
                            ['name' => 'Gallery of Biodiversity', 'items' => [
                                'Life in Crisis - Arctic - Polar Bear.pdf',
                                'Life in Crisis - Biodiversity Tour Outline.pdf',
                                'Life in Crisis - Biodiversity Bigs.pdf',
                            ]],
                            ['name' => '4.6 Billion Years of Earth History'],
                        ],
                        'Dawn of Life' => [],
                    ],
                ],
                [
                    'name' => 'World Culture',
                    'visibility' => 'group',
                    'sections' => [
                        'AAAP' => [
                            ['name' => 'Africa, the Americas and Asia-Pacific'],
                        ],
                        'Canadian Heritage' => [
                            ['name' => 'Canadian Heritage'],
                            ['name' => 'First Peoples Art and Culture'],
                            ['name' => 'Canada: Art and Culture'],
                        ],
                        'Cyprus, Bronze Age & Ancient Greece' => [
                            ['name' => 'Ancient Greece'],
                            ['name' => 'Cyprus and Bronze Age Aegean'],
                        ],
                        'East Asia - China and Korea' => [
                            ['name' => 'China', 'items' => [
                                'China - Bronzes.pdf',
                                'China - A Brief History.pdf',
                            ]],
                            ['name' => 'Korea'],
                            ['name' => 'East Asia'],
                        ],
                        'East Asia - Japan' => [
                            ['name' => 'Japan'],
                        ],
                        'Egypt & Nubia' => [
                            ['name' => 'Ancient Egypt & Nubia', 'items' => [
                                ['file' => 'Egypt - Sekhmet.pdf', 'lines' => [
                                    'Gallery: Egypt, Level 3.',
                                    'The lion-headed goddess of war and healing.',
                                ]],
                                'Nubia - Meroitic Writing.pdf',
                                'Egypt - Gallery Script.pdf',
                            ]],
                        ],
                        'Europe' => [
                            ['name' => 'Judaica'],
                            ['name' => 'European Decorative Arts'],
                        ],
                    ],
                ],
                [
                    'name' => 'Exhibition',
                    'visibility' => 'group',
                    'sections' => [
                        'Ultimate Dinosaurs' => [['name' => 'Ultimate Dinosaurs']],
                        'Mesopotamia' => [['name' => 'Mesopotamia']],
                        'Forbidden City' => [['name' => 'Forbidden City']],
                        'Pompeii' => [['name' => 'Pompeii']],
                        'Vikings' => [['name' => 'Vikings']],
                        'Great Whales' => [['name' => 'Great Whales']],
                        'Canadian Modern' => [['name' => 'Canadian Modern']],
                        'Sharks Exhibition' => [['name' => 'Sharks Exhibition']],
                        'Nature in Brilliant Colour' => [['name' => 'Nature in Brilliant Colour']],
                        'Exhibition' => [
                            ['name' => 'Quilts: Made in Canada'],
                            ['name' => 'Crawford Lake'],
                            ['name' => 'Shokkan'],
                        ],
                    ],
                ],
                [
                    'name' => 'Spot',
                    'visibility' => 'group',
                    'sections' => [
                        'Great Whales' => [['name' => 'Great Whales Spot']],
                        'Dawn of Life' => [['name' => 'Dawn of Life Spot']],
                        'Dinosaur' => [['name' => 'Dinosaur Spot']],
                        'Holiday' => [],
                        'Free Tuesday Night' => [],
                    ],
                ],
            ],
            'Publications' => [
                [
                    'name' => 'General Documents',
                    'visibility' => 'members',
                    'items' => [
                        ['name' => 'General Info', 'items' => [
                            'DMV Website - Docents - Signing In and Out.pdf',
                            'DMV Website - Docents - Scheduling.pdf',
                        ]],
                        ['name' => '2023 Docent Training Materials', 'items' => [
                            'Session 00 Agenda - Orientation.pdf',
                            'Session 00 Golden Eagle.pdf',
                        ]],
                        ['name' => '2019 Docent Training Materials', 'items' => [
                            'Session 00 Tell What - Show What - So What.pdf',
                        ]],
                        ['name' => '2016 Docent Training Materials', 'items' => [
                            'How to put together a tour stop.pdf',
                        ]],
                        ['name' => 'Eduspots', 'items' => [
                            'Nubia - A Riddle Wrapped in a Gallery inside a Museum - notes.pdf',
                            'Smoking and Pipes - slides only.pdf',
                        ]],
                    ],
                ],
                [
                    'name' => 'Evaluation / Vetting',
                    'visibility' => 'group',
                    'items' => [
                        ['name' => 'Information for Docents', 'items' => [
                            '2025 Guidelines for Museum Highlights Tours.pdf',
                            'Refresher - Where to find vetted materials.pdf',
                        ]],
                        ['name' => 'Information for Evaluators', 'items' => [
                            '2025 Docent evaluation form.pdf',
                            '2025 Notes for Evaluators.pdf',
                        ]],
                        ['name' => 'Chair Procedures', 'items' => [
                            '2023 Docent evaluation form for future updating.pdf',
                        ]],
                    ],
                ],
                ['name' => 'Adult Learning Principles and Recommended Practices', 'visibility' => 'members'],
                ['name' => 'Reimagining Guided Experiences', 'visibility' => 'members'],
                ['name' => 'Application for 2026 New Docent Training', 'visibility' => 'members', 'items' => [
                    'Application for 2026 New Docent Training.pdf',
                ]],
            ],
        ],
        'items' => [
            ['file' => 'Docent handbook.pdf', 'lines' => [
                'How a Docent tour runs, from the meeting point to the last stop.',
                'Required reading for every new Docent.',
            ]],
            ['link' => 'https://collections.rom.on.ca', 'title' => 'ROM Collections Online'],
        ],
    ],

    'dmv' => [
        'sections' => [
            'Handbooks & policies' => [
                ['name' => 'DMV Handbook', 'visibility' => 'members', 'items' => ['DMV Handbook.pdf']],
                ['name' => 'DMV Job Descriptions', 'visibility' => 'members'],
                ['name' => 'By-Law, Policies, Practices', 'visibility' => 'members', 'items' => ['DMV By-Law.pdf']],
                ['name' => 'Emergency Procedures', 'visibility' => 'members', 'items' => ['Emergency Procedures.pdf']],
            ],
            'Bulletins' => [
                ['name' => 'First Magnitude', 'visibility' => 'members', 'items' => ['First Magnitude - Fall issue.pdf']],
                ['name' => 'Special Exhibition Info and Briefings', 'visibility' => 'members'],
                ['name' => 'OpenROM Updates', 'visibility' => 'members'],
            ],
        ],
        'items' => [
            ['name' => 'Annual Meeting Documents', 'visibility' => 'group', 'items' => ['Annual Meeting agenda.pdf']],
            ['name' => 'Truth and Reconciliation', 'visibility' => 'members'],
        ],
    ],

    'awards' => [
        'sections' => [
            'Awards' => [
                ['name' => 'Ontario Volunteer Service Awards', 'visibility' => 'members', 'items' => ['Nomination guidelines.pdf']],
                ['name' => 'DMV Service Awards', 'visibility' => 'members'],
            ],
        ],
        'items' => [
            ['name' => 'Recognition', 'visibility' => 'group', 'items' => ['Recognition planning.pdf']],
        ],
    ],

    'records' => [
        'sections' => [
            'Procedures' => [
                ['name' => 'All Procedures - Revised 2024', 'visibility' => 'members', 'items' => ['All Procedures - Revised 2024.pdf']],
            ],
            'Records' => [
                ['name' => 'DMV Statistics', 'visibility' => 'members', 'items' => [
                    ['file' => 'Volunteer statistics.csv', 'lines' => ['Year,Members,Hours', '2024,512,61840', '2025,498,60210']],
                ]],
                ['name' => 'DMV Renewals', 'visibility' => 'members'],
                ['name' => 'Cheque Deposit Sheets', 'visibility' => 'group'],
                ['name' => 'Records Room Key Inventory', 'visibility' => 'group', 'items' => ['Key inventory.pdf']],
            ],
        ],
    ],

    'governance' => [
        'sections' => [
            'Policies' => [
                ['name' => 'DMV Practices', 'visibility' => 'group', 'items' => ['DMV Practices.pdf']],
                ['name' => 'DMV Policies April 12.22', 'visibility' => 'group'],
            ],
        ],
    ],

    'membership' => [
        'sections' => [
            'Training' => [
                ['name' => 'Provisional Training', 'visibility' => 'members', 'items' => ['Provisional Training outline.pdf']],
                ['name' => 'Mentor Info', 'visibility' => 'members'],
            ],
        ],
        'items' => [
            ['name' => 'Police Letters', 'visibility' => 'group'],
        ],
    ],

    'communications' => [
        'sections' => [
            'Reports' => [
                ['name' => 'DMV Board Reports', 'visibility' => 'members', 'items' => ['Board Report - September.pdf']],
            ],
        ],
        'items' => [
            ['name' => 'Background Documents', 'visibility' => 'members'],
        ],
    ],

    'dei-committee' => [
        'sections' => [
            'Committee business' => [
                ['name' => 'Notes from Meetings', 'visibility' => 'group', 'items' => ['Meeting notes - September.pdf']],
                ['name' => 'Terms of Reference and Work Plan 2023', 'visibility' => 'group'],
            ],
        ],
    ],

    'chairs-corner' => [
        'sections' => [
            'Procedures' => [
                ['name' => 'Website Roles and Procedures', 'visibility' => 'members', 'items' => ['Website Roles and Procedures.pdf']],
            ],
            'Templates' => [
                ['name' => 'Board Report Template', 'visibility' => 'group', 'items' => ['Board Report Template.txt']],
            ],
        ],
    ],

    'visitor-wayfinders' => [
        'sections' => [
            'Wayfinding' => [
                ['name' => '***Wayfinding Information***', 'visibility' => 'members', 'items' => [
                    ['name' => 'Hike the ROM', 'items' => ['Climate Change Hike the ROM.pdf', 'Walk on the wild side.pdf']],
                    ['name' => "ROM's Dynamic Pricing Model", 'items' => ['ROM Dynamic Pricing.pdf']],
                    ['name' => 'Clickers', 'items' => ['Clicker Info.pdf']],
                ]],
                ['name' => 'Digital Wayfinding', 'visibility' => 'members', 'items' => [
                    ['name' => 'Digital Wayfinding', 'items' => ['ROM Treasures - List of Iconic Objects.pdf']],
                ]],
            ],
            'Training' => [
                ['name' => 'Training', 'visibility' => 'members', 'items' => [
                    ['name' => 'Welcome and Orientation', 'items' => ['ROM Mobile App August 2024.pdf', 'How to scan a QR code.pdf']],
                ]],
            ],
        ],
        'items' => [
            ['name' => 'ROM in the News', 'visibility' => 'members', 'items' => [
                ['name' => 'Summer 2023', 'items' => [
                    ['link' => 'https://www.rom.on.ca/en/about-us/newsroom', 'title' => 'Royal Ontario Museum is Offering Free Admission This Summer'],
                ]],
            ]],
            ['name' => 'RAD-ROM After Dark', 'visibility' => 'members', 'items' => [
                ['name' => 'K-Pop September 16', 'items' => ['RAD Schedule.pdf', 'Floor Plans.pdf']],
            ]],
            ['name' => 'Original Files - ARCHIVE', 'visibility' => 'group', 'items' => [
                ['name' => 'Meetings Archive', 'items' => ['Meetings Archive index.txt']],
            ]],
        ],
    ],

    'blue-whale' => [
        'sections' => [
            'Training' => [
                ['name' => 'Additional Whale Facts', 'visibility' => 'group', 'items' => [
                    ['name' => 'Genetics', 'items' => ['Genetics Q and A.pdf']],
                    ['name' => 'Biology', 'items' => ['Biology Q and A.pdf']],
                ]],
            ],
        ],
    ],

    'zuul' => [
        'sections' => [
            'Training' => [
                ['name' => 'Zuul Overview', 'visibility' => 'group', 'items' => ['Zuul Exhibit Interpretive Plan.pdf']],
            ],
        ],
    ],

    'trex-spot-tours' => [
        'sections' => [
            'Training' => [
                ['name' => 'T. rex training materials', 'visibility' => 'group', 'items' => ['T. rex tour notes.pdf']],
            ],
        ],
    ],

    'osiris-rex-volunteers' => [
        'sections' => [
            'Training' => [
                ['name' => 'OSIRIS-REx Learning Materials', 'visibility' => 'group', 'items' => ['OSIRIS-REx mission overview.pdf']],
            ],
        ],
    ],

    'visitor-guides' => [
        'sections' => [
            'Visitor information' => [
                ['name' => 'General', 'visibility' => 'members', 'items' => [
                    ['name' => 'Assisting ROM Visitors', 'items' => ['Fact Sheet - Visitor Guides.pdf', 'WiFi Signal Strength in the ROM.pdf']],
                    ['name' => 'Galleries', 'items' => ['List of Galleries by Level.pdf']],
                    ['name' => 'Accessibility', 'items' => ['Accessibility Map of ROM.pdf']],
                ]],
            ],
            'Committee' => [
                ['name' => 'Internal', 'visibility' => 'members', 'items' => [
                    ['name' => 'Job Descriptions', 'items' => ['VG Co-Chair Job Description.pdf', 'VG Member Job Description.pdf']],
                    ['name' => 'Procedures', 'items' => ['Recording Volunteer Hours.pdf']],
                    ['name' => 'Visitor Guide Annual Report', 'items' => ['Annual Report 2021-2022.pdf']],
                ]],
                ['name' => 'Source Documents', 'visibility' => 'members', 'items' => [
                    ['name' => 'Training', 'items' => ['Visitor Guide Training Manual.pdf']],
                ]],
            ],
        ],
        'items' => [
            ['name' => 'ROM Exhibitions, Installations & Briefings', 'visibility' => 'group'],
        ],
    ],

    'friends-of-palaeontology-fop' => [
        'sections' => [
            'Committee business' => [
                ['name' => 'General Documents', 'visibility' => 'group', 'items' => [
                    ['name' => 'Financials', 'items' => ['Reimbursement form - attach receipts.pdf']],
                    ['name' => 'Annual General Meeting', 'items' => ['Agendum - 2019.pdf']],
                    ['name' => 'Job Descriptions', 'items' => ['Co-Chair.pdf', 'Secretary.pdf']],
                ]],
            ],
            'Learning' => [
                ['name' => 'Resources to Share', 'visibility' => 'members', 'items' => [
                    ['name' => 'Palaeo Articles', 'items' => ['Archaea.pdf', 'History of Life on Earth.pdf']],
                    ['name' => 'Quizzes', 'items' => ['Dino Name Quiz sign.pdf']],
                    ['name' => 'Online Course Notes - Dino 101', 'items' => ['Lesson 1 - Anatomy.pdf']],
                    ['name' => 'Exhibitions - Zuul', 'items' => [
                        ['link' => 'https://www.rom.on.ca/en/exhibitions-galleries', 'title' => 'Ankylosaurid dinosaur tail clubs'],
                    ]],
                ]],
            ],
        ],
        'items' => [
            ['name' => 'Palaeo News', 'visibility' => 'members', 'items' => [
                ['name' => 'Archive', 'items' => [
                    ['link' => 'https://www.rom.on.ca/en/collections', 'title' => 'Dinosaur mating dances'],
                ]],
            ]],
        ],
    ],

    'romforyou' => [
        'sections' => [
            'Presentations' => [
                ['name' => 'Presentations', 'visibility' => 'members', 'sections' => [
                    'Final Conventional Presentations' => [
                        ['name' => 'Celebration Conventional', 'items' => ['Celebration Conventional - Script.pdf', 'Celebration Conventional - Slides.pdf']],
                        ['name' => 'Building A Museum Conventional'],
                        ['name' => 'Climate History, Climate Hope Conventional'],
                    ],
                    'Final Adaptive Presentations' => [
                        ['name' => 'Celebration Adapted', 'items' => ['Celebration Adapted - Script.pdf']],
                        ['name' => 'A Royal Procession 45 min Adapted'],
                        ['name' => 'A Sporting Life Adapted'],
                    ],
                    'Work in Progress' => [
                        ['name' => 'Anomalocaris - revised'],
                    ],
                ]],
            ],
            'Objects' => [
                ['name' => 'RFY Objects', 'visibility' => 'members', 'items' => [
                    ['name' => 'Object Guides for Presentations', 'items' => ['MATERIAL WORLD Object Guide.pdf', 'CELEBRATION Object Guide.pdf']],
                    ['name' => 'RFY Object Inventory', 'items' => [
                        ['file' => 'RFY Object Inventory.csv', 'lines' => ['Object,Case,Status', 'Trilobite cast,A1,Available', 'Beaver pelt,B3,On loan', 'Pottery shard,C2,Available']],
                    ]],
                    ['name' => '"How To" Guides', 'items' => ['How To Reserve RFY Objects on website.pdf']],
                ]],
            ],
        ],
        'items' => [
            ['name' => 'Marketing', 'visibility' => 'group', 'items' => [
                ['name' => 'Flyers - Spring 2026', 'items' => ['ROMForYou Adapted Presentation List.pdf']],
            ]],
            ['name' => 'Training Materials', 'visibility' => 'members'],
        ],
    ],

    'romwalks' => [
        'sections' => [
            'Walk Data Sheets' => [
                ['name' => 'Walks', 'visibility' => 'members', 'sections' => [
                    'Active' => [
                        ['name' => 'Annex East', 'items' => ['Annex East - Script.pdf']],
                        ['name' => 'Rosedale II', 'items' => ['Rosedale II - Script.pdf', 'Rosedale II - Map.pdf']],
                        ['name' => 'Yorkville', 'items' => [
                            ['file' => 'Yorkville - Script.pdf', 'lines' => [
                                'Start: the ROM main entrance on Bloor Street.',
                                'Allow two hours.',
                            ]],
                            'Yorkville - Map.pdf',
                        ]],
                        ['name' => 'Sacred Stones and Steeples'],
                        ['name' => 'Whiskey, Wharf and Windmill', 'items' => ['Supplementary material.pdf']],
                    ],
                    'Inactive' => [
                        ['name' => 'Historic Toronto', 'items' => ['Historic Toronto - Script.pdf']],
                    ],
                ]],
            ],
            'Committee' => [
                ['name' => 'Script Vetting', 'visibility' => 'group', 'items' => ['Script Vetting Manual.pdf']],
                ['name' => 'Job Descriptions', 'visibility' => 'group', 'items' => ['ROMWalk Chair.pdf', 'Education Coordinator.pdf']],
            ],
        ],
        'items' => [
            ['name' => 'Webinars', 'visibility' => 'members', 'items' => [
                ['name' => 'Sacred Stones and Steeples', 'items' => ['Virtual ROMWalk - Slides.pdf', 'Virtual ROMWalk - Script.pdf']],
                ['name' => 'Waterfront', 'items' => ['Virtual Waterfront Walk.pdf']],
            ]],
            ['name' => 'Training', 'visibility' => 'members', 'items' => ['Ten Steps to Describe a Building.pdf']],
        ],
    ],

    'gallery-interpreters' => [
        'sections' => [
            'Information Packages' => [
                ['name' => 'General Museum', 'visibility' => 'members', 'items' => [
                    ['name' => 'Object Handling', 'items' => ['Handling Guidelines.pdf']],
                    ['name' => 'Training Materials', 'items' => ['Training Day 1 - Learning From Objects.pdf']],
                ]],
                ['name' => 'Natural History', 'visibility' => 'members', 'items' => [
                    ['name' => 'Earth Sciences', 'items' => [
                        ['file' => 'Agate - variety of Quartz.pdf', 'lines' => [
                            'Handling object: yes.',
                            'Agate forms in layers inside volcanic rock.',
                        ]],
                        'Basalt.pdf',
                    ]],
                    ['name' => 'Dawn Of Life', 'items' => ['Giant Sea Scorpion.pdf']],
                    ['name' => 'Birds', 'items' => ['Blue Jay.pdf']],
                ]],
                ['name' => 'World Cultures', 'visibility' => 'group', 'items' => [
                    ['name' => 'China', 'items' => ['Gold Leaf.pdf']],
                    ['name' => 'Korea', 'items' => ['Celadon Bowl.pdf']],
                ]],
                ['name' => 'Special Exhibition', 'visibility' => 'group', 'items' => [
                    ['name' => 'Pompeii: In the Shadow of the Volcano', 'items' => ['Pompeii Presentation.pdf']],
                ]],
                ['name' => 'Special Events', 'visibility' => 'group', 'items' => ['Special Events Briefing.pdf']],
            ],
        ],
    ],

    'gallery-interpreters-events' => [
        'sections' => [
            'Events' => [
                ['name' => 'Offsite Events Information', 'visibility' => 'group', 'items' => ['Offsite event checklist.pdf']],
            ],
        ],
    ],

    'guides-du-rom' => [
        'sections' => [
            'Fiches' => [
                ['name' => 'Le musée - histoire/architecture', 'visibility' => 'members', 'items' => [
                    ['name' => 'General Museum', 'items' => ['Histoire du musée.pdf', 'La rotonde.pdf']],
                ]],
                ['name' => 'Fiches - Sciences naturelles', 'visibility' => 'members', 'items' => [
                    ['name' => 'Early Life', 'items' => ['Anomalocaris.pdf']],
                    ['name' => 'Earth Sciences', 'items' => ['Météorite Canyon Diablo.pdf']],
                ]],
                ['name' => 'Fiches - Cultures du monde', 'visibility' => 'group', 'items' => [
                    ['name' => 'China', 'items' => ['Bronzes chinois.pdf']],
                    ['name' => 'Korea', 'items' => [
                        ['file' => 'Les céladons coréens.pdf', 'lines' => [
                            'Galerie : Corée, niveau 1.',
                            'Le céladon est un grès à glaçure vert pâle.',
                        ]],
                    ]],
                    ['name' => 'Japan', 'items' => ['Armures et casques de samouraïs.pdf']],
                ]],
            ],
            'Expositions' => [
                ['name' => 'Expositions temporaires', 'visibility' => 'group', 'items' => [
                    ['name' => 'Pompéi', 'items' => ['Divinités vénérées à Pompéi.pdf']],
                    ['name' => 'Mésopotamie', 'items' => ['Écriture cunéiforme.pdf']],
                ]],
            ],
        ],
    ],
];
