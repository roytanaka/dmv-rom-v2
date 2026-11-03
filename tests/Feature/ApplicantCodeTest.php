<?php

use App\Enums\ApplicantStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Applicant;
use App\Models\ApplicantCode;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Applicant code and the public application form (#930, ADR-0038 §8, §14.6). The holder's
 * Chair sets one shared code, a publish flag and an expiry; while it is published and not expired,
 * the sign-in page offers the code entry, and a visitor with the code applies as a New Applicant.
 */

/** The holder (Membership) with its Chair and a plain member. */
function codeOrg(): array
{
    $membership = Group::factory()->standingCommittee()->create(['slug' => 'membership', 'name' => 'Membership']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::Applicants)->create(['group_id' => $membership->id]);

    $join = function (?Role $role) use ($membership): Member {
        $member = Member::factory()->create();
        $row = $membership->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
        if ($role !== null) {
            $row->roles()->create(['role' => $role]);
        }

        return $member;
    };

    return ['chair' => $join(Role::Chair), 'member' => $join(null)];
}

function openCode(string $code = 'ROM2026', ?string $expiresOn = null): ApplicantCode
{
    $row = ApplicantCode::current();
    $row->forceFill([
        'code' => $code,
        'published' => true,
        'expires_on' => $expiresOn ?? OrgTime::today()->addMonth()->toDateString(),
    ])->save();

    return $row;
}

function publicApplication(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Applicant',
        'email' => 'ada@example.com',
        'phone' => '416-555-0101',
        'address_street' => '100 Queen\'s Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'alternate_phone' => '416-555-0102',
        'membership_number' => '123456',
        'membership_expiry' => '2027-05-31',
        'emergency_contact' => 'Ben Applicant',
        'emergency_contact_number' => '416-555-0103',
        'languages' => [
            ['language_code' => 'en', 'speaks' => true, 'reads' => true, 'writes' => true],
            ['language_code' => 'fr', 'speaks' => true, 'reads' => false, 'writes' => false],
            ['language_code' => 'asl', 'speaks' => true],
            ['language_code' => 'Japanese', 'speaks' => true, 'reads' => true, 'writes' => false],
        ],
        ...$overrides,
    ];
}

beforeEach(fn () => RateLimiter::clear('applicant-code|127.0.0.1'));

// The code dialog.

it('lets the holder Chair set the code, the publish flag and the expiry', function () {
    $org = codeOrg();

    $this->actingAs($org['chair'])
        ->put('/applicant-code', ['code' => 'INTAKE7', 'published' => true, 'expires_on' => '2026-12-31'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $row = ApplicantCode::current();
    expect($row->code)->toBe('INTAKE7')
        ->and($row->published)->toBeTrue()
        ->and($row->expires_on->toDateString())->toBe('2026-12-31')
        ->and($row->updated_by)->toBe($org['chair']->id);
});

it('refuses the code to a holder member who is not the Chair', function () {
    $org = codeOrg();

    $this->actingAs($org['member'])
        ->put('/applicant-code', ['code' => 'INTAKE7', 'published' => true, 'expires_on' => '2026-12-31'])
        ->assertForbidden();

    expect(ApplicantCode::current()->exists)->toBeFalse();
});

it('requires a code and an expiry to publish', function () {
    $org = codeOrg();

    $this->actingAs($org['chair'])
        ->put('/applicant-code', ['code' => '', 'published' => true, 'expires_on' => ''])
        ->assertSessionHasErrors(['code', 'expires_on']);
});

it('sends the code to the Applicants page for the Chair only', function () {
    $org = codeOrg();
    openCode('INTAKE7', '2026-12-31');

    $this->actingAs($org['chair'])->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page
            ->where('applicantCode.code', 'INTAKE7')
            ->where('applicantCode.published', true)
            ->where('applicantCode.expires_on', '2026-12-31'));

    $this->actingAs($org['member'])->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page->where('applicantCode', null));
});

// The sign-in page.

it('offers the code entry on the sign-in page only while published and unexpired', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('applicationOpen', false));

    $row = openCode();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('applicationOpen', true));

    $row->forceFill(['expires_on' => OrgTime::today()->toDateString()])->save();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('applicationOpen', true));

    $row->forceFill(['expires_on' => OrgTime::today()->subDay()->toDateString()])->save();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('applicationOpen', false));

    $row->forceFill(['expires_on' => OrgTime::today()->addDay()->toDateString(), 'published' => false])->save();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('applicationOpen', false));
});

// The code check.

it('shows the form for the right code, in either case', function () {
    openCode('ROM2026');

    $this->get('/apply?code=rom2026')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('apply/Form'));
});

it('refuses a wrong code with the number to call, and creates nothing', function () {
    openCode('ROM2026');

    $this->from('/login')->get('/apply?code=WRONG')
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['code' => __('apply.wrong_code', ['phone' => '(416) 586-8097'])]);

    expect(Applicant::count())->toBe(0);
});

