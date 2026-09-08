<?php

namespace Tests\Feature;

use App\Console\Commands\DrainDeliveries;
use App\Enums\DeliveryState;
use App\Enums\StewardshipFunction;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailStatusTest extends TestCase
{
    use RefreshDatabase;

    /** A member holding the Records stewardship — member-admin authority, but not super-tier. */
    private function recordsMember(): Member
    {
        $records = Group::factory()->create();
        $records->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

        $member = Member::factory()->create();
        GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $member->id]);

        return $member;
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/mail-status')->assertRedirect('/login');
    }

    public function test_super_tier_sees_the_mail_status_page()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        $this->get('/mail-status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('MailStatus'));
    }

    public function test_a_records_member_without_super_tier_is_forbidden()
    {
        // Member-administration authority (Records) buys nothing here: the gate returns
        // false and only the super-tier short-circuit grants it (ADR-0024 §10).
        $records = $this->recordsMember();
        $this->assertTrue($records->hasMemberAdminAuthority());

        $this->actingAs($records);
        $this->get('/mail-status')->assertForbidden();
    }

    public function test_the_four_values_reflect_the_cache_keys_and_delivery_rows()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        $ran = now()->subMinutes(2);
        $errorAt = now()->subMinutes(5);
        $sentAt = now()->subMinutes(3);

        Cache::forever(DrainDeliveries::SCHEDULER_LAST_RAN, $ran);
        Cache::forever(DrainDeliveries::LAST_CONNECTION_ERROR, ['message' => 'Connection refused', 'at' => $errorAt]);

        // Two sent rows: the newest is "mail last sent"; an older one must not win.
        Delivery::factory()->sent($sentAt->copy()->subHour())->create();
        Delivery::factory()->sent($sentAt)->create();
        // Two pending rows the queue is holding.
        Delivery::factory()->count(2)->create(['state' => DeliveryState::Pending]);

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page
                ->component('MailStatus')
                ->where('schedulerLastRan', $ran->toIso8601String())
                ->where('mailLastSent', $sentAt->toIso8601String())
                ->where('lastConnectionError.message', 'Connection refused')
                ->where('lastConnectionError.at', $errorAt->toIso8601String())
                ->where('pendingCount', 2));
    }

    public function test_dead_warns_when_the_scheduler_key_is_missing()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page->where('warnings.dead', true));
    }

    public function test_dead_warns_when_the_scheduler_key_is_stale()
    {
        $this->actingAs(Member::factory()->superTier()->create());
        Cache::forever(DrainDeliveries::SCHEDULER_LAST_RAN, now()->subMinutes(11));

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page->where('warnings.dead', true));
    }

    public function test_dead_is_quiet_when_the_scheduler_ran_recently()
    {
        $this->actingAs(Member::factory()->superTier()->create());
        Cache::forever(DrainDeliveries::SCHEDULER_LAST_RAN, now()->subMinutes(1));

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page->where('warnings.dead', false));
    }

    public function test_cannot_send_warns_when_the_error_is_newer_than_the_last_send()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        Delivery::factory()->sent(now()->subMinutes(10))->create();
        Cache::forever(DrainDeliveries::LAST_CONNECTION_ERROR, ['message' => 'nope', 'at' => now()->subMinutes(2)]);

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page->where('warnings.cannotSend', true));
    }

    public function test_cannot_send_is_quiet_when_a_send_followed_the_error()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        Cache::forever(DrainDeliveries::LAST_CONNECTION_ERROR, ['message' => 'nope', 'at' => now()->subMinutes(10)]);
        Delivery::factory()->sent(now()->subMinutes(2))->create();

        $this->get('/mail-status')
            ->assertInertia(fn (Assert $page) => $page->where('warnings.cannotSend', false));
    }

    public function test_the_menu_hint_is_true_for_super_tier()
    {
        $this->actingAs(Member::factory()->superTier()->create());

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.viewMailStatus', true));
    }

    public function test_the_menu_hint_is_false_for_a_records_member()
    {
        $records = $this->recordsMember();
        $this->assertTrue($records->hasMemberAdminAuthority());
        $this->actingAs($records);

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.viewMailStatus', false));
    }
}
