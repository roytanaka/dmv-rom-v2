<?php

namespace App\Http\Controllers;

use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Http\Requests\ArchiveGroupRequest;
use App\Http\Requests\PreviewStewardshipsRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupStructureRequest;
use App\Http\Requests\UpdateStewardshipRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Support\Groups\OrgTree;
use App\Support\Groups\OrgTreeNode;
use App\Support\Groups\ParentChoice;
use App\Support\Groups\SubGroupSlug;
use App\Support\Stewardships\StewardshipAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DMV Settings (#841, PRD #836, ADR-0036 §3): the super-tier's page for the organisation's
 * Group structure and Stewardships. Two sections, each a route: the Groups tree and the
 * Stewardships cards; plus one Group's structure form.
 *
 * Every method authorizes `administer-groups` first, itself or through its form request. The
 * gate denies everyone; only the super-tier Gate::before short-circuit grants it. A Chair, a
 * Records Member or any other Group role buys nothing here. The tree walk lives in OrgTree,
 * the stewardship preview and reassignment in StewardshipAssignment.
 */
class DmvSettingsController extends Controller
{
    /**
     * The stewardships the page shows. Statistics and website stay hidden until a feature
     * reads them (ADR-0036 §7).
     */
    public const LIVE_STEWARDSHIPS = [StewardshipFunction::MemberAdmin, StewardshipFunction::OrgMail];

    /**
     * The Groups section. The super-tier gets the whole org tree, archived Groups included. A
     * parent officer (ADR-0036 §11) gets only the subtrees below the Groups they administer:
     * each of their Groups heads its subtree as a row they cannot open, and nothing about the
     * rest of the org (no parent choices, no kinds) is sent.
     */
    public function groups(Request $request): Response
    {
        Gate::authorize('enter-dmv-settings');

        $tree = OrgTree::load();

        if (! Gate::allows('administer-groups')) {
            $subtrees = $tree->subtrees($request->user()->administeredGroups()->pluck('id')->all());
            $parents = array_values(array_filter($subtrees, fn (OrgTreeNode $node) => $node->group->isActive()));

            return Inertia::render('officer/settings/Groups', [
                ...$this->shell(),
                'scoped' => true,
                'tree' => array_map(fn (OrgTreeNode $node) => $this->treeRow($node, head: $node->depth === 0), $subtrees),
                'parentOptions' => [],
                'kinds' => [],
                // The Create sub-group dialog's one parent select (#884): each head and its
                // active descendants, in tree order, depth counted from the head.
                'subGroupParents' => array_map(
                    fn (OrgTreeNode $node) => ['id' => $node->group->id, 'name' => $node->group->name, 'depth' => $node->depth],
                    $parents,
                ),
                // Its Chair select (#885): each parent's active roster, keyed by parent id.
                'subGroupChairs' => $this->subGroupChairs(array_map(fn (OrgTreeNode $node) => $node->group->id, $parents)),
            ]);
        }

        return Inertia::render('officer/settings/Groups', [
            ...$this->shell(),
            'scoped' => false,
            'tree' => array_map(fn (OrgTreeNode $node) => $this->treeRow($node), $tree->nodes),
            // Section first, then the parent from its subtree (#850): each option names its
            // section, with its depth below it, so the client filters the second select.
            'parentOptions' => array_map(
                fn (ParentChoice $choice) => $this->parentOption($choice),
                $tree->parentChoices(),
            ),
            // A Container is made by the seeder only (#850).
            'kinds' => array_values(array_diff(array_column(Kind::cases(), 'value'), [Kind::Container->value])),
            'subGroupParents' => [],
            'subGroupChairs' => [],
        ]);
    }

    /**
     * The Members a parent officer may name Chair of a new sub-group (#885, ADR-0036 §11): the
     * active roster of each candidate parent, by surname, keyed by the parent's id. One query.
     *
     * @param  list<int>  $parentIds
     * @return array<int, list<array{id: int, first_name: string, last_name: string}>>
     */
    private function subGroupChairs(array $parentIds): array
    {
        return GroupMember::query()
            ->whereIn('group_member.group_id', $parentIds)
            ->whereIn('group_member.status', MembershipStatus::belongingValues())
            ->join('members', 'members.id', '=', 'group_member.member_id')
            ->orderBy('members.last_name')
            ->orderBy('members.first_name')
            ->get(['group_member.group_id', 'members.id', 'members.first_name', 'members.last_name'])
            ->groupBy('group_id')
            ->map(fn ($rows) => $rows->map(fn (GroupMember $row) => [
                'id' => $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
            ])->values()->all())
            ->all();
    }

    /**
     * The Stewardships section (#846, ADR-0036 §7): one card per live stewardship with its
     * holders and last change, and the active Groups a holder may be. Statistics and website
     * have no reader yet, so they are not sent.
     */
    public function stewardships(PreviewStewardshipsRequest $request): Response
    {
        return Inertia::render('officer/settings/Stewardships', [
            ...$this->shell(),
            'stewardships' => fn () => collect(self::LIVE_STEWARDSHIPS)
                ->mapWithKeys(fn (StewardshipFunction $function) => [$function->value => $this->stewardshipCard($function)])
                ->all(),
            // Depth counts from the root's children: the root holds nothing.
            'groups' => fn () => array_map(
                fn (OrgTreeNode $node) => ['id' => $node->group->id, 'name' => $node->group->name, 'depth' => max(0, $node->depth - 1)],
                OrgTree::load()->stewardshipChoices(),
            ),
            // The page reloads only this prop with the draft in the query string.
            'preview' => fn () => $request->hasDraft()
                ? StewardshipAssignment::preview($request->draftMemberAdmin(), $request->draftOrgMail())
                : null,
        ]);
    }

    /**
     * Create a Group (#842, ADR-0036 §4) from four fields; everything else starts at its
     * default: active, public listing, display order 0, no logo, not time-boxed, every
     * capability and collection flag off, hours multiplier 1. Lands on its structure form.
     * A parent officer's create (#884) takes its own path below.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $group = $request->scoped() ? $this->createSubGroup($request) : Group::create([
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
     * A parent officer's sub-group (#884, ADR-0036 §11): a name and a parent, the slug made by
     * the server. It starts as a Legacy sub-committee did: a Working group with Meetings and
     * Documents on, every other flag off, Group listing, hours multiplier 1, not time-boxed.
     * An optional Chair from the parent's roster (#885) joins it as Full with the Chair role, in
     * the same transaction: no Group is left behind if that write fails.
     */
    private function createSubGroup(StoreGroupRequest $request): Group
    {
        $parent = $request->parent();
        $name = $request->validated('name');
        $chairId = $request->validated('chair_id');

        return DB::transaction(function () use ($request, $parent, $name, $chairId) {
            $group = Group::create([
                'name' => $name,
                'slug' => SubGroupSlug::for($name, $parent),
                'parent_id' => $parent->id,
                'kind' => Kind::WorkingGroup,
                'lifecycle_state' => LifecycleState::Active,
                'listing_visibility' => ListingVisibility::Group,
                'display_order' => 0,
                'logo_key' => null,
                'time_boxed' => false,
                'has_meetings' => true,
                'has_documents' => true,
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

            if ($chairId !== null) {
                $group->memberships()
                    ->create(['member_id' => $chairId, 'status' => MembershipStatus::Full])
                    ->roles()->create(['role' => Role::Chair]);
            }

            return $group;
        });
    }

    /**
     * Reassign one stewardship (#846): its holder rows become exactly the chosen Groups.
     * Authority follows on the next request, since every reader resolves holders live.
     * A new row records its creator; every row written records the acting Member as its
     * last writer (ADR-0036 §8).
     */
    public function updateStewardship(UpdateStewardshipRequest $request): RedirectResponse
    {
        StewardshipAssignment::assign($request->stewardship(), $request->holderIds(), $request->user());

        return back();
    }

    /**
     * One Group's structure form (#843, ADR-0036 §5). The root and Containers have no form
     * (ADR-0036 §3): their addresses answer 404 here, as the tree never links them. An
     * archived Group still opens, read-only; its h1 carries the Archived or Ended badge.
     *
     * A parent officer (ADR-0036 §11) opens a Group below one they administer, never their own
     * Group, the root or a Container (403). Their form (`scoped`) is name and parent only, and
     * its parent choices are their own subtrees, each headed by a Group they administer, so
     * nothing about the rest of the org is sent.
     */
    public function group(Request $request, Group $group): Response
    {
        Gate::authorize('restructure-group', $group);
        abort_unless($group->isStructurallyEditable(), 404);

        $scoped = ! Gate::allows('administer-groups');
        $parents = $scoped
            ? OrgTree::load()->subtreeParentChoices($request->user()->administeredGroups()->pluck('id')->all(), $group)
            : OrgTree::load()->parentChoices($group);

        return Inertia::render('officer/settings/Group', [
            ...$this->shell(),
            'scoped' => $scoped,
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
                ...collect([...Group::CAPABILITY_FLAGS, ...Group::COLLECTION_FLAGS])->mapWithKeys(fn (string $flag) => [$flag => (bool) $group->{$flag}])->all(),
                'hours_multiplier' => $group->hours_multiplier,
                'archived' => $group->isArchived(),
                'endedOn' => $group->endedOn()?->toDateString(),
            ],
            // The current parent may be inactive: marked, so the select still shows it.
            'parents' => array_map(
                fn (ParentChoice $choice) => [...$this->parentOption($choice), 'inactive' => ! $choice->group->isActive()],
                $parents,
            ),
            // The Group's public page, localized (ADR-0008): shown as its address under the h1.
            'groupHref' => route('groups.show', ['group' => $group->slug], false),
            'kinds' => array_column(Kind::cases(), 'value'),
            'visibilities' => array_column(ListingVisibility::cases(), 'value'),
            'logos' => array_column(GroupLogo::cases(), 'value'),
            'lastChanged' => $this->lastChanged($group),
            // Each count carries its label key, named by the relation holding the records.
            'counts' => collect($group->capabilityRecordCounts())
                ->map(fn (int $count, string $flag) => ['count' => $count, 'key' => 'dmv_settings.count.'.Group::CAPABILITY_RECORDS[$flag]])
                ->all(),
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

        return $group->isStructurallyEditable() ? back() : redirect()->route('officer.settings');
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

    /**
     * One row of the section-then-parent picker (#850).
     *
     * @return array{id: int, name: string, depth: int, sectionId: int}
     */
    private function parentOption(ParentChoice $choice): array
    {
        return [
            'id' => $choice->group->id,
            'name' => $choice->group->name,
            'depth' => $choice->depth,
            'sectionId' => $choice->section->id,
        ];
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
     * One tree row. Archived and expired Groups stay under their parent, marked. The root and
     * Containers carry no structure form here (ADR-0036 §3), so they get no href. The row also
     * carries the facts the tree shows at a glance (#848): the six capability flags, the
     * stewardships the Group holds (eager-loaded by OrgTree) and its hours multiplier.
     *
     * A parent officer's own Group heads their subtree (`head`): listed, never opened, as only
     * the super-tier changes it (ADR-0036 §11).
     *
     * @return array{id: int, slug: string, name: string, depth: int, kind: string, archived: bool, endedOn: string|null, editable: bool, head: bool, href: string|null, capabilities: array<string, bool>, stewardships: list<string>, hoursMultiplier: float}
     */
    private function treeRow(OrgTreeNode $node, bool $head = false): array
    {
        [$group, $depth] = [$node->group, $node->depth];
        $editable = ! $head && $group->isStructurallyEditable();

        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'name' => $group->name,
            'depth' => $depth,
            'kind' => $group->kind->value,
            'archived' => $group->isArchived(),
            'endedOn' => $group->endedOn()?->toDateString(),
            'editable' => $editable,
            'head' => $head,
            'href' => $editable ? route('officer.settings.group', ['group' => $group->slug], false) : null,
            'capabilities' => collect(Group::CAPABILITY_FLAGS)->mapWithKeys(fn (string $flag) => [$flag => (bool) $group->{$flag}])->all(),
            'stewardships' => $group->stewardships->map(fn (GroupStewardship $row) => $row->function->value)->sort()->values()->all(),
            'hoursMultiplier' => (float) $group->hours_multiplier,
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
