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
     * deploy's `migrate:fresh --seed`): the known super-tier login below, then
     * {@see DemoSeeder} for the full curated org tree + roster. Both are
     * faker-free so they run under a --no-dev install (fakerphp/faker is a
     * dev-only dependency absent from deployed builds); the test-only
     * {@see OrgTreeSeeder} stays out of this path precisely because it uses
     * factories. Both are idempotent, so re-seeding heals rather than duplicates.
     */
    public function run(): void
    {
        // super_tier is not mass-assignable (#153) and defaults to false in the
        // schema, so no form can set it. email_verified_at is likewise non-fillable.
        // forceFill grants the known login both the org-wide all-DMV grant (so QA
        // can exercise admin features on staging — no other seeded account can, and
        // super_tier can't be granted through the UI from a fresh DB) and a verified
        // email, idempotently.
        $member = Member::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Member',
                'category' => Category::Active,
                'password' => Hash::make('password'),
            ],
        );

        $member->forceFill([
            'super_tier' => true,
            'email_verified_at' => $member->email_verified_at ?? now(),
        ])->save();

        $this->call(DemoSeeder::class);
    }
}
