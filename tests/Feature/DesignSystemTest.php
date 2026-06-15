<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/design-system');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_design_system_page()
    {
        $user = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/design-system');
        $response->assertStatus(200);
    }
}
