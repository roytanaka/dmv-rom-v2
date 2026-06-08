<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SidebarStatePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_open_defaults_to_true_without_a_cookie()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('sidebarOpen', true));
    }

    public function test_sidebar_open_reflects_the_collapsed_cookie()
    {
        $this->actingAs(User::factory()->create());

        $this->withUnencryptedCookie('sidebar:state', 'false')
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('sidebarOpen', false));
    }

    public function test_sidebar_open_reflects_the_expanded_cookie()
    {
        $this->actingAs(User::factory()->create());

        $this->withUnencryptedCookie('sidebar:state', 'true')
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('sidebarOpen', true));
    }
}
