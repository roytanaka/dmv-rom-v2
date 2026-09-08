<?php

namespace App\Support\Audiences;

use App\Enums\AccessTier;
use App\Enums\AudienceKey;
use App\Enums\Category;
use App\Enums\ContextType;
use App\Enums\Kind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves every Broadcast/Direct-message Audience (ADR-0024 §5) on the server, at
 * pick time. The browser names an {@see AudienceKey} in an {@see AudienceContext};
 * it never posts a recipient list, a From alias, or an exclusion — the fix for the
 * largest legacy defect. Two entry points: {@see available()} lists the Audiences an
 * actor may pick in a context (the composer menu), and {@see resolve()} turns one
 * into its recipients, honouring the picker rule, the per-Member edits, the
 * de-duplication, and the no-email flag.
 *
 * The picker rule (§5): any member of a Group gets its roster sets; the Group's
 * officers get the officer set and a child Group's roster; a Chair or Scheduler gets
 * the Sign-up sets; any Member gets the three leadership Audiences on the Directory;
 * the remaining org-wide Audiences and the Directory hand-pick need an org-wide
 * sender ({@see Member::isOrgWideSender()}). Super-tier inherits everything.
 */
class AudienceResolver
{
    /**
     * The Audiences an actor may pick in a context, each with the label the menu
     * shows and the record stores. Parameterised Audiences (one status, one
     * category, a child roster) are expanded to one entry per value present, so the
     * menu can show each with its own count.
     *
     * @return Collection<int, Audience>
     */
    public function available(Member $actor, AudienceContext $context): Collection
    {
        return collect($this->keysFor($context->type))
            ->filter(fn (AudienceKey $key): bool => $this->canPick($actor, $context, $key))
            ->flatMap(fn (AudienceKey $key): array => array_map(
                fn (?string $parameter): Audience => new Audience(
                    $key,
                    $parameter,
                    $this->label($context, $key, $parameter),
                ),
                $this->parametersFor($context, $key),
            ))
            ->values();
    }

    /**
     * Resolve one Audience to its recipients. Enforces the picker rule (a 403 when
     * the actor may not pick it here), validates the edits (removed ids must be
     * inside the resolved Audience; added ids inside the page's roster), then returns
     * the label, the recipients de-duplicated by Member, and the Members skipped for
     * the no-email flag — checked once, here (§9).
     *
     * @param  list<int|string>  $removed
     * @param  list<int|string>  $added
     */
    public function resolve(
        Member $actor,
        AudienceContext $context,
        AudienceKey $key,
        ?string $parameter = null,
        array $removed = [],
        array $added = [],
    ): ResolvedAudience {
        if (! in_array($key, $this->keysFor($context->type), true)) {
            throw new AuthorizationException('That audience is not available from here.');
        }

        if (! $this->canPick($actor, $context, $key)) {
            throw new AuthorizationException('You may not send to that audience.');
        }

        $base = $this->base($context, $key, $parameter)
            ->unique(fn (Member $member): int => $member->getKey())
            ->values();

        $roster = $this->pageRoster($context)
            ->unique(fn (Member $member): int => $member->getKey())
            ->keyBy(fn (Member $member): int => $member->getKey());

        $removedIds = collect($removed)->map(fn ($id): int => (int) $id)->unique()->values();
        $addedIds = collect($added)->map(fn ($id): int => (int) $id)->unique()->values();

        $this->assertEditsInBounds($base, $roster, $removedIds, $addedIds);

        $recipients = $base
            ->reject(fn (Member $member): bool => $removedIds->contains($member->getKey()))
            ->concat($addedIds->map(fn (int $id): Member => $roster->get($id)))
            ->unique(fn (Member $member): int => $member->getKey())
            ->values();

        [$skipped, $reachable] = $recipients->partition(
            fn (Member $member): bool => (bool) $member->no_email,
        );

        return new ResolvedAudience(
            $this->editedLabel($context, $key, $parameter, $removedIds->count()),
            $removedIds->isNotEmpty() || $addedIds->isNotEmpty(),
            $reachable->values(),
            $skipped->values(),
        );
    }

