<?php

namespace App\Http\Controllers;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Models\Group;
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
    /** The Groups section: the whole org tree, archived Groups included. */
    public function groups(): Response
    {
        Gate::authorize('administer-groups');

        return Inertia::render('officer/settings/Groups', [
            ...$this->shell(),
            'tree' => $this->tree(),
        ]);
    }

    /** The Stewardships section. Its cards land with #846; the section exists so the switch works. */
    public function stewardships(): Response
    {
        Gate::authorize('administer-groups');

        return Inertia::render('officer/settings/Stewardships', $this->shell());
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
        $byParent = Group::query()->get()->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $rows = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            $siblings = ($byParent->get($parentId) ?? collect())
                ->sortBy([['display_order', 'asc'], ['name', 'asc']]);

            foreach ($siblings as $group) {
                $rows[] = $this->treeRow($group, $depth);
                $walk($group->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $rows;
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
