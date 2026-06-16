<?php

namespace Database\Factories;

use App\Enums\Kind;
use App\Enums\LifecycleState;
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
            'has_hours_stats' => false,
            'has_announcements' => false,
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
            'has_hours_stats' => true,
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
