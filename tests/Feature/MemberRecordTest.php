<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Member record (#917, ADR-0038 §2, §3, §13): the Records office's page for one Member. A
 * member admin edits every field but Category; Notes are an append-only log; the Records-only
 * fields reach the Member and member administration, never a peer.
 */

function recordMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

/** A full save of the record: the required names, plus whatever the case is about. */
function recordPayload(Member $member, array $overrides = []): array
{
    return [
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'email' => $member->email,
        ...$overrides,
    ];
}

it('seeds the fee categories and the service awards', function () {
    expect(DB::table('fee_categories')->pluck('active', 'code')->all())->toBe([
        'Active' => 1, 'Active-SR' => 1, 'Active-ST' => 1, 'Sust' => 1, 'Sust-SR' => 1, 'LOA' => 1, 'Hon' => 0,
    ])->and((float) DB::table('fee_categories')->where('code', 'Active-ST')->value('amount'))->toBe(20.0)
        ->and(DB::table('service_awards')->orderBy('years')->pluck('years')->all())->toBe(range(5, 60, 5))
        ->and(DB::table('service_awards')->where('years', 15)->value('code'))->toBe('15 yr');
});

it('lets several Members go without an email', function () {
    Member::factory()->count(2)->create(['email' => null]);

    expect(Member::whereNull('email')->count())->toBe(2);
});

it('silences a Member who has no email', function () {
    $member = Member::factory()->create(['email' => null]);

    expect($member->fresh()->no_email)->toBeTrue();
});

it('opens the record to a member admin, at both language addresses', function () {
    $admin = recordMemberAdmin();
    $member = Member::factory()->create();

    $this->actingAs($admin)
        ->get("/officer/members/records/{$member->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/members/Record')
            ->where('member.id', $member->id)
            ->has('feeCategories', 6)
            ->has('serviceAwards', 12));

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->get("/fr/officier/membres/dossiers/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/members/Record')->where('locale', 'fr'));
    });
});

it('keeps an inactive fee category on offer for the Member who holds it', function () {
    $member = Member::factory()->create();
    $member->forceFill(['fee_category' => 'Hon'])->save();

    $this->actingAs(recordMemberAdmin())
        ->get("/officer/members/records/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page->has('feeCategories', 7));
});

it('shows the record the Member\'s Memberships and roles', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create(['name' => 'Docents']);
    $membership = $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Secretary]);

    $this->actingAs(recordMemberAdmin())
        ->get("/officer/members/records/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.groups.0.name', 'Docents')
            ->where('member.groups.0.roles', [Role::Secretary->value]));
});

it('opens the record to the super-tier and refuses a plain Member', function () {
    $member = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get("/officer/members/records/{$member->id}")
        ->assertOk();

    $this->actingAs(Member::factory()->create())
        ->get("/officer/members/records/{$member->id}")
        ->assertForbidden();
});

it('refuses a Member their own record', function () {
    $member = Member::factory()->create();

    $this->actingAs($member)->get("/officer/members/records/{$member->id}")->assertForbidden();
    $this->actingAs($member)->patch("/members/{$member->id}/record", recordPayload($member))->assertForbidden();
});

it('lets a member admin update every kept field', function () {
    $member = Member::factory()->create();

    $fields = [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '416-555-0100',
        'alternate_phone' => '416-555-0101',
        'business_phone' => '416-555-0102',
        'address_street' => '100 Queens Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'address_country' => 'Canada',
        'membership_number' => 'R12345',
        'membership_lifetime' => false,
        'amount_paid' => '25.00',
        'paid_through_year' => 2027,
        'fee_category' => 'Active-SR',
        'entry_year' => 2012,
        'service_awards' => ['5 yr', '10 yr'],
        'last_award_year' => 2022,
        'next_award_year' => 2027,
        'other_awards' => 'Volunteer of the year',
        'emergency_contact' => 'Charles Babbage',
        'emergency_contact_number' => '416-555-0199',
        'newsletter_opt_in' => true,
        'code_of_conduct_year' => 2026,
        'renewal_year' => 2026,
        'renewal_status' => 'Active',
        'renewal_placements' => 'Docents',
        'renewal_other' => 'Reception',
        'record_form_placements' => 'Docents, Reception',
        'record_form_other' => 'ROMBus',
        'mentor_name' => 'Grace Hopper',
        'volunteer_experience' => 'Hospital guide',
        'past_experience' => 'Mathematician',
        'education' => 'Private tutors',
    ];

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", [...$fields, 'membership_expiry' => '2027-03-31'])
        ->assertSessionHasNoErrors();

    $fresh = $member->fresh();

    expect($fresh->only(array_keys($fields)))->toBe($fields)
        ->and($fresh->membership_expiry->toDateString())->toBe('2027-03-31');
});

it('never moves the Category or the status date through the record', function () {
    $member = Member::factory()->create();
    $member->forceFill(['status_date' => '2020-01-01'])->save();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, [
            'category' => Category::Resigned->value,
            'status_date' => '2026-01-01',
        ]))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->category)->toBe(Category::Active)
        ->and($member->fresh()->status_date->toDateString())->toBe('2020-01-01');
});

it('lets a member admin blank an email, which silences the Member', function () {
    $member = Member::factory()->create();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, ['email' => '']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email)->toBeNull()
        ->and($member->fresh()->no_email)->toBeTrue();
});

it('refuses an email another Member holds, and clears verification on a change', function () {
    $member = Member::factory()->create();
    $other = Member::factory()->create();
    $admin = recordMemberAdmin();

    $this->actingAs($admin)
        ->patch("/members/{$member->id}/record", recordPayload($member, ['email' => $other->email]))
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)
        ->patch("/members/{$member->id}/record", recordPayload($member, ['email' => 'new@example.com']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email_verified_at)->toBeNull();
});

