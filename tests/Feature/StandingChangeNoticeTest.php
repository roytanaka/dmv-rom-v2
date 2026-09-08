<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\StandingChanged;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Support\Notices\StandingChangeNoticeWriter;
use Illuminate\Support\Facades\Mail;

/*
 * The standing-change Notice writer (#485, spec #479, ADR-0024 §8). When a Member's DMV-wide
 * Category moves to Resigned or Deceased, every Chair of every Group where that Member's
 * Membership is not already departed is told — one Notice Delivery per Chair, rendered by the
 * Drain from the row's payload snapshot in the Chair's own locale. This ticket ships the writer,
 * the Mailable, the bilingual template, and these direct-call tests; the member-administration
 * action that will call the writer is its own spec, so there is no route here.
 *
 * Prior art: SignUpCancellationMailTest, the first Notice on the queue.
 */

/** A Member with a Chair role on the given Group, optionally carrying a locale or the flag. */
function chairOf(Group $group, string $locale = 'en', bool $noEmail = false): Member
{
    $member = Member::factory()->create(['locale' => $locale]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => Role::Chair]);

    if ($noEmail) {
        $member->no_email = true;
        $member->save();
    }

    return $member;
}

/** Put $subject in $group with the given within-Group standing, and return the membership. */
function standingMemberIn(Group $group, Member $subject, MembershipStatus $status = MembershipStatus::Full): GroupMember
{
    return GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $subject->id,
        'status' => $status,
    ]);
}

it('writes nothing when the Category did not change', function () {
    $group = Group::factory()->create();
    chairOf($group);
    $subject = Member::factory()->create();
    standingMemberIn($group, $subject);

    (new StandingChangeNoticeWriter)->write($subject, Category::Resigned, Category::Resigned, now());

    expect(Delivery::count())->toBe(0);
});

it('writes nothing when the new Category is not a departure', function () {
    $group = Group::factory()->create();
    chairOf($group);
    $subject = Member::factory()->create();
    standingMemberIn($group, $subject);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Loa, now());

    expect(Delivery::count())->toBe(0);
});

it('on Resigned writes one Notice Delivery per Chair of each Group where the Membership is not resigned or deceased', function () {
    $subject = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);

    // Two Groups where the subject still stands — each Chair should hear.
    $active = Group::factory()->create(['name' => 'Visitor Wayfinders']);
    $activeChair = chairOf($active);
    standingMemberIn($active, $subject, MembershipStatus::Full);

    $auxiliary = Group::factory()->create(['name' => 'Docents']);
    $auxiliaryChair = chairOf($auxiliary);
    standingMemberIn($auxiliary, $subject, MembershipStatus::Auxiliary);

    // A Group the subject already left — its Chair is not told again.
    $departed = Group::factory()->create(['name' => 'Reception']);
    chairOf($departed);
    standingMemberIn($departed, $subject, MembershipStatus::Resigned);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());

    $rows = Delivery::all();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('member_id')->all())->toEqualCanonicalizing([$activeChair->id, $auxiliaryChair->id]);

    $row = $rows->firstWhere('member_id', $activeChair->id);
    expect($row->kind)->toBe(DeliveryKind::Notice);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->email)->toBe($activeChair->email);
    expect($row->next_attempt_at)->not->toBeNull();
    // The snapshot the Drain renders from — the Member's name, the Group it names, the new
    // standing, and the effective date.
    expect($row->payload['member']['first_name'])->toBe('Dana');
    expect($row->payload['group']['name'])->toBe('Visitor Wayfinders');
    expect($row->payload['group']['slug'])->toBe($active->slug);
    expect($row->payload['standing'])->toBe(Category::Resigned->value);
});

it('on Deceased writes for a Group whose roster already shows the Member resigned', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    $chair = chairOf($group);
    // The subject already resigned from this Group — a Resigned change would skip it, but a
    // death is told regardless (ADR-0024 §8: Deceased skips only a Membership already deceased).
    standingMemberIn($group, $subject, MembershipStatus::Resigned);

    (new StandingChangeNoticeWriter)->write($subject, Category::Resigned, Category::Deceased, now());

    $rows = Delivery::all();
    expect($rows)->toHaveCount(1);
    expect($rows->sole()->member_id)->toBe($chair->id);
    expect($rows->sole()->payload['standing'])->toBe(Category::Deceased->value);
});

