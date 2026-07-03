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
    private const COMMITTEE = 'governance-operations';

    private const EXECUTIVE = 'executive';

    private const COMMUNICATIONS = 'communications';

    private const RECORDS = 'records';

    private const DOCENTS = 'docents';

    private const RECEPTION = 'reception';

    // Program slugs the enrichment placements target — so the impersonated execs and
    // officers read as working volunteers (a President who is also a docent), not as
    // people who belong to a single committee. Kept in step with the curated tree.
    private const GALLERY_INTERPRETERS = 'gallery-interpreters';

    private const VISITOR_GUIDES = 'visitor-guides';

    private const VISITOR_WAYFINDERS = 'visitor-wayfinders';

    private const ROMWALKS = 'romwalks';

    private const ROMFORYOU = 'romforyou';

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
            // Operator — the maintainer identity the switcher runs from (the canonical
            // dev/QA login). Holds support-operator access, NOT super-tier: operating
            // the switcher is a maintainer power, deliberately split from org authority
            // so the President no longer doubles as the engineer. Force-filled
            // support_operator; no Group placement — operator reach is out-of-band.
            new Persona('operator@dmv.test', 'Support', 'Operator', PersonaGroup::Operator, 'Support Operator · Maintainer', operator: true),

            // Super-tier — org authority (President / VPs). No longer the switcher's
            // runner; these are fixtures you impersonate INTO to exercise org-wide
            // reach. Force-filled super_tier, in the Executive Group — plus the program
            // memberships a real exec carries (they came up through the programs), so
            // an impersonated President's My-Groups reads believably. These extra
            // placements hold no roles, so they add no authority beyond super_tier.
            new Persona('margaret.chen@dmv.test', 'Margaret', 'Chen', PersonaGroup::SuperTier, 'President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
                new PersonaPlacement(self::DOCENTS),
                new PersonaPlacement(self::GALLERY_INTERPRETERS),
            ]),
            new Persona('david.okafor@dmv.test', 'David', 'Okafor', PersonaGroup::SuperTier, 'Vice-President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
                new PersonaPlacement(self::VISITOR_GUIDES),
                new PersonaPlacement(self::VISITOR_WAYFINDERS),
            ]),
            new Persona('susan.wong@dmv.test', 'Susan', 'Wong', PersonaGroup::SuperTier, 'Vice-President · Executive', superTier: true, placements: [
                new PersonaPlacement(self::EXECUTIVE),
                // The docents-who-also-walk overlap, carried by an exec so the pattern
                // is guaranteed present on a named account you can impersonate.
                new PersonaPlacement(self::DOCENTS),
                new PersonaPlacement(self::ROMWALKS),
            ]),

            // Officers — the core roles that attach to any Group regardless of flags.
            // The DMV root has no Chair (its leadership is the executive), so the Chair
            // Persona chairs a real program and, like a real chair, also volunteers in
            // another — here Docents (chaired) plus Gallery Interpreters.
            new Persona(self::CHAIR_EMAIL, 'Oliver', 'Bennett', PersonaGroup::Officers, 'Chair · Docents', placements: [
                new PersonaPlacement(self::DOCENTS, roles: [Role::Chair]),
                new PersonaPlacement(self::GALLERY_INTERPRETERS),
            ]),
            new Persona('elena.rossi@dmv.test', 'Elena', 'Rossi', PersonaGroup::Officers, 'Secretary · Governance & Operations', placements: [
                new PersonaPlacement(self::COMMITTEE, roles: [Role::Secretary]),
                new PersonaPlacement(self::RECEPTION),
            ]),
            new Persona('marcus.patel@dmv.test', 'Marcus', 'Patel', PersonaGroup::Officers, 'Treasurer · Governance & Operations', placements: [
                new PersonaPlacement(self::COMMITTEE, roles: [Role::Treasurer]),
                new PersonaPlacement(self::ROMFORYOU),
            ]),

            // Stewards — authority via membership in the stewarding Group (ADR-0011);
            // Records stewards member administration.
            new Persona('priya.nair@dmv.test', 'Priya', 'Nair', PersonaGroup::Stewards, 'Member Admin · Records', placements: [
                new PersonaPlacement(self::RECORDS),
                new PersonaPlacement(self::VISITOR_GUIDES),
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
