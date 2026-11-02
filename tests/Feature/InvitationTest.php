<?php

use App\Enums\Category;
use App\Enums\InvitationState;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\Option;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Invitations (#902, spec #901, ADR-0034): the top-bar entry, the My invitations and
 * Hosting tabs, and a Host's Draft Invitations with their Options. Acts through the
 * HTTP routes as a Member and checks Inertia props and the database.
 */

$fields = [
    'name' => 'Annual Meeting',
    'date' => '2027-05-14',
    'start_time' => '10:30',
    'end_time' => '12:00',
    'description' => 'Our yearly gathering.',
    'options' => [],
];

// --- The entry and the tabs --------------------------------------------------

it('shows Invitations in the top bar between News and Directory for every Member', function () {
    $this->actingAs(Member::factory()->category(Category::Provisional)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.destinations.2.key', 'news')
            ->where('chromeNav.destinations.3', ['key' => 'invitations', 'labelKey' => 'nav.personal.invitations', 'href' => '/invitations'])
            ->where('chromeNav.destinations.4.key', 'directory'));
});

it('opens on an empty My invitations tab', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('invitations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Mine'));
});

it('offers Hosting only to a Member whose Category gives Full access', function (Category $category, bool $hosts) {
    $this->actingAs(Member::factory()->category($category)->create())
        ->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->where('can.host', $hosts));
})->with([
    'active' => [Category::Active, true],
    'honourary' => [Category::Honourary, true],
    'sustaining' => [Category::Sustaining, true],
    'on leave' => [Category::Loa, true],
    'provisional' => [Category::Provisional, false],
    'pre-active' => [Category::PreActive, false],
]);

it('offers Hosting to the super-tier whatever their Category', function () {
    $this->actingAs(Member::factory()->superTier()->category(Category::Provisional)->create())
        ->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->where('can.host', true));
});

it('refuses the Hosting tab to a Member without Full access', function () {
    $this->actingAs(Member::factory()->category(Category::Provisional)->create())
        ->get(route('invitations.hosting'))
        ->assertForbidden();
});

it('sends a signed-out visitor to login', function () {
    $this->get(route('invitations'))->assertRedirect(route('login'));
});

// --- Creating a Draft --------------------------------------------------------

it('lets a Host create a Draft with Options', function () use ($fields) {
    $host = Member::factory()->create();

    $this->actingAs($host)
        ->post(route('invitations.store'), [...$fields, 'options' => [['name' => 'Lunch'], ['name' => 'Tour']]])
        ->assertRedirect(route('invitations.hosting'));

    $invitation = Invitation::sole();

    expect($invitation->host_id)->toBe($host->id)
        ->and($invitation->state)->toBe(InvitationState::Draft)
        ->and($invitation->date->toDateString())->toBe('2027-05-14')
        ->and($invitation->start_time)->toBe('10:30')
        ->and($invitation->end_time)->toBe('12:00')
        ->and($invitation->options()->orderBy('id')->pluck('name')->all())->toBe(['Lunch', 'Tour']);
});

it('creates an Invitation with no Options', function () use ($fields) {
    $this->actingAs(Member::factory()->create())
        ->post(route('invitations.store'), $fields)
        ->assertRedirect(route('invitations.hosting'));

    expect(Invitation::sole()->options()->count())->toBe(0);
});

it('ignores a Host or a state sent in the request', function () use ($fields) {
    $host = Member::factory()->create();

    $this->actingAs($host)->post(route('invitations.store'), [
        ...$fields,
        'host_id' => Member::factory()->create()->id,
        'state' => 'open',
    ]);

    expect(Invitation::sole())
        ->host_id->toBe($host->id)
        ->state->toBe(InvitationState::Draft);
});

it('refuses to create for a Member without Full access', function () use ($fields) {
    $member = Member::factory()->category(Category::Provisional)->create();

    $this->actingAs($member)->get(route('invitations.create'))->assertForbidden();
    $this->actingAs($member)->post(route('invitations.store'), $fields)->assertForbidden();

    expect(Invitation::count())->toBe(0);
});

