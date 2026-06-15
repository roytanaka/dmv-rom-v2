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
        Member::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test Member',
                'email_verified_at' => now(),
                'category' => Category::Active,
                'super_tier' => false,
                'password' => Hash::make('password'),
            ],
        );
    }
}
