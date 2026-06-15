<?php

namespace App\Enums;

/**
 * A Group's Scope — where it sits in the tree (ADR-0010). One of three
 * orthogonal axes (Kind / Scope / Lifecycle), stored explicitly.
 *
 * Backed string enum: the stored value is the case value.
 */
enum Scope: string
{
    case Organization = 'organization';
    case Program = 'program';
    case Subteam = 'subteam';
}
