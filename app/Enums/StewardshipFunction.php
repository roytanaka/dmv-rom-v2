<?php

namespace App\Enums;

/**
 * An org-wide system function a Group stewards (PRD #126, slice 5). A Group that
 * stewards a function operates a service the rest of the DMV depends on — the
 * Records Group stewards `member_admin`, so member-administration authority is
 * membership in that Group, not a standalone flag (ADR-0011: authority stays
 * explicit and per-Group).
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum StewardshipFunction: string
{
    case MemberAdmin = 'member_admin';
    case Statistics = 'statistics';
    case Website = 'website';
}
