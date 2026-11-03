<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\BoardPosition;
use App\Models\Group;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\MemberLanguage;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The structured lists on the Member record (#918, ADR-0038 §3, §10): languages, Service awards
 * and board positions, kept by member administration and never sent to a peer.
 */

function recordListsAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

describe('languages', function () {
    it('adds a language and shows it on the record', function () {
        $member = Member::factory()->create();
        $admin = recordListsAdmin();

        $this->actingAs($admin)
            ->post("/members/{$member->id}/languages", ['language_code' => 'fr', 'speaks' => true, 'reads' => true, 'writes' => false])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get("/officer/members/records/{$member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('member.languages', 1)
                ->where('member.languages.0.language_code', 'fr')
                ->where('member.languages.0.speaks', true)
                ->where('member.languages.0.reads', true)
                ->where('member.languages.0.writes', false));
    });

    it('takes a free other language', function () {
        $member = Member::factory()->create();

        $this->actingAs(recordListsAdmin())
            ->post("/members/{$member->id}/languages", ['language_code' => 'Italian', 'reads' => true])
            ->assertSessionHasNoErrors();

        expect($member->languages()->sole()->language_code)->toBe('Italian');
    });

    it('stores ASL and LSQ as speak-only, however they are spelled or sent', function (string $sent, string $stored) {
        $member = Member::factory()->create();

        $this->actingAs(recordListsAdmin())
            ->post("/members/{$member->id}/languages", ['language_code' => $sent, 'reads' => true, 'writes' => true])
            ->assertSessionHasNoErrors();

        $row = $member->languages()->sole();

        expect($row->language_code)->toBe($stored)
            ->and($row->speaks)->toBeTrue()
            ->and($row->reads)->toBeFalse()
            ->and($row->writes)->toBeFalse();
    })->with([['asl', 'asl'], ['LSQ', 'lsq']]);

    it('keeps ASL speak-only through a change', function () {
        $member = Member::factory()->create();
        $row = $member->languages()->create(['language_code' => 'asl']);

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/languages/{$row->id}", ['speaks' => false, 'reads' => true, 'writes' => true])
            ->assertSessionHasNoErrors();

        expect($row->fresh()->only('speaks', 'reads', 'writes'))->toBe(['speaks' => true, 'reads' => false, 'writes' => false]);
    });

    it('refuses a second row for one language, and a blank one', function () {
        $member = Member::factory()->create();
        $member->languages()->create(['language_code' => 'en', 'speaks' => true]);
        $admin = recordListsAdmin();

        $this->actingAs($admin)
            ->post("/members/{$member->id}/languages", ['language_code' => 'en', 'reads' => true])
            ->assertSessionHasErrors('language_code');

        $this->actingAs($admin)
            ->post("/members/{$member->id}/languages", ['language_code' => ''])
            ->assertSessionHasErrors('language_code');

        expect($member->languages()->count())->toBe(1);
    });

    it('lets two Members hold the same language', function () {
        $member = Member::factory()->create();
        Member::factory()->create()->languages()->create(['language_code' => 'en', 'speaks' => true]);

        $this->actingAs(recordListsAdmin())
            ->post("/members/{$member->id}/languages", ['language_code' => 'en', 'speaks' => true])
            ->assertSessionHasNoErrors();

        expect(MemberLanguage::count())->toBe(2);
    });

    it('changes the flags and removes a row', function () {
        $member = Member::factory()->create();
        $row = $member->languages()->create(['language_code' => 'en', 'speaks' => true]);
        $admin = recordListsAdmin();

        $this->actingAs($admin)
            ->patch("/members/{$member->id}/languages/{$row->id}", ['speaks' => true, 'reads' => true, 'writes' => true])
            ->assertSessionHasNoErrors();

        expect($row->fresh()->only('speaks', 'reads', 'writes'))->toBe(['speaks' => true, 'reads' => true, 'writes' => true]);

        $this->actingAs($admin)->delete("/members/{$member->id}/languages/{$row->id}")->assertSessionHasNoErrors();

        expect($member->languages()->exists())->toBeFalse();
    });

    it('will not reach another Member\'s row through this Member\'s address', function () {
        $member = Member::factory()->create();
        $row = Member::factory()->create()->languages()->create(['language_code' => 'en', 'speaks' => true]);

        $this->actingAs(recordListsAdmin())
            ->delete("/members/{$member->id}/languages/{$row->id}")
            ->assertNotFound();

        expect($row->fresh())->not->toBeNull();
    });

    it('goes with the Member', function () {
        $member = Member::factory()->create();
        $member->languages()->create(['language_code' => 'en', 'speaks' => true]);

        $member->delete();

        expect(MemberLanguage::count())->toBe(0);
    });
});

describe('Service awards', function () {
    it('appends an award to the list, in the order given', function () {
        $member = Member::factory()->create();
        $member->forceFill(['service_awards' => ['5 yr']])->save();

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/record", [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'service_awards' => ['5 yr', '10 yr'],
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->service_awards)->toBe(['5 yr', '10 yr']);
    });

    it('sets the next award year to last + 5 when the last award year is set', function () {
        $member = Member::factory()->create();
        $member->forceFill(['last_award_year' => 2015, 'next_award_year' => 2020])->save();

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/record", [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'last_award_year' => 2021,
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->next_award_year)->toBe(2026);
    });

    it('keeps the next award year the admin gives, even the one already stored', function (?int $next) {
        $member = Member::factory()->create();
        $member->forceFill(['last_award_year' => 2015, 'next_award_year' => 2020])->save();

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/record", [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'last_award_year' => 2021,
                'next_award_year' => $next,
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->next_award_year)->toBe($next);
    })->with([2030, 2020, null]);

    it('leaves the next award year alone when the last award year does not move', function () {
        $member = Member::factory()->create();
        $member->forceFill(['last_award_year' => 2015, 'next_award_year' => 2030])->save();

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/record", [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'last_award_year' => 2015,
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->next_award_year)->toBe(2030);
    });

    it('leaves the next award year alone when the last award year is cleared', function () {
        $member = Member::factory()->create();
        $member->forceFill(['last_award_year' => 2015, 'next_award_year' => 2020])->save();

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/record", [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'last_award_year' => '',
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->next_award_year)->toBe(2020);
    });
});

