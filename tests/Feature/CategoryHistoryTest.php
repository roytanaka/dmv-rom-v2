<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DepartureReason;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Mail\MenteeActivated;
use App\Models\CategoryChange;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Support\Notices\MenteeActivatedNoticeWriter;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;

/*
 * Category history and Reinstate (#920, ADR-0038 §4). A Member's Category moves only through a
 * dated entry; the newest entry is the Category and the status date. Resigned and Deceased tell
 * the Chairs and depart the Memberships; activation plans the next award; Reinstate writes two
 * entries and reopens sign-in.
 */

function historyMemberAdmin(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);
    $admin = Member::factory()->create(['first_name' => 'Rita', 'last_name' => 'Records']);
    $records->memberships()->create(['member_id' => $admin->id, 'status' => MembershipStatus::Full]);

    return $admin;
}

function addEntry(Member $admin, Member $member, array $payload)
{
    return test()->actingAs($admin)->post(route('members.category-changes.store', $member), $payload);
}

it('sets the Category and status date from a new entry', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Provisional]);

    addEntry($admin, $member, ['category' => 'loa', 'date' => '2026-10-01'])->assertRedirect();

    $member->refresh();
    expect($member->category)->toBe(Category::Loa)
        ->and($member->status_date->toDateString())->toBe('2026-10-01');

    $change = CategoryChange::sole();
    expect($change->member_id)->toBe($member->id)
        ->and($change->category)->toBe('loa')
        ->and($change->created_by)->toBe($admin->id);
});

it('keeps the newest entry as the Category when an older one is added', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Provisional]);

    addEntry($admin, $member, ['category' => 'active', 'date' => '2026-10-01']);
    addEntry($admin, $member, ['category' => 'provisional', 'date' => '2024-09-01']);

    $member->refresh();
    expect($member->category)->toBe(Category::Active)
        ->and($member->status_date->toDateString())->toBe('2026-10-01');
});

it('recomputes the Category from what remains when an entry is removed', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Provisional]);

    addEntry($admin, $member, ['category' => 'provisional', 'date' => '2024-09-01']);
    addEntry($admin, $member, ['category' => 'sustaining', 'date' => '2026-10-01']);
    $newest = CategoryChange::where('category', 'sustaining')->sole();

    $this->actingAs($admin)
        ->delete(route('members.category-changes.destroy', [$member, $newest]))
        ->assertRedirect();

    $member->refresh();
    expect($member->category)->toBe(Category::Provisional)
        ->and($member->status_date->toDateString())->toBe('2024-09-01')
        ->and(CategoryChange::count())->toBe(1);
});

it('refuses to remove another Member’s entry through this Member', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create();
    $other = Member::factory()->create();
    addEntry($admin, $other, ['category' => 'loa', 'date' => '2026-10-01']);

    $this->actingAs($admin)
        ->delete(route('members.category-changes.destroy', [$member, CategoryChange::sole()]))
        ->assertNotFound();
});

it('lets only member administration add or remove an entry', function () {
    $peer = Member::factory()->create();
    $member = Member::factory()->create(['category' => Category::Active]);

    addEntry($peer, $member, ['category' => 'resigned', 'date' => '2026-10-01', 'departure_reason' => 'moved'])->assertForbidden();
    addEntry($member, $member, ['category' => 'honourary', 'date' => '2026-10-01'])->assertForbidden();

    expect($member->fresh()->category)->toBe(Category::Active)
        ->and(CategoryChange::count())->toBe(0);
});

it('requires a departure reason for Resigned and Withdrawn, and stores it', function (string $category) {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create();

    addEntry($admin, $member, ['category' => $category, 'date' => '2026-10-01'])
        ->assertSessionHasErrors('departure_reason');

    addEntry($admin, $member, ['category' => $category, 'date' => '2026-10-01', 'departure_reason' => 'moved'])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->departure_reason)->toBe(DepartureReason::Moved)
        ->and(CategoryChange::sole()->departure_reason)->toBe(DepartureReason::Moved);
})->with(['resigned', 'withdrawn']);

it('clears the departure reason when the Member comes back', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create();

    addEntry($admin, $member, ['category' => 'resigned', 'date' => '2026-01-01', 'departure_reason' => 'health']);
    addEntry($admin, $member, ['category' => 'active', 'date' => '2026-10-01', 'departure_reason' => 'health']);

    expect($member->fresh()->departure_reason)->toBeNull();
});

it('rejects an unknown Category and a missing date', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create();

    addEntry($admin, $member, ['category' => 'reinstate'])->assertSessionHasErrors(['category', 'date']);
});

it('on Resigned tells the Chairs and departs the Memberships', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Active]);
    $group = Group::factory()->create();
    $chair = Member::factory()->create();
    $chairship = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $chair->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $chairship->id, 'role' => Role::Chair]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id, 'status' => MembershipStatus::Full]);

    addEntry($admin, $member, ['category' => 'resigned', 'date' => '2026-10-01', 'departure_reason' => 'moved']);

    $notice = Delivery::sole();
    expect($notice->kind)->toBe(DeliveryKind::Notice)
        ->and($notice->member_id)->toBe($chair->id)
        ->and($notice->payload['standing'])->toBe('resigned')
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Resigned);
});

