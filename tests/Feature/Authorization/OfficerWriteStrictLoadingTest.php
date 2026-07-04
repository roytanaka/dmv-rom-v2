<?php

use App\Enums\GroupBanner;
use App\Models\Group;
use App\Models\Member;
use App\Personas\PersonaCatalogue;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Http;

/*
 * Regression guard for the officer-write strict-mode lazy-load bug: a non-super-tier
 * Group officer (here Oliver Bennett, Chair of Docents) editing their Group used to
 * 500 with a LazyLoadingViolationException. The write Form Request's authorize()
 * reaches the policy → Member::administers() → canActAs() → membershipIn(), and on the
 * realistic roster the actor arrives with `memberships` loaded (by the sidebar-nav
 * middleware) but WITHOUT `roles`, so reading a membership's roles tripped strict mode.
 * The super-tier Gate::before short-circuit hid the gap, so it only bit officers.
 *
 * This needs the seeded roster, not a lone factory Group: a factory actor is
 * `wasRecentlyCreated`, which suppresses the very violation we are guarding against.
 * Http::fake keeps DemoSeeder's avatar fetches off the network.
 */
beforeEach(function () {
    Http::fake();
    $this->seed(DemoSeeder::class);
});

it('lets a seeded Chair change their Group banner without tripping strict lazy-loading', function () {
    $chair = Member::where('email', PersonaCatalogue::CHAIR_EMAIL)->firstOrFail();
    $docents = Group::where('slug', 'docents')->firstOrFail();

    $this->actingAs($chair)
        ->patch(route('groups.update', $docents), ['banner_key' => GroupBanner::Gallery->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($docents->fresh()->banner_key)->toBe(GroupBanner::Gallery);
});
