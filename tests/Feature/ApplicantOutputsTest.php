<?php

use App\Enums\ApplicantStatus;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Mail\BroadcastMail;
use App\Mail\BroadcastSenderCopy;
use App\Models\Applicant;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The rest of the Applicants surface (#929, ADR-0038 §8): Delete while unlinked, Print record,
 * Print list, and Send email to the Applicants a status filter shows.
 */

/** The holder (Membership) with a Chair and a plain member, and a foreign Group with a Chair. */
function outputsOrg(): array
{
    $membership = Group::factory()->standingCommittee()->create(['slug' => 'membership', 'name' => 'Membership']);
    GroupStewardship::factory()->stewarding(StewardshipFunction::Applicants)->create(['group_id' => $membership->id]);
    $other = Group::factory()->standingCommittee()->create(['slug' => 'awards', 'name' => 'Awards']);

    return [
        'chair' => outputsMember($membership, Role::Chair, 'Chandra', 'Chair'),
        'member' => outputsMember($membership, null, 'Mo', 'Mentor'),
        'foreignChair' => outputsMember($other, Role::Chair, 'Fiona', 'Foreign'),
    ];
}

function outputsMember(Group $group, ?Role $role, string $first, string $last): Member
{
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last]);
    $membership = $group->memberships()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);
    if ($role !== null) {
        $membership->roles()->create(['role' => $role]);
    }

    return $member;
}

// Delete

it('deletes an unlinked Applicant for whoever may edit it, and offers Delete on the record', function () {
    $org = outputsOrg();
    $applicant = Applicant::factory()->mentoredBy($org['member'])->create();

    $this->actingAs($org['member'])
        ->get("/officer/members/applicants/{$applicant->id}")
        ->assertInertia(fn (Assert $page) => $page->where('canDelete', true));

    $this->actingAs($org['member'])
        ->delete("/applicants/{$applicant->id}")
        ->assertRedirect('/officer/members/applicants');

    expect(Applicant::find($applicant->id))->toBeNull();
});

it('refuses Delete once a Member is linked, even to the super-tier, and hides the button', function () {
    $org = outputsOrg();
    $applicant = Applicant::factory()->create(['member_id' => Member::factory()->create()->id]);

    $this->actingAs($org['chair'])
        ->get("/officer/members/applicants/{$applicant->id}")
        ->assertInertia(fn (Assert $page) => $page->where('canDelete', false));

    foreach ([$org['chair'], Member::factory()->superTier()->create()] as $actor) {
        $this->actingAs($actor)->delete("/applicants/{$applicant->id}")->assertForbidden();
    }

    expect(Applicant::find($applicant->id))->not->toBeNull();
});

it('refuses Delete to a reader', function () {
    $org = outputsOrg();
    $applicant = Applicant::factory()->create();

    foreach ([$org['member'], $org['foreignChair']] as $reader) {
        $this->actingAs($reader)
            ->get("/officer/members/applicants/{$applicant->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canDelete', false)->where('canEdit', false));
        $this->actingAs($reader)->delete("/applicants/{$applicant->id}")->assertForbidden();
    }

    expect(Applicant::find($applicant->id))->not->toBeNull();
});

// Print record and Print list

it('prints the full record for any reader, and refuses a plain Member', function () {
    $org = outputsOrg();
    $applicant = Applicant::factory()->mentoredBy($org['member'])->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'address_city' => 'Toronto',
        'notes' => '2026-11-01 Mo Mentor: Called.',
    ]);
    $applicant->languages()->create(['language_code' => 'fr', 'speaks' => true, 'reads' => true, 'writes' => false]);

    $this->actingAs($org['foreignChair'])
        ->get("/officer/members/applicants/{$applicant->id}/print")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/applicants/PrintRecord')
            ->where('name', 'Ada Lovelace')
            ->where('notes', '2026-11-01 Mo Mentor: Called.')
            ->where('sections', fn ($sections) => collect($sections)->flatMap(fn ($section) => $section['rows'])
                ->contains(fn ($row) => $row === ['label' => 'Mentor', 'value' => 'Mo Mentor'])
                && collect($sections)->flatMap(fn ($section) => $section['rows'])
                    ->contains(fn ($row) => $row === ['label' => 'City', 'value' => 'Toronto'])
                && collect($sections)->flatMap(fn ($section) => $section['rows'])
                    ->contains(fn ($row) => $row === ['label' => 'French', 'value' => 'Speaks, Reads'])));

    $this->actingAs(Member::factory()->create())
        ->get("/officer/members/applicants/{$applicant->id}/print")
        ->assertForbidden();
});

it('prints every row a filter and search show, unpaged', function () {
    $org = outputsOrg();
    Applicant::factory()->count(55)->status(ApplicantStatus::Placed)->create();
    $smith = Applicant::factory()->status(ApplicantStatus::New)->create(['last_name' => 'Smith']);
    Applicant::factory()->status(ApplicantStatus::New)->create(['last_name' => 'Jones']);
    Applicant::factory()->status(ApplicantStatus::Incomplete)->create(['last_name' => 'Smythe']);

    $this->actingAs($org['member'])
        ->get('/officer/members/applicants/print?status=placed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/applicants/PrintList')
            ->where('status', 'placed')
            ->has('applicants', 55));

    $this->actingAs($org['member'])
        ->get('/officer/members/applicants/print?status=new&q=Sm')
        ->assertInertia(fn (Assert $page) => $page
            ->where('applicants', fn ($rows) => collect($rows)->pluck('id')->all() === [$smith->id]));

    $this->actingAs(Member::factory()->create())
        ->get('/officer/members/applicants/print')
        ->assertForbidden();
});