describe('board positions', function () {
    it('adds a position, signed, and lists the record\'s newest first', function () {
        $member = Member::factory()->create();
        $admin = recordListsAdmin();

        foreach (['Treasurer', 'President'] as $position) {
            $this->actingAs($admin)
                ->post("/members/{$member->id}/board-positions", ['position' => $position, 'years' => '2019-2021'])
                ->assertSessionHasNoErrors();
        }

        expect($member->boardPositions()->first()->created_by)->toBe($admin->id);

        $this->actingAs($admin)
            ->get("/officer/members/records/{$member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('member.board_positions', 2)
                ->where('member.board_positions.0.position', 'President')
                ->where('member.board_positions.0.years', '2019-2021')
                ->where('member.board_positions.1.position', 'Treasurer'));
    });

    it('requires a position, and leaves the years open', function () {
        $member = Member::factory()->create();
        $admin = recordListsAdmin();

        $this->actingAs($admin)
            ->post("/members/{$member->id}/board-positions", ['position' => '', 'years' => '2020'])
            ->assertSessionHasErrors('position');

        $this->actingAs($admin)
            ->post("/members/{$member->id}/board-positions", ['position' => 'Secretary', 'years' => ''])
            ->assertSessionHasNoErrors();

        expect($member->boardPositions()->sole()->years)->toBeNull();
    });

    it('changes a position, signed, and removes one', function () {
        $member = Member::factory()->create();
        $row = $member->boardPositions()->create(['position' => 'Treasurer', 'years' => '2019']);
        $admin = recordListsAdmin();

        $this->actingAs($admin)
            ->patch("/members/{$member->id}/board-positions/{$row->id}", ['position' => 'Past President', 'years' => '2020-2022'])
            ->assertSessionHasNoErrors();

        expect($row->fresh()->only('position', 'years', 'updated_by'))
            ->toBe(['position' => 'Past President', 'years' => '2020-2022', 'updated_by' => $admin->id]);

        $this->actingAs($admin)->delete("/members/{$member->id}/board-positions/{$row->id}")->assertSessionHasNoErrors();

        expect($member->boardPositions()->exists())->toBeFalse();
    });

    it('offers the positions in use across the DMV as suggestions', function () {
        $member = Member::factory()->create();
        Member::factory()->create()->boardPositions()->create(['position' => 'Treasurer']);
        Member::factory()->create()->boardPositions()->create(['position' => 'Treasurer']);
        Member::factory()->create()->boardPositions()->create(['position' => 'President']);

        $this->actingAs(recordListsAdmin())
            ->get("/officer/members/records/{$member->id}")
            ->assertInertia(fn (Assert $page) => $page->where('boardPositionSuggestions', ['President', 'Treasurer']));
    });

    it('will not reach another Member\'s row through this Member\'s address', function () {
        $member = Member::factory()->create();
        $row = Member::factory()->create()->boardPositions()->create(['position' => 'Treasurer']);

        $this->actingAs(recordListsAdmin())
            ->patch("/members/{$member->id}/board-positions/{$row->id}", ['position' => 'Forged'])
            ->assertNotFound();

        expect($row->fresh()->position)->toBe('Treasurer');
    });

    it('goes with the Member, and forgets a deleted author', function () {
        $author = Member::factory()->create();
        $kept = Member::factory()->create()->boardPositions()->create(['position' => 'Secretary']);
        $kept->forceFill(['created_by' => $author->id, 'updated_by' => $author->id])->save();
        $gone = Member::factory()->create();
        $gone->boardPositions()->create(['position' => 'Treasurer']);

        $author->delete();
        $gone->delete();

        expect(BoardPosition::count())->toBe(1)
            ->and($kept->fresh()->created_by)->toBeNull()
            ->and($kept->fresh()->updated_by)->toBeNull();
    });
});

