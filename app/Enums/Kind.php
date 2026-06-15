<?php

namespace App\Enums;

/**
 * A Group's Kind — what it is *for* (ADR-0010). One of three orthogonal axes
 * (Kind / Scope / Lifecycle), stored explicitly and never derived from depth or
 * from which capability flags are on: a Program with scheduling temporarily off
 * is still a Program.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum Kind: string
{
    case StandingCommittee = 'standing_committee';
    case Program = 'program';
    case WorkingGroup = 'working_group';
    case Project = 'project';
    case Cohort = 'cohort';
}