it('serves both print pages at their French addresses', function () {
    $org = outputsOrg();
    $applicant = Applicant::factory()->create();

    $this->withLocaleRoutes('fr', function () use ($org, $applicant) {
        $this->actingAs($org['chair']);
        $this->get("/fr/officier/membres/candidats/{$applicant->id}/imprimer")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/PrintRecord')->where('sections.0.rows.1.label', 'Statut'));
        $this->get('/fr/officier/membres/candidats/imprimer')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/applicants/PrintList'));
    });
});

// Send email

it('resolves the Applicants Audience to the rows a status filter shows', function () {
    $org = outputsOrg();
    $new = Applicant::factory()->count(2)->status(ApplicantStatus::New)->create();
    $placed = Applicant::factory()->status(ApplicantStatus::Placed)->create();
    Applicant::factory()->status(ApplicantStatus::Incomplete)->create();

    $this->actingAs($org['member'])
        ->getJson(route('audiences.index', ['context' => 'applicants', 'subject' => 'new']))
        ->assertOk()
        ->assertJsonCount(1, 'audiences')
        ->assertJsonPath('audiences.0.key', 'applicants')
        ->assertJsonPath('audiences.0.label', 'Applicants: New')
        ->assertJsonPath('audiences.0.count', 2);

    $show = $this->actingAs($org['member'])
        ->getJson(route('audiences.show', ['audience' => 'applicants', 'context' => 'applicants', 'subject' => 'new']))
        ->assertOk()
        ->assertJsonPath('recipients.0.photo', null);
    expect(collect($show->json('recipients'))->pluck('id')->sort()->values()->all())->toBe($new->pluck('id')->sort()->values()->all());

    // All is every live status: Incomplete stays out.
    $all = $this->actingAs($org['member'])
        ->getJson(route('audiences.show', ['audience' => 'applicants', 'context' => 'applicants', 'subject' => 'all']))
        ->assertOk()
        ->assertJsonPath('label', 'Applicants: All');
    expect(collect($all->json('recipients'))->pluck('id')->sort()->values()->all())
        ->toBe($new->pluck('id')->push($placed->id)->sort()->values()->all());

    $this->actingAs($org['member'])
        ->getJson(route('audiences.index', ['context' => 'applicants', 'subject' => 'bogus']))
        ->assertNotFound();
});

it('offers the Applicants Audience to holder members only', function () {
    $org = outputsOrg();
    Applicant::factory()->create();

    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $memberAdmin = outputsMember($records, null, 'Rita', 'Records');

    foreach ([$org['foreignChair'], $memberAdmin] as $reader) {
        $this->actingAs($reader)
            ->getJson(route('audiences.index', ['context' => 'applicants', 'subject' => 'all']))
            ->assertOk()
            ->assertJsonCount(0, 'audiences');
        $this->actingAs($reader)
            ->getJson(route('audiences.show', ['audience' => 'applicants', 'context' => 'applicants', 'subject' => 'all']))
            ->assertForbidden();
        $this->actingAs($reader)
            ->postJson(route('broadcasts.store'), [
                'context' => 'applicants', 'context_subject' => 'all', 'audience' => 'applicants',
                'subject' => 'Hello', 'body' => '<p>Hi</p>',
            ])
            ->assertForbidden();
    }

    expect(Broadcast::count())->toBe(0);
});

it('sends a Broadcast with one Delivery per Applicant, by the Applicant\'s email', function () {
    $org = outputsOrg();
    $kept = Applicant::factory()->status(ApplicantStatus::Accepted)->create(['email' => 'kept@example.test']);
    $removed = Applicant::factory()->status(ApplicantStatus::Accepted)->create();
    $unreachable = Applicant::factory()->status(ApplicantStatus::Accepted)->create(['email' => null, 'first_name' => 'No', 'last_name' => 'Address']);
    Applicant::factory()->status(ApplicantStatus::New)->create();

    $this->actingAs($org['chair'])
        ->postJson(route('broadcasts.store'), [
            'context' => 'applicants', 'context_subject' => 'accepted', 'audience' => 'applicants',
            'subject' => 'Orientation', 'body' => '<p>See you Monday.</p>', 'removed' => [$removed->id],
        ])
        ->assertOk()
        ->assertJsonPath('queued', 2)
        ->assertJsonPath('skipped', []);

    $broadcast = Broadcast::sole();
    $rows = $broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->orderBy('id')->get();
    expect($broadcast->audience_label)->toBe('Applicants: Accepted, 1 removed')
        ->and($broadcast->edited)->toBeTrue()
        ->and($rows->pluck('member_id')->all())->toBe([null, null])
        ->and($rows->pluck('state', 'email')->sortKeys()->all())->toBe(['' => DeliveryState::Failed, 'kept@example.test' => DeliveryState::Pending]);

    // The Drain mails the Applicant and names the unreached one in the sender's copy.
    Mail::fake();
    $this->artisan('mail:drain')->assertSuccessful();
    Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $mail): bool => $mail->hasTo('kept@example.test'));
    Mail::assertSent(BroadcastSenderCopy::class, fn (BroadcastSenderCopy $mail): bool => $mail->failedNames === ['No Address']);
    expect(Delivery::where('kind', DeliveryKind::Broadcast)->where('state', DeliveryState::Sent)->count())->toBe(1);
});
