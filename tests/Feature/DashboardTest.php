<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_the_dashboard_renders_its_own_content_directly()
    {
        // The Dashboard renders its content directly (the group-tile launcher),
        // not a page-level tab strip of its own (#195, PRD #187, ADR-0013 amendment).
        $this->actingAs(Member::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // No page-level section-tab driver — the Group page owns a `section`
                // tab set in its body; the Dashboard has none.
                ->missing('section'));
    }

    public function test_the_dashboard_exposes_no_top_bar_tab_set_of_its_own()
    {
        // The top bar is ONE fixed global strip everywhere (#194): the Dashboard
        // contributes no tab set of its own — its `chromeNav` is the same fixed
        // global strip (My Hours · My Calendar · News · Directory + Help) shared on
        // every page, so nothing about the bar is Dashboard-specific.
        $this->actingAs(Member::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('chromeNav.destinations', 4)
                ->where('chromeNav.destinations.0.key', 'hours')
                ->where('chromeNav.destinations.1.key', 'calendar')
                ->where('chromeNav.destinations.2.key', 'news')
                ->where('chromeNav.destinations.3.key', 'directory')
                ->where('chromeNav.help.key', 'help'));
    }
}
