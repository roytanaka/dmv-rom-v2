<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// A 419 (CSRF token mismatch, usually an expired session) never shows the bare
// "Page Expired" screen: guests go to sign-in and come back, members go back.
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF checks are off under unit tests, so raise the 419 directly.
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));
        Route::middleware('web')->delete('/_test/expired', fn () => abort(419));
    }

    public function test_guest_is_sent_to_login_and_back_to_the_page_they_were_on()
    {
        $response = $this->from('/groups/5')->post('/_test/expired');

        $response->assertStatus(303)->assertRedirect(route('login'));
        $this->assertSame(url('/groups/5'), session('url.intended'));

        $this->get(route('login'))->assertInertia(fn ($page) => $page
            ->where('status', __('auth.login.session_expired')));

        $member = Member::factory()->create();
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertRedirect(url('/groups/5'));
    }

    public function test_delete_redirect_is_303_so_inertia_follows_with_get()
    {
        $this->from('/groups/5')->delete('/_test/expired')->assertStatus(303);
    }

    public function test_expired_login_form_does_not_loop_back_to_login()
    {
        $this->from(route('login'))->post('/_test/expired')
            ->assertRedirect(route('login'));

        $this->assertNull(session('url.intended'));
    }

    public function test_offsite_referer_is_not_remembered()
    {
        $this->from('https://evil.example/phish')->post('/_test/expired')
            ->assertRedirect(route('login'));

        $this->assertNull(session('url.intended'));
    }

    public function test_signed_in_member_goes_back_to_the_page()
    {
        $this->actingAs(Member::factory()->create())
            ->from('/groups/5')->post('/_test/expired')
            ->assertStatus(303)->assertRedirect(url('/groups/5'));
    }

    public function test_json_callers_still_get_419()
    {
        $this->postJson('/_test/expired')->assertStatus(419);
    }
}