it('marks the admin the last editor of the record on a list change', function () {
    Carbon::setTestNow('2026-11-03 12:00:00');
    $member = Member::factory()->create();
    $admin = recordListsAdmin();

    $this->actingAs($admin)
        ->post("/members/{$member->id}/board-positions", ['position' => 'Secretary'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post("/members/{$member->id}/languages", ['language_code' => 'en', 'speaks' => true])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->updated_by)->toBe($admin->id);
});

describe('authority', function () {
    it('refuses every list write to anyone without member administration', function (string $actor) {
        $member = Member::factory()->create();
        $language = $member->languages()->create(['language_code' => 'en', 'speaks' => true]);
        $position = $member->boardPositions()->create(['position' => 'Treasurer']);

        $this->actingAs($actor === 'self' ? $member : Member::factory()->create());

        $this->post("/members/{$member->id}/languages", ['language_code' => 'fr', 'speaks' => true])->assertForbidden();
        $this->patch("/members/{$member->id}/languages/{$language->id}", ['reads' => true])->assertForbidden();
        $this->delete("/members/{$member->id}/languages/{$language->id}")->assertForbidden();
        $this->post("/members/{$member->id}/board-positions", ['position' => 'Secretary'])->assertForbidden();
        $this->patch("/members/{$member->id}/board-positions/{$position->id}", ['position' => 'Secretary'])->assertForbidden();
        $this->delete("/members/{$member->id}/board-positions/{$position->id}")->assertForbidden();

        expect($member->languages()->count())->toBe(1)
            ->and($member->boardPositions()->sole()->position)->toBe('Treasurer');
    })->with(['self', 'peer']);

    it('lets the super-tier write the lists', function () {
        $member = Member::factory()->create();

        $this->actingAs(Member::factory()->superTier()->create())
            ->post("/members/{$member->id}/languages", ['language_code' => 'en', 'speaks' => true])
            ->assertSessionHasNoErrors();

        expect($member->languages()->count())->toBe(1);
    });

    it('never sends a peer the languages, awards or positions, even a Group officer', function () {
        $member = Member::factory()->create();
        $member->forceFill(['service_awards' => ['5 yr']])->save();
        $member->languages()->create(['language_code' => 'en', 'speaks' => true]);
        $member->boardPositions()->create(['position' => 'Treasurer']);
        $group = Group::factory()->create();
        $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
        $chair = Member::factory()->create();
        $membership = $group->memberships()->create(['member_id' => $chair->id, 'status' => MembershipStatus::Full]);
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);

        $this->actingAs($chair)
            ->get("/members/{$member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->missing('member.languages')
                ->missing('member.service_awards')
                ->missing('member.board_positions'));

        $this->actingAs($chair)->get("/officer/members/records/{$member->id}")->assertForbidden();
    });

    it('keeps the lists out of any serialized Member', function () {
        $member = Member::factory()->create();
        $member->languages()->create(['language_code' => 'en', 'speaks' => true]);
        $member->boardPositions()->create(['position' => 'Treasurer']);

        expect($member->load('languages', 'boardPositions')->toArray())
            ->not->toHaveKeys(['languages', 'board_positions']);
    });
});
