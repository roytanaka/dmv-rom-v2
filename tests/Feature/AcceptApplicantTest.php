<?php

use App\Enums\ApplicantStatus;
use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Mail\ApplicantNotice;
use App\Models\Applicant;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Facades\Mail;

/*
 * Accept an Applicant and the intake Notices (#931, ADR-0038 §4, §8, §14.5). A move to Accepted or
 * Placed with no linked Member creates a Provisional Member (or updates the Member matching by
 * email and name) and links it; status moves tell the Records office, the mentor and the Treasurer.
 */

/** The holder with its Chair, the Records Group with two members, the Executive with a Treasurer. */
function acceptOrg(): array
{
    $membership = Group::factory()->standingCommittee()->create(['slug' => 'membership', 'name' => 'Membership']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::Applicants)->create(['group_id' => $membership->id]);
    $records = Group::factory()->create(['name' => 'Records']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $executive = Group::factory()->create(['slug' => Group::EXECUTIVE_SLUG, 'name' => 'Executive']);

    return [
        'membership' => $membership,
        'chair' => acceptMember($membership, Role::Chair, 'Chandra', 'Chair'),
        'mentor' => acceptMember($membership, null, 'Mo', 'Mentor'),
        'records' => [
            acceptMember($records, null, 'Rita', 'Records'),
            acceptMember($records, Role::Chair, 'Rory', 'Records'),
        ],
        'treasurer' => acceptMember($executive, Role::Treasurer, 'Tess', 'Treasurer'),
        'president' => acceptMember($executive, Role::Chair, 'Pat', 'President'),
    ];
}

function acceptMember(Group $group, ?Role $role, string $first, string $last): Member
{
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last]);
    $membership = $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    if ($role !== null) {
        $membership->roles()->create(['role' => $role]);
    }

    return $member;
}

/** A filled-in Applicant mentored by the org's mentor. */
function acceptApplicant(array $org, array $attributes = []): Applicant
{
    $applicant = Applicant::factory()->mentoredBy($org['mentor'])->status(ApplicantStatus::AssignedForInterview)->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'phone' => '416-555-0101',
        'alternate_phone' => '416-555-0102',
        'address_street' => '100 Queen\'s Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'membership_number' => 'ROM-123',
        'membership_expiry' => '2027-06-30',
        'emergency_contact' => 'Byron Lovelace',
        'emergency_contact_number' => '416-555-0199',
        'volunteer_experience' => 'Library',
        'past_experience' => 'Teacher',
        'education' => 'Mathematics',
        'notes' => '2026-10-01 Chandra Chair: Interviewed.',
        ...$attributes,
    ]);
    $applicant->languages()->create(['language_code' => 'fr', 'speaks' => true, 'reads' => true, 'writes' => false]);

    return $applicant;
}

function moveApplicant(Member $actor, Applicant $applicant, string $status, array $overrides = [])
{
    return test()->actingAs($actor)->patch("/applicants/{$applicant->id}", [
        'first_name' => $applicant->first_name,
        'last_name' => $applicant->last_name,
        'email' => $applicant->email,
        'phone' => $applicant->phone,
        'alternate_phone' => $applicant->alternate_phone,
        'address_street' => $applicant->address_street,
        'address_city' => $applicant->address_city,
        'address_province' => $applicant->address_province,
        'address_postal_code' => $applicant->address_postal_code,
        'membership_number' => $applicant->membership_number,
        'membership_expiry' => $applicant->membership_expiry?->toDateString(),
        'emergency_contact' => $applicant->emergency_contact,
        'emergency_contact_number' => $applicant->emergency_contact_number,
        'volunteer_experience' => $applicant->volunteer_experience,
        'past_experience' => $applicant->past_experience,
        'education' => $applicant->education,
        'mentor_id' => $applicant->mentor_id,
        'placement_group_id' => $applicant->placement_group_id,
        'applied_on' => $applicant->applied_on->toDateString(),
        'languages' => $applicant->languages->map->only(['language_code', 'speaks', 'reads', 'writes'])->all(),
        'status' => $status,
        ...$overrides,
    ]);
}

/** @return list<int|null> */
function noticeRecipients(string $type): array
{
    return Delivery::where('kind', DeliveryKind::Notice)->get()
        ->filter(fn (Delivery $row) => $row->payload['notice'] === $type)
        ->pluck('member_id')->sort()->values()->all();
}

