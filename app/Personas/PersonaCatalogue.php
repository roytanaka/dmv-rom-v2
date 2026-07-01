<?php

namespace App\Personas;

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use Database\Seeders\DemoSeeder;

/**
 * The persona catalogue (ADR-0009 dev half, PRD #220 / #221): the single source
 * of truth for the curated dev/QA Personas the role-switcher operates on. A code
 * artifact by design — not a DB column, not the dropped `is_persona` flag — since
 * staging never holds real data, curation belongs in code where it can't drift.
 *
 * Three consumers read from here, so all three stay in sync by construction:
 *   1. {@see DemoSeeder} seeds exactly these Personas.
 *   2. the switcher's grouped picker (its list, labels and functional grouping).
 *   3. the impersonation allowlist ({@see emails()} / {@see has()}) — the switcher
 *      refuses to become any account not catalogued here.
 *
 * Group slugs below match the curated tree in {@see DemoSeeder}
 * (a slug that fails to resolve surfaces loudly when the seeder runs). Emails are
 * realistic `first.last@dmv.test` with a known dev password.
 */
final class PersonaCatalogue
{
    // Group slugs the placements target — kept in step with the curated tree.
    private const ROOT = 'dmv';

    private const COMMITTEE = 'governance-operations';

    private const EXECUTIVE = 'executive';

    private const COMMUNICATIONS = 'communications';

    private const RECORDS = 'records';

    private const DOCENTS = 'docents';

    private const RECEPTION = 'reception';

    // Stable email handles the seeder re-exports for its historical constants.
    public const CHAIR_EMAIL = 'oliver.bennett@dmv.test';

    public const SCHEDULER_EMAIL = 'james.tremblay@dmv.test';

    /**
     * Every catalogued Persona, in picker order (grouped by function).
     *
     * @return list<Persona>
     */
    public static function all(): array
    {
        return [
            // Super-tier — the QA operators the switcher runs from (President is
            // the canonical login); force-filled super_tier, in the Executive Group.
            new Persona('margaret.chen@dmv.test', 'Margaret', 'Chen', PersonaGroup::SuperTier, 'President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
            ]),
            new Persona('david.okafor@dmv.test', 'David', 'Okafor', PersonaGroup::SuperTier, 'Vice-President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
            ]),
            new Persona('susan.wong@dmv.test', 'Susan', 'Wong', PersonaGroup::SuperTier, 'Vice-President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
            ]),

            // Officers — the core roles that attach to any Group regardless of flags.
            new Persona(self::CHAIR_EMAIL, 'Oliver', 'Bennett', PersonaGroup::Officers, 'Chair · DMV', placements: [
                new PersonaPlacement(self::ROOT, roles: [Role::Chair]),
            ]),
            new Persona('elena.rossi@dmv.test', 'Elena', 'Rossi', PersonaGroup::Officers, 'Secretary · Governance & Operations', placements: [
                new PersonaPlacement(self::COMMITTEE, roles: [Role::Secretary]),
            ]),
            new Persona('marcus.patel@dmv.test', 'Marcus', 'Patel', PersonaGroup::Officers, 'Treasurer · Governance & Operations', placements: [
                new PersonaPlacement(self::COMMITTEE, roles: [Role::Treasurer]),
            ]),

            // Stewards — authority via membership in the stewarding Group (ADR-0011);
            // Records stewards member administration.
            new Persona('priya.nair@dmv.test', 'Priya', 'Nair', PersonaGroup::Stewards, 'Member Admin · Records', placements: [
                new PersonaPlacement(self::RECORDS),
            ]),

            // Roles — capability-backed roles, each on a Group whose flag is on.
            new Persona(self::SCHEDULER_EMAIL, 'James', 'Tremblay', PersonaGroup::Roles, 'Scheduler · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, roles: [Role::Scheduler]),
            ]),
            new Persona('hannah.schmidt@dmv.test', 'Hannah', 'Schmidt', PersonaGroup::Roles, 'Librarian · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, roles: [Role::Librarian]),
            ]),
            new Persona('ravi.singh@dmv.test', 'Ravi', 'Singh', PersonaGroup::Roles, 'Statistician · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, roles: [Role::Statistician]),
            ]),
            new Persona('nadia.haddad@dmv.test', 'Nadia', 'Haddad', PersonaGroup::Roles, 'News Editor · Communications', placements: [
                new PersonaPlacement(self::COMMUNICATIONS, roles: [Role::NewsEditor]),
            ]),

            // Standings — varied within-Group standings on no-office memberships.
            new Persona('tomas.novak@dmv.test', 'Tomas', 'Novak', PersonaGroup::Standings, 'Trainee · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, MembershipStatus::Trainee),
            ]),
            new Persona('clara.moreau@dmv.test', 'Clara', 'Moreau', PersonaGroup::Standings, 'On Leave · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, MembershipStatus::Loa),
            ]),
            new Persona('felix.andersson@dmv.test', 'Felix', 'Andersson', PersonaGroup::Standings, 'Full Member · Reception', placements: [
                new PersonaPlacement(self::RECEPTION),
            ]),
            new Persona('ingrid.lindqvist@dmv.test', 'Ingrid', 'Lindqvist', PersonaGroup::Standings, 'Emeritus · Reception', placements: [
                new PersonaPlacement(self::RECEPTION, MembershipStatus::Emeritus),
            ]),
            new Persona('diego.costa@dmv.test', 'Diego', 'Costa', PersonaGroup::Standings, 'Transitional · Reception', placements: [
                new PersonaPlacement(self::RECEPTION, MembershipStatus::Transitional),
            ]),
            new Persona('amara.abara@dmv.test', 'Amara', 'Abara', PersonaGroup::Standings, 'Member · Docents + Reception', placements: [
                new PersonaPlacement(self::DOCENTS),
                new PersonaPlacement(self::RECEPTION),
            ]),

            // Negative — no-authority accounts. The departed Persona is Resigned
            // both DMV-wide (Category) and within its Group (standing), exercising
            // both exclusion axes (Directory keys on Category; My-Groups on standing).
            new Persona('sven.larsson@dmv.test', 'Sven', 'Larsson', PersonaGroup::Negative, 'Resigned · Docents', category: Category::Resigned, placements: [
                new PersonaPlacement(self::DOCENTS, MembershipStatus::Resigned),
            ]),
        ];
    }

    /**
     * The impersonation allowlist: the emails the switcher may become. Sourced
     * from {@see all()} so list and allowlist can never drift.
     *
     * @return list<string>
     */
    public static function emails(): array
    {
        return array_map(fn (Persona $persona) => $persona->email, self::all());
    }

    /** Whether the given email is a catalogued Persona (the allowlist check). */
    public static function has(string $email): bool
    {
        return in_array($email, self::emails(), true);
    }
}
