<?php

namespace App\Enums;

/**
 * The breadth of access a Member's DMV-wide Category resolves to (ADR-0017).
 *
 * Resolved org-wide and independently of the sign-up floor: view-access and
 * sign-up-ability are orthogonal (a member on Leave of Absence has Full view
 * but cannot sign up). See Category::accessTier() / Category::canSignUp().
 */
enum AccessTier
{
    case Full;
    case Limited;
    case None;
}
