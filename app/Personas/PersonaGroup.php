<?php

namespace App\Personas;

/**
 * The functional grouping a catalogued {@see Persona} falls under — the section
 * headings the dev role-switcher groups its picker by (ADR-0009 dev half, PRD
 * #220). Presentation metadata only: it carries no authority, it just lets a
 * tester find "the Records steward" or "a departed member" quickly in the list.
 */
enum PersonaGroup: string
{
    case Operator = 'operator';
    case SuperTier = 'super_tier';
    case Officers = 'officers';
    case Stewards = 'stewards';
    case Roles = 'roles';
    case Standings = 'standings';
    case Negative = 'negative';

    /** A short human label for the picker section heading (dev-only, English). */
    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::SuperTier => 'Super-tier',
            self::Officers => 'Officers',
            self::Stewards => 'Stewards',
            self::Roles => 'Roles',
            self::Standings => 'Standings',
            self::Negative => 'Negative',
        };
    }
}