it('refuses a fee category or a service award that is not on the list', function () {
    $member = Member::factory()->create();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, [
            'fee_category' => 'Nope',
            'service_awards' => ['7 yr'],
        ]))
        ->assertSessionHasErrors(['fee_category', 'service_awards.0']);
});

it('prepends a dated, signed note and leaves earlier lines alone', function () {
    Carbon::setTestNow('2026-11-03 12:00:00');
    $member = Member::factory()->create();
    $member->forceFill(['notes' => "2024-05-01:Moved to Ottawa\r\nOlder line"])->save();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, ['new_note' => 'Called about the card']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->notes)->toBe("2026-11-03 Rita Records: Called about the card\n2024-05-01:Moved to Ottawa\r\nOlder line");
});

it('dates a note on the museum\'s clock', function () {
    Carbon::setTestNow('2026-11-04 02:00:00');
    $member = Member::factory()->create();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, ['new_note' => 'Late call']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->notes)->toBe('2026-11-03 Rita Records: Late call');
});

it('leaves the Notes as they are on a save with no new text, and refuses a rewrite', function () {
    $member = Member::factory()->create();
    $member->forceFill(['notes' => 'Original'])->save();

    $this->actingAs(recordMemberAdmin())
        ->patch("/members/{$member->id}/record", recordPayload($member, ['new_note' => '', 'notes' => 'Rewritten']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->notes)->toBe('Original');
});

it('records and shows who last updated the record', function () {
    Carbon::setTestNow('2026-11-03 12:00:00');
    $admin = recordMemberAdmin();
    $member = Member::factory()->create();

    $this->actingAs($admin)
        ->get("/officer/members/records/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page->where('lastUpdated', null));

    $this->actingAs($admin)
        ->patch("/members/{$member->id}/record", recordPayload($member, ['phone' => '416-555-0100']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->updated_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->get("/officer/members/records/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page->where('lastUpdated', ['name' => 'Rita Records', 'date' => '2026-11-03']));
});

it('forgets the author when the author is deleted', function () {
    $author = Member::factory()->create();
    $member = Member::factory()->create();
    $member->forceFill(['updated_by' => $author->id, 'created_by' => $author->id])->save();

    $author->delete();

    expect($member->fresh()->updated_by)->toBeNull()
        ->and($member->fresh()->created_by)->toBeNull();
});

describe('field visibility', function () {
    beforeEach(function () {
        $this->member = Member::factory()->create();
        $this->member->forceFill([
            'membership_number' => 'R12345',
            'notes' => 'Private line',
            'paid_through_year' => 2026,
            'emergency_contact' => 'Charles Babbage',
            'service_awards' => ['5 yr'],
        ])->save();
    });

    it('sends the Records fields to the Member and member administration', function (string $viewer) {
        $actor = match ($viewer) {
            'self' => $this->member,
            'admin' => recordMemberAdmin(),
            'super' => Member::factory()->superTier()->create(),
        };

        $this->actingAs($actor)
            ->get("/members/{$this->member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('member.membership_number', 'R12345')
                ->where('member.notes', 'Private line')
                ->where('member.service_awards', ['5 yr'])
                ->where('member.paid_through_year', 2026));
    })->with(['self', 'admin', 'super']);

    it('never sends them to a peer, even a Group officer', function () {
        $group = Group::factory()->create();
        $group->memberships()->create(['member_id' => $this->member->id, 'status' => MembershipStatus::Full]);
        $chair = Member::factory()->create();
        $membership = $group->memberships()->create(['member_id' => $chair->id, 'status' => MembershipStatus::Full]);
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);

        $this->actingAs($chair)
            ->get("/members/{$this->member->id}")
            ->assertInertia(function (Assert $page) {
                foreach (Member::RECORDS_FIELDS as $field) {
                    $page->missing("member.{$field}");
                }
            });
    });

    it('keeps them out of the shared payload and any serialized Member', function () {
        expect($this->member->fresh()->toArray())->not->toHaveKeys(Member::RECORDS_FIELDS);

        $this->actingAs($this->member->fresh())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->missing('auth.user.notes')->missing('auth.user.membership_number'));
    });
});

describe('account settings', function () {
    it('shows the Member their paid-through year, ROM expiry and last award', function () {
        $member = Member::factory()->create();
        $member->forceFill([
            'paid_through_year' => 2026,
            'membership_expiry' => '2027-03-31',
            'last_award_year' => 2020,
        ])->save();

        $this->actingAs($member)
            ->get('/settings/profile')
            ->assertInertia(fn (Assert $page) => $page->where('membership', [
                'paid_through_year' => 2026,
                'membership_expiry' => '2027-03-31',
                'membership_lifetime' => false,
                'last_award_year' => 2020,
                'renewal_year' => null,
                'payment_pending' => false,
            ]));
    });

    it('lets the Member edit their ROM expiry, and marks them the last editor', function () {
        $member = Member::factory()->create();

        $this->actingAs($member)
            ->patch('/settings/profile', [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'membership_expiry' => '2028-01-31',
                'membership_lifetime' => false,
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->membership_expiry->toDateString())->toBe('2028-01-31')
            ->and($member->fresh()->updated_by)->toBe($member->id);
    });

    it('keeps every other Records field out of a Member\'s own reach', function () {
        $member = Member::factory()->create();
        $member->forceFill(['paid_through_year' => 2020])->save();

        $this->actingAs($member)
            ->patch('/settings/profile', [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'paid_through_year' => 2099,
                'membership_number' => 'FORGED',
                'notes' => 'Forged',
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->paid_through_year)->toBe(2020)
            ->and($member->fresh()->membership_number)->toBeNull()
            ->and($member->fresh()->notes)->toBeNull();
    });
});