it('creates a Provisional Member from an accepted Applicant and keeps the Applicant linked', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org);

    moveApplicant($org['chair'], $applicant, 'accepted')->assertSessionHasNoErrors();

    $applicant->refresh();
    $member = $applicant->member;
    expect($member)->not->toBeNull()
        ->and($member->category)->toBe(Category::Provisional)
        ->and($member->status_date->toDateString())->toBe(OrgTime::today()->toDateString())
        ->and($member->categoryChanges()->sole()->category)->toBe('provisional')
        ->and($member->only(['first_name', 'last_name', 'email', 'phone', 'alternate_phone', 'address_city', 'address_postal_code',
            'membership_number', 'emergency_contact', 'emergency_contact_number', 'volunteer_experience', 'past_experience', 'education']))
        ->toBe([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'phone' => '416-555-0101',
            'alternate_phone' => '416-555-0102', 'address_city' => 'Toronto', 'address_postal_code' => 'M5S 2C6',
            'membership_number' => 'ROM-123', 'emergency_contact' => 'Byron Lovelace', 'emergency_contact_number' => '416-555-0199',
            'volunteer_experience' => 'Library', 'past_experience' => 'Teacher', 'education' => 'Mathematics',
        ])
        ->and($member->membership_expiry->toDateString())->toBe('2027-06-30')
        ->and($member->mentor_name)->toBe('Mo Mentor')
        ->and($member->notes)->toBe('2026-10-01 Chandra Chair: Interviewed.')
        ->and($member->entry_year)->toBe(OrgTime::today()->year)
        ->and($member->created_by)->toBe($org['chair']->id)
        ->and($member->languages()->sole()->only(['language_code', 'speaks', 'reads', 'writes']))
        ->toBe(['language_code' => 'fr', 'speaks' => true, 'reads' => true, 'writes' => false])
        ->and($applicant->status)->toBe(ApplicantStatus::Accepted)
        ->and($applicant->entry_year)->toBe(OrgTime::today()->year)
        ->and(Applicant::count())->toBe(1);
});

it('accepts on a direct move to Placed too, and only once', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org, ['placement_group_id' => $org['membership']->id]);

    moveApplicant($org['chair'], $applicant, 'placed')->assertSessionHasNoErrors();
    moveApplicant($org['chair'], $applicant->fresh(), 'placed', ['first_name' => 'Ada B.'])->assertSessionHasNoErrors();

    expect(Member::where('email', 'ada@example.test')->count())->toBe(1)
        ->and($applicant->fresh()->member_id)->not->toBeNull();
});

it('accepts only on a move, so a save on an unlinked Accepted Applicant makes no Member', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org, ['status' => ApplicantStatus::Accepted]);

    moveApplicant($org['chair'], $applicant, 'accepted', ['new_note' => 'Called back.'])->assertSessionHasNoErrors();

    expect($applicant->fresh()->member_id)->toBeNull()
        ->and(Member::where('email', 'ada@example.test')->exists())->toBeFalse()
        ->and(Delivery::count())->toBe(0);
});

it('updates a Member matching by email and name, leaving its Category alone', function () {
    $org = acceptOrg();
    $donor = Member::factory()->category(Category::Donor)->create([
        'first_name' => 'ADA', 'last_name' => 'lovelace', 'email' => 'ada@example.test', 'membership_number' => 'OLD', 'education' => 'Kept',
        'entry_year' => 2001,
    ]);
    $applicant = acceptApplicant($org, ['education' => null]);

    moveApplicant($org['chair'], $applicant, 'accepted')->assertSessionHasNoErrors();

    $donor->refresh();
    expect($applicant->fresh()->member_id)->toBe($donor->id)
        ->and(Member::count())->toBe(7)
        ->and($donor->category)->toBe(Category::Donor)
        ->and($donor->categoryChanges()->count())->toBe(0)
        ->and($donor->membership_number)->toBe('ROM-123')
        ->and($donor->education)->toBe('Kept')
        ->and($donor->entry_year)->toBe(OrgTime::today()->year)
        ->and($applicant->fresh()->entry_year)->toBe(OrgTime::today()->year)
        ->and($donor->languages()->pluck('language_code')->all())->toBe(['fr']);
});

it('refuses to accept when a Member under another name has the email', function () {
    $org = acceptOrg();
    Member::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else', 'email' => 'ada@example.test']);
    $applicant = acceptApplicant($org);

    moveApplicant($org['chair'], $applicant, 'accepted')->assertSessionHasErrors('email');

    expect($applicant->fresh()->status)->toBe(ApplicantStatus::AssignedForInterview)
        ->and($applicant->fresh()->member_id)->toBeNull()
        ->and(Delivery::count())->toBe(0);
});

