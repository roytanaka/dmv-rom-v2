<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Mail\RenewalSubmitted;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\RenewalSetting;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Step 2 of the Renewal (#940, ADR-0038 §9, §14.5): the renewal form, pre-filled from the Member
 * record. Save writes the renewal columns, the four placement texts rendered server-side, and a
 * Notice to the Records Audience with the changed fields. Prior art: RenewalCodeOfConductTest, the
 * standing-change Notice test.
 */

function openRenewalForm(int $year = 2027, array $settings = []): void
{
    RenewalSetting::current()->forceFill(['show_renewal' => true, 'renewal_year' => $year, ...$settings])->save();
}

function renewalFormMember(array $attributes = []): Member
{
    return Member::factory()->create([
        'first_name' => 'Dana',
        'last_name' => 'Okafor',
        'phone' => '416-555-0101',
        'alternate_phone' => null,
        'business_phone' => null,
        'address_street' => '100 Queen\'s Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'membership_number' => '123456',
        'membership_expiry' => '2027-06-30',
        'membership_lifetime' => false,
        'emergency_contact' => '2026 Sam Okafor',
        'emergency_contact_number' => '416-555-0199',
        'paid_through_year' => 2026,
        'code_of_conduct_year' => 2027,
        ...$attributes,
    ]);
}

/** Put the Member in a Group, optionally with roles. */
function placeIn(Member $member, string $groupName, array $roles = [], MembershipStatus $status = MembershipStatus::Full): Group
{
    $group = Group::factory()->create(['name' => $groupName]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id, 'status' => $status]);

    foreach ($roles as $role) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $group;
}

it('pre-fills the form from the Member record', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Visitor Wayfinders', [Role::Chair]);
    placeIn($member, 'Docents');

    $this->actingAs($member)->get('/renew/form')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/Form')
            ->where('year', 2027)
            ->where('editing', false)
            ->where('fields.membership_number', '123456')
            ->where('fields.membership_expiry', '2027-06-30')
            ->where('fields.membership_lifetime', false)
            ->where('fields.first_name', 'Dana')
            ->where('fields.address_postal_code', 'M5S 2C6')
            ->where('fields.emergency_contact', 'Sam Okafor')
            ->where('fields.emergency_contact_number', '416-555-0199')
            ->where('fields.category', null)
            ->where('placements', [
                ['name' => 'Docents', 'position' => 'Member', 'checked' => false],
                ['name' => 'Visitor Wayfinders', 'position' => 'Chair', 'checked' => false],
            ])
            ->has('groups')
            ->where('categories.0.code', 'Active')
            ->where('categories', fn ($rows) => collect($rows)->pluck('code')->doesntContain('Hon')));
});

it('opens the French twin', function () {
    openRenewalForm();

    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(renewalFormMember())->get('/fr/renouveler/formulaire')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('renew/Form'));
    });
});

/** A valid submission for renewalFormMember(), unchanged but for the overrides. */
function renewalSubmission(array $overrides = []): array
{
    return [
        'membership_number' => '123456',
        'membership_number_confirmed' => true,
        'membership_lifetime' => false,
        'membership_expiry' => '2027-06-30',
        'first_name' => 'Dana',
        'last_name' => 'Okafor',
        'address_street' => '100 Queen\'s Park',
        'address_city' => 'Toronto',
        'address_province' => 'ON',
        'address_postal_code' => 'M5S 2C6',
        'phone' => '416-555-0101',
        'alternate_phone' => null,
        'business_phone' => null,
        'placements' => [0],
        'other' => [['group_id' => null, 'position' => 'Member'], ['group_id' => null, 'position' => 'Member']],
        'other_texts' => ['', ''],
        'emergency_contact' => 'Sam Okafor',
        'emergency_contact_number' => '416-555-0199',
        'emergency_verified' => true,
        'category' => 'Active',
        ...$overrides,
    ];
}

/** A Member of the Group holding member administration (the Records Audience). */
function renewalRecordsMember(array $attributes = []): Member
{
    $records = Group::stewardOf(StewardshipFunction::MemberAdmin);

    if ($records === null) {
        $records = Group::factory()->create(['name' => 'Volunteer Records']);
        GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    }

    $member = Member::factory()->create(['paid_through_year' => 2027, ...$attributes]);
    $records->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);

    return $member;
}

