<?php

use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\RenewalSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Renewal settings (#937, ADR-0038 §9, §11): the one row member administration edits once a year
 * to run the Renewal, on a page under Officer Tools → Members.
 */

function renewalMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

it('shows member administration every field of the row', function () {
    $admin = renewalMemberAdmin();
    $row = RenewalSetting::current();
    $row->forceFill([
        'show_renewal' => true,
        'renewal_year' => 2027,
        'active_send_date' => '2026-09-01',
        'sustaining_send_date' => '2026-09-15',
        'active_message_1' => 'Active one',
        'active_message_2' => 'Active two',
        'sustaining_message_1' => 'Sustaining one',
        'sustaining_message_2' => 'Sustaining two',
        'external_addresses' => ['records@rom.on.ca'],
        'updated_by' => $admin->id,
    ])->save();

    $this->actingAs($admin)->get('/officer/members/renewal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/members/RenewalSettings')
            ->where('settings.show_renewal', true)
            ->where('settings.renewal_year', 2027)
            ->where('settings.active_send_date', '2026-09-01')
            ->where('settings.sustaining_send_date', '2026-09-15')
            ->where('settings.active_message_1', 'Active one')
            ->where('settings.active_message_2', 'Active two')
            ->where('settings.sustaining_message_1', 'Sustaining one')
            ->where('settings.sustaining_message_2', 'Sustaining two')
            ->where('settings.external_addresses', ['records@rom.on.ca'])
            ->where('settings.attachment', null)
            ->where('lastUpdated.name', 'Rita Records')
            ->has('lastUpdated.date'));
});

it('opens the French twin', function () {
    $admin = renewalMemberAdmin();

    $this->withLocaleRoutes('fr', function () use ($admin) {
        $this->actingAs($admin)->get('/fr/officier/membres/renouvellement')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/members/RenewalSettings')
                ->where('lastUpdated', null));
    });
});

it('refuses anyone outside member administration', function () {
    $this->actingAs(Member::factory()->create())->get('/officer/members/renewal')->assertForbidden();
});

it('counts the Members renewed and paid for the renewal year', function () {
    $admin = renewalMemberAdmin();
    RenewalSetting::current()->forceFill(['renewal_year' => 2027])->save();

    Member::factory()->create(['renewal_year' => 2027, 'paid_through_year' => 2027]);
    Member::factory()->create(['renewal_year' => 2027, 'paid_through_year' => 2026]);
    Member::factory()->create(['renewal_year' => 2027]);
    Member::factory()->create(['renewal_year' => 2026, 'paid_through_year' => 2026]);

    $this->actingAs($admin)->get('/officer/members/renewal')
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.renewed', 3)
            ->where('counts.paid', 1));
});

function renewalPayload(array $overrides = []): array
{
    return [
        'show_renewal' => true,
        'renewal_year' => 2027,
        'active_send_date' => '2026-09-01',
        'sustaining_send_date' => '2026-09-15',
        'active_message_1' => 'Please renew.',
        'active_message_2' => 'Last call.',
        'sustaining_message_1' => 'Sustaining, please renew.',
        'sustaining_message_2' => 'Sustaining, last call.',
        'external_addresses' => ['records@rom.on.ca', 'membership@rom.on.ca'],
        ...$overrides,
    ];
}

it('saves every field and who saved it', function () {
    $admin = renewalMemberAdmin();

    $this->actingAs($admin)->from('/officer/members/renewal')
        ->put('/renewal-settings', renewalPayload())
        ->assertRedirect('/officer/members/renewal')
        ->assertSessionHasNoErrors();

    $row = RenewalSetting::current();
    expect(RenewalSetting::count())->toBe(1)
        ->and($row->show_renewal)->toBeTrue()
        ->and($row->renewal_year)->toBe(2027)
        ->and($row->active_send_date->toDateString())->toBe('2026-09-01')
        ->and($row->sustaining_send_date->toDateString())->toBe('2026-09-15')
        ->and($row->active_message_1)->toBe('Please renew.')
        ->and($row->active_message_2)->toBe('Last call.')
        ->and($row->sustaining_message_1)->toBe('Sustaining, please renew.')
        ->and($row->sustaining_message_2)->toBe('Sustaining, last call.')
        ->and($row->external_addresses)->toBe(['records@rom.on.ca', 'membership@rom.on.ca'])
        ->and($row->updated_by)->toBe($admin->id);

    $this->actingAs($admin)->put('/renewal-settings', renewalPayload(['show_renewal' => false, 'external_addresses' => []]));

    expect(RenewalSetting::count())->toBe(1)
        ->and(RenewalSetting::current()->show_renewal)->toBeFalse()
        ->and(RenewalSetting::current()->external_addresses)->toBe([]);
});