it('rejects invalid fields', function (array $override, string $error) use ($fields) {
    $this->actingAs(Member::factory()->create())
        ->post(route('invitations.store'), [...$fields, ...$override])
        ->assertSessionHasErrors($error);

    expect(Invitation::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'no date' => [['date' => ''], 'date'],
    'no description' => [['description' => ''], 'description'],
    'off the minute grid' => [['start_time' => '10:07'], 'start_time'],
    'end before start' => [['end_time' => '09:00'], 'end_time'],
    'a nameless Option' => [['options' => [['name' => '']]], 'options.0.name'],
]);

// --- The Hosting list --------------------------------------------------------

it('lists only the Host\'s own Invitations, past ones included', function () {
    $host = Member::factory()->create();
    $past = Invitation::factory()->create(['host_id' => $host->id, 'date' => '2020-01-15']);
    $future = Invitation::factory()->create(['host_id' => $host->id, 'date' => '2099-01-15']);
    Invitation::factory()->create();

    $this->actingAs($host)
        ->get(route('invitations.hosting'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Hosting')
            ->has('invitations', 2)
            ->where('invitations.0.id', $future->id)
            ->where('invitations.1.id', $past->id)
            ->where('invitations.1.date', '2020-01-15')
            ->where('invitations.1.start_time', '10:00')
            ->where('invitations.1.state', 'draft')
            ->where('invitations.1.can', ['update' => true, 'delete' => true, 'guests' => true]));
});

it('carries no email address on the Hosting list', function () {
    $host = Member::factory()->create(['email' => 'host-address@example.test']);
    Invitation::factory()->create(['host_id' => $host->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => expect(json_encode($page->toArray()['props']['invitations']))
            ->not->toContain('host-address@example.test'));
});

it('shows the super-tier every Invitation', function () {
    Invitation::factory()->count(2)->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invitations', 2)
            ->where('invitations.0.can', ['update' => true, 'delete' => true, 'guests' => true]));
});

// --- Editing and deleting ----------------------------------------------------

it('lets a Host open their Invitation for editing', function () {
    $invitation = Invitation::factory()->create();
    $option = $invitation->options()->create(['name' => 'Lunch']);

    $this->actingAs($invitation->host)
        ->get(route('invitations.edit', $invitation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Form')
            ->where('invitation.id', $invitation->id)
            ->where('invitation.start_time', '10:00')
            ->where('invitation.options', [['id' => $option->id, 'name' => 'Lunch']]));
});

it('saves an edit, keeping a renamed Option\'s row and dropping a removed one', function () use ($fields) {
    $invitation = Invitation::factory()->create();
    $kept = $invitation->options()->create(['name' => 'Lunch']);
    $dropped = $invitation->options()->create(['name' => 'Tour']);

    $this->actingAs($invitation->host)
        ->patch(route('invitations.update', $invitation), [
            ...$fields,
            'name' => 'New name',
            'options' => [['id' => $kept->id, 'name' => 'Brunch'], ['id' => null, 'name' => 'Walk']],
        ])
        ->assertRedirect(route('invitations.hosting'));

    expect($invitation->refresh()->name)->toBe('New name')
        ->and($kept->refresh()->name)->toBe('Brunch')
        ->and(Option::find($dropped->id))->toBeNull()
        ->and($invitation->options()->orderBy('id')->pluck('name')->all())->toBe(['Brunch', 'Walk']);
});

it('refuses an Option of another Invitation', function () use ($fields) {
    $invitation = Invitation::factory()->create();
    $foreign = Invitation::factory()->create()->options()->create(['name' => 'Lunch']);

    $this->actingAs($invitation->host)
        ->patch(route('invitations.update', $invitation), [...$fields, 'options' => [['id' => $foreign->id, 'name' => 'Stolen']]])
        ->assertSessionHasErrors('options.0.id');

    expect($foreign->refresh()->name)->toBe('Lunch');
});

it('soft-deletes an Invitation, which leaves the Hosting list', function () {
    $invitation = Invitation::factory()->create();
    $host = $invitation->host;

    $this->actingAs($host)
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect(route('invitations.hosting'));

    $this->assertSoftDeleted($invitation);

    $this->actingAs($host)
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));

    $this->actingAs($host)->get(route('invitations.edit', $invitation))->assertNotFound();
});

it('refuses every other Member on a Draft', function () use ($fields) {
    $invitation = Invitation::factory()->create(['name' => 'Private']);
    $other = Member::factory()->create();

    $this->actingAs($other)->get(route('invitations.edit', $invitation))->assertForbidden();
    $this->actingAs($other)->patch(route('invitations.update', $invitation), $fields)->assertForbidden();
    $this->actingAs($other)->delete(route('invitations.destroy', $invitation))->assertForbidden();

    $this->actingAs($other)
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));

    expect($invitation->refresh())
        ->name->toBe('Private')
        ->deleted_at->toBeNull();
});

it('refuses a Host who has lost Full access', function () use ($fields) {
    $invitation = Invitation::factory()->create();
    $invitation->host->update(['category' => Category::Resigned]);

    $this->actingAs($invitation->host)->patch(route('invitations.update', $invitation), $fields)->assertForbidden();
});

it('lets the super-tier manage any Invitation', function () use ($fields) {
    $invitation = Invitation::factory()->create();
    $super = Member::factory()->superTier()->create();

    $this->actingAs($super)->get(route('invitations.edit', $invitation))->assertOk();
    $this->actingAs($super)
        ->patch(route('invitations.update', $invitation), [...$fields, 'name' => 'Rescued'])
        ->assertRedirect(route('invitations.hosting'));
    $this->actingAs($super)->delete(route('invitations.destroy', $invitation))->assertRedirect(route('invitations.hosting'));

    expect($invitation->refresh()->name)->toBe('Rescued')
        ->and($invitation->host_id)->not->toBe($super->id);
    $this->assertSoftDeleted($invitation);
});

// --- French ------------------------------------------------------------------

it('serves the French routes with translated segments', function () use ($fields) {
    $invitation = Invitation::factory()->create();
    $host = $invitation->host;

    $this->withLocaleRoutes('fr', function () use ($invitation, $host, $fields) {
        $this->actingAs($host);

        $this->get('/fr/invitations')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invitations/Mine')->where('locale', 'fr'));
        $this->get('/fr/invitations/organisation')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invitations/Hosting')->where('invitations.0.id', $invitation->id));
        $this->get('/fr/invitations/creer')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invitations/Form'));
        $this->get("/fr/invitations/{$invitation->id}/modifier")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('invitation.id', $invitation->id));

        // A save from a French page lands on the French list.
        $this->post('/fr/invitations', $fields)->assertRedirect('/fr/invitations/organisation');
    });
});
