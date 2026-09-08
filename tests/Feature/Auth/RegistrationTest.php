<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Self-service registration is removed: identity is roster-seeded, not public
// sign-up (ADR-0001). The register routes must be gone, not merely hidden.
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_reachable()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_cannot_be_submitted()
    {
        // No POST handler exists. The app's GET-only locale fallback (ADR-0008)
        // owns the URI, so a removed POST route surfaces as 405 rather than 404;
        // either way there is no registration path.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertMethodNotAllowed();
        $this->assertGuest();
        $this->assertSame(0, Member::query()->count());
    }
}
