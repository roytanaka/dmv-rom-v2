<?php

use App\Enums\ApplicantStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Applicant;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Support\OrgTime;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Applicant record and the Applicants list (#928, ADR-0038 §8). The holder of the
 * `applicants` Stewardship runs intake: its Chair edits every Applicant, its members edit the ones
 * they mentor and read the rest; any Chair of an active Group and member administration read.
 */

/** The holder (Membership) with a Chair and a plain member, and a foreign Group with a Chair. */
function intakeOrg(): array
{
    $membership = Group::factory()->standingCommittee()->create(['slug' => 'membership', 'name' => 'Membership']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::Applicants)->create(['group_id' => $membership->id]);
    $other = Group::factory()->standingCommittee()->create(['slug' => 'awards', 'name' => 'Awards']);

    return [
        'membership' => $membership,
        'chair' => intakeMember($membership, Role::Chair, 'Chandra', 'Chair'),
        'member' => intakeMember($membership, null, 'Mo', 'Mentor'),
        'foreignChair' => intakeMember($other, Role::Chair, 'Fiona', 'Foreign'),
    ];
}

function intakeMember(Group $group, ?Role $role, string $first, string $last): Member
{
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last]);
    $membership = $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    if ($role !== null) {
        $membership->roles()->create(['role' => $role]);
    }

    return $member;
}

function intakeMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);

    return intakeMember($records, null, 'Rita', 'Records');
}

/** A full save of the record: the required fields, plus whatever the case is about. */
function applicantPayload(Applicant $applicant, array $overrides = []): array
{
    return [
        'first_name' => $applicant->first_name,
        'last_name' => $applicant->last_name,
        'email' => $applicant->email,
        'phone' => $applicant->phone,
        'status' => $applicant->status->value,
        'applied_on' => $applicant->applied_on->toDateString(),
        ...$overrides,
    ];
}

function rowIds(): Closure
{
    return fn ($rows) => collect($rows)->pluck('id')->all();
}

it('opens the list to the holder, any active Chair, member administration and the super-tier', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create();

    foreach ([$org['chair'], $org['member'], $org['foreignChair'], intakeMemberAdmin(), Member::factory()->superTier()->create()] as $reader) {
        $this->actingAs($reader)
            ->get('/officer/members/applicants')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officer/applicants/Index')
                ->where('applicants.data.0.id', $applicant->id));
    }
});

it('refuses a plain Member the list, the record and every write, and hides the rail item', function () {
    intakeOrg();
    $applicant = Applicant::factory()->create();
    $plain = Member::factory()->create();

    $this->actingAs($plain)->get('/officer/members/applicants')->assertForbidden();
    $this->get('/officer/members/applicants/new')->assertForbidden();
    $this->get("/officer/members/applicants/{$applicant->id}")->assertForbidden();
    $this->post('/applicants', ['first_name' => 'A', 'last_name' => 'B'])->assertForbidden();
    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant))->assertForbidden();

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->missing('rail.officer'));
});

it('shows the rail item to a holder member', function () {
    $org = intakeOrg();

    $this->actingAs($org['member'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('rail.officer.items', fn ($items) => collect($items)->contains(
            fn ($item) => $item['key'] === 'applicants' && $item['href'] === '/officer/members/applicants',
        )));
});

it('leaves the rail item to the Members item for member administration', function () {
    intakeOrg();

    $this->actingAs(intakeMemberAdmin())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('rail.officer.items', fn ($items) => collect($items)->pluck('key')->all() === ['members']));
});

