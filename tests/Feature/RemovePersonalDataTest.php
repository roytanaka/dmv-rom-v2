<?php

use App\Enums\ApplicantStatus;
use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Applicant;
use App\Models\ApplicantLanguage;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Remove personal data (#921, ADR-0038 §6): the yearly privacy clean-up. For every Resigned or
 * Deceased Member whose status date is before 1 January of the chosen year, it blanks the
 * personal fields, keeps name, history and hours, and moves the Member to the Past list.
 */

function removalMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create();
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

/** A Member with every field the run blanks filled in. */
function removalFilledMember(Category $category, string $statusDate): Member
{
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/'.$statusDate.$category->value.'.webp', 'x');

    $member = Member::factory()->create([
        'category' => $category,
        'first_name' => 'Kept',
        'last_name' => 'Name',
        'phone' => '416-555-0100',
        'alternate_phone' => '416-555-0101',
        'business_phone' => '416-555-0102',
        'address_street' => '100 Queen\'s Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'address_country' => 'Canada',
        'membership_number' => 'R123',
        'membership_expiry' => '2019-06-30',
        'membership_lifetime' => true,
        'amount_paid' => '75.00',
        'paid_through_year' => 2018,
        'volunteer_experience' => 'Lots',
        'past_experience' => 'Some',
        'education' => 'BA',
        'next_award_year' => 2020,
        'emergency_contact' => 'Kin',
        'emergency_contact_number' => '416-555-0199',
        'photo_public' => true,
        'entry_year' => 2005,
        'mentor_name' => 'Mentor',
    ]);
    $member->forceFill([
        'status_date' => $statusDate,
        'notes' => '2018-01-01 Rita Records: moved',
        'photo_path' => 'profile-photos/'.$statusDate.$category->value.'.webp',
    ])->save();
    $member->languages()->create(['language_code' => 'fr', 'speaks' => true]);
    $member->skills()->attach(Skill::factory()->create(['category_id' => SkillCategory::factory()->create()->id]));
    $member->categoryChanges()->create(['category' => $category->value, 'date' => $statusDate]);
    $member->boardPositions()->create(['position' => 'Treasurer', 'years' => '2010-2012']);

    return $member;
}

it('previews the counts for a year, defaulting to seven years ago', function () {
    $this->travelTo('2026-11-03');
    $admin = removalMemberAdmin();
    Member::factory()->create(['category' => Category::Resigned])->forceFill(['status_date' => '2018-05-01'])->save();
    Member::factory()->create(['category' => Category::Withdrawn])->forceFill(['status_date' => '2018-12-31'])->save();
    Member::factory()->create(['category' => Category::Deceased])->forceFill(['status_date' => '2015-01-01'])->save();
    Member::factory()->create(['category' => Category::Resigned])->forceFill(['status_date' => '2019-01-01'])->save();
    Member::factory()->create(['category' => Category::Active])->forceFill(['status_date' => '2010-01-01'])->save();
    Member::factory()->create(['category' => Category::Resigned, 'personal_data_removed_at' => '2020-01-01'])
        ->forceFill(['status_date' => '2010-01-01'])->save();

    $this->actingAs($admin)->get('/officer/members/remove-personal-data')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/members/RemovePersonalData')
            ->where('year', 2019)
            ->where('memberCount', 3)
            ->where('applicantCount', 0));

    $this->actingAs($admin)->get('/officer/members/remove-personal-data?year=2016')
        ->assertInertia(fn (Assert $page) => $page->where('year', 2016)->where('memberCount', 1));

    $this->withLocaleRoutes('fr', function () use ($admin) {
        $this->actingAs($admin)->get('/fr/officier/membres/retrait-donnees-personnelles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/members/RemovePersonalData'));
    });
});

