<?php

use App\Enums\AudienceKey;
use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Support\Audiences\AudienceContext;
use App\Support\Audiences\AudienceResolver;
use Database\Seeders\OrgTreeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/*
 * The Audience resolver (#484, ADR-0024 §5) resolved against the spine fixture:
 * every Audience resolving to its documented set, the picker rule, the no-email
 * skip, and de-duplication — asserted through the resolver's public API.
 */

function resolver(): AudienceResolver
{
    return app(AudienceResolver::class);
}

function member(string $email): Member
{
    return Member::where('email', $email)->firstOrFail();
}

function emails(iterable $members): array
{
    return collect($members)->map(fn (Member $m) => $m->email)->sort()->values()->all();
}

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);
    $this->docents = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
});

it('answers whether a Member is an org-wide sender', function () {
    // A member of a Group that stewards org_mail (Records, Executive), or a Chair of
    // any active Group, is a sender; a plain officer of neither kind is not.
    expect(member('clerk@dmv.test')->isOrgWideSender())->toBeTrue()
        ->and(member(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL)->isOrgWideSender())->toBeTrue()
        ->and(member('president@dmv.test')->isOrgWideSender())->toBeTrue()
        ->and(member('secretary@dmv.test')->isOrgWideSender())->toBeFalse()
        ->and(member(OrgTreeSeeder::SCHEDULER_EMAIL)->isOrgWideSender())->toBeFalse();
});

it('does not treat a Chair of an archived Group as an org-wide sender', function () {
    $project = Group::where('slug', OrgTreeSeeder::PROJECT)->firstOrFail();
    $chair = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $project->id,
        'member_id' => $chair->id,
    ])->roles()->create(['role' => Role::Chair]);

    expect($chair->fresh()->isOrgWideSender())->toBeFalse();
});

it('resolves the Whole Group to its roster in present standing', function () {
    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
    );

    // Scheduler (Full) and Trainee sign up; the on-leave membership is dropped.
    expect(emails($resolved->recipients))
        ->toBe(emails([member(OrgTreeSeeder::SCHEDULER_EMAIL), member('trainee@dmv.test')]));
});

it('resolves the Whole Group and on leave to add the LOA membership', function () {
    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroupOnLeave,
    );

    expect(emails($resolved->recipients))->toBe(emails([
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        member('trainee@dmv.test'),
        member('onleave@dmv.test'),
    ]));
});

it('resolves One status to the memberships with that standing', function () {
    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::OneStatus,
        MembershipStatus::Trainee->value,
    );

    expect(emails($resolved->recipients))->toBe(emails([member('trainee@dmv.test')]));
});

it('resolves the Group officers to memberships holding any Role', function () {
    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::GroupOfficers,
    );

    expect(emails($resolved->recipients))->toBe(emails([member(OrgTreeSeeder::SCHEDULER_EMAIL)]));
});

it('resolves Active to the Full memberships only', function () {
    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::GroupActive,
    );

    // The Scheduler is Full; the Trainee and the on-leave membership are not.
    expect(emails($resolved->recipients))->toBe(emails([member(OrgTreeSeeder::SCHEDULER_EMAIL)]));
});

it('resolves a child Group roster for a parent officer', function () {
    $cohort = Group::where('slug', OrgTreeSeeder::COHORT)->firstOrFail();
    $cohortMember = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $cohort->id,
        'member_id' => $cohortMember->id,
    ]);

    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL), // a Docents officer; the cohort is its child
        AudienceContext::group($this->docents),
        AudienceKey::ChildRoster,
        $cohort->slug,
    );

    expect(emails($resolved->recipients))->toBe(emails([$cohortMember]))
        ->and($resolved->label)->toBe($cohort->name);
});

it('resolves Active and Provisional across the Directory', function () {
    $provisional = Member::factory()->category(Category::Provisional)->create();
    $withdrawn = Member::factory()->category(Category::Withdrawn)->create();

    $resolved = resolver()->resolve(
        member('clerk@dmv.test'),
        AudienceContext::directory(),
        AudienceKey::ActiveProvisional,
    );

    $ids = $resolved->recipients->pluck('id');
    expect($ids)->toContain($provisional->id, member('clerk@dmv.test')->id)
        ->and($ids)->not->toContain($withdrawn->id);
});

it('resolves One Category to that Category alone', function () {
    $provisional = Member::factory()->category(Category::Provisional)->create();

    $resolved = resolver()->resolve(
        member('clerk@dmv.test'),
        AudienceContext::directory(),
        AudienceKey::OneCategory,
        Category::Provisional->value,
    );

    expect($resolved->recipients->pluck('id')->all())->toBe([$provisional->id]);
});

it('resolves Sign-ups on a Schedule to holders of live Shifts', function () {
    $schedule = $this->docents->schedules()->firstOrFail();
    $shift = $schedule->shifts()->create([
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(13, 0),
        'capacity' => 3,
    ]);
    $shift->signUps()->create(['member_id' => member('trainee@dmv.test')->id]);

    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::schedule($schedule),
        AudienceKey::SignUpsSchedule,
    );

    expect(emails($resolved->recipients))->toBe(emails([member('trainee@dmv.test')]));
});