it('shows the rail item to a Chair of another active Group', function () {
    $org = intakeOrg();

    $this->actingAs($org['foreignChair'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('rail.officer.items', fn ($items) => collect($items)->contains('key', 'applicants')));
});

it('refuses a Chair of an archived Group', function () {
    intakeOrg();
    $archived = Group::factory()->archived()->create();
    $chair = intakeMember($archived, Role::Chair, 'Ada', 'Archived');

    $this->actingAs($chair)->get('/officer/members/applicants')->assertForbidden();
});

it('lets the holder Chair edit any Applicant', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->mentoredBy($org['member'])->create();

    $this->actingAs($org['chair'])
        ->get("/officer/members/applicants/{$applicant->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/Record')->where('canEdit', true));

    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['first_name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($applicant->fresh()->first_name)->toBe('Renamed')
        ->and($applicant->fresh()->updated_by)->toBe($org['chair']->id);
});

it('lets a holder member edit their mentee and only read the others', function () {
    $org = intakeOrg();
    $mine = Applicant::factory()->mentoredBy($org['member'])->create();
    $theirs = Applicant::factory()->mentoredBy($org['chair'])->create();

    $this->actingAs($org['member']);
    $this->patch("/applicants/{$mine->id}", applicantPayload($mine, ['first_name' => 'Mine']))->assertSessionHasNoErrors();
    $this->get("/officer/members/applicants/{$theirs->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    $this->patch("/applicants/{$theirs->id}", applicantPayload($theirs, ['first_name' => 'Theirs']))->assertForbidden();

    expect($mine->fresh()->first_name)->toBe('Mine')
        ->and($theirs->fresh()->first_name)->not->toBe('Theirs');
});

it('lets a foreign Chair and member administration read but not write', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create();

    foreach ([$org['foreignChair'], intakeMemberAdmin()] as $reader) {
        $this->actingAs($reader)
            ->get("/officer/members/applicants/{$applicant->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
        $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant))->assertForbidden();
        $this->get('/officer/members/applicants/new')->assertForbidden();
        $this->post('/applicants', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'phone' => '4165550000'])->assertForbidden();
    }
});

it('defaults to All, leaving out Incomplete and Ready to go active', function () {
    $org = intakeOrg();
    $live = collect(ApplicantStatus::live())->map(fn (ApplicantStatus $status) => Applicant::factory()->status($status)->create()->id);
    Applicant::factory()->status(ApplicantStatus::Incomplete)->create(['departure_reason' => 'moved']);
    Applicant::factory()->status(ApplicantStatus::ReadyToGoActive)->create();

    $this->actingAs($org['member'])->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'all')
            ->has('applicants.data', 5)
            ->where('applicants.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === $live->sort()->values()->all()));
});

it('filters to one status', function (ApplicantStatus $status) {
    $org = intakeOrg();
    $rows = collect(ApplicantStatus::cases())->mapWithKeys(fn (ApplicantStatus $case) => [
        $case->value => Applicant::factory()->status($case)->create()->id,
    ]);

    $this->actingAs($org['member'])->get("/officer/members/applicants?status={$status->value}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', $status->value)
            ->where('applicants.data', fn ($data) => collect($data)->pluck('id')->all() === [$rows[$status->value]]));
})->with(ApplicantStatus::cases());

it('searches by the start of the last name', function () {
    $org = intakeOrg();
    $match = Applicant::factory()->create(['last_name' => 'Okafor']);
    Applicant::factory()->create(['last_name' => 'Bokamba']);

    $this->actingAs($org['member'])->get('/officer/members/applicants?q=oka')
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'oka')
            ->where('applicants.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$match->id]));
});

it('sorts by mentor then last name, the unassigned first, with the list columns', function () {
    $org = intakeOrg();
    $placement = Group::factory()->create(['name' => 'Gallery Interpreters']);
    $zed = Applicant::factory()->mentoredBy($org['member'])->create(['last_name' => 'Zed']);
    $abe = Applicant::factory()->mentoredBy($org['member'])->create(['last_name' => 'Abe']);
    $chairs = Applicant::factory()->mentoredBy($org['chair'])->status(ApplicantStatus::Placed)
        ->create(['last_name' => 'Young', 'placement_group_id' => $placement->id]);
    $unassigned = Applicant::factory()->create(['last_name' => 'Xu']);

    $this->actingAs($org['chair'])->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page
            ->where('applicants.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$unassigned->id, $chairs->id, $abe->id, $zed->id])
            ->where('applicants.data.1.mentor', 'Chandra Chair')
            ->where('applicants.data.1.status', 'placed')
            ->where('applicants.data.1.placement', 'Gallery Interpreters')
            ->where('applicants.data.1.email', $chairs->email)
            ->has('applicants.data.1.applied_on')
            ->has('applicants.data.1.last_update')
            ->where('applicants.data.0.mentor', null));
});