    /**
     * The structural catalog of Audience keys a context offers, before the picker
     * rule narrows it to a given actor. A Schedule also offers the owning Group's
     * Audiences (§6.3); a Shift offers its Sign-ups alone.
     *
     * @return list<AudienceKey>
     */
    private function keysFor(ContextType $type): array
    {
        $group = [
            AudienceKey::WholeGroup,
            AudienceKey::WholeGroupOnLeave,
            AudienceKey::OneStatus,
            AudienceKey::GroupActive,
            AudienceKey::GroupOfficers,
            AudienceKey::ChildRoster,
            AudienceKey::HandPicked,
        ];

        return match ($type) {
            ContextType::Group => $group,
            ContextType::Schedule => [AudienceKey::SignUpsSchedule, ...$group],
            ContextType::Shift => [AudienceKey::SignUpsShift],
            ContextType::Directory => [
                AudienceKey::AllMembers,
                AudienceKey::AllMembersOnLeave,
                AudienceKey::ActiveProvisional,
                AudienceKey::OneCategory,
                AudienceKey::BoardOfDirectors,
                AudienceKey::CommitteeChairs,
                AudienceKey::AllChairs,
                AudienceKey::HandPicked,
            ],
            ContextType::Member => [AudienceKey::OneMember],
        };
    }

    /**
     * Whether the actor may pick the Audience in the context — the picker rule.
     * Super-tier inherits everything.
     */
    private function canPick(Member $actor, AudienceContext $context, AudienceKey $key): bool
    {
        if ($actor->isAllDmv()) {
            return true;
        }

        return match ($context->type) {
            ContextType::Directory => match ($key) {
                AudienceKey::BoardOfDirectors,
                AudienceKey::CommitteeChairs,
                AudienceKey::AllChairs => true,
                default => $actor->isOrgWideSender(),
            },
            ContextType::Member => true,
            // Group, Schedule, and Shift are all scoped to a Group.
            default => match ($key) {
                AudienceKey::GroupOfficers,
                AudienceKey::ChildRoster => $actor->isOfficerOf($context->owningGroup()),
                AudienceKey::SignUpsSchedule,
                AudienceKey::SignUpsShift => $actor->canActAs(Role::Scheduler, $context->owningGroup()),
                default => $actor->membershipIn($context->owningGroup()) !== null,
            },
        };
    }

    /**
     * The base recipient set an Audience resolves to, before edits and the no-email
     * skip. Duplicates are removed by the caller.
     *
     * @return Collection<int, Member>
     */
    private function base(AudienceContext $context, AudienceKey $key, ?string $parameter): Collection
    {
        return match ($key) {
            AudienceKey::WholeGroup => $this->rosterMembers($context->owningGroup(), onLeave: false),
            AudienceKey::WholeGroupOnLeave => $this->rosterMembers($context->owningGroup(), onLeave: true),
            AudienceKey::GroupActive => $this->membersWithStatus($context->owningGroup(), MembershipStatus::Full),
            AudienceKey::OneStatus => $this->membersWithStatus($context->owningGroup(), $this->status($parameter)),
            AudienceKey::GroupOfficers => $this->officerMembers($context->owningGroup()),
            AudienceKey::ChildRoster => $this->rosterMembers($this->requireChild($context->owningGroup(), $parameter), onLeave: false),
            AudienceKey::SignUpsSchedule => $this->scheduleSignUps($context),
            AudienceKey::SignUpsShift => $this->shiftSignUps($context),
            AudienceKey::AllMembers => $this->membersInCategories($this->currentCategories(withLoa: false)),
            AudienceKey::AllMembersOnLeave => $this->membersInCategories($this->currentCategories(withLoa: true)),
            AudienceKey::ActiveProvisional => $this->membersInCategories([Category::Active, Category::Provisional]),
            AudienceKey::OneCategory => $this->membersInCategories([$this->category($parameter)]),
            AudienceKey::BoardOfDirectors => $this->boardOfDirectors(),
            AudienceKey::CommitteeChairs => $this->chairMembers(topLevelOnly: true),
            AudienceKey::AllChairs => $this->chairMembers(topLevelOnly: false),
            AudienceKey::OneMember => collect([$context->subject]),
            AudienceKey::HandPicked => collect(),
        };
    }

    /**
     * The set an added id must belong to — the page's roster (§5). For a Group (and
     * a Schedule/Shift scoped to one) that is the Group's whole membership; on the
     * Directory, any listed Member; for a Direct message, the one recipient.
     *
     * @return Collection<int, Member>
     */
    private function pageRoster(AudienceContext $context): Collection
    {
        return match ($context->type) {
            ContextType::Directory => Member::query()->inDirectory()->get(),
            ContextType::Member => collect([$context->subject]),
            default => $context->owningGroup()->memberships()->with('member')->get()
                ->map(fn (GroupMember $membership): Member => $membership->member),
        };
    }