it('writes nothing on Deceased for a Group whose roster already shows the Member deceased', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Deceased);

    (new StandingChangeNoticeWriter)->write($subject, Category::Resigned, Category::Deceased, now());

    expect(Delivery::count())->toBe(0);
});

it('Resigned then Deceased writes twice; a repeated identical call writes nothing', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    $writer = new StandingChangeNoticeWriter;

    $writer->write($subject, Category::Active, Category::Resigned, now());
    expect(Delivery::count())->toBe(1);

    // A repeated identical save — the Category did not change — writes nothing.
    $writer->write($subject, Category::Resigned, Category::Resigned, now());
    expect(Delivery::count())->toBe(1);

    // Then a death: a genuine second departure, so a second Notice.
    $writer->write($subject, Category::Resigned, Category::Deceased, now());
    expect(Delivery::count())->toBe(2);
});

it('gives each co-chair one Delivery and skips a Chair carrying the no-email flag', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    $firstChair = chairOf($group);
    $coChair = chairOf($group);
    $silencedChair = chairOf($group, noEmail: true);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());

    // Both reachable co-chairs get one row each; the flagged Chair gets none.
    $rows = Delivery::all();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('member_id')->all())->toEqualCanonicalizing([$firstChair->id, $coChair->id]);
    expect($rows->pluck('member_id')->all())->not->toContain($silencedChair->id);
});

it('sends each Chair’s Notice through the Drain in their own saved locale', function () {
    Mail::fake();

    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    $english = chairOf($group, locale: 'en');
    $french = chairOf($group, locale: 'fr');
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());
    Mail::assertNothingSent();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(StandingChanged::class, 2);
    Mail::assertSent(StandingChanged::class, fn (StandingChanged $mail) => $mail->hasTo($english->email) && $mail->locale === 'en');
    Mail::assertSent(StandingChanged::class, fn (StandingChanged $mail) => $mail->hasTo($french->email) && $mail->locale === 'fr');

    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(2);
});

it('names the Member, the Group, the new standing, and the effective date', function () {
    $subject = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    $group = Group::factory()->create(['name' => 'Visitor Wayfinders']);
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now()->setTime(0, 0));

    $mail = StandingChanged::fromSnapshot(Delivery::sole()->payload);

    $mail->assertSeeInHtml('Dana Okafor');
    $mail->assertSeeInHtml('Visitor Wayfinders');
    $mail->assertSeeInHtml(__('member.standing.resigned'));
    $mail->assertSeeInHtml(now()->translatedFormat('j F Y'));
});

it('links to the Group roster for the render locale — a /fr/ twin in French, the English root in English', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create();
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());

    $payload = Delivery::sole()->payload;

    (StandingChanged::fromSnapshot($payload))->locale('fr')
        ->assertSeeInHtml("/fr/groupes/{$group->slug}/roster");
    (StandingChanged::fromSnapshot($payload))->locale('en')
        ->assertSeeInHtml("/groups/{$group->slug}/roster");
});

it('carries the Group name as the From display name, the app address as From, and no Reply-To', function () {
    $subject = Member::factory()->create();
    $group = Group::factory()->create(['name' => 'Docents']);
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());

    $envelope = StandingChanged::fromSnapshot(Delivery::sole()->payload)->envelope();

    expect($envelope->from->address)->toBe(config('mail.from.address'));
    expect($envelope->from->name)->toBe('Docents');
    expect($envelope->replyTo)->toBe([]);
});

it('renders the French subject and heading from the lang files', function () {
    $subject = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    $group = Group::factory()->create();
    chairOf($group);
    standingMemberIn($group, $subject, MembershipStatus::Full);

    (new StandingChangeNoticeWriter)->write($subject, Category::Active, Category::Resigned, now());

    $mail = StandingChanged::fromSnapshot(Delivery::sole()->payload)->locale('fr');

    $mail->assertHasSubject(__('notices.standing_change.subject', ['member' => 'Dana Okafor'], 'fr'));
    $mail->assertSeeInHtml(__('notices.standing_change.heading', [], 'fr'));
});