it('refuses a save from anyone outside member administration', function () {
    $this->actingAs(Member::factory()->create())->put('/renewal-settings', renewalPayload())->assertForbidden();

    expect(RenewalSetting::count())->toBe(0);
});

it('rejects a bad date, an empty message and a bad address', function (string $field, mixed $value) {
    $this->actingAs(renewalMemberAdmin())
        ->put('/renewal-settings', renewalPayload([$field => $value]))
        ->assertSessionHasErrors(str_starts_with($field, 'external') ? 'external_addresses.0' : $field);

    expect(RenewalSetting::count())->toBe(0);
})->with([
    'active date' => ['active_send_date', 'not-a-date'],
    'sustaining date' => ['sustaining_send_date', '2026-02-30'],
    'year' => ['renewal_year', 'soon'],
    'active message 1' => ['active_message_1', ''],
    'active message 2' => ['active_message_2', ''],
    'sustaining message 1' => ['sustaining_message_1', ''],
    'sustaining message 2' => ['sustaining_message_2', ''],
    'address' => ['external_addresses', ['not an address']],
]);

it('stores the Sustaining attachment privately and serves it only to member administration', function () {
    Storage::fake('local');
    $admin = renewalMemberAdmin();

    $this->actingAs($admin)->put('/renewal-settings', renewalPayload([
        'sustaining_attachment' => UploadedFile::fake()->createWithContent('Sustaining form 2027.pdf', "%PDF-1.4\n%%EOF"),
    ]))->assertSessionHasNoErrors();

    $row = RenewalSetting::current();
    expect($row->sustaining_attachment_filename)->toBe('Sustaining form 2027.pdf')
        ->and($row->sustaining_attachment_path)->toStartWith('documents/')
        ->and($row->sustaining_attachment_path)->not->toContain('Sustaining')
        ->and($row->sustaining_attachment_mime_type)->toBe('application/pdf');
    Storage::disk('local')->assertExists($row->sustaining_attachment_path);

    $this->actingAs($admin)->get('/officer/members/renewal')
        ->assertInertia(fn (Assert $page) => $page->where('settings.attachment', 'Sustaining form 2027.pdf'));

    $this->actingAs($admin)->get('/officer/members/renewal/attachment')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('Sustaining form 2027.pdf');

    $this->actingAs(Member::factory()->create())->get('/officer/members/renewal/attachment')->assertForbidden();
});

it('keeps the attachment on a save without one, and replaces it with a new one', function () {
    Storage::fake('local');
    $admin = renewalMemberAdmin();

    $this->actingAs($admin)->put('/renewal-settings', renewalPayload([
        'sustaining_attachment' => UploadedFile::fake()->createWithContent('First.pdf', "%PDF-1.4\n%%EOF"),
    ]));
    $first = RenewalSetting::current()->sustaining_attachment_path;

    $this->actingAs($admin)->put('/renewal-settings', renewalPayload());
    expect(RenewalSetting::current()->sustaining_attachment_path)->toBe($first);

    $this->actingAs($admin)->put('/renewal-settings', renewalPayload([
        'sustaining_attachment' => UploadedFile::fake()->createWithContent('Second.pdf', "%PDF-1.4\n%%EOF"),
    ]));

    $row = RenewalSetting::current();
    expect($row->sustaining_attachment_filename)->toBe('Second.pdf')
        ->and($row->sustaining_attachment_path)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
});

it('refuses an attachment of a type a Document may not be', function () {
    Storage::fake('local');

    $this->actingAs(renewalMemberAdmin())->put('/renewal-settings', renewalPayload([
        'sustaining_attachment' => UploadedFile::fake()->createWithContent('form.html', '<html><script>x</script></html>'),
    ]))->assertSessionHasErrors('sustaining_attachment');
});

it('answers 404 for the attachment before one is uploaded', function () {
    $this->actingAs(renewalMemberAdmin())->get('/officer/members/renewal/attachment')->assertNotFound();
});

it('counts nothing before a renewal year is set', function () {
    $admin = renewalMemberAdmin();
    Member::factory()->create(['renewal_year' => 2027, 'paid_through_year' => 2027]);

    $this->actingAs($admin)->get('/officer/members/renewal')
        ->assertInertia(fn (Assert $page) => $page->where('counts', null));
});
