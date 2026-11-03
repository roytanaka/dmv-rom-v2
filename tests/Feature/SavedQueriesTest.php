<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\SavedQuery;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Saved queries (#923, ADR-0038 §7): named filters over Member data a member admin keeps and
 * runs. Stored as structure, run by the server over a fixed allowlist of sources and fields.
 */

function queriesMemberAdmin(): Member
{
    $records = Group::factory()->create(['name' => 'Records']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['category' => Category::Admin, 'first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

/**
 * A stored query, built as the form posts it.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string|null}>  $clauses
 * @param  list<string>  $columns
 */
function queriesMake(array $clauses, string $conjunction = 'and', array $columns = ['last_name', 'first_name'], string $sort = 'last_name'): SavedQuery
{
    return SavedQuery::create([
        'name' => 'Test query',
        'clauses' => array_map(fn (array $clause) => array_combine(['source', 'field', 'operator', 'value'], $clause), $clauses),
        'conjunction' => $conjunction,
        'sort' => $sort,
        'output_columns' => $columns,
    ]);
}

/** The ids a run returns, in order. */
function queriesRunIds(Member $admin, SavedQuery $query): array
{
    $ids = null;

    test()->actingAs($admin)
        ->get(route('officer.members.queries.run', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids) {
            $page->component('officer/members/queries/Run');
            $ids = collect($page->toArray()['props']['rows'])->pluck('id')->all();
        });

    return $ids;
}

it('runs a member-field query in the sort order', function () {
    $admin = queriesMemberAdmin();
    $b = Member::factory()->create(['last_name' => 'Brown', 'next_award_year' => 2026]);
    $a = Member::factory()->create(['last_name' => 'Adams', 'next_award_year' => 2026]);
    Member::factory()->create(['last_name' => 'Clark', 'next_award_year' => 2027]);

    $query = queriesMake([['member', 'next_award_year', 'is', '2026']]);

    expect(queriesRunIds($admin, $query))->toBe([$a->id, $b->id]);
});

it('applies each operator', function (string $operator, string $value, array $expected) {
    $admin = queriesMemberAdmin();
    $members = [
        'early' => Member::factory()->create(['entry_year' => 2001, 'mentor_name' => 'Alex Morgan']),
        'mid' => Member::factory()->create(['entry_year' => 2010, 'mentor_name' => 'Sam Chen']),
        'late' => Member::factory()->create(['entry_year' => 2020, 'mentor_name' => null]),
    ];
    $field = in_array($operator, ['contains', 'not_contains'], true) ? 'mentor_name' : 'entry_year';

    $ids = queriesRunIds($admin, queriesMake([['member', $field, $operator, $value]]));

    expect($ids)->toEqualCanonicalizing(array_map(fn (string $key) => $members[$key]->id, $expected));
})->with([
    'is' => ['is', '2010', ['mid']],
    'is not' => ['is_not', '2010', ['early', 'late']],
    'less than' => ['less_than', '2010', ['early']],
    'greater than' => ['greater_than', '2010', ['late']],
    'contains' => ['contains', 'morg', ['early']],
    'does not contain' => ['not_contains', 'morg', ['mid', 'late']],
]);

it('joins clauses with AND or OR', function (string $conjunction, array $expected) {
    $admin = queriesMemberAdmin();
    $members = [
        'both' => Member::factory()->create(['entry_year' => 2010, 'paid_through_year' => 2026]),
        'entry' => Member::factory()->create(['entry_year' => 2010, 'paid_through_year' => 2025]),
        'paid' => Member::factory()->create(['entry_year' => 2000, 'paid_through_year' => 2026]),
        'neither' => Member::factory()->create(['entry_year' => 2000, 'paid_through_year' => 2025]),
    ];

    $ids = queriesRunIds($admin, queriesMake([
        ['member', 'entry_year', 'is', '2010'],
        ['member', 'paid_through_year', 'is', '2026'],
    ], $conjunction));

    expect($ids)->toEqualCanonicalizing(array_map(fn (string $key) => $members[$key]->id, $expected));
})->with([
    'and' => ['and', ['both']],
    'or' => ['or', ['both', 'entry', 'paid']],
]);

it('reads an empty value as a blank field', function () {
    $admin = queriesMemberAdmin();
    $none = Member::factory()->create(['email' => null]);
    Member::factory()->create();

    $ids = queriesRunIds($admin, queriesMake([['member', 'email', 'is', '']]));

    expect($ids)->toBe([$none->id]);
});

it('scopes to the Current group unless a clause names a Category group', function () {
    $admin = queriesMemberAdmin();
    $current = Member::factory()->create(['category' => Category::Active, 'entry_year' => 2010]);
    $resigned = Member::factory()->create(['category' => Category::Resigned, 'entry_year' => 2010]);

    expect(queriesRunIds($admin, queriesMake([['member', 'entry_year', 'is', '2010']])))->toBe([$current->id])
        ->and(queriesRunIds($admin, queriesMake([
            ['member', 'entry_year', 'is', '2010'],
            ['member', 'category_group', 'is', 'resigned'],
        ])))->toBe([$resigned->id]);
});

it('joins the related sources', function () {
    $admin = queriesMemberAdmin();
    $docents = Group::factory()->create(['name' => 'Docents', 'slug' => 'docents']);
    $inDocents = Member::factory()->create();
    $docents->memberships()->create(['member_id' => $inDocents->id, 'status' => MembershipStatus::Full]);
    $treasurer = Member::factory()->create();
    $treasurer->boardPositions()->create(['position' => 'Treasurer', 'years' => '2019-2021']);
    $resignedLastYear = Member::factory()->create(['category' => Category::Active]);
    $resignedLastYear->categoryChanges()->create(['category' => 'resigned', 'date' => '2025-06-15']);
    $neither = Member::factory()->create();

    expect(queriesRunIds($admin, queriesMake([['membership', 'group', 'is', 'docents']])))->toBe([$inDocents->id])
        ->and(queriesRunIds($admin, queriesMake([['board_position', 'position', 'contains', 'treas']])))->toBe([$treasurer->id])
        ->and(queriesRunIds($admin, queriesMake([
            ['category_history', 'category', 'is', 'resigned'],
            ['category_history', 'date', 'greater_than', '2024-12-31'],
        ])))->toBe([$resignedLastYear->id])
        ->and(queriesRunIds($admin, queriesMake([['membership', 'group', 'is_not', 'docents']])))
        ->toEqualCanonicalizing([$treasurer->id, $resignedLastYear->id, $neither->id]);
});

it('shows the count, the clause text and the chosen columns', function () {
    $admin = queriesMemberAdmin();
    $member = Member::factory()->create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'entry_year' => 2010,
        'membership_number' => 'R123456', 'photo_path' => 'photos/ada.jpg',
    ]);
    $member->boardPositions()->create(['position' => 'Treasurer', 'years' => '2019-2021']);
    $member->boardPositions()->create(['position' => 'President', 'years' => null]);
    $query = queriesMake([['member', 'entry_year', 'is', '2010']], columns: ['last_name', 'category', 'board_positions']);

    $this->actingAs($admin)
        ->get(route('officer.members.queries.run', $query))
        ->assertOk()
        ->assertDontSee('R123456')
        ->assertDontSee('ada.jpg')
        ->assertInertia(fn (Assert $page) => $page
            ->where('count', 1)
            ->where('clauseText', 'Category group is Current AND Entry year is “2010”')
            ->where('columns', [
                ['key' => 'last_name', 'label' => 'Last name'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'board_positions', 'label' => 'Board positions'],
            ])
            ->where('rows', [[
                'id' => $member->id,
                'cells' => ['last_name' => 'Lovelace', 'category' => 'Active', 'board_positions' => 'Treasurer (2019-2021); President'],
            ]]));
});

it('writes the clause text in French on the French route', function () {
    $admin = queriesMemberAdmin();
    $query = queriesMake([['member', 'category_group', 'is', 'resigned'], ['member', 'email', 'is', '']], 'or');

    $this->withLocaleRoutes('fr', function () use ($admin, $query) {
        $this->actingAs($admin)
            ->get('/fr/officier/membres/requetes/'.$query->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clauseText', fn (string $text) => str_starts_with($text, 'Groupe de catégories est ')
                    && str_ends_with($text, ' OU Courriel est vide')));
    });
});

it('creates a query and opens its run', function () {
    $admin = queriesMemberAdmin();

    $response = $this->actingAs($admin)->post(route('saved-queries.store'), [
        'name' => 'Awards this year',
        'conjunction' => 'and',
        'clauses' => [['source' => 'member', 'field' => 'next_award_year', 'operator' => 'is', 'value' => ' 2026 ']],
        'sort' => 'last_name',
        'output_columns' => ['first_name', 'last_name', 'memberships'],
    ]);

    $query = SavedQuery::sole();
    $response->assertRedirect(route('officer.members.queries.run', $query));
    expect($query->name)->toBe('Awards this year')
        ->and($query->clauses)->toBe([['source' => 'member', 'field' => 'next_award_year', 'operator' => 'is', 'value' => '2026']])
        ->and($query->output_columns)->toBe(['first_name', 'last_name', 'memberships'])
        ->and($query->created_by)->toBe($admin->id)
        ->and($query->updated_by)->toBe($admin->id);
});

it('rejects a field, operator, value or column outside the allowlist', function (array $override, string $errorKey) {
    $admin = queriesMemberAdmin();

    $this->actingAs($admin)->post(route('saved-queries.store'), [
        'name' => 'Bad',
        'conjunction' => 'and',
        'clauses' => [['source' => 'member', 'field' => 'entry_year', 'operator' => 'is', 'value' => '2010']],
        'sort' => 'last_name',
        'output_columns' => ['last_name'],
        ...$override,
    ])->assertSessionHasErrors($errorKey);

    expect(SavedQuery::count())->toBe(0);
})->with([
    'membership number filter' => [['clauses' => [['source' => 'member', 'field' => 'membership_number', 'operator' => 'is', 'value' => 'R1']]], 'clauses.0'],
    'password filter' => [['clauses' => [['source' => 'member', 'field' => 'password', 'operator' => 'contains', 'value' => 'a']]], 'clauses.0'],
    'unknown source' => [['clauses' => [['source' => 'sign_ins', 'field' => 'ip', 'operator' => 'is', 'value' => '1']]], 'clauses.0'],
    'operator the type refuses' => [['clauses' => [['source' => 'member', 'field' => 'entry_year', 'operator' => 'contains', 'value' => '20']]], 'clauses.0'],
    'value not a number' => [['clauses' => [['source' => 'member', 'field' => 'entry_year', 'operator' => 'is', 'value' => 'soon']]], 'clauses.0'],
    'value not an option' => [['clauses' => [['source' => 'member', 'field' => 'category', 'operator' => 'is', 'value' => 'boss']]], 'clauses.0'],
    'membership number column' => [['output_columns' => ['last_name', 'membership_number']], 'output_columns.1'],
    'password column' => [['output_columns' => ['password']], 'output_columns.0'],
    'sort off the list' => [['sort' => 'password'], 'sort'],
    'eleven clauses' => [['clauses' => array_fill(0, 11, ['source' => 'member', 'field' => 'entry_year', 'operator' => 'is', 'value' => '2010'])], 'clauses'],
]);

it('refuses to run a stored field outside the allowlist', function () {
    $admin = queriesMemberAdmin();
    $query = queriesMake([['member', 'membership_number', 'is', 'R1']]);

    $this->actingAs($admin)
        ->get(route('officer.members.queries.run', $query))
        ->assertSessionHasErrors('clauses.0');
});

it('never offers the membership number or a secret on the form', function () {
    $admin = queriesMemberAdmin();

    $this->actingAs($admin)
        ->get(route('officer.members.queries.create'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('officer/members/queries/Form');
            $props = $page->toArray()['props'];
            $offered = [
                ...collect($props['sources'])->flatMap(fn ($source) => collect($source['fields'])->pluck('key'))->all(),
                ...collect($props['columns'])->pluck('key')->all(),
                ...collect($props['sortFields'])->pluck('key')->all(),
            ];

            foreach (['membership_number', 'password', 'remember_token', 'photo_path', 'super_tier', 'support_operator'] as $secret) {
                expect($offered)->not->toContain($secret);
            }
        });
});

it('edits, renames and deletes a query', function () {
    $admin = queriesMemberAdmin();
    $query = queriesMake([['member', 'entry_year', 'is', '2010']]);

    $this->actingAs($admin)->get(route('officer.members.queries.edit', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/members/queries/Form')->where('query.id', $query->id));

    $this->actingAs($admin)->put(route('saved-queries.update', $query), [
        'name' => 'Entry 2011',
        'conjunction' => 'or',
        'clauses' => [['source' => 'member', 'field' => 'entry_year', 'operator' => 'is', 'value' => '2011']],
        'sort' => 'entry_year',
        'output_columns' => ['entry_year'],
    ])->assertRedirect(route('officer.members.queries.run', $query));

    $query->refresh();
    expect($query->name)->toBe('Entry 2011')
        ->and($query->conjunction)->toBe('or')
        ->and($query->sort)->toBe('entry_year')
        ->and($query->clauses[0]['value'])->toBe('2011');

    $this->actingAs($admin)->patch(route('saved-queries.rename', $query), ['name' => 'Renamed'])->assertRedirect();
    expect($query->refresh()->name)->toBe('Renamed');

    $this->actingAs($admin)->delete(route('saved-queries.destroy', $query))
        ->assertRedirect(route('officer.members.queries'));
    expect(SavedQuery::count())->toBe(0);
});

it('lists the queries by name', function () {
    $admin = queriesMemberAdmin();
    $b = queriesMake([]);
    $b->update(['name' => 'Bravo']);
    $a = queriesMake([]);
    $a->update(['name' => 'Alpha']);

    $this->actingAs($admin)->get('/officer/members/queries')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/members/queries/Index')
            ->where('queries', [['id' => $a->id, 'name' => 'Alpha'], ['id' => $b->id, 'name' => 'Bravo']]));

    $this->withLocaleRoutes('fr', function () use ($admin) {
        $this->actingAs($admin)->get('/fr/officier/membres/requetes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/members/queries/Index'));
    });
});

it('lets the super-tier in', function () {
    $super = Member::factory()->create(['super_tier' => true]);

    $this->actingAs($super)->get(route('officer.members.queries'))->assertOk();
});

it('forbids a plain Member every page and write', function () {
    queriesMemberAdmin();
    $member = Member::factory()->create();
    $query = queriesMake([['member', 'entry_year', 'is', '2010']]);
    $body = ['name' => 'X', 'conjunction' => 'and', 'clauses' => [], 'sort' => 'last_name', 'output_columns' => ['last_name']];

    $this->actingAs($member)->get(route('officer.members.queries'))->assertForbidden();
    $this->actingAs($member)->get(route('officer.members.queries.create'))->assertForbidden();
    $this->actingAs($member)->get(route('officer.members.queries.edit', $query))->assertForbidden();
    $this->actingAs($member)->get(route('officer.members.queries.run', $query))->assertForbidden();
    $this->actingAs($member)->post(route('saved-queries.store'), $body)->assertForbidden();
    $this->actingAs($member)->put(route('saved-queries.update', $query), $body)->assertForbidden();
    $this->actingAs($member)->patch(route('saved-queries.rename', $query), ['name' => 'Y'])->assertForbidden();
    $this->actingAs($member)->delete(route('saved-queries.destroy', $query))->assertForbidden();

    expect($query->refresh()->name)->toBe('Test query');
});