it('tells every Records member and the mentor on accept, and nobody else', function () {
    $org = acceptOrg();
    $org['records'][1]->forceFill(['no_email' => true])->save();
    $applicant = acceptApplicant($org);

    moveApplicant($org['chair'], $applicant, 'accepted')->assertSessionHasNoErrors();

    expect(noticeRecipients(ApplicantNotice::ACCEPTED))->toBe([$org['mentor']->id, $org['records'][0]->id])
        ->and(Delivery::count())->toBe(2)
        ->and(Delivery::pluck('email'))->not->toContain('ada@example.test');

    $row = Delivery::where('member_id', $org['records'][0]->id)->sole();
    expect($row->payload['member']['id'])->toBe($applicant->fresh()->member_id);

    Mail::fake();
    $this->artisan('mail:drain')->assertSuccessful();
    Mail::assertSent(ApplicantNotice::class, fn (ApplicantNotice $mail) => $mail->hasTo($org['records'][0]->email)
        && str_contains($mail->render(), '/officer/members/records/'.$applicant->fresh()->member_id));
});

it('tells the mentor when an accepted Applicant is placed', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org);
    moveApplicant($org['chair'], $applicant, 'accepted');
    Delivery::query()->delete();

    moveApplicant($org['chair'], $applicant->fresh(), 'placed', ['placement_group_id' => $org['membership']->id])->assertSessionHasNoErrors();

    expect(noticeRecipients(ApplicantNotice::PLACED))->toBe([$org['mentor']->id])
        ->and(Delivery::count())->toBe(1);
});

it('tells the Treasurer of the Executive on yellow card completed', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org, ['status' => ApplicantStatus::Placed, 'member_id' => $org['records'][0]->id]);

    moveApplicant($org['chair'], $applicant, 'yellow_card_completed')->assertSessionHasNoErrors();

    expect(noticeRecipients(ApplicantNotice::YELLOW_CARD))->toBe([$org['treasurer']->id])
        ->and(Delivery::count())->toBe(1);
});

it('tells the mentor with the reason when a linked Applicant goes Incomplete, and nobody when unlinked', function () {
    $org = acceptOrg();
    $unlinked = acceptApplicant($org);
    moveApplicant($org['chair'], $unlinked, 'incomplete', ['departure_reason' => 'schedule'])->assertSessionHasNoErrors();
    expect(Delivery::count())->toBe(0);

    $linked = acceptApplicant($org, ['email' => 'b@example.test', 'status' => ApplicantStatus::Placed, 'member_id' => $org['records'][0]->id]);
    moveApplicant($org['chair'], $linked, 'incomplete', ['departure_reason' => 'schedule'])->assertSessionHasNoErrors();

    $row = Delivery::sole();
    expect($row->member_id)->toBe($org['mentor']->id)
        ->and($row->payload['notice'])->toBe(ApplicantNotice::INCOMPLETE)
        ->and($row->payload['reason'])->toBe('schedule');
});

it('skips a mentor carrying the no-email flag', function () {
    $org = acceptOrg();
    $org['mentor']->forceFill(['no_email' => true])->save();
    $applicant = acceptApplicant($org, ['status' => ApplicantStatus::Accepted, 'member_id' => $org['records'][0]->id]);

    moveApplicant($org['chair'], $applicant, 'placed')->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(0);
});

it('sends the activation mentor Notice to the mentor of the linked Applicant', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org);
    moveApplicant($org['chair'], $applicant, 'accepted');
    Delivery::query()->delete();
    $member = $applicant->fresh()->member;

    $this->actingAs($org['records'][0])
        ->post(route('members.category-changes.store', ['member' => $member->id]), ['category' => 'active', 'date' => OrgTime::today()->toDateString()])
        ->assertSessionHasNoErrors();

    expect(Delivery::sole()->member_id)->toBe($org['mentor']->id);
});

it('links the Member record from the Applicant for member administration only', function () {
    $org = acceptOrg();
    $applicant = acceptApplicant($org);
    moveApplicant($org['chair'], $applicant, 'accepted');

    $this->actingAs($org['records'][0])->get("/officer/members/applicants/{$applicant->id}")
        ->assertInertia(fn ($page) => $page->where('canOpenMember', true)->where('applicant.member_id', $applicant->fresh()->member_id)->etc());
    $this->actingAs($org['chair'])->get("/officer/members/applicants/{$applicant->id}")
        ->assertInertia(fn ($page) => $page->where('canOpenMember', false)->etc());
});