it('requires both confirm boxes and a Category', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission([
        'membership_number_confirmed' => false,
        'emergency_verified' => false,
        'category' => '',
    ]))->assertSessionHasErrors(['membership_number_confirmed', 'emergency_verified', 'category']);

    expect($member->fresh()->renewal_year)->toBeNull();
});

it('refuses a Category outside the active fee rows', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => 'Hon']))
        ->assertSessionHasErrors('category');
});

it('requires a placement unless LOA, Resign or Sustaining', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');
    $none = ['placements' => []];

    $this->actingAs($member)->post('/renew/form', renewalSubmission([...$none, 'category' => 'Active-SR']))
        ->assertSessionHasErrors('placements');

    foreach (['LOA', 'Resign', 'Sust', 'Sust-SR'] as $code) {
        $this->actingAs($member)->post('/renew/form', renewalSubmission([...$none, 'category' => $code]))
            ->assertSessionHasNoErrors();
    }
});

it('counts an other placement or a free text as a placement', function () {
    openRenewalForm();
    $member = renewalFormMember();
    $group = Group::factory()->create(['name' => 'Outreach']);

    $this->actingAs($member)->post('/renew/form', renewalSubmission([
        'placements' => [],
        'other' => [['group_id' => $group->id, 'position' => 'Chair'], ['group_id' => null, 'position' => 'Member']],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($member)->post('/renew/form', renewalSubmission([
        'placements' => [],
        'other_texts' => ['Gallery talks', ''],
    ]))->assertSessionHasNoErrors();
});

it('saves a past expiry', function () {
    $this->travelTo('2026-11-04 12:00');
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['membership_expiry' => '2026-01-31']))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/renew/skills');

    expect($member->fresh()->membership_expiry->toDateString())->toBe('2026-01-31');
});

it('saves the renewal columns, the placements and the fee, and moves to skills', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');
    placeIn($member, 'Visitor Wayfinders', [Role::Chair]);
    $outreach = Group::factory()->create(['name' => 'Outreach']);

    $this->actingAs($member)->post('/renew/form', renewalSubmission([
        'address_city' => 'Ottawa',
        'placements' => [1],
        'other' => [['group_id' => $outreach->id, 'position' => 'Co-Chair'], ['group_id' => null, 'position' => 'Member']],
        'other_texts' => ['Gallery <talks>', ''],
        'emergency_contact' => 'Robin Okafor',
        'category' => 'Active-SR',
    ]))->assertRedirect('/renew/skills');

    $member->refresh();
    expect($member->renewal_status)->toBe('Active-SR')
        ->and($member->renewal_year)->toBe(2027)
        ->and($member->address_city)->toBe('Ottawa')
        ->and($member->emergency_contact)->toBe('2027 Robin Okafor')
        ->and((string) $member->amount_paid)->toBe('25.00')
        ->and($member->fee_category)->toBe('Active Senior 65+')
        ->and($member->renewal_placements)->toBe(
            "<tr><td><input type='checkbox' name='committeeName[]'  /></td><td>Docents</td><td><input type='text' size='32' value='Member' /></td></tr>"
            ."<tr><td><input type='checkbox' name='committeeName[]'  checked  /></td><td>Visitor Wayfinders</td><td><input type='text' size='32' value='Chair' /></td></tr>"
        )
        ->and($member->record_form_placements)->toBe(
            '<tr><td>Category</td><td>Active-SR</td></tr>'
            ."<tr><td><input type='checkbox' /> Docents</td><td>Member</td></tr>"
            ."<tr><td><input type='checkbox'  checked /> Visitor Wayfinders</td><td>Chair</td></tr>"
        )
        ->and($member->record_form_other)->toBe('<tr><td>Outreach</td><td>Co-Chair</td></tr><tr><td colspan=2>Gallery &lt;talks&gt;</td></tr>')
        ->and($member->renewal_other)->toContain("<option value='{$outreach->id}' selected >Outreach</option>")
        ->and($member->renewal_other)->toContain("value='Gallery &lt;talks&gt;'");
});