    /**
     * The Group's memberships in present standing (§5) — `canSignUp()` true — as
     * Members; with `onLeave`, the LOA memberships join them.
     *
     * @return Collection<int, Member>
     */
    private function rosterMembers(Group $group, bool $onLeave): Collection
    {
        return $group->memberships()->with('member')->get()
            ->filter(fn (GroupMember $membership): bool => $membership->status->canSignUp()
                || ($onLeave && $membership->status === MembershipStatus::Loa))
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->values();
    }

    /**
     * The Group's memberships with exactly the given within-Group standing.
     *
     * @return Collection<int, Member>
     */
    private function membersWithStatus(Group $group, MembershipStatus $status): Collection
    {
        return $group->memberships()->with('member')->get()
            ->filter(fn (GroupMember $membership): bool => $membership->status === $status)
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->values();
    }

    /**
     * The Group's officers — memberships holding at least one Role (§5).
     *
     * @return Collection<int, Member>
     */
    private function officerMembers(Group $group): Collection
    {
        return $group->memberships()->with(['member', 'roles'])->get()
            ->filter(fn (GroupMember $membership): bool => $membership->roles->isNotEmpty())
            ->map(fn (GroupMember $membership): Member => $membership->member)
            ->values();
    }

    /**
     * Distinct Members with a Sign-up on any Shift of the context's Schedule that
     * ends now or later (§5).
     *
     * @return Collection<int, Member>
     */
    private function scheduleSignUps(AudienceContext $context): Collection
    {
        return $context->subject->shifts()
            ->where('ends_at', '>=', now())
            ->with('signUps.member')
            ->get()
            ->flatMap(fn (Shift $shift): Collection => $shift->signUps
                ->map(fn (SignUp $signUp): Member => $signUp->member))
            ->values();
    }

    /**
     * Distinct Members with a Sign-up on the context's Shift (§5).
     *
     * @return Collection<int, Member>
     */
    private function shiftSignUps(AudienceContext $context): Collection
    {
        return $context->subject->signUps()->with('member')->get()
            ->map(fn (SignUp $signUp): Member => $signUp->member)
            ->values();
    }

    /**
     * The DMV Executive Group's roster in present standing — the Board of Directors
     * (§5). Empty when no Executive Group is seeded.
     *
     * @return Collection<int, Member>
     */
    private function boardOfDirectors(): Collection
    {
        $executive = Group::executive();

        return $executive !== null ? $this->rosterMembers($executive, onLeave: false) : collect();
    }

    /**
     * The Chair-role holders across active Groups (§5): every active Group at any
     * depth for All Chairs; only the top-level Groups — those whose nearest
     * non-container ancestor is the root — for Committee Chairs.
     *
     * @return Collection<int, Member>
     */
    private function chairMembers(bool $topLevelOnly): Collection
    {
        $groups = Group::query()->active()->with(['memberships.roles', 'memberships.member'])->get();

        if ($topLevelOnly) {
            $topLevel = $this->topLevelGroupIds();
            $groups = $groups->filter(fn (Group $group): bool => $topLevel->contains($group->getKey()));
        }

        return $groups
            ->flatMap(fn (Group $group): Collection => $group->memberships
                ->filter(fn (GroupMember $membership): bool => $membership->roles->contains('role', Role::Chair))
                ->map(fn (GroupMember $membership): Member => $membership->member))
            ->values();
    }

    /**
     * The ids of the top-level Groups — those a legacy top-level committee maps to:
     * a Group whose nearest non-container ancestor is the root (§5). Walked in memory
     * over one lightweight query so the ancestor climb is not N+1.
     *
     * @return Collection<int, int>
     */
    private function topLevelGroupIds(): Collection
    {
        $all = Group::query()->get(['id', 'parent_id', 'kind', 'slug'])->keyBy('id');

        return $all
            ->filter(function (Group $group) use ($all): bool {
                $ancestor = $group->parent_id !== null ? $all->get($group->parent_id) : null;

                while ($ancestor !== null) {
                    if ($ancestor->kind !== Kind::Container) {
                        return $ancestor->slug === Group::ROOT_SLUG;
                    }

                    $ancestor = $ancestor->parent_id !== null ? $all->get($ancestor->parent_id) : null;
                }

                return false;
            })
            ->keys();
    }

    /**
     * Members whose DMV-wide Category is one of the given set.
     *
     * @param  list<Category>  $categories
     * @return Collection<int, Member>
     */
    private function membersInCategories(array $categories): Collection
    {
        return Member::query()
            ->whereIn('category', array_map(fn (Category $category): string => $category->value, $categories))
            ->get();
    }

