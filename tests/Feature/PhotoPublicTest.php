<?php

use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The photo-public flag (#922, ADR-0038 §3): a Member may keep their photo from peers. The
 * Member, member administration and the super-tier always see it; the Records Photo action
 * uploads and sets the flag for any Member.
 */

function photoMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create();
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

function privatePhotoMember(): Member
{
    $member = Member::factory()->create(['last_name' => 'Aaprivate']);
    $member->forceFill(['photo_path' => 'profile-photos/private.webp', 'photo_public' => false])->save();

    return $member;
}

beforeEach(fn () => Storage::fake('public'));

it('defaults a new Member to a public photo', function () {
    expect(Member::factory()->create()->fresh()->photo_public)->toBeTrue();
});

it('withholds the photo from a peer on the profile when the flag is off', function () {
    $member = privatePhotoMember();

    $this->actingAs(Member::factory()->create())
        ->get("/members/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page->where('member.photo', null));
});

it('sends a public photo to a peer', function () {
    $member = Member::factory()->create();
    $member->forceFill(['photo_path' => 'profile-photos/open.webp'])->save();

    $this->actingAs(Member::factory()->create())
        ->get("/members/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page->where('member.photo', Storage::disk('public')->url('profile-photos/open.webp')));
});

it('sends a private photo to the Member, member administration and the super-tier', function (string $viewer) {
    $member = privatePhotoMember();
    $actor = match ($viewer) {
        'self' => $member,
        'admin' => photoMemberAdmin(),
        'super' => Member::factory()->superTier()->create(),
    };

    $this->actingAs($actor)
        ->get("/members/{$member->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.photo', Storage::disk('public')->url('profile-photos/private.webp'))
            ->where('member.photo_public', false));
})->with(['self', 'admin', 'super']);

it('withholds a private photo from the Directory', function () {
    $member = privatePhotoMember();

    $this->actingAs(Member::factory()->create())
        ->get('/directory')
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn (Collection $rows) => $rows->firstWhere('id', $member->id)['photo'] === null));
});

it('withholds a private photo from the Group roster and the Email picker', function () {
    $member = privatePhotoMember();
    $group = Group::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $member->id]);
    $peer = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $peer->id]);

    $this->actingAs($peer)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.roster', fn (Collection $rows) => $rows->firstWhere('id', $member->id)['photo'] === null));
});

it('lets the Member turn the flag off in Account settings', function () {
    $member = Member::factory()->create();

    $this->actingAs($member)
        ->patch('/settings/profile', [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'photo_public' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->photo_public)->toBeFalse();

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('photoPublic', false));
});

describe('the Records Photo action', function () {
    it('shows each Current row its photo and flag', function () {
        $member = privatePhotoMember();

        $this->actingAs(photoMemberAdmin())
            ->get('/officer/members')
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.data.0.id', $member->id)
                ->where('members.data.0.photo', Storage::disk('public')->url('profile-photos/private.webp'))
                ->where('members.data.0.photo_public', false));
    });

    it('uploads a photo and sets the flag for any Member', function () {
        $member = Member::factory()->create();
        $admin = photoMemberAdmin();

        $this->actingAs($admin)
            ->post("/members/{$member->id}/photo", [
                'photo' => UploadedFile::fake()->image('face.jpg', 400, 400),
                'photo_public' => false,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $member->fresh();
        expect($fresh->photo_path)->toStartWith('profile-photos/')
            ->and($fresh->photo_public)->toBeFalse()
            ->and($fresh->updated_by)->toBe($admin->id);
        Storage::disk('public')->assertExists($fresh->photo_path);
    });

    it('replaces the photo and deletes the old file', function () {
        $member = privatePhotoMember();
        Storage::disk('public')->put('profile-photos/private.webp', 'old');

        $this->actingAs(photoMemberAdmin())
            ->post("/members/{$member->id}/photo", [
                'photo' => UploadedFile::fake()->image('face.jpg', 400, 400),
                'photo_public' => true,
            ])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->photo_path)->not->toBe('profile-photos/private.webp')
            ->and($member->fresh()->photo_public)->toBeTrue();
        Storage::disk('public')->assertMissing('profile-photos/private.webp');
    });

    it('sets the flag alone, leaving the photo', function () {
        $member = privatePhotoMember();

        $this->actingAs(photoMemberAdmin())
            ->post("/members/{$member->id}/photo", ['photo_public' => true])
            ->assertSessionHasNoErrors();

        expect($member->fresh()->photo_path)->toBe('profile-photos/private.webp')
            ->and($member->fresh()->photo_public)->toBeTrue();
    });

    it('refuses a plain Member', function () {
        $member = privatePhotoMember();

        $this->actingAs(Member::factory()->create())
            ->post("/members/{$member->id}/photo", ['photo_public' => true])
            ->assertForbidden();

        expect($member->fresh()->photo_public)->toBeFalse();
    });
});
