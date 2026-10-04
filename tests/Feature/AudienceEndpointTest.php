<?php

use App\Models\Group;
use App\Models\Member;
use Database\Seeders\OrgTreeSeeder;

/*
 * The two Audience endpoints (#484, ADR-0024 §5) the composer reads: the index of
 * pickable Audiences with counts, and the show of one Audience's rows. Asserts the
 * HTTP contract — auth, the picker rule as status codes, index/show count parity,
 * and that no row carries an address.
 */

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);
    $this->docents = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
});

function actor(string $email): Member
{
    return Member::where('email', $email)->firstOrFail();
}

it('requires authentication', function () {
    $this->getJson(route('audiences.index', ['context' => 'directory']))
        ->assertUnauthorized();
});

it('lists the Group Audiences a member may pick, with counts', function () {
    $this->actingAs(actor(OrgTreeSeeder::SCHEDULER_EMAIL))
        ->getJson(route('audiences.index', ['context' => 'group', 'subject' => $this->docents->slug]))
        ->assertOk()
        ->assertJsonFragment(['key' => 'whole_group', 'parameter' => null, 'count' => 2])
        ->assertJsonFragment(['key' => 'group_officers', 'count' => 1]);
});

it('never offers an org-wide Audience in a Group context', function () {
    $response = $this->actingAs(actor('president@dmv.test'))
        ->getJson(route('audiences.index', ['context' => 'group', 'subject' => $this->docents->slug]))
        ->assertOk();

    $keys = collect($response->json('audiences'))->pluck('key');

    expect($keys)->not->toContain('all_members', 'all_members_on_leave', 'active_provisional', 'one_category', 'board_of_directors');
});

it('shows a plain Member only the three leadership Audiences on the Directory', function () {
    $response = $this->actingAs(actor('trainee@dmv.test'))
        ->getJson(route('audiences.index', ['context' => 'directory']))
        ->assertOk();

    $keys = collect($response->json('audiences'))->pluck('key')->sort()->values()->all();

    expect($keys)->toBe(['all_chairs', 'board_of_directors', 'committee_chairs']);
});

it('lets an org-wide sender pick the org-wide Audiences on the Directory', function () {
    $response = $this->actingAs(actor('clerk@dmv.test'))
        ->getJson(route('audiences.index', ['context' => 'directory']))
        ->assertOk();

    $keys = collect($response->json('audiences'))->pluck('key');

    expect($keys)->toContain('all_members', 'active_provisional', 'board_of_directors');
});

it('matches the index count to the show rows and carries no address', function () {
    $actor = actor('clerk@dmv.test');

    $indexCount = collect(
        $this->actingAs($actor)
            ->getJson(route('audiences.index', ['context' => 'directory']))
            ->json('audiences')
    )->firstWhere('key', 'board_of_directors')['count'];

    $show = $this->actingAs($actor)
        ->getJson(route('audiences.show', ['audience' => 'board_of_directors', 'context' => 'directory']))
        ->assertOk();

    expect($show->json('count'))->toBe($indexCount)
        ->and($show->json('count'))->toBe(count($show->json('recipients')));

    $row = $show->json('recipients.0');
    expect($row)->toHaveKeys(['id', 'first_name', 'last_name', 'photo', 'standing'])
        ->and($row)->not->toHaveKey('address')
        ->and($row)->not->toHaveKey('email');
});

it('403s when the actor may not pick the Audience', function () {
    // The Executive Chair is not a member of Docents, so cannot pick its Whole Group.
    $this->actingAs(actor(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL))
        ->getJson(route('audiences.show', [
            'audience' => 'whole_group',
            'context' => 'group',
            'subject' => $this->docents->slug,
        ]))
        ->assertForbidden();
});

it('403s a plain Member picking an org-wide Audience', function () {
    $this->actingAs(actor('trainee@dmv.test'))
        ->getJson(route('audiences.show', ['audience' => 'all_members', 'context' => 'directory']))
        ->assertForbidden();
});

it('returns the flagged Member as skipped on show', function () {
    $trainee = actor('trainee@dmv.test');
    $trainee->forceFill(['no_email' => true])->save();

    $show = $this->actingAs(actor(OrgTreeSeeder::SCHEDULER_EMAIL))
        ->getJson(route('audiences.show', [
            'audience' => 'whole_group',
            'context' => 'group',
            'subject' => $this->docents->slug,
        ]))
        ->assertOk();

    expect(collect($show->json('recipients'))->pluck('id'))->not->toContain($trainee->id)
        ->and(collect($show->json('skipped'))->pluck('id'))->toContain($trainee->id);
});

it('offers the one-Member Direct message from a profile', function () {
    $target = actor('trainee@dmv.test');

    $this->actingAs(actor('clerk@dmv.test'))
        ->getJson(route('audiences.show', [
            'audience' => 'one_member',
            'context' => 'member',
            'subject' => $target->id,
        ]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('recipients.0.id', $target->id);
});

it('resolves a Sign-ups Audience for a Scheduler', function () {
    // A Scheduler placing themselves on a live Shift, then reading the Shift Audience.
    $schedule = $this->docents->schedules()->firstOrFail();
    $shift = $schedule->shifts()->create([
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(13, 0),
        'capacity' => 3,
    ]);
    $scheduler = actor(OrgTreeSeeder::SCHEDULER_EMAIL);
    $shift->signUps()->create(['member_id' => $scheduler->id]);

    $this->actingAs($scheduler)
        ->getJson(route('audiences.show', [
            'audience' => 'sign_ups_shift',
            'context' => 'shift',
            'subject' => $shift->id,
        ]))
        ->assertOk()
        ->assertJsonPath('recipients.0.id', $scheduler->id);
});

it('404s an unknown context or audience key', function () {
    $actor = actor('president@dmv.test');

    $this->actingAs($actor)
        ->getJson(route('audiences.index', ['context' => 'nonsense']))
        ->assertNotFound();

    $this->actingAs($actor)
        ->getJson(route('audiences.show', ['audience' => 'nonsense', 'context' => 'directory']))
        ->assertNotFound();
});
