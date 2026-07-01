<?php

namespace App\Personas;

use App\Enums\Category;

/**
 * A single catalogued Persona (ADR-0009 dev half, PRD #220): a realistic-identity
 * seed account curated to exercise one authorization gate or membership standing.
 * The {@see PersonaCatalogue} is the single source of truth for the set of them;
 * this value object carries everything three consumers need — how the Persona is
 * seeded (identity, category, super-tier, Group placements), how it labels in the
 * dev switcher ({@see $descriptor} + functional {@see $group}), and — via the
 * catalogue's allowlist — whether the switcher may become it.
 */
final class Persona
{
    /**
     * @param  list<PersonaPlacement>  $placements
     */
    public function __construct(
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly PersonaGroup $group,
        public readonly string $descriptor,
        public readonly Category $category = Category::Active,
        public readonly bool $superTier = false,
        public readonly array $placements = [],
    ) {}

    /** The display name — "First Last" — shown in the switcher's picker row. */
    public function name(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }
}
