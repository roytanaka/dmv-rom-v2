<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deployed_version_is_shared_as_the_short_commit_and_deploy_time()
    {
        config(['app.version' => [
            'commit' => '6c382a74e1f0b9d2c3a4b5c6d7e8f90a1b2c3d4e',
            'deployed_at' => '2026-09-26T14:05:00Z',
        ]]);
        $this->actingAs(Member::factory()->create());

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('appVersion.commit', '6c382a7')
                ->where('appVersion.deployedAt', '2026-09-26T14:05:00Z'));
    }

    public function test_the_version_is_null_when_none_is_configured()
    {
        config(['app.version' => null]);
        $this->actingAs(Member::factory()->create());

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('appVersion', null));
    }
}
