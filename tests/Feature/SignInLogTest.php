<?php

use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\SignIn;
use App\Personas\PersonaCatalogue;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Sign-in log (#926, ADR-0038 §12): every sign-in leaves a row, and `last_sign_in_at` follows.
 * A sign-in is not an edit of the record, so "Last updated" stays where it was.
 */

afterEach(fn () => Carbon::setTestNow());

it('writes a row and the last sign-in on a login', function () {
    Carbon::setTestNow('2026-11-03 14:30:00');
    $member = Member::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeader('User-Agent', 'Test Browser/1.0')
        ->post('/login', ['email' => $member->email, 'password' => 'password'])
        ->assertRedirect();

    $signIn = SignIn::where('member_id', $member->id)->sole();

    expect($signIn->ip)->toBe('203.0.113.9')
        ->and($signIn->user_agent)->toBe('Test Browser/1.0')
        ->and($signIn->signed_in_at->toDateTimeString())->toBe('2026-11-03 14:30:00')
        ->and($member->fresh()->last_sign_in_at->toDateTimeString())->toBe('2026-11-03 14:30:00');
});

it('takes a user agent of any length', function () {
    $member = Member::factory()->create();
    $userAgent = str_repeat('x', 2000);

    $this->withHeader('User-Agent', $userAgent)
        ->post('/login', ['email' => $member->email, 'password' => 'password']);

    expect(SignIn::where('member_id', $member->id)->sole()->user_agent)->toBe($userAgent);
});

it('writes nothing on a failed login', function () {
    $member = Member::factory()->create();

    $this->post('/login', ['email' => $member->email, 'password' => 'wrong-password']);

    expect(SignIn::count())->toBe(0)
        ->and($member->fresh()->last_sign_in_at)->toBeNull();
});

it('records nothing when a support operator becomes a Persona or stops', function () {
    $operator = Member::factory()->create();
    $operator->forceFill(['support_operator' => true])->save();
    $chair = Member::factory()->create(['email' => PersonaCatalogue::CHAIR_EMAIL]);

    $this->actingAs($operator)->post(route('impersonation.start'), ['email' => $chair->email]);
    $this->assertAuthenticatedAs($chair);
    $this->delete(route('impersonation.stop'));
    $this->assertAuthenticatedAs($operator);

    expect(SignIn::count())->toBe(0)
        ->and($chair->fresh()->last_sign_in_at)->toBeNull()
        ->and($operator->fresh()->last_sign_in_at)->toBeNull();
});

it('records a sign-in restored from a Remember me cookie', function () {
    $member = Member::factory()->create();
    $member->forceFill(['remember_token' => 'remembered-token'])->save();
    $recaller = $member->id.'|remembered-token|'.$member->getAuthPassword();

    $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)->get('/dashboard')->assertOk();

    expect(SignIn::where('member_id', $member->id)->count())->toBe(1);
});

it('leaves the record’s last update alone', function () {
    Carbon::setTestNow('2026-01-01 12:00:00');
    $member = Member::factory()->create();

    Carbon::setTestNow('2026-11-03 14:30:00');
    $this->post('/login', ['email' => $member->email, 'password' => 'password']);

    expect($member->fresh()->updated_at->toDateTimeString())->toBe('2026-01-01 12:00:00');
});

it('accepts a row with no IP or user agent, as the legacy log imports', function () {
    $member = Member::factory()->create();

    $signIn = SignIn::create(['member_id' => $member->id, 'signed_in_at' => '2011-05-01 09:00:00']);

    expect($signIn->fresh()->ip)->toBeNull()
        ->and($signIn->fresh()->user_agent)->toBeNull();
});

it('goes with the Member', function () {
    $member = Member::factory()->create();
    SignIn::create(['member_id' => $member->id, 'signed_in_at' => now()]);

    $member->delete();

    expect(SignIn::count())->toBe(0);
});

describe('the nightly blank', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-11-03 02:00:00');
        $member = Member::factory()->create();
        $row = fn (string $at) => SignIn::create([
            'member_id' => $member->id,
            'signed_in_at' => $at,
            'ip' => '203.0.113.9',
            'user_agent' => 'Test Browser/1.0',
        ]);

        $this->old = $row('2025-11-03 01:59:59');
        $this->onTheLine = $row('2025-11-03 02:00:00');
        $this->recent = $row('2026-06-01 09:00:00');
    });

    it('blanks the IP and user agent past one year and keeps the row', function () {
        $this->artisan('sign-ins:blank-expired')->assertSuccessful();

        expect($this->old->fresh()->ip)->toBeNull()
            ->and($this->old->fresh()->user_agent)->toBeNull()
            ->and($this->old->fresh()->signed_in_at->toDateTimeString())->toBe('2025-11-03 01:59:59');
    });

    it('leaves a row of a year or less alone', function () {
        $this->artisan('sign-ins:blank-expired')->assertSuccessful();

        expect($this->onTheLine->fresh()->ip)->toBe('203.0.113.9')
            ->and($this->onTheLine->fresh()->user_agent)->toBe('Test Browser/1.0')
            ->and($this->recent->fresh()->ip)->toBe('203.0.113.9')
            ->and($this->recent->fresh()->user_agent)->toBe('Test Browser/1.0');
    });

    it('is safe to run twice', function () {
        $this->artisan('sign-ins:blank-expired')->assertSuccessful();
        $this->artisan('sign-ins:blank-expired')->assertSuccessful();

        expect(SignIn::count())->toBe(3)
            ->and(SignIn::whereNull('ip')->count())->toBe(1)
            ->and(SignIn::whereNull('user_agent')->count())->toBe(1);
    });

    it('is on the nightly schedule in the org timezone', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'sign-ins:blank-expired'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 3 * * *')
            ->and($event->timezone)->toBe(config('app.org_timezone'))
            ->and($event->withoutOverlapping)->toBeTrue();
    });
});

describe('on the Member record', function () {
    beforeEach(function () {
        $this->member = Member::factory()->create();
        $this->member->forceFill(['last_sign_in_at' => '2026-11-03 02:00:00'])->save();
        SignIn::create(['member_id' => $this->member->id, 'signed_in_at' => '2026-11-03 02:00:00']);
        SignIn::create(['member_id' => $this->member->id, 'signed_in_at' => '2026-10-01 14:00:00']);
    });

    it('shows member administration the last sign-in time and the count', function () {
        $records = Group::factory()->create();
        GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
        $admin = Member::factory()->create();
        $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

        $this->actingAs($admin)
            ->get("/officer/members/records/{$this->member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('member.last_sign_in_at', '2026-11-03T02:00:00.000000Z')
                ->where('signInsCount', 2));
    });

    it('counts none for a Member who never signed in', function () {
        $other = Member::factory()->create();

        $this->actingAs(Member::factory()->superTier()->create())
            ->get("/officer/members/records/{$other->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('member.last_sign_in_at', null)
                ->where('signInsCount', 0));
    });

    it('never reaches a peer', function () {
        $peer = Member::factory()->create();

        $this->actingAs($peer)->get("/officer/members/records/{$this->member->id}")->assertForbidden();

        $this->actingAs($peer)
            ->get("/members/{$this->member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->missing('member.last_sign_in_at')
                ->missing('signInsCount'));
    });
});
