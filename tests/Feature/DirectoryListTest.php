<?php

use App\Enums\Category;
use App\Enums\DirectoryList;
use App\Models\Member;
use App\Support\Audiences\AudienceResolver;
use Database\Seeders\OrgTreeSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Directory's Who's who list (#699). Each list holds the same Members as its email
 * Audience, without the no-email skip; every Member sees the open lists; only Records,
 * Officers, and super-tier see the rest, and anyone else gets no rows for them.
 */

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);

    // One extra Member in every Category, so each standing list has someone to show.
    foreach (Category::cases() as $category) {
        Member::factory()->category($category)->create();
    }
});

function directoryIds(Member $viewer, ?string $list = null): array
{
    $ids = [];

    test()->actingAs($viewer)
        ->get(route('directory', array_filter(['list' => $list])))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids) {
            $page->where('members', function (Collection $members) use (&$ids) {
                $ids = $members->pluck('id')->sort()->values()->all();

                return true;
            });
        });

    return $ids;
}

function seededMember(string $email): Member
{
    return Member::where('email', $email)->firstOrFail();
}

it('lists the same Members as each list\'s email Audience', function (DirectoryList $list) {
    [$key, $parameter] = $list->audience();
    $expected = app(AudienceResolver::class)->directoryMembers($key, $parameter)
        ->pluck('id')->sort()->values()->all();

    expect($expected)->not->toBeEmpty()
        ->and(directoryIds(seededMember('president@dmv.test'), $list->value))->toBe($expected);
})->with(fn () => array_values(array_filter(
    DirectoryList::cases(),
    fn (DirectoryList $list) => $list->audience() !== null,
)));

it('keeps the default list as the Directory roster', function () {
    $expected = Member::inDirectory()->pluck('id')->sort()->values()->all();

    expect(directoryIds(seededMember('president@dmv.test')))->toBe($expected)
        ->and(directoryIds(seededMember('president@dmv.test'), DirectoryList::AllMembers->value))->toBe($expected);
});

it('falls back to the default list for an unknown list', function () {
    $this->actingAs(seededMember('trainee@dmv.test'))
        ->get(route('directory', ['list' => 'everyone']))
        ->assertInertia(fn (Assert $page) => $page->where('list', DirectoryList::AllMembers->value));
});

it('still lists a Member who has set no-email', function () {
    $chair = seededMember(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL);
    $chair->forceFill(['no_email' => true])->save();

    expect(directoryIds(seededMember('trainee@dmv.test'), DirectoryList::BoardOfDirectors->value))->toContain($chair->id);
});

it('offers every list to Records, Officers, and super-tier', function (string $email) {
    $this->actingAs(seededMember($email))
        ->get(route('directory'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'lists',
            array_map(fn (DirectoryList $list) => $list->value, DirectoryList::cases()),
        ));
})->with([
    'Records' => 'clerk@dmv.test',
    'Officer' => 'secretary@dmv.test',
    'super-tier' => 'president@dmv.test',
]);

it('offers only the open lists to other Members', function () {
    $this->actingAs(seededMember(OrgTreeSeeder::SCHEDULER_EMAIL))
        ->get(route('directory'))
        ->assertInertia(fn (Assert $page) => $page->where('lists', [
            DirectoryList::AllMembers->value,
            DirectoryList::BoardOfDirectors->value,
            DirectoryList::CommitteeChairs->value,
            DirectoryList::AllChairs->value,
        ]));
});

it('returns no rows for a closed list the viewer may not see', function () {
    expect(directoryIds(seededMember(OrgTreeSeeder::SCHEDULER_EMAIL), DirectoryList::Provisional->value))->toBe([])
        ->and(directoryIds(seededMember('secretary@dmv.test'), DirectoryList::Provisional->value))->not->toBeEmpty();
});
