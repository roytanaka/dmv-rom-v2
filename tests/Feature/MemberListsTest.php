<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Member lists (#919, ADR-0038 §5): Officer Tools → Members. Seven lists, each filtering on
 * its Category group, with the row actions the Legacy app gave it. Member administration only.
 */

function listsMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['category' => Category::Active, 'last_name' => 'Zzrecords']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

/** One Member per list, keyed by the list's URL word. The acting member admin is Current too. */
function oneMemberPerList(): array
{
    return [
        'current' => Member::factory()->create(['category' => Category::Sustaining])->id,
        'resigned' => Member::factory()->create(['category' => Category::Withdrawn])->id,
        'past' => Member::factory()->create(['category' => Category::Resigned, 'personal_data_removed_at' => now()])->id,
        'deceased' => Member::factory()->create(['category' => Category::Deceased])->id,
        'admin' => Member::factory()->create(['category' => Category::Admin])->id,
        'donors' => Member::factory()->create(['category' => Category::Donor])->id,
    ];
}

it('opens on the Current list', function () {
    $admin = listsMemberAdmin();
    $members = oneMemberPerList();

    $this->actingAs($admin)
        ->get('/officer/members')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/members/Index')
            ->where('list', 'current')
            ->has('tabs', 7)
            ->where('members.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$members['current'], $admin->id]));
});

it('returns exactly the Members of each list', function (string $word) {
    $admin = listsMemberAdmin();
    $members = oneMemberPerList();
    $expected = $word === 'current' ? [$members['current'], $admin->id] : [$members[$word]];

    $this->actingAs($admin)
        ->get("/officer/members/{$word}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('list', $word)
            ->where('members.data', fn ($rows) => collect($rows)->pluck('id')->all() === $expected));
})->with(['current', 'resigned', 'past', 'deceased', 'admin', 'donors']);

it('moves a Deceased Member to Past once their data is removed', function () {
    $admin = listsMemberAdmin();
    $gone = Member::factory()->create(['category' => Category::Deceased, 'personal_data_removed_at' => now()]);

    $this->actingAs($admin)->get('/officer/members/deceased')
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    $this->actingAs($admin)->get('/officer/members/past')
        ->assertInertia(fn (Assert $page) => $page->where('members.data.0.id', $gone->id));
});

it('leaves the Past list empty until data is removed', function () {
    Member::factory()->create(['category' => Category::Resigned]);

    $this->actingAs(listsMemberAdmin())
        ->get('/officer/members/past')
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
});

it('keeps Guests, Volunteers and Security off every list', function (string $word) {
    $admin = listsMemberAdmin();
    Member::factory()->create(['category' => Category::Guest]);
    Member::factory()->create(['category' => Category::Volunteer]);
    Member::factory()->create(['category' => Category::Security]);

    $this->actingAs($admin)->get("/officer/members/{$word}")
        ->assertInertia(fn (Assert $page) => $page->where(
            'members.data',
            fn ($rows) => collect($rows)->pluck('id')->diff([$admin->id])->isEmpty(),
        ));
})->with(['current', 'resigned', 'past', 'deceased', 'admin', 'donors']);

it('sorts by last name and searches by the start of it', function () {
    $admin = listsMemberAdmin();
    Member::factory()->create(['category' => Category::Active, 'last_name' => 'Tremblay']);
    Member::factory()->create(['category' => Category::Active, 'last_name' => 'Abara']);
    Member::factory()->create(['category' => Category::Active, 'last_name' => 'Trudeau']);
    Member::factory()->create(['category' => Category::Active, 'last_name' => 'Petrov']);

    $this->actingAs($admin)->get('/officer/members')
        ->assertInertia(fn (Assert $page) => $page->where(
            'members.data',
            fn ($rows) => collect($rows)->pluck('last_name')->all() === ['Abara', 'Petrov', 'Tremblay', 'Trudeau', 'Zzrecords'],
        ));

    $this->actingAs($admin)->get('/officer/members?q=tr')
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'tr')
            ->where('members.data', fn ($rows) => collect($rows)->pluck('last_name')->all() === ['Tremblay', 'Trudeau']));
});

it('treats a search wildcard as a plain character', function () {
    $admin = listsMemberAdmin();
    Member::factory()->create(['category' => Category::Active, 'last_name' => 'Abara']);

    $this->actingAs($admin)->get('/officer/members?q=%25')
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
});

it('shows name, Category, email, phone and last update on a row, and no more', function () {
    Carbon::setTestNow('2026-03-02 12:00:00');
    $admin = listsMemberAdmin();
    $member = Member::factory()->create([
        'category' => Category::Active,
        'first_name' => 'Ada',
        'last_name' => 'Abara',
        'email' => 'ada@example.test',
        'phone' => '416-555-0100',
        'notes' => 'Private note',
        'membership_number' => '123456',
    ]);

    $this->actingAs($admin)->get('/officer/members')
        ->assertInertia(fn (Assert $page) => $page->where('members.data.0', [
            'id' => $member->id,
            'first_name' => 'Ada',
            'last_name' => 'Abara',
            'standing' => 'active',
            'email' => 'ada@example.test',
            'phone' => '416-555-0100',
            'last_update' => '2026-03-02',
            'photo' => null,
            'photo_public' => true,
        ]));
});

it('gives each list its row actions and Send email', function (string $word, array $actions, bool $sendEmail) {
    $this->actingAs(listsMemberAdmin())->get("/officer/members/{$word}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('actions', $actions)
            ->where('canSendEmail', $sendEmail));
})->with([
    ['current', ['edit', 'photo'], true],
    ['resigned', ['edit', 'reinstate'], true],
    ['past', ['edit', 'delete'], false],
    ['deceased', ['edit', 'delete'], false],
    ['admin', ['edit', 'delete'], false],
    ['donors', ['edit'], false],
]);

it('points the seven tabs at their lists, the Applicants tab at the placeholder', function () {
    $this->actingAs(listsMemberAdmin())->get('/officer/members')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs.0', ['key' => 'current', 'href' => '/officer/members'])
            ->where('tabs.1', ['key' => 'resigned', 'href' => '/officer/members/resigned'])
            ->where('tabs.6', ['key' => 'applicants', 'href' => '/officer/members/applicants']));

    $this->get('/officer/members/applicants')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ComingSoon'));
});

it('serves the lists at translated French addresses', function () {
    $admin = listsMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Resigned]);

    $this->withLocaleRoutes('fr', function () use ($admin, $member) {
        $this->actingAs($admin)->get('/fr/officier/membres/demissionnaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('list', 'resigned')
                ->where('members.data.0.id', $member->id)
                ->where('tabs.0.href', '/fr/officier/membres')
                ->where('tabs.1.href', '/fr/officier/membres/demissionnaires')
                ->where('tabs.2.href', '/fr/officier/membres/anciens'));
    });
});

it('is a 404 for an unknown list', function () {
    $this->actingAs(listsMemberAdmin())->get('/officer/members/nobody')->assertNotFound();
});

it('refuses a Member without member administration, at every list', function (string $path) {
    $this->actingAs(Member::factory()->create())->get($path)->assertForbidden();
})->with(['/officer/members', '/officer/members/resigned', '/officer/members/applicants']);

it('lets the super-tier in, and hides the rail item from a plain Member', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/members')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('rail.officer.items.0.key', 'members'));

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->missing('rail.officer'));
});