it('saves Life as the lifetime flag', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['membership_lifetime' => true, 'membership_expiry' => '']))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->membership_lifetime)->toBeTrue()
        ->and($member->fresh()->membership_expiry)->toBeNull();
});

it('sets no amount or fee category for a Provisional Member going Active', function () {
    RenewalSetting::current()->forceFill(['show_renewal' => false, 'renewal_year' => 2026])->save();
    $member = renewalFormMember(['paid_through_year' => null, 'code_of_conduct_year' => 2026]);
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission())->assertSessionHasNoErrors();

    expect($member->fresh()->renewal_status)->toBe('Active')
        ->and($member->fresh()->amount_paid)->toBeNull()
        ->and($member->fresh()->fee_category)->toBeNull();
});

it('ends the flow for Resign, LOA and Active to Sustaining', function (string $code) {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => $code]))
        ->assertRedirect('/renew/contact');

    $this->actingAs($member)->get('/renew/contact')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('renew/RecordsInTouch'));
})->with(['Resign', 'LOA', 'Sust', 'Sust-SR']);

it('moves a Sustaining Member staying Sustaining on to step 3', function () {
    openRenewalForm();
    $member = renewalFormMember(['category' => Category::Sustaining]);

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => 'Sust', 'placements' => []]))
        ->assertRedirect('/renew/skills');
});

it('acts only on the signed-in Member', function () {
    openRenewalForm();
    $member = renewalFormMember();
    $other = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', [...renewalSubmission(), 'member_id' => $other->id]);

    expect($other->fresh()->renewal_year)->toBeNull()
        ->and($member->fresh()->renewal_year)->toBe(2027);
});

it('refuses a Member who need not renew', function () {
    openRenewalForm();
    $paid = renewalFormMember(['paid_through_year' => 2027]);

    $this->actingAs($paid)->post('/renew/form', renewalSubmission())->assertForbidden();
    $this->actingAs($paid)->get('/renew/contact')->assertForbidden();
});

it('reopens in edit mode with the saved placements', function () {
    openRenewalForm();
    $member = renewalFormMember();
    placeIn($member, 'Docents');
    placeIn($member, 'Visitor Wayfinders', [Role::Chair]);
    $outreach = Group::factory()->create(['name' => 'Outreach']);

    $this->actingAs($member)->post('/renew/form', renewalSubmission([
        'placements' => [1],
        'other' => [['group_id' => null, 'position' => 'Member'], ['group_id' => $outreach->id, 'position' => 'Auxiliary']],
        'other_texts' => ['', 'Gallery talks'],
        'category' => 'Active-ST',
    ]));

    // A Membership that ends after the save does not change the saved checklist.
    $member->memberships()->delete();

    $this->actingAs($member)->get('/renew/form')
        ->assertInertia(fn (Assert $page) => $page->component('renew/Form')
            ->where('editing', true)
            ->where('fields.category', 'Active-ST')
            ->where('fields.other', [['group_id' => null, 'position' => 'Member'], ['group_id' => $outreach->id, 'position' => 'Auxiliary']])
            ->where('fields.other_texts', ['', 'Gallery talks'])
            ->where('placements', [
                ['name' => 'Docents', 'position' => 'Member', 'checked' => false],
                ['name' => 'Visitor Wayfinders', 'position' => 'Chair', 'checked' => true],
            ]));
});

