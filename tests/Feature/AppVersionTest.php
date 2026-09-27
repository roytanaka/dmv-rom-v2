<?php

use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * App version in the footer (#673). scripts/deploy.sh writes version.json, config
 * reads it, and the version is shared on every page as the short commit id and the
 * deploy instant. With no version configured (local development) the prop is null.
 */

it('shares a deployed version as the short commit and the deploy time', function () {
    config(['app.version' => [
        'commit' => '6c382a74e1f0b9d2c3a4b5c6d7e8f90a1b2c3d4e',
        'deployed_at' => '2026-09-26T14:05:00Z',
    ]]);
    $this->actingAs(Member::factory()->create());

    $this->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('appVersion.commit', '6c382a7')
            ->where('appVersion.deployedAt', '2026-09-26T14:05:00Z'));
});

it('shares a null version when none is configured', function () {
    config(['app.version' => null]);
    $this->actingAs(Member::factory()->create());

    $this->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('appVersion', null));
});