it('resolves the Board of Directors to the Executive roster', function () {
    $resolved = resolver()->resolve(
        member('president@dmv.test'),
        AudienceContext::directory(),
        AudienceKey::BoardOfDirectors,
    );

    expect(emails($resolved->recipients))->toBe(emails([member(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL)]));
});

it('separates Committee Chairs from All Chairs by depth', function () {
    // The Executive Chair is a top-level Chair; the President chairs the root, which
    // is not a committee, so appears in All Chairs only.
    $committee = resolver()->resolve(
        member('president@dmv.test'),
        AudienceContext::directory(),
        AudienceKey::CommitteeChairs,
    );
    $all = resolver()->resolve(
        member('president@dmv.test'),
        AudienceContext::directory(),
        AudienceKey::AllChairs,
    );

    expect(emails($committee->recipients))->toBe(emails([member(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL)]))
        ->and(emails($all->recipients))->toBe(emails([
            member('president@dmv.test'),
            member(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL),
        ]));
});

it('returns flagged Members as skipped, never as recipients', function () {
    $trainee = member('trainee@dmv.test');
    $trainee->forceFill(['no_email' => true])->save();

    $resolved = resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
    );

    expect(emails($resolved->recipients))->toBe(emails([member(OrgTreeSeeder::SCHEDULER_EMAIL)]))
        ->and(emails($resolved->skipped))->toBe(emails([$trainee]));
});

it('de-duplicates recipients by Member, not by address', function () {
    // The dedup key is the Member id: a Member reachable by two paths (here, in the
    // Whole Group and added again by hand) appears exactly once. Two *distinct*
    // Members are never collapsed — the resolver keys on the Member, so were two ever
    // to share an address (as legacy's shared pairs do) both would still appear.
    $scheduler = member(OrgTreeSeeder::SCHEDULER_EMAIL);
    $trainee = member('trainee@dmv.test');

    $resolved = resolver()->resolve(
        $scheduler,
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
        null,
        added: [$trainee->id], // already in the Whole Group
    );

    $ids = $resolved->recipients->pluck('id')->all();

    expect($ids)->toContain($scheduler->id, $trainee->id)
        ->and(count($ids))->toBe(count(array_unique($ids)))
        ->and(count($ids))->toBe(2);
});

it('applies removed and added edits and marks the Audience edited', function () {
    $scheduler = member(OrgTreeSeeder::SCHEDULER_EMAIL);
    $trainee = member('trainee@dmv.test');
    $onLeave = member('onleave@dmv.test'); // on the roster, not in Whole Group

    $resolved = resolver()->resolve(
        $scheduler,
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
        null,
        removed: [$trainee->id],
        added: [$onLeave->id],
    );

    expect($resolved->edited)->toBeTrue()
        ->and(emails($resolved->recipients))->toBe(emails([$scheduler, $onLeave]))
        ->and($resolved->label)->toContain('1 removed');
});

it('rejects a removed id outside the resolved Audience', function () {
    resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
        null,
        removed: [member('president@dmv.test')->id],
    );
})->throws(ValidationException::class);

it('rejects an added id outside the page roster', function () {
    resolver()->resolve(
        member(OrgTreeSeeder::SCHEDULER_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
        null,
        added: [member('president@dmv.test')->id],
    );
})->throws(ValidationException::class);

it('denies an actor who is not a member of the Group', function () {
    // The Executive Chair is a Chair elsewhere but not a member of Docents.
    resolver()->resolve(
        member(OrgTreeSeeder::EXECUTIVE_CHAIR_EMAIL),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
    );
})->throws(AuthorizationException::class);

it('denies a plain member the officer Audience', function () {
    $trainee = member('trainee@dmv.test'); // a member of Docents, but no Role
    resolver()->resolve($trainee, AudienceContext::group($this->docents), AudienceKey::GroupOfficers);
})->throws(AuthorizationException::class);

it('denies a non-sender the org-wide Audience but allows a sender', function () {
    $context = AudienceContext::directory();

    // A plain member cannot pick All Members.
    expect(fn () => resolver()->resolve(member('trainee@dmv.test'), $context, AudienceKey::AllMembers))
        ->toThrow(AuthorizationException::class);

    // Records (org_mail steward) can.
    $resolved = resolver()->resolve(member('clerk@dmv.test'), $context, AudienceKey::AllMembers);
    expect($resolved->recipients)->not->toBeEmpty();
});

it('lets super-tier pick anything', function () {
    $resolved = resolver()->resolve(
        member('president@dmv.test'),
        AudienceContext::group($this->docents),
        AudienceKey::WholeGroup,
    );

    expect($resolved->recipients)->not->toBeEmpty();
});
