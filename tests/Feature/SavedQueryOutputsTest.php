<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Mail\SavedQueryResults;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\SavedQuery;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Saved query outputs (#924, ADR-0038 §7): CSV, Labels and Email results on a run. Each covers the
 * whole query, as the Legacy app's did, and none ever carries the membership number.
 */

function outputsMemberAdmin(): Member
{
    $records = Group::factory()->create(['name' => 'Records']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['category' => Category::Active, 'first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

/** @param  list<string>  $columns */
function outputsQuery(array $columns = ['last_name', 'first_name', 'mentor_name'], string $name = 'Awards 2026'): SavedQuery
{
    return SavedQuery::create([
        'name' => $name,
        'clauses' => [['source' => 'member', 'field' => 'next_award_year', 'operator' => 'is', 'value' => '2026']],
        'conjunction' => 'and',
        'sort' => 'last_name',
        'output_columns' => $columns,
    ]);
}

/** @return list<list<string>> */
function outputsCsvRows(string $body): array
{
    expect(substr($body, 0, 3))->toBe("\xEF\xBB\xBF");

    return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", substr($body, 3)), fn ($line) => $line !== '')));
}

it('exports the chosen columns as CSV named after the query and the date', function () {
    $this->travelTo('2026-11-03 10:00');
    $admin = outputsMemberAdmin();
    Member::factory()->create(['first_name' => 'Bea', 'last_name' => 'Brown', 'mentor_name' => 'Sam, Chen', 'next_award_year' => 2026, 'membership_number' => 'M-111']);
    Member::factory()->create(['first_name' => 'Ann', 'last_name' => 'Adams', 'mentor_name' => null, 'next_award_year' => 2026]);
    Member::factory()->create(['last_name' => 'Clark', 'next_award_year' => 2027]);

    $response = $this->actingAs($admin)->get(route('officer.members.queries.csv', outputsQuery()));

    $response->assertOk()->assertDownload('awards-2026-2026-11-03.csv');
    $body = $response->streamedContent();
    expect(outputsCsvRows($body))->toBe([
        ['Last name', 'First name', 'Mentor'],
        ['Adams', 'Ann', ''],
        ['Brown', 'Bea', 'Sam, Chen'],
    ])->and($body)->not->toContain('M-111');
});

it('labels the CSV header in French on the French route', function () {
    $admin = outputsMemberAdmin();
    Member::factory()->create(['last_name' => 'Brown', 'next_award_year' => 2026]);

    $query = outputsQuery(['last_name']);
    $body = null;

    $this->withLocaleRoutes('fr', function () use ($admin, $query, &$body) {
        $body = $this->actingAs($admin)->get('/fr/officier/membres/requetes/'.$query->id.'/export.csv')
            ->assertOk()->streamedContent();
    });

    expect(outputsCsvRows($body)[0])->toBe([__('saved_queries.field.last_name', [], 'fr')]);
});

it('renders the labels thirty to a sheet in order, with the country only outside Canada', function () {
    $admin = outputsMemberAdmin();
    foreach (range(1, 31) as $n) {
        Member::factory()->create([
            'first_name' => 'Member',
            'last_name' => sprintf('L%02d', $n),
            'next_award_year' => 2026,
            'address_street' => "{$n} Queen St",
            'address_city' => 'Toronto',
            'address_province' => 'ON',
            'address_postal_code' => 'M5S 2C6',
            'address_country' => $n === 2 ? 'USA' : ($n === 3 ? 'canada' : null),
            'membership_number' => "M-{$n}",
        ]);
    }

    $this->actingAs($admin)
        ->get(route('officer.members.queries.labels', outputsQuery()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/members/queries/Labels')
            ->where('query.name', 'Awards 2026')
            ->where('printer', 'laser')
            ->has('sheets', 2)
            ->has('sheets.0', 30)
            ->has('sheets.1', 1)
            ->where('sheets.0.0', ['Member L01', '1 Queen St', 'Toronto, ON', 'M5S 2C6'])
            ->where('sheets.0.1', ['Member L02', '2 Queen St', 'Toronto, ON', 'USA    M5S 2C6'])
            ->where('sheets.0.2', ['Member L03', '3 Queen St', 'Toronto, ON', 'M5S 2C6'])
            ->where('sheets.1.0', ['Member L31', '31 Queen St', 'Toronto, ON', 'M5S 2C6'])
        );
});

it('keeps the chosen printer on the labels page', function () {
    $admin = outputsMemberAdmin();

    $this->actingAs($admin)
        ->get(route('officer.members.queries.labels', ['savedQuery' => outputsQuery(), 'printer' => 'xerox']))
        ->assertInertia(fn (Assert $page) => $page->where('printer', 'xerox')->where('printers', ['xerox', 'inkjet', 'laser']));

    $this->actingAs($admin)
        ->get(route('officer.members.queries.labels', ['savedQuery' => outputsQuery(), 'printer' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page->where('printer', 'laser'));
});

it('emails the results as one Notice per chosen Member, skipping no_email', function () {
    $admin = outputsMemberAdmin();
    $query = outputsQuery();
    $colleague = Member::factory()->create(['email' => 'colleague@example.test']);
    $silent = Member::factory()->noEmail()->create();

    $this->actingAs($admin)
        ->post(route('saved-queries.email-results', $query), [
            'member_ids' => [$colleague->id, $silent->id],
            'note' => 'For the awards tea.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $deliveries = Delivery::all();
    expect($deliveries)->toHaveCount(1);
    $delivery = $deliveries->first();
    expect($delivery->kind)->toBe(DeliveryKind::Notice)
        ->and($delivery->member_id)->toBe($colleague->id)
        ->and($delivery->email)->toBe('colleague@example.test')
        ->and($delivery->payload)->toMatchArray([
            'notice' => 'saved_query_results',
            'query' => ['id' => $query->id, 'name' => 'Awards 2026'],
            'note' => 'For the awards tea.',
            'sender' => 'Rita Records',
        ]);
});

it('renders the results mail with the note and a link to the run in the recipient locale', function () {
    $admin = outputsMemberAdmin();
    $query = outputsQuery();
    $colleague = Member::factory()->create(['locale' => 'fr']);

    Mail::fake();
    $this->actingAs($admin)->post(route('saved-queries.email-results', $query), ['member_ids' => [$colleague->id], 'note' => 'Voir la liste.']);
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SavedQueryResults::class, fn (SavedQueryResults $mail) => $mail->hasTo($colleague->email) && $mail->locale === 'fr');

    $mail = SavedQueryResults::fromSnapshot(Delivery::sole()->payload)->locale('fr');
    $mail->assertSeeInHtml('Voir la liste.');
    $mail->assertSeeInHtml('Awards 2026');
    $mail->assertSeeInHtml('/fr/officier/membres/requetes/'.$query->id, false);
});

it('requires at least one Member to email', function () {
    $admin = outputsMemberAdmin();

    $this->actingAs($admin)
        ->post(route('saved-queries.email-results', outputsQuery()), ['member_ids' => []])
        ->assertSessionHasErrors('member_ids');

    expect(Delivery::count())->toBe(0);
});

it('offers Current and Admin Members as email recipients on demand', function () {
    $admin = outputsMemberAdmin();
    $active = Member::factory()->create(['last_name' => 'Active']);
    $resigned = Member::factory()->create(['last_name' => 'Gone', 'category' => Category::Resigned]);
    $query = outputsQuery();

    $this->actingAs($admin)
        ->get(route('officer.members.queries.run', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('recipients')
            ->reloadOnly('recipients', fn (Assert $reload) => $reload->where('recipients', function ($recipients) use ($admin, $active, $resigned) {
                $ids = collect($recipients)->pluck('id');

                return $ids->contains($admin->id) && $ids->contains($active->id) && ! $ids->contains($resigned->id);
            })));
});

it('opens the emailed link only behind member administration', function () {
    $query = outputsQuery();
    $plain = Member::factory()->create();

    $this->get(route('officer.members.queries.run', $query))->assertRedirect();
    $this->actingAs($plain)->get(route('officer.members.queries.run', $query))->assertForbidden();
});

it('forbids a plain Member every output', function () {
    $query = outputsQuery();
    $plain = Member::factory()->create();

    $this->actingAs($plain)->get(route('officer.members.queries.csv', $query))->assertForbidden();
    $this->actingAs($plain)->get(route('officer.members.queries.labels', $query))->assertForbidden();
    $this->actingAs($plain)->post(route('saved-queries.email-results', $query), ['member_ids' => [$plain->id]])->assertForbidden();

    expect(Delivery::count())->toBe(0);
});