it('refuses the right code once the application is closed', function () {
    openCode('ROM2026', OrgTime::today()->subDay()->toDateString());

    $this->from('/login')->get('/apply?code=ROM2026')->assertRedirect('/login')->assertSessionHasErrors('code');
});

it('throttles the sixth try from one address', function () {
    openCode('ROM2026');

    foreach (range(1, 5) as $try) {
        $this->from('/login')->get('/apply?code=WRONG'.$try)
            ->assertSessionHasErrors(['code' => __('apply.wrong_code', ['phone' => '(416) 586-8097'])]);
    }

    $this->from('/login')->get('/apply?code=ROM2026')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->not->toBe(__('apply.wrong_code', ['phone' => '(416) 586-8097']));
});

it('sends a visitor without the code back to the sign-in page', function () {
    openCode();

    $this->get('/apply')->assertRedirect('/login');
});

it('keeps a signed-in Member off the guest pages', function () {
    openCode('ROM2026');

    $this->actingAs(Member::factory()->create())->get('/apply?code=ROM2026')->assertRedirect();
    expect(Applicant::count())->toBe(0);
});

// The store.

it('stores a New, self-entered Applicant with its languages and shows the confirmation', function () {
    openCode('ROM2026');
    $this->get('/apply?code=ROM2026')->assertOk();

    $this->post('/apply', publicApplication())->assertRedirect('/apply');

    $applicant = Applicant::sole();
    expect($applicant->status)->toBe(ApplicantStatus::New)
        ->and($applicant->entered_by_applicant)->toBeTrue()
        ->and($applicant->mentor_id)->toBeNull()
        ->and($applicant->created_by)->toBeNull()
        ->and($applicant->applied_on->toDateString())->toBe(OrgTime::today()->toDateString())
        ->and($applicant->email)->toBe('ada@example.com')
        ->and($applicant->address_city)->toBe('Toronto')
        ->and($applicant->membership_expiry->toDateString())->toBe('2027-05-31')
        ->and($applicant->emergency_contact_number)->toBe('416-555-0103');

    expect($applicant->languages->sortBy(fn ($row) => strtolower($row->language_code))->values()->map->only(['language_code', 'speaks', 'reads', 'writes'])->all())
        ->toBe([
            ['language_code' => 'asl', 'speaks' => true, 'reads' => false, 'writes' => false],
            ['language_code' => 'en', 'speaks' => true, 'reads' => true, 'writes' => true],
            ['language_code' => 'fr', 'speaks' => true, 'reads' => false, 'writes' => false],
            ['language_code' => 'Japanese', 'speaks' => true, 'reads' => true, 'writes' => false],
        ]);

    $this->get('/apply')->assertInertia(fn (Assert $page) => $page->component('apply/Received'));
    // The code is spent: a second application needs it again.
    $this->get('/apply')->assertRedirect('/login');
});

it('creates nothing when a required field is missing or the phone is short', function (string $field, mixed $value) {
    openCode('ROM2026');
    $this->get('/apply?code=ROM2026');

    $this->post('/apply', publicApplication([$field => $value]))->assertSessionHasErrors($field);

    expect(Applicant::count())->toBe(0);
})->with([
    ['first_name', ''],
    ['last_name', ''],
    ['email', ''],
    ['email', 'not-an-email'],
    ['phone', ''],
    ['phone', '555-0101'],
]);

it('takes one other language at most, and none of the office fields', function () {
    openCode('ROM2026');
    $this->get('/apply?code=ROM2026');

    $this->post('/apply', publicApplication(['languages' => [
        ['language_code' => 'Japanese', 'speaks' => true],
        ['language_code' => 'Tagalog', 'speaks' => true],
    ]]))->assertSessionHasErrors('languages');

    $this->post('/apply', publicApplication(['business_phone' => '416-555-0199', 'membership_lifetime' => true]))
        ->assertRedirect('/apply');

    $applicant = Applicant::sole();
    expect($applicant->business_phone)->toBeNull()
        ->and($applicant->membership_lifetime)->not->toBeTrue();
});

it('refuses a store without the code, or once the code is withdrawn', function () {
    $row = openCode('ROM2026');

    $this->post('/apply', publicApplication())->assertForbidden();

    $this->get('/apply?code=ROM2026');
    $row->forceFill(['published' => false])->save();
    $this->post('/apply', publicApplication())->assertForbidden();

    $this->get('/apply?code=ROM2026');
    expect(Applicant::count())->toBe(0);
});

it('serves the form and the store at their French addresses', function () {
    openCode('ROM2026');

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/postuler?code=ROM2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('apply/Form')->where('locale', 'fr'));

        $this->post('/fr/postuler', publicApplication())->assertRedirect('/fr/postuler');
        $this->get('/fr/postuler')->assertInertia(fn (Assert $page) => $page->component('apply/Received')->where('locale', 'fr'));
    });
    expect(Applicant::count())->toBe(1);
});