it('reads a Legacy app checklist in edit mode', function () {
    openRenewalForm();
    $member = renewalFormMember([
        'renewal_year' => 2027,
        'renewal_status' => 'Active',
        'renewal_placements' => "<tr><td><input type='checkbox' name='committeeName[]'  checked  /></td><td>DMV Board</td><td><input type='text' size='32' value='Treasurer' /></td></tr>",
        'renewal_other' => "<tr><td><select id='pl0'><option value='0'>Select Committee</option><option value='outreach' selected >Outreach</option></select></td><td><select id='ch0'><option>Member</option><option selected >Chair</option></select></td></tr>"
            ."<tr><td  colspan='2'><input type='text' size='40' id='OtherCommittee_1' placeholder='Other Placement / Committee'  value='Bus trips'</td></tr>",
    ]);
    $outreach = Group::factory()->create(['name' => 'Outreach']);

    $this->actingAs($member)->get('/renew/form')
        ->assertInertia(fn (Assert $page) => $page
            ->where('placements', [['name' => 'DMV Board', 'position' => 'Treasurer', 'checked' => true]])
            ->where('fields.other.0', ['group_id' => $outreach->id, 'position' => 'Chair'])
            ->where('fields.other_texts.0', 'Bus trips'));
});

it('writes one Notice per Records Member with the changed fields, skipping no-email', function () {
    openRenewalForm();
    $records = renewalRecordsMember();
    renewalRecordsMember(['no_email' => true]);
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['address_city' => 'Ottawa', 'category' => 'Sust']));

    $notices = Delivery::query()->where('kind', DeliveryKind::Notice)->get();
    expect($notices)->toHaveCount(1)
        ->and($notices[0]->member_id)->toBe($records->id)
        ->and($notices[0]->payload['notice'])->toBe('renewal_submitted')
        ->and($notices[0]->payload['changes'])->toBe([['field' => 'address_city', 'from' => 'Toronto', 'to' => 'Ottawa']])
        ->and($notices[0]->payload['category_change'])->toBe(['from' => 'Active', 'to' => 'Sust']);
});

it('tells Records of a first submission with no changes, and of an edit only when something changed', function () {
    openRenewalForm();
    renewalRecordsMember();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => 'Active-SR']));
    expect(Delivery::count())->toBe(1)
        ->and(Delivery::first()->payload['category_change'])->toBeNull();

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => 'Active-SR']));
    expect(Delivery::count())->toBe(1);

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['category' => 'Active-SR', 'phone' => '647-555-0100']));
    expect(Delivery::count())->toBe(2)
        ->and(Delivery::latest('id')->first()->payload['update'])->toBeTrue();
});

it('copies a Notice with changes to each external address, and only then', function () {
    openRenewalForm(settings: ['external_addresses' => ['membership@rom.on.ca', 'records@rom.on.ca']]);
    renewalRecordsMember();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission());
    expect(Delivery::query()->whereNull('member_id')->count())->toBe(0);

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['last_name' => 'Okafor-Reyes']));
    $copies = Delivery::query()->whereNull('member_id')->get();
    expect($copies->pluck('email')->all())->toBe(['membership@rom.on.ca', 'records@rom.on.ca'])
        ->and($copies[0]->payload['locale'])->toBe('en')
        ->and($copies[0]->payload['changes'][0]['field'])->toBe('last_name');
});

it('writes no external copy when no address is set', function () {
    openRenewalForm();
    renewalRecordsMember();
    $member = renewalFormMember();
    placeIn($member, 'Docents');

    $this->actingAs($member)->post('/renew/form', renewalSubmission(['last_name' => 'Okafor-Reyes']));

    expect(Delivery::query()->whereNull('member_id')->count())->toBe(0)
        ->and(Delivery::count())->toBe(1);
});

it('renders the Notice in the recipient locale with the changes', function () {
    openRenewalForm();
    renewalRecordsMember(['locale' => 'fr']);
    $member = renewalFormMember();
    placeIn($member, 'Docents');
    $this->actingAs($member)->post('/renew/form', renewalSubmission(['address_city' => 'Ottawa', 'category' => 'Sust', 'membership_expiry' => '2020-01-01']));

    $mailable = RenewalSubmitted::fromSnapshot(Delivery::first()->payload);
    $mailable->locale('fr');

    $mailable->assertHasSubject('Dana Okafor : renouvellement DMV 2027');
    $mailable->assertSeeInHtml('Ville');
    $mailable->assertSeeInHtml('Ottawa');
    $mailable->assertSeeInHtml('Son adhésion au ROM est expirée.');
    $mailable->assertSeeInHtml('Changement de catégorie');
    $mailable->assertSeeInHtml('Sustaining');
});
