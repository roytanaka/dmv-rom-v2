<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\MemberList;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Broadcast;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\SavedQuery;
use App\Support\Audiences\AudienceContext;
use App\Support\Audiences\AudienceResolver;

/*
 * Send email from a Member list and a Saved query (#925, ADR-0038 §5, §7; ADR-0024). Two Audience
 * contexts whose one Audience resolves on the server from the list name or the query id, for member
 * administration only. Unchecked rows on a query run travel as the hand-picked edit path's removals.
 */

function recordsAudienceAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['category' => Category::Active]);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

function recordsAudienceQuery(): SavedQuery
{
    return SavedQuery::create([
        'name' => 'Award year 2026',
        'clauses' => [['source' => 'member', 'field' => 'next_award_year', 'operator' => 'is', 'value' => '2026']],
        'conjunction' => 'and',
        'sort' => 'last_name',
        'output_columns' => ['last_name', 'first_name'],
    ]);
}

/** @return list<int> */
function recordsAudienceIds(array $rows): array
{
    return collect($rows)->pluck('id')->sort()->values()->all();
}

it('resolves a Member list Audience to exactly the list\'s Members', function () {
    $admin = recordsAudienceAdmin();
    // The acting member admin is Current too.
    $current = [
        $admin->id,
        Member::factory()->create(['category' => Category::Active])->id,
        Member::factory()->create(['category' => Category::Sustaining])->id,
    ];
    Member::factory()->create(['category' => Category::Withdrawn]);
    Member::factory()->create(['category' => Category::Donor]);

    $this->actingAs($admin)
        ->getJson(route('audiences.index', ['context' => 'member_list', 'subject' => 'current']))
        ->assertOk()
        ->assertJsonPath('audiences.0.key', 'member_list')
        ->assertJsonPath('audiences.0.count', 3)
        ->assertJsonCount(1, 'audiences');

    $show = $this->actingAs($admin)
        ->getJson(route('audiences.show', ['audience' => 'member_list', 'context' => 'member_list', 'subject' => 'current']))
        ->assertOk()
        ->assertJsonPath('label', 'Current list')
        ->assertJsonPath('edited', false);

    expect(recordsAudienceIds($show->json('recipients')))->toBe(collect($current)->sort()->values()->all());
});

it('resolves the Resigned list and refuses a list without Send email', function () {
    $admin = recordsAudienceAdmin();
    $resigned = Member::factory()->create(['category' => Category::Withdrawn]);
    Member::factory()->create(['category' => Category::Active]);

    $show = $this->actingAs($admin)
        ->getJson(route('audiences.show', ['audience' => 'member_list', 'context' => 'member_list', 'subject' => 'resigned']))
        ->assertOk();

    expect(recordsAudienceIds($show->json('recipients')))->toBe([$resigned->id]);

    $this->actingAs($admin)
        ->getJson(route('audiences.index', ['context' => 'member_list', 'subject' => 'deceased']))
        ->assertNotFound();
});

it('resolves a Saved query Audience to the run\'s Members', function () {
    $admin = recordsAudienceAdmin();
    $hit = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2026]);
    Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2027]);
    // Outside the default Current scope.
    Member::factory()->create(['category' => Category::Withdrawn, 'next_award_year' => 2026]);
    $query = recordsAudienceQuery();

    $show = $this->actingAs($admin)
        ->getJson(route('audiences.show', ['audience' => 'saved_query', 'context' => 'saved_query', 'subject' => $query->id]))
        ->assertOk()
        ->assertJsonPath('label', 'Award year 2026');

    expect(recordsAudienceIds($show->json('recipients')))->toBe([$hit->id]);
});