it('serves the pages at their French addresses', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create();

    $this->withLocaleRoutes('fr', function () use ($org, $applicant) {
        $this->actingAs($org['chair']);
        $this->get('/fr/officier/membres/candidats')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/Index')->where('locale', 'fr'));
        $this->get('/fr/officier/membres/candidats/nouveau')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/Create'));
        $this->get("/fr/officier/membres/candidats/{$applicant->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/Record'));
    });
});

it('keeps the Member list tabs for member administration only', function () {
    $org = intakeOrg();

    $this->actingAs(intakeMemberAdmin())->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page->where('memberListTabs.6', ['key' => 'applicants', 'href' => '/officer/members/applicants']));
    $this->actingAs($org['member'])->get('/officer/members/applicants')
        ->assertInertia(fn (Assert $page) => $page->where('memberListTabs', null));
});

/** A paper application as Add applicant sends it. */
function newApplication(array $overrides = []): array
{
    return [
        'first_name' => 'Priya',
        'last_name' => 'Nair',
        'email' => 'priya.nair@example.test',
        'phone' => '416-555-0142',
        'address_city' => 'Toronto',
        'membership_number' => 'R123',
        'languages' => [
            ['language_code' => 'en', 'speaks' => true, 'reads' => true, 'writes' => true],
            ['language_code' => 'asl', 'speaks' => true, 'reads' => true, 'writes' => false],
            ['language_code' => 'Tamil', 'speaks' => true, 'reads' => false, 'writes' => false],
        ],
        ...$overrides,
    ];
}

it('offers the holder roster as mentors, the Chair first', function () {
    $org = intakeOrg();

    $this->actingAs($org['member'])->get('/officer/members/applicants/new')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/applicants/Create')
            ->where('mentors', [
                ['id' => $org['chair']->id, 'name' => 'Chandra Chair'],
                ['id' => $org['member']->id, 'name' => 'Mo Mentor'],
            ]));
});

it('adds an application as New today, recording the entering Member, the mentor and the languages', function () {
    $org = intakeOrg();

    $this->actingAs($org['member'])
        ->post('/applicants', newApplication(['mentor_id' => $org['chair']->id]))
        ->assertSessionHasNoErrors();

    $applicant = Applicant::sole();
    expect($applicant->status)->toBe(ApplicantStatus::New)
        ->and($applicant->first_name)->toBe('Priya')
        ->and($applicant->address_city)->toBe('Toronto')
        ->and($applicant->mentor_id)->toBe($org['chair']->id)
        ->and($applicant->created_by)->toBe($org['member']->id)
        ->and($applicant->updated_by)->toBe($org['member']->id)
        ->and($applicant->entered_by_applicant)->toBeFalse()
        ->and($applicant->applied_on->toDateString())->toBe(OrgTime::today()->toDateString())
        ->and($applicant->status_date->toDateString())->toBe(OrgTime::today()->toDateString())
        ->and($applicant->languages()->orderBy('id')->get(['language_code', 'speaks', 'reads', 'writes'])->toArray())->toBe([
            ['language_code' => 'en', 'speaks' => true, 'reads' => true, 'writes' => true],
            ['language_code' => 'asl', 'speaks' => true, 'reads' => false, 'writes' => false],
            ['language_code' => 'Tamil', 'speaks' => true, 'reads' => false, 'writes' => false],
        ]);

    $this->get("/officer/members/applicants/{$applicant->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('enteredBy', 'Mo Mentor')
            ->where('lastUpdated.name', 'Mo Mentor'));
});