it('on Deceased departs every Membership not already deceased', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Resigned]);
    $resigned = GroupMember::factory()->create(['member_id' => $member->id, 'status' => MembershipStatus::Resigned]);
    $loa = GroupMember::factory()->create(['member_id' => $member->id, 'status' => MembershipStatus::Loa]);

    addEntry($admin, $member, ['category' => 'deceased', 'date' => '2026-10-01']);

    expect($resigned->fresh()->status)->toBe(MembershipStatus::Deceased)
        ->and($loa->fresh()->status)->toBe(MembershipStatus::Deceased);
});

it('departs nothing and tells nobody for Withdrawn', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Active]);
    $membership = GroupMember::factory()->create(['member_id' => $member->id, 'status' => MembershipStatus::Full]);

    addEntry($admin, $member, ['category' => 'withdrawn', 'date' => '2026-10-01', 'departure_reason' => 'other']);

    expect(Delivery::count())->toBe(0)
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Full);
});

it('sets the next award year to entry year + 5 on activation from Provisional or Pre-active', function (Category $from) {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => $from]);
    $member->forceFill(['entry_year' => 2025, 'next_award_year' => null])->save();

    addEntry($admin, $member, ['category' => 'active', 'date' => '2026-10-01']);

    expect($member->fresh()->next_award_year)->toBe(2030)
        ->and(Delivery::count())->toBe(0);
})->with([Category::Provisional, Category::PreActive]);

it('leaves the next award year alone on any other move to Active', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Loa]);
    $member->forceFill(['entry_year' => 2025, 'next_award_year' => 2035])->save();

    addEntry($admin, $member, ['category' => 'active', 'date' => '2026-10-01']);

    expect($member->fresh()->next_award_year)->toBe(2035);
});

it('writes the mentor Notice when a mentor is known, and nothing without one', function () {
    $mentee = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    $mentor = Member::factory()->create(['locale' => 'fr']);
    $writer = new MenteeActivatedNoticeWriter;

    $writer->write($mentee, null);
    expect(Delivery::count())->toBe(0);

    $writer->write($mentee, $mentor);
    $row = Delivery::sole();
    expect($row->member_id)->toBe($mentor->id)
        ->and($row->kind)->toBe(DeliveryKind::Notice)
        ->and($row->email)->toBe($mentor->email);

    Mail::fake();
    $this->artisan('mail:drain')->assertSuccessful();
    Mail::assertSent(MenteeActivated::class, fn (MenteeActivated $mail) => $mail->hasTo($mentor->email) && $mail->locale === 'fr');
});

it('lists the history newest first on the record', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create();

    addEntry($admin, $member, ['category' => 'provisional', 'date' => '2024-09-01']);
    addEntry($admin, $member, ['category' => 'active', 'date' => '2025-09-01']);
    addEntry($admin, $member, ['category' => 'resigned', 'date' => '2026-10-01', 'departure_reason' => 'job']);

    $this->actingAs($admin)
        ->get(route('officer.members.record', $member))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categoryHistory.0.category', 'resigned')
            ->where('categoryHistory.0.date', '2026-10-01')
            ->where('categoryHistory.0.departure_reason', 'job')
            ->where('categoryHistory.1.category', 'active')
            ->where('categoryHistory.2.category', 'provisional')
            ->where('member.departure_reason', 'job')
            ->etc());
});

it('reinstates a Resigned Member: two entries, Active, and sign-in reopens', function () {
    $admin = historyMemberAdmin();
    $member = Member::factory()->create(['category' => Category::Active, 'email' => 'back@dmv.test']);
    addEntry($admin, $member, ['category' => 'resigned', 'date' => '2025-01-01', 'departure_reason' => 'moved']);

    auth()->logout();
    $this->post(route('login'), ['email' => 'back@dmv.test', 'password' => 'password']);
    $this->assertGuest();

    $this->actingAs($admin)->post(route('members.reinstate', $member))->assertRedirect();

    $member->refresh();
    expect($member->category)->toBe(Category::Active)
        ->and($member->status_date->toDateString())->toBe(now(config('app.org_timezone'))->toDateString())
        ->and($member->departure_reason)->toBeNull()
        ->and(CategoryChange::orderByDesc('id')->limit(2)->pluck('category')->all())->toBe(['active', 'reinstate'])
        ->and(Delivery::count())->toBe(0);

    auth()->logout();
    $this->post(route('login'), ['email' => 'back@dmv.test', 'password' => 'password']);
    $this->assertAuthenticatedAs($member);
});

it('reinstates only a Member in the Resigned group, and only for member administration', function () {
    $admin = historyMemberAdmin();
    $current = Member::factory()->create(['category' => Category::Active]);
    $resigned = Member::factory()->create(['category' => Category::Resigned]);

    $this->actingAs($admin)->post(route('members.reinstate', $current))->assertForbidden();
    $this->actingAs(Member::factory()->create())->post(route('members.reinstate', $resigned))->assertForbidden();

    expect(CategoryChange::count())->toBe(0);
});

it('assigns the Category only through the ChangeCategory action', function () {
    $writers = [];

    foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
        $source = $file->getContents();

        if (preg_match('/->category\s*=(?![=>])|[\'"]category[\'"]\s*=>\s*Category::(?!class)|forceFill\(\[[^\]]*[\'"]category[\'"]/s', $source)) {
            $writers[] = $file->getRelativePathname();
        }
    }

    expect($writers)->toBe(['Actions/ChangeCategory.php']);
});