it('removes only the unchecked ids and marks the Audience edited', function () {
    $admin = recordsAudienceAdmin();
    $kept = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2026]);
    $unchecked = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2026]);
    $query = recordsAudienceQuery();

    $show = $this->actingAs($admin)
        ->getJson(route('audiences.show', [
            'audience' => 'saved_query', 'context' => 'saved_query', 'subject' => $query->id, 'removed' => [$unchecked->id],
        ]))
        ->assertOk()
        ->assertJsonPath('edited', true)
        ->assertJsonPath('label', 'Award year 2026, 1 removed');

    expect(recordsAudienceIds($show->json('recipients')))->toBe([$kept->id]);

    // An id outside the run is not an edit.
    $outsider = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2027]);
    $this->actingAs($admin)
        ->getJson(route('audiences.show', [
            'audience' => 'saved_query', 'context' => 'saved_query', 'subject' => $query->id, 'removed' => [$outsider->id],
        ]))
        ->assertUnprocessable();
});

it('offers neither Audience to a Member without member administration', function () {
    recordsAudienceAdmin();
    $officer = Member::factory()->create(['category' => Category::Active]);
    $query = recordsAudienceQuery();

    foreach ([['member_list', 'current'], ['saved_query', $query->id]] as [$context, $subject]) {
        $this->actingAs($officer)
            ->getJson(route('audiences.index', ['context' => $context, 'subject' => $subject]))
            ->assertOk()
            ->assertJsonCount(0, 'audiences');

        $this->actingAs($officer)
            ->getJson(route('audiences.show', ['audience' => $context, 'context' => $context, 'subject' => $subject]))
            ->assertForbidden();

        $this->actingAs($officer)
            ->postJson(route('broadcasts.store'), [
                'context' => $context, 'context_subject' => (string) $subject, 'audience' => $context,
                'subject' => 'Hello', 'body' => '<p>Hi</p>',
            ])
            ->assertForbidden();
    }

    expect(Broadcast::count())->toBe(0);
});

it('names the empty reason for a Member without member administration', function () {
    $admin = recordsAudienceAdmin();
    $officer = Member::factory()->create(['category' => Category::Active]);
    $resolver = app(AudienceResolver::class);

    foreach ([AudienceContext::memberList(MemberList::Current), AudienceContext::savedQuery(recordsAudienceQuery())] as $context) {
        expect($resolver->emptyReason($officer, $context))->toBe('not_member_admin')
            ->and($resolver->emptyReason($admin, $context))->toBeNull();
    }
});

it('sends a Broadcast from a list with one Delivery per Member, skipping no_email', function () {
    $admin = recordsAudienceAdmin();
    $reachable = [
        $admin->id,
        Member::factory()->create(['category' => Category::Active])->id,
        Member::factory()->create(['category' => Category::Provisional])->id,
    ];
    $flagged = Member::factory()->create(['category' => Category::Active, 'no_email' => true]);

    $this->actingAs($admin)
        ->postJson(route('broadcasts.store'), [
            'context' => 'member_list', 'context_subject' => 'current', 'audience' => 'member_list',
            'subject' => 'Renewals', 'body' => '<p>Please renew.</p>',
        ])
        ->assertOk()
        ->assertJsonPath('queued', 3)
        ->assertJsonPath('skipped', [$flagged->fullName()]);

    $broadcast = Broadcast::sole();
    expect($broadcast->audience_label)->toBe('Current list')
        ->and($broadcast->edited)->toBeFalse()
        ->and($broadcast->group_id)->toBeNull()
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->pluck('member_id')->sort()->values()->all())
        ->toBe(collect($reachable)->sort()->values()->all());
});

it('sends a Broadcast from a query run to the checked rows, keeping the name and the edited flag', function () {
    $admin = recordsAudienceAdmin();
    $kept = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2026]);
    $unchecked = Member::factory()->create(['category' => Category::Active, 'next_award_year' => 2026]);
    $query = recordsAudienceQuery();

    $this->actingAs($admin)
        ->postJson(route('broadcasts.store'), [
            'context' => 'saved_query', 'context_subject' => (string) $query->id, 'audience' => 'saved_query',
            'removed' => [$unchecked->id], 'subject' => 'Awards', 'body' => '<p>Congratulations.</p>',
        ])
        ->assertOk()
        ->assertJsonPath('queued', 1);

    $broadcast = Broadcast::sole();
    expect($broadcast->audience_label)->toBe('Award year 2026, 1 removed')
        ->and($broadcast->edited)->toBeTrue()
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->pluck('member_id')->all())->toBe([$kept->id]);
});