it('requires the names, the email and a primary phone of ten characters', function () {
    $org = intakeOrg();

    $this->actingAs($org['member'])
        ->post('/applicants', newApplication(['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '555-0142']))
        ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);

    expect(Applicant::count())->toBe(0);
});

it('refuses a mentor outside the holder', function () {
    $org = intakeOrg();

    $this->actingAs($org['member'])
        ->post('/applicants', newApplication(['mentor_id' => $org['foreignChair']->id]))
        ->assertSessionHasErrors('mentor_id');
});

it('flags an email a Member already has, and adds the applicant once the office overrides', function () {
    $org = intakeOrg();
    Member::factory()->create(['email' => 'priya.nair@example.test']);

    $this->actingAs($org['member'])
        ->post('/applicants', newApplication())
        ->assertSessionHasErrors(['duplicate_email' => 'A Member already has this email.']);
    expect(Applicant::count())->toBe(0);

    $this->post('/applicants', newApplication(['override_duplicate_email' => true]))->assertSessionHasNoErrors();
    expect(Applicant::count())->toBe(1);
});

it('moves the status date with a status change, and only then', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create(['status_date' => '2026-01-15']);

    $this->actingAs($org['chair'])
        ->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['first_name' => 'Same status']))
        ->assertSessionHasNoErrors();
    expect($applicant->fresh()->status_date->toDateString())->toBe('2026-01-15');

    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['status' => 'assigned_for_interview']))
        ->assertSessionHasNoErrors();
    expect($applicant->fresh()->status)->toBe(ApplicantStatus::AssignedForInterview)
        ->and($applicant->fresh()->status_date->toDateString())->toBe(OrgTime::today()->toDateString());
});

it('requires a reason for Incomplete, and drops it for any other status', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create();

    $this->actingAs($org['chair'])
        ->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['status' => 'incomplete']))
        ->assertSessionHasErrors('departure_reason');
    expect($applicant->fresh()->status)->toBe(ApplicantStatus::New);

    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['status' => 'incomplete', 'departure_reason' => 'schedule']))
        ->assertSessionHasNoErrors();
    expect($applicant->fresh()->departure_reason?->value)->toBe('schedule');

    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['status' => 'new', 'departure_reason' => 'schedule']))
        ->assertSessionHasNoErrors();
    expect($applicant->fresh()->departure_reason)->toBeNull();
});

it('prepends a dated, signed note and keeps the earlier lines', function () {
    $org = intakeOrg();
    $applicant = Applicant::factory()->create(['notes' => '2025-03-01: Phoned, left a message.']);

    $this->actingAs($org['chair'])
        ->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['new_note' => 'Interview booked.', 'notes' => 'overwritten']))
        ->assertSessionHasNoErrors();

    expect($applicant->fresh()->notes)->toBe(OrgTime::now()->toDateString().' Chandra Chair: Interview booked.'."\n".'2025-03-01: Phoned, left a message.');
});

it('saves the placement, the mentor and the languages with the record', function () {
    $org = intakeOrg();
    $placement = Group::factory()->create();
    $applicant = Applicant::factory()->create();
    $applicant->languages()->create(['language_code' => 'fr', 'speaks' => true]);

    $this->actingAs($org['chair'])
        ->patch("/applicants/{$applicant->id}", applicantPayload($applicant, [
            'placement_group_id' => $placement->id,
            'mentor_id' => $org['member']->id,
            'education' => 'BA, history',
            'languages' => [['language_code' => 'EN', 'speaks' => true, 'reads' => true, 'writes' => false]],
        ]))
        ->assertSessionHasNoErrors();

    $applicant->refresh();
    expect($applicant->placement_group_id)->toBe($placement->id)
        ->and($applicant->mentor_id)->toBe($org['member']->id)
        ->and($applicant->education)->toBe('BA, history')
        ->and($applicant->languages()->pluck('language_code')->all())->toBe(['en']);
});

it('refuses an archived placement Group, unless the Applicant already has it', function () {
    $org = intakeOrg();
    $archived = Group::factory()->archived()->create();
    $applicant = Applicant::factory()->create();

    $this->actingAs($org['chair'])
        ->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['placement_group_id' => $archived->id]))
        ->assertSessionHasErrors('placement_group_id');

    $applicant->forceFill(['placement_group_id' => $archived->id])->save();
    $this->patch("/applicants/{$applicant->id}", applicantPayload($applicant, ['placement_group_id' => $archived->id]))
        ->assertSessionHasNoErrors();
});
