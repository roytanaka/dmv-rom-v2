<?php

use App\Actions\ChangeCategory;
use App\Actions\RemovePersonalData;
use App\Enums\Category;
use App\Models\Member;
use App\Models\SignIn;
use App\Support\OrgTime;
use Illuminate\Support\Facades\Auth;

/*
 * Access closes when a Member leaves the Current Category group (ADR-0038 §4, spec #913). Sign-in
 * already refuses them; this covers a session already open and a Remember me cookie. Their next
 * request signs them out and sends them to sign-in.
 */

function currentSessionRecaller(Member $member): string
{
    $member->refresh();

    return $member->id.'|'.$member->getRememberToken().'|'.$member->getAuthPassword();
}

it('signs a Member out on the next request once they leave the Current group', function () {
    $member = Member::factory()->create(['category' => Category::Active]);
    $this->actingAs($member)->get('/dashboard')->assertOk();

    $member->forceFill(['category' => Category::Resigned])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps a Current Member signed in', function () {
    $member = Member::factory()->create(['category' => Category::Loa]);

    $this->actingAs($member)->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($member);
});

it('no longer signs a resigned Member in from a Remember me cookie', function () {
    $member = Member::factory()->create(['category' => Category::Active]);
    $member->forceFill(['remember_token' => 'remembered-token'])->save();
    $recaller = currentSessionRecaller($member);

    $member->forceFill(['category' => Category::Deceased])->save();

    $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
        ->get('/dashboard')
        ->assertRedirect(route('login'));
    $this->assertGuest();
    expect(SignIn::count())->toBe(0);
});

it('cycles the remember token when the Category moves out of Current', function () {
    $member = Member::factory()->create(['category' => Category::Active]);
    $member->forceFill(['remember_token' => 'remembered-token'])->save();

    app(ChangeCategory::class)->change($member, Category::Resigned, OrgTime::now());

    expect($member->fresh()->remember_token)->not->toBe('remembered-token');
});

it('keeps the remember token on a move within Current', function () {
    $member = Member::factory()->create(['category' => Category::Active]);
    $member->forceFill(['remember_token' => 'remembered-token'])->save();

    app(ChangeCategory::class)->change($member, Category::Loa, OrgTime::now());

    expect($member->fresh()->remember_token)->toBe('remembered-token');
});

it('cycles the remember token when personal data is removed', function () {
    $admin = Member::factory()->create();
    $member = Member::factory()->create(['category' => Category::Resigned]);
    $member->forceFill(['remember_token' => 'remembered-token', 'status_date' => '2015-01-01'])->save();

    app(RemovePersonalData::class)->run(2019, $admin);

    expect($member->fresh()->remember_token)->not->toBe('remembered-token');
});
