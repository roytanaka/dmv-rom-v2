<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = Member::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = Member::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = Member::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    // Remember me (#687, ADR-0001): ticked keeps the member signed in on the
    // device for 14 days; unticked leaves only the 2-hour idle session.

    public function test_remember_me_sets_a_cookie_that_expires_in_14_days()
    {
        $this->freezeSecond();
        $user = Member::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $cookie = $response->getCookie($this->recallerName(), decrypt: false);
        $this->assertNotNull($cookie);
        $this->assertSame(now()->addDays(14)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_without_remember_me_no_remember_cookie_is_set()
    {
        $user = Member::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertCookieMissing($this->recallerName());
    }

    public function test_remember_cookie_signs_the_member_in_after_the_session_expires()
    {
        $user = Member::factory()->create();
        $recaller = $this->signInRemembered($user);

        $this->expireSession();

        $this->withCookie($this->recallerName(), $recaller)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_removes_the_remember_cookie_and_the_old_cookie_stops_working()
    {
        $user = Member::factory()->create();
        $recaller = $this->signInRemembered($user);

        $this->withCookie($this->recallerName(), $recaller)
            ->post('/logout')
            ->assertCookieExpired($this->recallerName());

        $this->expireSession();

        $this->withCookie($this->recallerName(), $recaller)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** Sign in with Remember me ticked and return the plain recaller cookie value. */
    private function signInRemembered(Member $user): string
    {
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        return $response->getCookie($this->recallerName())->getValue();
    }

    /** Drop the server session and the guard's cached user, as a 2-hour idle timeout would. */
    private function expireSession(): void
    {
        $this->flushSession();
        Auth::forgetGuards();
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }
}
