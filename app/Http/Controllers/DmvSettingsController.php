<?php

namespace App\Http\Controllers;

use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Http\Requests\ArchiveGroupRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupStructureRequest;
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

        $ordered = $this->orderedGroups();

        return Inertia::render('officer/settings/Groups', [
            ...$this->shell(),
            'tree' => $this->tree($ordered),
            'parentOptions' => $this->parentOptions($ordered),
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
     * Create a Group (#842, ADR-0036 §4) from four fields; everything else starts at its
     * default: active, public listing, display order 0, no logo, not time-boxed, every
     * capability and collection flag off, hours multiplier 1. Lands on its structure form.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $group = Group::create([
            ...$request->validated(),
            'lifecycle_state' => LifecycleState::Active,
            'listing_visibility' => ListingVisibility::Public,
            'display_order' => 0,
            'logo_key' => null,
            'time_boxed' => false,
            'has_meetings' => false,
            'has_documents' => false,
            'has_scheduling' => false,
            'has_vetting' => false,
            'has_announcements' => false,
            'has_bookings' => false,
            'collects_visitor_count' => false,
            'collects_extra_interactions' => false,
            'collects_visitor_provenance' => false,
            'visitor_figures_await_booking' => false,
            'hours_multiplier' => 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('officer.settings.group', ['group' => $group->slug]);
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

    /**
     * One Group's structure form (#843, ADR-0036 §5). The root and Containers have no form
     * (ADR-0036 §3): their addresses answer 404 here, as the tree never links them. An
     * archived Group still opens, read-only; its h1 carries the Archived or Ended badge.
     */
    public function group(Group $group): Response
    {
        Gate::authorize('administer-groups');
        abort_unless(self::editable($group), 404);

        $excluded = $group->descendants()->pluck('id')->push($group->id)->all();

        return Inertia::render('officer/settings/Group', [
            ...$this->shell(),
            'group' => [
                'id' => $group->id,
                'slug' => $group->slug,
                'name' => $group->name,
                'parent_id' => $group->parent_id,
                'kind' => $group->kind->value,
                'listing_visibility' => $group->listing_visibility->value,
                'display_order' => $group->display_order,
                'logo_key' => $group->logo_key?->value,
                'time_boxed' => $group->time_boxed,
                'start_date' => $group->start_date?->toDateString(),
                'end_date' => $group->end_date?->toDateString(),
                ...collect(self::FLAGS)->mapWithKeys(fn (string $flag) => [$flag => (bool) $group->{$flag}])->all(),
                'hours_multiplier' => $group->hours_multiplier,
                'archived' => $group->lifecycle_state === LifecycleState::Archived,
                'endedOn' => $this->endedOn($group),
            ],
            'parents' => array_values(array_filter(
                $this->parentOptions($this->orderedGroups()),
                fn (array $option) => ! in_array($option['id'], $excluded, true),
            )),
            'kinds' => array_column(Kind::cases(), 'value'),
            'visibilities' => array_column(ListingVisibility::cases(), 'value'),
            'logos' => array_column(GroupLogo::cases(), 'value'),
            'lastChanged' => $this->lastChanged($group),
        ]);
    }

    /**
     * Save a Group's structure (#843, ADR-0036 §5). The request has already refused the root,
     * Containers and archived Groups, and never carries the slug. A Group just made a
     * Container has no form any more, so the browser lands on the tree instead.
     */
    public function update(UpdateGroupStructureRequest $request, Group $group): RedirectResponse
    {
        $group->update([
            ...$request->structure(),
            'updated_by' => $request->user()->id,
        ]);

        return self::editable($group) ? back() : redirect()->route('officer.settings');
    }

    /**
     * Archive a Group (#845, ADR-0036 §2): it leaves the rail and refuses writes, its pages
     * still open read-only. The request has refused the root, Containers and stewardship holders.
     */
    public function archive(ArchiveGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update(['lifecycle_state' => LifecycleState::Archived, 'updated_by' => $request->user()->id]);

        return back();
    }

    /** Unarchive a Group (#845): back on the rail, writable again. */
    public function unarchive(ArchiveGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update(['lifecycle_state' => LifecycleState::Active, 'updated_by' => $request->user()->id]);

        return back();
    }

    /** The structure form's on/off flags: six capabilities, then the visitor-figure collection. */
    public const FLAGS = [
        'has_meetings', 'has_documents', 'has_scheduling', 'has_vetting', 'has_announcements', 'has_bookings',
        'collects_visitor_count', 'collects_extra_interactions', 'collects_visitor_provenance', 'visitor_figures_await_booking',
    ];

    /** Whether a Group has a structure form here: every Group but the root and Containers. */
    public static function editable(Group $group): bool
    {
        return ! $group->isRoot() && $group->kind !== Kind::Container;
    }

    /**
     * The structure form's footer: who last saved it and when. Null for seeded Groups.
     *
     * @return array{name: string, date: string}|null
     */
    private function lastChanged(Group $group): ?array
    {
        $editor = $group->updatedBy;

        return $editor === null ? null : [
            'name' => $editor->fullName(),
            'date' => $group->updated_at->toDateString(),
        ];
    }

    /** A lapsed time-boxed Group's end date (the Ended badge); null while it runs or when not boxed. */
    private function endedOn(Group $group): ?string
    {
        $expired = $group->time_boxed && $group->end_date !== null && $group->end_date->startOfDay()->lt(now()->startOfDay());

        return $expired ? $group->end_date->toDateString() : null;
    }

    /**
     * One row per Group, in tree order (the rail's order). Archived and expired Groups stay
     * under their parent, marked. The root and Containers carry no
     * structure form here (ADR-0036 §3), so they get no href.
     *
     * @param  list<array{Group, int}>  $ordered
     * @return list<array{id: int, slug: string, name: string, depth: int, kind: string, archived: bool, endedOn: string|null, editable: bool, href: string|null}>
     */
    private function tree(array $ordered): array
    {
        return array_map(fn (array $entry) => $this->treeRow(...$entry), $ordered);
    }

    /**
     * The Create dialog's parent choices (#842): every active Group, the root included, in
     * tree order with its depth. Archived and expired Groups take no new children.
     *
     * @param  list<array{Group, int}>  $ordered
     * @return list<array{id: int, name: string, depth: int}>
     */
    private function parentOptions(array $ordered): array
    {
        return array_values(array_map(
            fn (array $entry) => ['id' => $entry[0]->id, 'name' => $entry[0]->name, 'depth' => $entry[1]],
            array_filter($ordered, fn (array $entry) => $entry[0]->isActive()),
        ));
    }

    /**
     * Every Group with its depth, in tree order: each parentless Group, then its children
     * depth-first, siblings by `display_order` then name.
     *
     * @return list<array{Group, int}>
     */
    private function orderedGroups(): array
    {
        $byParent = Group::query()->get()->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $ordered = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$ordered, $byParent): void {
            $siblings = ($byParent->get($parentId) ?? collect())
                ->sortBy([['display_order', 'asc'], ['name', 'asc']]);

            foreach ($siblings as $group) {
                $ordered[] = [$group, $depth];
                $walk($group->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $ordered;
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
        foreach ($this->orderedGroups() as [$group, $depth]) {
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
        $editable = self::editable($group);

        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'name' => $group->name,
            'depth' => $depth,
            'kind' => $group->kind->value,
            'archived' => $group->lifecycle_state === LifecycleState::Archived,
            'endedOn' => $this->endedOn($group),
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
