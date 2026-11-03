<?php

namespace App\Enums;

/**
 * The grouping of a {@see Category} the Records office lists and filters by (ADR-0038
 * §4). Always derived by {@see Category::group()}, never stored. Past is not a group:
 * it is a Resigned or Deceased group Member whose personal data was removed.
 *
 * Backed string enum: the value keys the `member.category_group` label.
 */
enum CategoryGroup: string
{
    case Current = 'current';
    case Resigned = 'resigned';
    case Deceased = 'deceased';
    case Admin = 'admin';
    case NonMember = 'non_member';
    case Security = 'security';
}