    /**
     * The current DMV-wide Categories — those with an access tier (§5). Without LOA
     * for "All Members"; with it for "All Members and on leave", the six current
     * Categories.
     *
     * @return list<Category>
     */
    private function currentCategories(bool $withLoa): array
    {
        return array_values(array_filter(
            Category::cases(),
            fn (Category $category): bool => $category->accessTier() !== AccessTier::None
                && ($withLoa || $category !== Category::Loa),
        ));
    }

    /**
     * The parameter values to expand a key into for the menu — the distinct statuses
     * present in the Group, the current Categories present among Members, or the
     * Group's children. Non-parameterised keys expand to a single null.
     *
     * @return list<?string>
     */
    private function parametersFor(AudienceContext $context, AudienceKey $key): array
    {
        return match ($key) {
            AudienceKey::OneStatus => $context->owningGroup()->memberships()->get()
                ->map(fn (GroupMember $membership): string => $membership->status->value)
                ->unique()->sort()->values()->all(),
            AudienceKey::OneCategory => Member::query()->get(['category'])
                ->map(fn (Member $member): Category => $member->category)
                ->unique()
                ->filter(fn (Category $category): bool => $category->accessTier() !== AccessTier::None)
                ->map(fn (Category $category): string => $category->value)
                ->sort()->values()->all(),
            AudienceKey::ChildRoster => $context->owningGroup()->children()->orderBy('display_order')
                ->pluck('slug')->all(),
            default => [null],
        };
    }

    /**
     * The label the menu shows and the record stores. Chrome for the fixed
     * Audiences; content — passed through as authored — for the parameterised ones.
     */
    private function label(AudienceContext $context, AudienceKey $key, ?string $parameter): string
    {
        return match ($key) {
            AudienceKey::OneStatus => (string) __('group.standing.'.$this->status($parameter)->value),
            AudienceKey::OneCategory => (string) __('member.standing.'.$this->category($parameter)->value),
            AudienceKey::ChildRoster => $this->requireChild($context->owningGroup(), $parameter)->name,
            AudienceKey::OneMember => $this->memberName($context->subject),
            AudienceKey::SignUpsSchedule => (string) __('audience.sign_ups_schedule', ['schedule' => $context->subject->name]),
            default => (string) __('audience.'.$key->value),
        };
    }

    /**
     * The stored label with the "N removed" suffix when the picker removed anyone
     * (§6); the plain label otherwise.
     */
    private function editedLabel(AudienceContext $context, AudienceKey $key, ?string $parameter, int $removed): string
    {
        $label = $this->label($context, $key, $parameter);

        return $removed > 0
            ? (string) __('audience.edited', ['label' => $label, 'count' => $removed])
            : $label;
    }

    /**
     * Reject edits that reach outside their bounds (§5): a removed id not in the
     * resolved Audience, or an added id not on the page's roster.
     *
     * @param  Collection<int, Member>  $base
     * @param  Collection<int, Member>  $roster
     * @param  Collection<int, int>  $removedIds
     * @param  Collection<int, int>  $addedIds
     */
    private function assertEditsInBounds(Collection $base, Collection $roster, Collection $removedIds, Collection $addedIds): void
    {
        $baseIds = $base->map(fn (Member $member): int => $member->getKey());

        if ($removedIds->diff($baseIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'removed' => __('validation.in', ['attribute' => 'removed']),
            ]);
        }

        if ($addedIds->diff($roster->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'added' => __('validation.in', ['attribute' => 'added']),
            ]);
        }
    }

    /**
     * The child Group named by the parameter, or a 422 when the parameter names no
     * child of the context Group.
     */
    private function requireChild(Group $parent, ?string $slug): Group
    {
        $child = $parent->children()->where('slug', $slug)->first();

        if ($child === null) {
            throw ValidationException::withMessages([
                'parameter' => __('validation.exists', ['attribute' => 'parameter']),
            ]);
        }

        return $child;
    }

    /**
     * The MembershipStatus named by the parameter, or a 422 when it names none.
     */
    private function status(?string $parameter): MembershipStatus
    {
        $status = $parameter !== null ? MembershipStatus::tryFrom($parameter) : null;

        if ($status === null) {
            throw ValidationException::withMessages([
                'parameter' => __('validation.in', ['attribute' => 'parameter']),
            ]);
        }

        return $status;
    }

    /**
     * The Category named by the parameter, or a 422 when it names none.
     */
    private function category(?string $parameter): Category
    {
        $category = $parameter !== null ? Category::tryFrom($parameter) : null;

        if ($category === null) {
            throw ValidationException::withMessages([
                'parameter' => __('validation.in', ['attribute' => 'parameter']),
            ]);
        }

        return $category;
    }

    private function memberName(Member $member): string
    {
        return trim($member->first_name.' '.$member->last_name);
    }
}
