<?php

namespace Tests\Feature\Settings;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_appearance_settings_route_no_longer_exists()
    {
        // Dark mode was excised per ADR-0012. An authenticated user proves the
        // route is gone (a surviving route would return 200), not just hidden
        // behind an auth redirect.
        $user = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/settings/appearance');
        $response->assertStatus(404);
    }
}
