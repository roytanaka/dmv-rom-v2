<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Created directly (not via MemberFactory) so it runs under a --no-dev
     * install: the factory's defaults call fake(), and fakerphp/faker is a
     * dev-only dependency absent from deployed builds. The staging deploy
     * (migrate:fresh --seed) reinstates this known login on every run.
     */
    public function run(): void
    {
        // super_tier is not mass-assignable (#153) and defaults to false in the
        // schema, so it is set by neither this seeder nor any form. email_verified_at
        // is likewise non-fillable: forceFill marks the known login verified once.
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
    }
}
