<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database — the single `db:seed` entry point.
     *
     * Two parts run on every seed (local `db:seed`/`db:fresh` and the staging
     * deploy's `migrate:fresh --seed`): the known ordinary-member login below, then
     * {@see DemoSeeder} for the full curated org tree + persona roster. Both are
     * faker-free so they run under a --no-dev install (fakerphp/faker is a
     * dev-only dependency absent from deployed builds); the test-only
     * {@see OrgTreeSeeder} stays out of this path precisely because it uses
     * factories. Both are idempotent, so re-seeding heals rather than duplicates.
     *
     * The super-tier login QA operates the role-switcher from is no longer here:
     * it is the President Persona in the catalogue (ADR-0009 dev half, #221), and
     * `test@example.com` is now demoted to the lowest-privilege ordinary member —
     * "works as test" means "works as an ordinary member," not god mode.
     */
    public function run(): void
    {
        // No roles, no stewardships, no super_tier (defaults false, and it's not
        // mass-assignable — #153); DemoSeeder's enrollEveryoneInRoot gives it the
        // one root-DMV membership every Member has, and it stays out of the bulk
        // pool, so it picks up no incidental authority. email_verified_at is
        // non-fillable, force-filled to mark the login verified, idempotently.
        $member = Member::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Member',
                'category' => Category::Active,
                'password' => Hash::make('password'),
            ],
        );

        if ($member->email_verified_at === null) {
            $member->forceFill(['email_verified_at' => now()])->save();
        }

        // The org-owned Skills catalog (PRD #243) — reference data, faker-free and
        // idempotent, so it belongs on the standard seed path and is present after
        // `migrate:fresh --seed`.
        $this->call(SkillCatalogSeeder::class);

        $this->call(DemoSeeder::class);
    }
}
