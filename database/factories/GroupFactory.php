<?php

namespace Database\Factories;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\Scope;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a plain active, standing organization Group with every
     * capability off; the per-Kind states below switch on the capabilities that
     * Kind typically runs.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'parent_id' => null,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'description' => fake()->sentence(),
            'kind' => Kind::StandingCommittee,
            'scope' => Scope::Organization,
            'listing_visibility' => ListingVisibility::Group,
            'lifecycle_state' => LifecycleState::Active,
            'time_boxed' => false,
            'start_date' => null,
            'end_date' => null,
            'display_order' => 0,
            'has_meetings' => false,
            'has_documents' => false,
            'has_scheduling' => false,
            'has_content_catalog' => false,
            'has_vetting' => false,
            'has_announcements' => false,
            // Hours is always-on (ADR-0022 §3); the multiplier defaults to 1 and is
            // raised only for a Group that converts activity to hours at a ratio
            // (ROMWalks → 2).
            'hours_multiplier' => 1,
        ];
    }

    /**
     * A standing committee — org-scoped, meets and holds documents, no scheduling.
     */
    public function standingCommittee(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::StandingCommittee,
            'scope' => Scope::Organization,
            'has_meetings' => true,
            'has_documents' => true,
        ]);
    }

    /**
     * A container section — an org-scope grouping that heads the Other Groups zone
     * (PRD #289): no page, no roster, no capabilities, pure scaffolding that explodes
     * to the members-facing Groups beneath it. Modelled as {@see Kind::Container}, the
     * stored marker the rail, launcher and page-gate read from.
     */
    public function container(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::Container,
            'scope' => Scope::Organization,
        ]);
    }

    /**
     * A program — the member-facing operating unit that runs scheduling, content,
     * and stats.
     */
    public function program(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::Program,
            'scope' => Scope::Program,
            'has_documents' => true,
            'has_scheduling' => true,
            'has_content_catalog' => true,
        ]);
    }

    /**
     * A Group that collects a per-shift visitor count (#445, ADR-0023 §5) — the sign-out
     * panel's box. Implies scheduling: a Group with no Sign-ups has nothing to hang a count
     * on. Layer over another program-shaped state when the surrounding shape matters.
     */
    public function collectsVisitorCount(): static
    {
        return $this->state(fn () => [
            'has_scheduling' => true,
            'collects_visitor_count' => true,
        ]);
    }

    /**
     * A tour-leading Group that also collects the extra-interaction split (#447, ADR-0023 §2) —
     * the sign-out panel's second box. Implies the visitor count (the box sits beside it, and a
     * tour's visitor number is the tour), so it layers on {@see collectsVisitorCount}.
     */
    public function collectsExtraInteractions(): static
    {
        return $this->collectsVisitorCount()->state(fn () => [
            'collects_extra_interactions' => true,
        ]);
    }

    /**
     * GDR — a Group that also collects the five-origin visitor provenance split (#448, ADR-0023
     * §3): the sign-out panel's five origin boxes, which must sum to the count. Implies the
     * visitor count (the five sum to it, which is the count itself), so it layers on
     * {@see collectsVisitorCount}.
     */
    public function collectsVisitorProvenance(): static
    {
        return $this->collectsVisitorCount()->state(fn () => [
            'collects_visitor_provenance' => true,
        ]);
    }

    /**
     * A Group whose visitor figures are knowingly incomplete (#451, ADR-0023 §6) — part of its
     * visitor number comes from a group-booking table the booking map has not delivered, so
     * Summary Visitor Interactions marks the row and says why. ROMForYou is the sharpest case.
     */
    public function awaitingBookingAudiences(): static
    {
        return $this->state(fn () => [
            'visitor_figures_await_booking' => true,
        ]);
    }

    /**
     * A working group — a functional sub-team that meets.
     */
    public function workingGroup(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::WorkingGroup,
            'scope' => Scope::Subteam,
            'has_meetings' => true,
        ]);
    }

    /**
     * A project — a time-boxed sub-team.
     */
    public function project(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::Project,
            'scope' => Scope::Subteam,
            'has_documents' => true,
        ])->timeBoxed();
    }

    /**
     * A cohort — a time-boxed, schedulable pool (e.g. an exhibition's volunteers).
     */
    public function cohort(): static
    {
        return $this->state(fn () => [
            'kind' => Kind::Cohort,
            'scope' => Scope::Program,
            'has_scheduling' => true,
        ])->timeBoxed();
    }

    /**
     * List the Group to every logged-in Member (ADR-0019) — the org-open tier.
     */
    public function publicListing(): static
    {
        return $this->state(fn () => [
            'listing_visibility' => ListingVisibility::Public,
        ]);
    }

    /**
     * List the Group only to its own members, hiding its page's existence from
     * everyone else (ADR-0019) — the strictest tier.
     */
    public function privateListing(): static
    {
        return $this->state(fn () => [
            'listing_visibility' => ListingVisibility::Private,
        ]);
    }

    /**
     * Mark the Group as archived (lifecycle no longer active).
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'lifecycle_state' => LifecycleState::Archived,
        ]);
    }

    /**
     * Give the Group a time-boxed window. Defaults to an open window (started a
     * month ago, ends a month from now); override end_date to model an expired
     * one.
     */
    public function timeBoxed(): static
    {
        return $this->state(fn () => [
            'time_boxed' => true,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
    }
}
