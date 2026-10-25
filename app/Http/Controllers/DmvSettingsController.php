<?php

namespace App\Http\Controllers;

use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\StewardshipFunction;
use App\Http\Requests\ArchiveGroupRequest;
use App\Http\Requests\PreviewStewardshipsRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupStructureRequest;
use App\Http\Requests\UpdateStewardshipRequest;
use App\Models\Group;
use App\Models\GroupStewardship;
use App\Support\Groups\OrgTree;
use App\Support\Groups\OrgTreeNode;
use App\Support\Stewardships\StewardshipAssignment;
use Illuminate\Http\RedirectResponse;
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

    /** The Groups section: the whole org tree, archived Groups included. */
    public function groups(): Response
    {
        Gate::authorize('administer-groups');

        $tree = OrgTree::load();

        return Inertia::render('officer/settings/Groups', [
            ...$this->shell(),
            'tree' => array_map(fn (OrgTreeNode $node) => $this->treeRow($node), $tree->nodes),
            'parentOptions' => array_map(
                fn (OrgTreeNode $node) => ['id' => $node->group->id, 'name' => $node->group->name, 'depth' => $node->depth],
                $tree->parentChoices(),
            ),
        ]);
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
        StewardshipAssignment::assign($request->stewardship(), $request->holderIds(), $request->user());

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
            // The current parent may be inactive: marked, so the select still shows it.
            'parents' => array_map(
                fn (OrgTreeNode $node) => ['id' => $node->group->id, 'name' => $node->group->name, 'depth' => $node->depth, 'inactive' => ! $node->group->isActive()],
                OrgTree::load()->parentChoices($group),
            ),
            // The Group's public page, localized (ADR-0008): shown as its address under the h1.
            'groupHref' => route('groups.show', ['group' => $group->slug], false),
            'kinds' => array_column(Kind::cases(), 'value'),
            'visibilities' => array_column(ListingVisibility::cases(), 'value'),
            'logos' => array_column(GroupLogo::cases(), 'value'),
            'lastChanged' => $this->lastChanged($group),
            'counts' => $this->capabilityCounts($group),
        ]);
    }

    /**
     * The records behind each capability, by the Group relation that holds them (#844). Turning
     * the capability off hides them; nothing is deleted.
     */
    private const CAPABILITY_RECORDS = [
        'has_meetings' => 'meetings',
        'has_documents' => 'documents',
        'has_scheduling' => 'shifts',
        'has_vetting' => 'tours',
        'has_announcements' => 'news',
        'has_bookings' => 'bookings',
    ];

    /**
     * How many records sit behind each capability that is on, keyed by flag, so the form shows
     * what turning it off makes unreachable. One query: a count subselect per relation.
     *
     * @return array<string, int>
     */
    private function capabilityCounts(Group $group): array
    {
        $on = array_filter(self::CAPABILITY_RECORDS, fn (string $relation, string $flag) => (bool) $group->{$flag}, ARRAY_FILTER_USE_BOTH);
        if ($on === []) {
            return [];
        }

        $group->loadCount(array_values($on));

        return array_map(fn (string $relation) => (int) $group->{"{$relation}_count"}, $on);
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
     * Containers carry no structure form here (ADR-0036 §3), so they get no href.
     *
     * @return array{id: int, slug: string, name: string, depth: int, kind: string, archived: bool, endedOn: string|null, editable: bool, href: string|null}
     */
    private function treeRow(OrgTreeNode $node): array
    {
        [$group, $depth] = [$node->group, $node->depth];
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
