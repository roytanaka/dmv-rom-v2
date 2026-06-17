<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// The redesigned ROM split login (#157). The visual layout lives in Vue, but two
// things are server-observable and worth pinning: the redesigned Inertia page is
// what renders, and every visible string resolves from the new bilingual
// lang/{en,fr}/auth.php — without clobbering Laravel's framework auth messages.
class LoginScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_the_redesigned_component(): void
    {
        $this->get('/login')->assertInertia(
            fn (Assert $page) => $page->component('auth/Login')->where('canResetPassword', true)
        );
    }

    public function test_login_chrome_resolves_in_english(): void
    {
        $this->assertSame('Sign in', __('auth.login.heading', [], 'en'));
        $this->assertSame('Forgot your password?', __('auth.login.forgot', [], 'en'));
        $this->assertSame('email the office', __('auth.login.help_email', [], 'en'));
    }

    public function test_login_chrome_resolves_in_french(): void
    {
        $this->assertSame('Connexion', __('auth.login.heading', [], 'fr'));
        $this->assertSame('Mot de passe oublié?', __('auth.login.forgot', [], 'fr'));
        $this->assertNotSame('auth.login.help_email', __('auth.login.help_email', [], 'fr'));
    }

    public function test_reception_phone_interpolates_into_the_help_text(): void
    {
        $this->assertStringContainsString(
            '(416) 586-8097',
            __('auth.login.help_before', ['phone' => '(416) 586-8097'], 'en')
        );
    }

    public function test_framework_auth_messages_are_preserved(): void
    {
        // Adding lang/{en,fr}/auth.php must not shadow Laravel's own auth keys,
        // or login-failure / password-confirmation messages break.
        $this->assertSame('These credentials do not match our records.', __('auth.failed', [], 'en'));
        $this->assertSame('The provided password is incorrect.', __('auth.password', [], 'en'));
    }
}