it('blanks the personal fields on exactly the qualifying Members and logs once', function () {
    $this->travelTo('2026-11-03 10:00:00');
    $admin = removalMemberAdmin();
    $old = removalFilledMember(Category::Resigned, '2018-05-01');
    $oldPhoto = $old->getAttributes()['photo_path'];
    HoursRecord::factory()->create(['member_id' => $old->id]);
    $recent = Member::factory()->create(['category' => Category::Deceased, 'phone' => '416-555-0000']);
    $recent->forceFill(['status_date' => '2019-01-01'])->save();
    $active = Member::factory()->create(['category' => Category::Active, 'phone' => '416-555-0001']);
    $active->forceFill(['status_date' => '2010-01-01'])->save();
    Log::spy();

    $this->actingAs($admin)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => '1'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/members/past');

    $old->refresh();
    foreach ([
        'email', 'phone', 'alternate_phone', 'business_phone', 'address_street', 'address_city',
        'address_province', 'address_postal_code', 'address_country', 'membership_number',
        'membership_expiry', 'paid_through_year', 'volunteer_experience', 'past_experience',
        'education', 'next_award_year', 'emergency_contact', 'emergency_contact_number',
        'photo_path', 'notes', 'amount_paid',
    ] as $field) {
        expect($old->getAttributes()[$field])->toBeNull("{$field} should be blank");
    }
    expect($old->photo_public)->toBeFalse()
        ->and($old->personal_data_removed_at->toDateTimeString())->toBe('2026-11-03 10:00:00')
        ->and($old->first_name)->toBe('Kept')
        ->and($old->last_name)->toBe('Name')
        ->and($old->entry_year)->toBe(2005)
        ->and($old->languages()->count())->toBe(0)
        ->and($old->skills()->count())->toBe(0)
        ->and($old->categoryChanges()->count())->toBe(1)
        ->and($old->boardPositions()->count())->toBe(1)
        ->and(HoursRecord::where('member_id', $old->id)->count())->toBe(1)
        ->and(Storage::disk('public')->exists($oldPhoto))->toBeFalse()
        ->and($recent->refresh()->phone)->toBe('416-555-0000')
        ->and($recent->personal_data_removed_at)->toBeNull()
        ->and($active->refresh()->phone)->toBe('416-555-0001');

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context) => $context['count'] === 1
        && $context['actor_id'] === $admin->id);

    $this->actingAs($admin)->get('/officer/members/past')
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$old->id]));
});

it('requires the typed count', function (string $typed) {
    $admin = removalMemberAdmin();
    $old = Member::factory()->create(['category' => Category::Resigned, 'phone' => '416-555-0000']);
    $old->forceFill(['status_date' => '2010-01-01'])->save();

    $this->actingAs($admin)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => $typed])
        ->assertSessionHasErrors('confirm_count');

    expect($old->refresh()->phone)->toBe('416-555-0000');
})->with(['wrong' => '2', 'blank' => '']);

it('keeps a Member whose data was removed from signing in, and the form fails cleanly', function () {
    $this->travelTo('2026-11-03');
    $admin = removalMemberAdmin();
    $old = Member::factory()->create(['category' => Category::Resigned, 'email' => 'gone@dmv.test']);
    $old->forceFill(['status_date' => '2010-01-01'])->save();

    $this->actingAs($admin)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => '1'])
        ->assertSessionHasNoErrors();
    auth()->logout();

    $this->post('/login', ['email' => '', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'gone@dmv.test', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('forbids a Member without member administration', function () {
    $member = Member::factory()->create();

    $this->actingAs($member)->get('/officer/members/remove-personal-data')->assertForbidden();
    $this->actingAs($member)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => '0'])
        ->assertForbidden();
});

it('counts and deletes exactly the Applicants whose status date is before the year, linked or not', function () {
    $this->travelTo('2026-11-03');
    $admin = removalMemberAdmin();
    $old = Applicant::factory()->status(ApplicantStatus::Incomplete)->create(['status_date' => '2018-12-31']);
    $old->languages()->create(['language_code' => 'fr', 'speaks' => true]);
    $linked = Applicant::factory()->status(ApplicantStatus::ReadyToGoActive)
        ->create(['status_date' => '2015-06-01', 'member_id' => Member::factory()->create()->id]);
    $newer = Applicant::factory()->create(['status_date' => '2019-01-01']);
    $undated = Applicant::factory()->create(['status_date' => null]);
    Log::spy();

    $this->actingAs($admin)->get('/officer/members/remove-personal-data?year=2019')
        ->assertInertia(fn (Assert $page) => $page->where('memberCount', 0)->where('applicantCount', 2));

    // The typed number is the total, Members and Applicants (ADR-0038 §6: it names the count).
    $this->actingAs($admin)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => '0'])
        ->assertSessionHasErrors('confirm_count');
    expect(Applicant::count())->toBe(4);

    $this->actingAs($admin)
        ->post(route('members.remove-personal-data'), ['year' => 2019, 'confirm_count' => '2'])
        ->assertSessionHasNoErrors();

    expect(Applicant::pluck('id')->sort()->values()->all())->toBe([$newer->id, $undated->id])
        ->and(ApplicantLanguage::where('applicant_id', $old->id)->count())->toBe(0)
        ->and(Member::find($linked->member_id))->not->toBeNull();

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context) => $context['count'] === 0
        && $context['applicant_count'] === 2);
});
