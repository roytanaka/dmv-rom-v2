<?php

namespace App\Http\Controllers;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Http\Requests\UpdateStewardshipRequest;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DMV Settings (#841, PRD #836, ADR-0036 §3): the super-tier's page for the organisation's
 * Group structure and Stewardships. Two sections, each a route: the Groups tree and the
 * Stewardships cards; plus one Group's structure form.
 *
 * Every method authorizes `administer-groups` first. The gate denies everyone; only the
 * super-tier Gate::before short-circuit grants it. A Chair, a Records Member or any other
 * Group role buys nothing here.
 */
class DmvSettingsController extends Controller
{
    /**
     * The stewardships the page shows. Statistics and website stay hidden until a feature
     * reads them (ADR-0036 §7).
     */
    public const LIVE_STEWARDSHIPS = [StewardshipFunction::MemberAdmin, StewardshipFunction::OrgMail];

    /** The Groups section: the whole org tree, archived Groups included. */
    public function groups(): Response
    {
        Gate::authorize('administer-groups');

        return Inertia::render('officer/settings/Groups', [
            ...$this->shell(),
            'tree' => $this->tree(),
        ]);
    }

    /**
     * The Stewardships section (#846, ADR-0036 §7): one card per live stewardship with its
     * holders and last change, and the active Groups a holder may be. Statistics and website
     * have no reader yet, so they are not sent.
     */
    public function stewardships(Request $request): Response
    {
        Gate::authorize('administer-groups');

        return Inertia::render('officer/settings/Stewardships', [
            ...$this->shell(),
            'stewardships' => fn () => collect(self::LIVE_STEWARDSHIPS)
                ->mapWithKeys(fn (StewardshipFunction $function) => [$function->value => $this->stewardshipCard($function)])
                ->all(),
            'groups' => fn () => $this->stewardshipChoices(),
            // The page reloads only this prop with the draft in the query string
            // (`?member_admin=ID&org_mail[]=ID`); without a draft there is nothing to preview.
            'preview' => fn () => $request->hasAny(['member_admin', 'org_mail']) ? $this->stewardshipPreview($request) : null,
        ]);
    }

    /**
     * Reassign one stewardship (#846): its holder rows become exactly the chosen Groups.
     * Authority follows on the next request, since every reader resolves holders live.
     * A new row records its creator; every row written records the acting Member as its
     * last writer (ADR-0036 §8).
     */
    public function updateStewardship(UpdateStewardshipRequest $request): RedirectResponse
    {
        $function = $request->stewardship();
        $holderIds = $request->holderIds();
        $actor = $request->user();

        DB::transaction(function () use ($function, $holderIds, $actor) {
            GroupStewardship::query()
                ->where('function', $function)
                ->whereNotIn('group_id', $holderIds)
                ->delete();

            foreach ($holderIds as $groupId) {
                $row = GroupStewardship::query()->firstOrNew(['group_id' => $groupId, 'function' => $function]);
                if (! $row->exists) {
                    $row->created_by = $actor->id;
                }
                $row->updated_by = $actor->id;
                // A re-save of an unchanged holder is still a change of record: stamp it.
                $row->touch();
            }
        });

        return back();
    }

    /** One Group's structure form. Its fields land with #843; the route exists so the tree can link. */
    public function group(Group $group): Response
    {
        Gate::authorize('administer-groups');

        return Inertia::render('officer/settings/Group', [
            ...$this->shell(),
            'group' => ['id' => $group->id, 'slug' => $group->slug, 'name' => $group->name],
        ]);
    }

    /**
     * Every Group, flattened in display order: each parentless Group, then its children
     * depth-first, siblings by `display_order` then name (the rail's order). Archived and
     * expired Groups stay under their parent, marked. The root and Containers carry no
     * structure form here (ADR-0036 §3), so they get no href.
     *
     * @return list<array{id: int, slug: string, name: string, depth: int, kind: string, archived: bool, endedOn: string|null, editable: bool, href: string|null}>
     */
    private function tree(): array
    {
        return array_map(fn (array $node) => $this->treeRow($node[0], $node[1]), $this->treeOrder());
    }

    /**
     * Every Group with its depth, in tree order: each parentless Group, then its children
     * depth-first, siblings by `display_order` then name (the rail's order).
     *
     * @return list<array{0: Group, 1: int}>
     */
    private function treeOrder(): array
    {
        $byParent = Group::query()->get()->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $nodes = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$nodes, $byParent): void {
            $siblings = ($byParent->get($parentId) ?? collect())
                ->sortBy([['display_order', 'asc'], ['name', 'asc']]);

            foreach ($siblings as $group) {
                $nodes[] = [$group, $depth];
                $walk($group->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $nodes;
    }

    /**
     * One stewardship's card: its holders by name, and the most recent authored write
     * (ADR-0036 §8). Seeded rows carry no author, so a never-edited stewardship has no
     * "last changed" line.
     *
     * @return array{holders: list<array{id: int, name: string}>, updatedBy: string|null, updatedAt: string|null}
     */
    private function stewardshipCard(StewardshipFunction $function): array
    {
        $rows = GroupStewardship::query()
            ->where('function', $function)
            ->with(['group', 'updatedBy'])
            ->get();

        $last = $rows->whereNotNull('updated_by')->sortByDesc('updated_at')->first();

        return [
            'holders' => $rows
                ->map(fn (GroupStewardship $row) => ['id' => $row->group->id, 'name' => $row->group->name])
                ->sortBy('name')
                ->values()
                ->all(),
            'updatedBy' => $last?->updatedBy?->fullName(),
            'updatedAt' => $last?->updated_at?->toDateString(),
        ];
    }

    /**
     * Who gains and who loses each stewardship's authority if the draft were saved. Authority
     * is membership, any status, in a holder Group: the same reading as
     * `Member::hasMemberAdminAuthority()` and `Member::isOrgWideSender()`. A Chair of an active
     * Group sends org-wide whatever the stewardship says, so org-wide sender leaves them out.
     * A query string drops an empty array, so an absent `org_mail` is a draft of no holders.
     *
     * @return array<string, array{gain: list<array{id: int, name: string}>, lose: list<array{id: int, name: string}>}>
     */
    private function stewardshipPreview(Request $request): array
    {
        $saved = fn (StewardshipFunction $function) => GroupStewardship::query()
            ->where('function', $function)->pluck('group_id')->all();

        $draftMemberAdmin = $request->filled('member_admin')
            ? [(int) $request->query('member_admin')]
            : $saved(StewardshipFunction::MemberAdmin);
        $draftOrgMail = array_map('intval', (array) $request->query('org_mail', []));

        $chairs = Member::query()
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->whereHas('roles', fn (Builder $role) => $role->where('role', Role::Chair))
                ->whereHas('group', fn (Builder $group) => $group->active()))
            ->pluck('id');

        return [
            StewardshipFunction::MemberAdmin->value => $this->authorityDiff($saved(StewardshipFunction::MemberAdmin), $draftMemberAdmin, collect()),
            StewardshipFunction::OrgMail->value => $this->authorityDiff($saved(StewardshipFunction::OrgMail), $draftOrgMail, $chairs),
        ];
    }

    /**
     * Members of the draft holders not in the saved ones (gain), and the reverse (lose),
     * each sorted by surname. Members in `$unaffected` carry the authority either way.
     *
     * @param  list<int>  $savedIds
     * @param  list<int>  $draftIds
     * @param  Collection<int, int>  $unaffected
     * @return array{gain: list<array{id: int, name: string}>, lose: list<array{id: int, name: string}>}
     */
    private function authorityDiff(array $savedIds, array $draftIds, Collection $unaffected): array
    {
        $membersOf = fn (array $groupIds) => Member::query()
            ->whereHas('memberships', fn (Builder $membership) => $membership->whereIn('group_id', $groupIds))
            ->get()
            ->keyBy('id');

        $before = $membersOf($savedIds);
        $after = $membersOf($draftIds);

        $rows = fn (Collection $members) => $members
            ->reject(fn (Member $member) => $unaffected->contains($member->id))
            ->sortBy(fn (Member $member) => $member->surnameKey())
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->fullName()])
            ->values()
            ->all();

        return [
            'gain' => $rows($after->diffKeys($before)),
            'lose' => $rows($before->diffKeys($after)),
        ];
    }

    /**
     * The Groups a stewardship may go to: active ones, in tree order, the root left out (it
     * holds nothing; ADR-0036 §7). Depth counts from the root's children.
     *
     * @return list<array{id: int, name: string, depth: int}>
     */
    private function stewardshipChoices(): array
    {
        $choices = [];
        foreach ($this->treeOrder() as [$group, $depth]) {
            if (! $group->isRoot() && $group->isActive()) {
                $choices[] = ['id' => $group->id, 'name' => $group->name, 'depth' => max(0, $depth - 1)];
            }
        }

        return $choices;
    }

    /**
     * One tree row.
     *
     * @return array{id: int, slug: string, name: string, depth: int, kind: string, archived: bool, endedOn: string|null, editable: bool, href: string|null}
     */
    private function treeRow(Group $group, int $depth): array
    {
        $editable = ! $group->isRoot() && $group->kind !== Kind::Container;
        $expired = $group->time_boxed && $group->end_date !== null && $group->end_date->startOfDay()->lt(now()->startOfDay());

        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'name' => $group->name,
            'depth' => $depth,
            'kind' => $group->kind->value,
            'archived' => $group->lifecycle_state === LifecycleState::Archived,
            'endedOn' => $expired ? $group->end_date->toDateString() : null,
            'editable' => $editable,
            'href' => $editable ? route('officer.settings.group', ['group' => $group->slug], false) : null,
        ];
    }

    /**
     * Props every section shares: the two section hrefs, localized per ADR-0008 so the
     * section switch and the breadcrumb land in the active locale.
     *
     * @return array<string, string>
     */
    private function shell(): array
    {
        return [
            'groupsHref' => route('officer.settings', [], false),
            'stewardshipsHref' => route('officer.settings.stewardships', [], false),
        ];
    }
}
