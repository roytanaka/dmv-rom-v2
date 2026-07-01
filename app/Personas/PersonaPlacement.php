<?php

namespace App\Personas;

use App\Enums\MembershipStatus;
use App\Enums\Role;

/**
 * One Group membership a {@see Persona} is seeded with: the Group (by slug), the
 * within-Group standing, and any roles carried on that membership. A Persona may
 * hold several placements (e.g. the multi-group Member, Full in two programs).
 */
final class PersonaPlacement
{
    /**
     * @param  list<Role>  $roles
     */
    public function __construct(
        public readonly string $groupSlug,
        public readonly MembershipStatus $status = MembershipStatus::Full,
        public readonly array $roles = [],
    ) {}
}
