<?php

// The Member's Renewal (#939, ADR-0038 §9): the steps and the code of conduct. The code of conduct
// is the Legacy app's text, kept as a translation string (not edited in-app). Mirrors
// lang/fr/renewal.php.
return [
    'title' => 'Renew your membership',
    'period' => 'April 1, :year to March 31, :next',
    'next' => 'Next',
    'step' => [
        'form' => 'Your renewal form',
        'skills' => 'Your skills',
        'payment' => 'Payment',
    ],
    // Step 2, the renewal form (#940). Field labels come from records.field.*.
    'form' => [
        'section' => [
            'rom' => 'ROM membership',
            'name' => 'Name',
            'address' => 'Address',
            'phones' => 'Phones',
            'placements' => 'Your placements',
            'other' => 'Other placements',
            'emergency' => 'Emergency contact',
            'category' => 'Membership category',
        ],
        'confirm_number' => 'I confirm my ROM membership number',
        'expired' => 'Your ROM membership has expired. Please renew it with the ROM.',
        'other_group' => 'Other placement :number',
        'other_position' => 'Position :number',
        'other_text' => 'Other placement or committee :number',
        'none' => 'None',
        'no_placements' => 'You have no current placements.',
        'positions' => [
            'Member' => 'Member',
            'Chair' => 'Chair',
            'Co-Chair' => 'Co-Chair',
            'Vice-Chair' => 'Vice-Chair',
            'Auxiliary' => 'Auxiliary',
        ],
        'verify_emergency' => 'I verify my emergency contact is current',
        'category' => 'Category',
        'choose' => 'Choose a category',
        'resign' => 'I wish to resign from the DMV',
        'placement_required' => 'Confirm at least one placement, or choose Sustaining, Leave of Absence or Resign.',
        'save' => 'Save',
    ],
    // The end of a Renewal Records follows up in person: Resign, LOA, Active to Sustaining (#940).
    'contact' => [
        'heading' => 'Thank you',
        'body' => 'A member of DMV Records will be in touch with you. This renewal stays open until then.',
    ],
    // Step 4, the payment page (#941): the Legacy app's `_paymentText`. Nothing comes back from
    // the ROM's page; Records records the payment by hand.
    'payment' => [
        'thanks' => 'Thank you for filling in your renewal.',
        'fee' => 'Your membership donation',
        'card_heading' => 'By credit or debit card',
        'card' => 'Make your membership donation of :amount on the ROM website. When you complete the payment, you will receive a thank-you letter from the DMV and a tax receipt from the ROM.',
        'link' => 'ROM donation page',
        'cheque_heading' => 'By cheque',
        'cheque' => 'Write your name and the amount of your DMV dues, plus any optional extra donation, on the cheque. Make it payable to “ROM DMV” and mail it to: Department of Museum Volunteers, 100 Queens Park, Toronto, ON M5S 2C6. You will receive a tax receipt from the ROM later.',
        'cash' => 'Payment by cash is not possible.',
        'no_fee' => 'DMV Records will confirm your fee with you.',
    ],
    'code_of_conduct' => [
        'title' => 'Code of Conduct',
        'preamble_heading' => 'Preamble',
        'preamble' => 'Members of the DMV have duties, responsibilities and opportunities. Volunteers at all levels, whether members of a Section/Committee or in a leadership position, must be familiar with and adhere to the Code of Conduct.',
        'policy_heading' => 'Policy',
        'policy' => 'The DMV values teamwork in the achievement of its objectives. Accordingly, the DMV embraces a working environment that recognizes individual integrity, commitment, co-operation, reliability, initiative, excellence and the highest ethical standards. To create such an environment, members must abide by the following Code of Conduct.',
        'duties_intro' => 'In the performance of your duties as a volunteer, you will:',
        'duties' => [
            'Treat all visitors, DMV members, ROM staff, other volunteers and partners with respect, tact, courtesy, sensitivity and professionalism, without abuse, harassment, sexual harassment, discrimination or violence.',
            'Respect diversity in visitors, DMV members, ROM staff and other volunteers.',
            'Use your skills, abilities and experiences for the benefit of the DMV and ROM, while acting ethically and with the highest degree of honesty, integrity, collegiality and professionalism.',
            'Represent the DMV and ROM in a positive manner and support DMV and ROM management decisions.',
            'Perform your volunteer assignments safely and take appropriate action to minimize any safety risk, including contacting ROM Security when necessary.',
            'Report for volunteer duties neither impaired by, nor appearing to be impaired by, substance use and remain so throughout the duration of those duties.',
            "Respect and protect DMV and ROM property and facilities and use them only in the DMV's and ROM's interests.",
            'Refrain from divulging any DMV or ROM confidential information unless you are required to do so to perform your duties, or you are legally required to do so.',
            'Abstain from using any information obtained as a DMV member for the advantage of any individual or institution other than ROM.',
            'Adhere to the DMV By-Law, Policies, Practices and strategic objectives set by the Board and comply with all legislation, rules and regulations applicable to the DMV and ROM.',
            'Avoid conflicts of interest and perceptions of conflicts of interest as far as possible. If either should occur, recognize and declare it promptly.',
            'Distinguish clearly between DMV member roles and ROM staff roles and refrain from any interference, or any appearance of interference, with institutional operations.',
            'Work through the President, DMV Board or Section/Committee Chairs if any assistance from ROM staff is required, unless direct staff contact is sanctioned by the President, Board or Section/Committee Chair and/or is an established Committee practice.',
        ],
        'closing' => 'Failure to comply with the foregoing Code of Conduct may, at the discretion of the DMV Board, result in disciplinary action up to and including revocation of DMV membership.',
        'accept' => 'I have read and agree to adhere to this Code of Conduct',
    ],
];
