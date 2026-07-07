<?php

namespace App\Http\Middleware;

use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Http\Controllers\ImpersonationController;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Personas\Persona;
use App\Personas\PersonaCatalogue;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            // Active locale, resolved from the URL by mcamara's localize middleware
            // (ADR-0008). Surfaced so the laravel-vue-i18n bridge boots in the right
            // locale on first paint (the prop is in the initial Inertia payload).
            'locale' => app()->getLocale(),
            // Per-locale URI-segment translation table, for localising the static
            // nav hrefs (the fixture authors them English-canonical) so in-app
            // navigation stays in the active locale instead of reverting to English
            // (ADR-0008). Keyed by non-default locale → { englishSegment: localised }.
            'routeSegments' => $this->routeSegments(),
            // Top-bar language switcher (ADR-0013): the active locale plus every
            // supported locale's twin URL for the current page. Each option's url
            // is the page's twin in that locale, or null when no twin is
            // registered — the option renders disabled rather than offering a link
            // that 404s (ADR-0008 / #110).
            'localeSwitcher' => $this->localeSwitcher($request),
            // Fixed global top-bar navigation (#194, ADR-0013 amendment): the
            // cross-domain destinations, distinct from a Group's section set and
            // the rail. Hrefs are localized server-side (ADR-0008); gating is
            // resolved here, never echoed from the client.
            'chromeNav' => $this->chromeNav($request),
            // Grouping rail (PRD #209): the per-Member Group/officer navigation,
            // built and pruned server-side and shared as a single prop — all three
            // zones (My Groups, All Groups, Officer Tools). Hrefs are localized
            // server-side, gating resolves here — the client renders only what it is given.
            'rail' => $this->rail($request),
            // Dev/QA role-switcher (PRD #220, ADR-0009 dev half): the data the
            // floating impersonation toolbar renders from — the grouped Persona
            // picker and the active-impersonation state. Null in production and
            // whenever the visibility rule fails, so an ordinary Member (and every
            // production request) ships nothing. Built server-side like chromeNav /
            // rail; the toolbar computes no persona list, grouping, or authority.
            'impersonation' => $this->impersonation($request),
            'auth' => [
                'user' => $request->user(),
                // Coarse, app-wide capability map for chrome/nav (ADR-0017 §9).
                // UI hint only — every action is enforced server-side; hiding a
                // control is never the lock. Fine-grained per-resource `can` props
                // are computed by the relevant policy on each page.
                'can' => [
                    'administerMembers' => (bool) $request->user()?->can('administer-members'),
                ],
            ],
            // Persisted sidebar state. The cookie is written client-side by the
            // shadcn SidebarProvider (raw, hence excepted from encryption in
            // bootstrap/app.php); seeding it here lets the rail render expanded
            // or collapsed on first paint without a flash. Defaults to open.
            'sidebarOpen' => $request->cookie('sidebar:state') !== 'false',
        ]);
    }

    /**
     * The fixed global top-bar nav model (#194): the primary cross-domain
     * destinations plus the Help utility, shared on every page. Each destination's
     * href is localized to the active locale (ADR-0008); declared gating is resolved
     * here against the signed-in member, so the client never echoes authority back.
     *
     * @return array{destinations: list<array{key: string, labelKey: string, href: string}>, help: array{key: string, labelKey: string, href: string}}
     */
    private function chromeNav(Request $request): array
    {
        // Primary destinations, in render order (My Hours · My Calendar · News ·
        // Directory). My Hours / My Calendar are ComingSoon stubs until their slices
        // land; News and Directory point at the live routes.
        $primary = [
            ['key' => 'hours', 'route' => 'hours', 'labelKey' => 'nav.personal.hours'],
            ['key' => 'calendar', 'route' => 'calendar', 'labelKey' => 'nav.personal.calendar'],
            ['key' => 'news', 'route' => 'news', 'labelKey' => 'nav.personal.news'],
            ['key' => 'directory', 'route' => 'directory', 'labelKey' => 'nav.personal.directory'],
        ];

        return [
            'destinations' => collect($primary)
                ->filter(fn (array $spec) => $this->destinationVisible($request, $spec))
                ->map(fn (array $spec) => $this->destination($spec))
                ->values()
                ->all(),
            'help' => $this->destination(['key' => 'help', 'route' => 'help', 'labelKey' => 'nav.help']),
        ];
    }

    /** The Governance & Operations container, folded into the root DMV node (Option C). */
    private const GOVERNANCE_SLUG = 'governance-operations';

    /** The Programs container, dissolved with its programs promoted to the top level (Option C). */
    private const PROGRAMS_SLUG = 'programs';

    /**
     * The grouping rail (PRD #209), built per signed-in Member and server-pruned —
     * the client receives only the nodes it may see. Ships all three zones: My Groups,
     * All Groups, and Officer Tools (each per-item gated by a real authority). A zone is
     * omitted whole when nothing in it survives for the Member. Guests get an empty rail.
     *
     * @return array{myGroups?: array{labelKey: string, items: list<array{groupId: string, name: string, href: string}>}, allGroups?: array{labelKey: string, items: list<array<string, mixed>>}, officer?: array{labelKey: string, items: list<array{key: string, labelKey: string, href: string}>}}
     */
    private function rail(Request $request): array
    {
        $member = $request->user();

        if ($member === null) {
            return [];
        }

        $rail = [];

        if ($myGroups = $this->myGroups($member)) {
            $rail['myGroups'] = $myGroups;
        }

        if ($allGroups = $this->allGroups($member)) {
            $rail['allGroups'] = $allGroups;
        }

        if ($officer = $this->officer($member)) {
            $rail['officer'] = $officer;
        }

        return $rail;
    }

    /**
     * The dev/QA role-switcher prop (PRD #220, ADR-0009 dev half). Null in production
     * and whenever the visibility rule fails; otherwise it carries the grouped Persona
     * picker and the active-impersonation state, both computed here so the toolbar is
     * pure presentation.
     *
     * Visibility keys off support-operator access or the active impersonation session:
     * `non-prod AND (support operator OR an active impersonation session)`. So an idle
     * operator sees the picker, and — crucially — an impersonated no-authority Persona
     * still sees the loud active bar (with its way back), even though it holds no
     * access of its own. A super-tier executive is not an operator, so the President
     * does not see the switcher — operating it is a maintainer power, not org authority.
     *
     * @return array{personas: list<array{key: string, label: string, personas: list<array{email: string, name: string, descriptor: string}>}>, active: array{as: array{name: string, descriptor: string}, operator: string}|null}|null
     */
    private function impersonation(Request $request): ?array
    {
        if (app()->environment('production')) {
            return null;
        }

        $member = $request->user();
        $operatorId = $request->session()->get(ImpersonationController::OPERATOR_KEY);
        $active = $operatorId !== null;

        if ($member === null || (! $member->isSupportOperator() && ! $active)) {
            return null;
        }

        return [
            'personas' => $this->personaGroups(),
            'active' => $active ? $this->activeImpersonation($member, $operatorId) : null,
        ];
    }

    /**
     * The Persona picker, grouped by function (Operator / Super-tier / Officers /
     * Stewards / Roles / Standings / Negative) in catalogue order — the single source of truth,
     * so the list and the seeded data can never drift. Each row carries the realistic
     * name, the `{role · group}` descriptor, and the email the toolbar posts to start.
     *
     * @return list<array{key: string, label: string, personas: list<array{email: string, name: string, descriptor: string}>}>
     */
    private function personaGroups(): array
    {
        return collect(PersonaCatalogue::all())
            ->groupBy(fn (Persona $persona) => $persona->group->value)
            ->map(fn (Collection $personas, string $key) => [
                'key' => $key,
                'label' => $personas->first()->group->label(),
                'personas' => $personas->map(fn (Persona $persona) => [
                    'email' => $persona->email,
                    'name' => $persona->name(),
                    'descriptor' => $persona->descriptor,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The active-impersonation state: who is being impersonated (name + descriptor,
     * resolved from the catalogue by the current Member's email) and the operator to
     * return to. Drives the loud "⚠ IMPERSONATING {persona} · as {operator}" bar.
     *
     * @return array{as: array{name: string, descriptor: string}, operator: string}
     */
    private function activeImpersonation(Member $member, int $operatorId): array
    {
        $persona = collect(PersonaCatalogue::all())
            ->first(fn (Persona $candidate) => $candidate->email === $member->email);

        $operator = Member::find($operatorId);

        return [
            'as' => [
                'name' => $persona?->name() ?? "{$member->first_name} {$member->last_name}",
                'descriptor' => $persona?->descriptor ?? '',
            ],
            'operator' => $operator ? "{$operator->first_name} {$operator->last_name}" : '',
        ];
    }

    /**
     * The Officer Tools zone (#212): the org-wide administration cluster, each item
     * gated by a real authority resolved server-side. "Officer Tools" denotes org-wide
     * administration and is deliberately distinct from a Group's officers (Chair /
     * Secretary / Treasurer). The cluster — heading included — is emitted only when at
     * least one item survives for this Member, so an ordinary Member never sees it.
     *
     * Members and Communications map to existing Gate abilities (Records / member-admin
     * stewardship and news-editor of an announcements-on Group). Reports, Flash Messages
     * and DMV Settings are super-tier-only on an interim basis — they are ComingSoon
     * stubs with no real ability yet, a placeholder a real gate later replaces (PRD #209).
     * Super-tier passes the abilities too via the Gate::before short-circuit, so it sees
     * all five.
     *
     * @return array{labelKey: string, items: list<array{key: string, labelKey: string, href: string}>}|null
     */
    private function officer(Member $member): ?array
    {
        // Each item, in render order, with the authority that gates it: a Gate ability
        // (`gate`) or the interim super-tier-only check (no ability yet).
        $items = [
            ['key' => 'members', 'route' => 'officer.members', 'labelKey' => 'nav.officer.members', 'gate' => 'administer-members'],
            ['key' => 'communications', 'route' => 'officer.communications', 'labelKey' => 'nav.officer.communications', 'gate' => 'post-news'],
            ['key' => 'reports', 'route' => 'officer.reports', 'labelKey' => 'nav.officer.reports'],
            ['key' => 'flash-messages', 'route' => 'officer.flash-messages', 'labelKey' => 'nav.officer.flash_messages'],
            ['key' => 'settings', 'route' => 'officer.settings', 'labelKey' => 'nav.officer.dmv_settings'],
        ];

        $visible = collect($items)
            ->filter(fn (array $spec) => isset($spec['gate'])
                ? $member->can($spec['gate'])
                : $member->isAllDmv())
            ->map(fn (array $spec) => $this->destination($spec))
            ->values();

        if ($visible->isEmpty()) {
            return null;
        }

        return [
            'labelKey' => 'nav.rail.officer',
            'items' => $visible->all(),
        ];
    }

    /**
     * The My Groups zone: the Groups this Member belongs to with Full or on-leave
     * (LOA) standing, flat and alphabetical by name. Departed standings (Resigned,
     * Deceased, and every other non-participating status) never contribute. Returns
     * null when the Member belongs to no qualifying Group, so the whole section
     * (heading included) is omitted from the prop.
     *
     * Reuses the Member's already-eager-loaded memberships (loaded for policy checks)
     * via loadMissing, pulling in each membership's Group without a second query.
     *
     * @return array{labelKey: string, items: list<array{groupId: string, name: string, href: string}>}|null
     */
    private function myGroups(Member $member): ?array
    {
        $member->loadMissing('memberships.group');

        $groups = $member->memberships
            ->filter(fn (GroupMember $membership) => in_array(
                $membership->status,
                [MembershipStatus::Full, MembershipStatus::Loa],
                true,
            ))
            ->map(fn (GroupMember $membership) => $membership->group)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if ($groups->isEmpty()) {
            return null;
        }

        return [
            'labelKey' => 'nav.rail.my_groups',
            'items' => $groups->map(fn (Group $group) => $this->nodeAttributes($group))->all(),
        ];
    }

    /**
     * The All Groups zone (#211): the whole active organization tree, reshaped by the
     * curated Option C transform (below) into the shape volunteers already know from
     * the live rail. Visible to every signed-in Member and collapsed by default in the
     * client; returns null when no active Group exists, so the section is omitted whole.
     *
     * Sourced from {@see Group::scopeActive()} — archived and stale (lapsed-window)
     * Groups never appear — and nested by parent_id at full depth, then pruned on
     * `listing_visibility` for this Member ({@see pruneListingVisibility()}). The tree
     * is loaded in one query and assembled in PHP (no N+1).
     *
     * @return array{labelKey: string, items: list<array<string, mixed>>}|null
     */
    private function allGroups(Member $member): ?array
    {
        $active = $this->pruneListingVisibility($member, Group::active()->get());
        $byParent = $active->groupBy(fn (Group $group) => $group->parent_id);
        $roots = $active->whereNull('parent_id');

        if ($roots->isEmpty()) {
            return null;
        }

        $items = $this->sortNodes($roots)
            ->flatMap(fn (Group $root) => $this->optionCForest($root, $byParent))
            ->all();

        return [
            'labelKey' => 'nav.rail.all_groups',
            'items' => $items,
        ];
    }

    /**
     * Prune the active Group set on `listing_visibility` for the viewing Member (#271,
     * ADR-0019), composing beneath the active-tree prune — a Group already excluded
     * stays excluded. The rule, per node:
     *
     * - **Public** — kept for everyone (today's org-open behaviour);
     * - **Group** — kept only for members of the node's parent Group;
     * - **Private** — kept only for the node's own members.
     *
     * Super-tier sees everything, everywhere, so it short-circuits before any filter.
     * "Member of" resolves from current participation (Full / LOA); departed standings
     * grant nothing — the same standing rule My Groups applies. Pruning the flat set
     * before the tree is rebuilt from its roots drops any visible node orphaned by a
     * pruned ancestor, so a hidden branch takes its whole subtree with it.
     *
     * @param  Collection<int, Group>  $active
     * @return Collection<int, Group>
     */
    private function pruneListingVisibility(Member $member, Collection $active): Collection
    {
        if ($member->isAllDmv()) {
            return $active;
        }

        $memberGroupIds = $this->participatingGroupIds($member);

        return $active->filter(fn (Group $group) => match ($group->listing_visibility) {
            ListingVisibility::Public => true,
            ListingVisibility::Group => in_array($group->parent_id, $memberGroupIds, true),
            ListingVisibility::Private => in_array($group->id, $memberGroupIds, true),
        })->values();
    }

    /**
     * The ids of the Groups this Member currently participates in — Full or on-leave
     * (LOA) standing only. Departed standings (Resigned, Deceased, and every other
     * non-participating status) contribute nothing, mirroring {@see myGroups()}.
     *
     * @return list<int>
     */
    private function participatingGroupIds(Member $member): array
    {
        $member->loadMissing('memberships');

        return $member->memberships
            ->filter(fn (GroupMember $membership) => in_array(
                $membership->status,
                [MembershipStatus::Full, MembershipStatus::Loa],
                true,
            ))
            ->pluck('group_id')
            ->all();
    }

    /**
     * The Option C transform (PRD #209), applied server-side to one root's subtree. It
     * is a curated, named transform — each container is handled on purpose, not by a
     * uniform rule:
     *
     * - the Governance & Operations container's children fold into the root DMV node;
     * - the Programs container dissolves, its programs promoted to the top level (each
     *   still carrying its own subcommittees);
     * - Special Projects and the Friends-of committees stay as ordinary top-level
     *   expandable nodes.
     *
     * The container Groups remain real rows in the data model — they keep parenting
     * children and carrying scope; this is presentation only.
     *
     * @param  Collection<int, Collection<int, Group>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function optionCForest(Group $root, Collection $byParent): array
    {
        $folded = [];   // Governance & Operations' children → the DMV node's children
        $promoted = []; // the Programs container's children → the rail's top level
        $others = [];   // Special Projects + Friends-of → top level, as authored

        foreach ($this->childrenOf($root, $byParent) as $child) {
            if ($child->slug === self::GOVERNANCE_SLUG) {
                $folded = $this->builtChildren($child, $byParent);
            } elseif ($child->slug === self::PROGRAMS_SLUG) {
                $promoted = $this->builtChildren($child, $byParent);
            } else {
                $others[] = $this->railNode($child, $byParent);
            }
        }

        $rootNode = $this->nodeAttributes($root);
        if ($folded) {
            $rootNode['children'] = $folded;
        }

        return array_merge([$rootNode], $promoted, $others);
    }

    /**
     * Build a rail node for a Group and, recursively, its active subcommittees at full
     * depth. The `children` key is present only when the Group has visible children.
     *
     * @param  Collection<int, Collection<int, Group>>  $byParent
     * @return array<string, mixed>
     */
    private function railNode(Group $group, Collection $byParent): array
    {
        $node = $this->nodeAttributes($group);

        if ($children = $this->builtChildren($group, $byParent)) {
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * The built rail nodes for a Group's direct children, in display order.
     *
     * @param  Collection<int, Collection<int, Group>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function builtChildren(Group $group, Collection $byParent): array
    {
        return $this->childrenOf($group, $byParent)
            ->map(fn (Group $child) => $this->railNode($child, $byParent))
            ->all();
    }

    /**
     * A Group's direct children, sorted into display order.
     *
     * @param  Collection<int, Collection<int, Group>>  $byParent
     * @return Collection<int, Group>
     */
    private function childrenOf(Group $group, Collection $byParent): Collection
    {
        return $this->sortNodes($byParent->get($group->id) ?? collect());
    }

    /**
     * Sibling order on the rail: the curated `display_order`, then name as a stable
     * tiebreak.
     *
     * @param  Collection<int, Group>  $groups
     * @return Collection<int, Group>
     */
    private function sortNodes(Collection $groups): Collection
    {
        return $groups->sortBy([
            ['display_order', 'asc'],
            ['name', 'asc'],
        ])->values();
    }

    /**
     * The shared, as-authored shape of one Group rail row: a stable slug id, the Group
     * name verbatim (content — never translated, ADR-0004), its locale-localized path,
     * and its logo key (identity, PRD #253). The logo rides on every rail row so the
     * launcher and rail can't disagree, but only the launcher grid reads it — a null
     * key resolves to the generic fallback mark client-side.
     *
     * @return array{groupId: string, name: string, href: string, logo: string|null}
     */
    private function nodeAttributes(Group $group): array
    {
        return [
            'groupId' => $group->slug,
            'name' => $group->name,
            'href' => $this->groupHref($group),
            'logo' => $group->logo_key?->value,
        ];
    }

    /**
     * The active locale's localized path for a Group's page (ADR-0008): `/groups/{slug}`
     * in English, its `/fr/groupes/{slug}` twin under French. Path-only, mirroring
     * {@see destination()}, so Inertia navigates client-side and the active-state match
     * against the current URL is exact.
     */
    private function groupHref(Group $group): string
    {
        $url = LaravelLocalization::getURLFromRouteNameTranslated(
            app()->getLocale(),
            'routes.groups.show',
            ['group' => $group->slug],
        );

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }

    /**
     * Resolve one global destination to its shareable shape: a stable key, its chrome
     * label key (translated client-side via the i18n bridge), and the active locale's
     * localized path for the destination's route (ADR-0008).
     *
     * @param  array{key: string, route: string, labelKey: string}  $spec
     * @return array{key: string, labelKey: string, href: string}
     */
    private function destination(array $spec): array
    {
        $url = LaravelLocalization::getURLFromRouteNameTranslated(app()->getLocale(), "routes.{$spec['route']}");

        return [
            'key' => $spec['key'],
            'labelKey' => $spec['labelKey'],
            // Strip the host so Inertia navigates client-side and the active-state
            // match against the (path-only) current URL is exact.
            'href' => parse_url($url, PHP_URL_PATH) ?: $url,
        ];
    }

    /**
     * Whether a global destination is visible to the current member. A destination
     * may declare a `gate` ability; it is shown only when the member passes — the
     * gating resolves server-side, never from client-supplied flags. Destinations
     * with no `gate` are always shown.
     *
     * @param  array{gate?: string}  $spec
     */
    private function destinationVisible(Request $request, array $spec): bool
    {
        return ! isset($spec['gate']) || (bool) $request->user()?->can($spec['gate']);
    }

    /**
     * Resolve the top-bar language switcher: the active locale and one option per
     * supported locale, each carrying the current page's twin URL in that locale.
     *
     * A locale's url is null when the current page has no registered twin in it —
     * the active locale (no self-link needed) and any locale outside the page's
     * localized route group (auth, settings, design-system). The component renders
     * those disabled so we never offer a link that 404s (ADR-0008 / #110).
     *
     * @return array{current: string, options: list<array{code: string, label: string, url: string|null}>}
     */
    private function localeSwitcher(Request $request): array
    {
        $current = app()->getLocale();
        $routeName = $request->route()?->getName();

        $options = collect(LaravelLocalization::getSupportedLocales())
            ->map(fn (array $props, string $code) => [
                'code' => $code,
                'label' => $this->localeLabel($code, $props),
                'url' => $code === $current ? null : $this->twinUrl($request, $routeName, $code),
            ])
            ->values()
            ->all();

        return ['current' => $current, 'options' => $options];
    }

    /**
     * The current page's twin URL in the given locale, or null when no twin is
     * registered (the route has no segment translation for that locale).
     */
    private function twinUrl(Request $request, ?string $routeName, string $locale): ?string
    {
        if ($routeName === null || ! Lang::has("routes.{$routeName}", $locale)) {
            return null;
        }

        // Build via route name + params rather than the raw URL string.
        // getLocalizedURL(url) must reverse-match the path to a route before
        // applying the segment table — a step that fails FR→EN on the dynamic
        // group route and leaves /groupes untranslated (#121, ADR-0008).
        return LaravelLocalization::getURLFromRouteNameTranslated(
            $locale,
            "routes.{$routeName}",
            $request->route()->parameters(),
        );
    }

    /**
     * The switcher label for a locale — its autonym (the language named in itself:
     * English, Français), falling back to the configured native name.
     *
     * @param  array{native?: string}  $props
     */
    private function localeLabel(string $code, array $props): string
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ][$code] ?? Str::ucfirst($props['native'] ?? $code);
    }

    /**
     * URI-segment translation table per non-default locale, derived from the route
     * tables (lang/{locale}/routes.php) so the segment words stay single-sourced.
     *
     * The frontend localises English-canonical nav hrefs by mapping each path
     * segment through this table (slugs and {params} pass through unchanged), so a
     * Volunteer on /fr/… navigates to /fr/… twins rather than reverting to English.
     *
     * Derived purely from static config (the route lang files + supported locales),
     * so it's the same for every request — cached forever and rebuilt on deploy when
     * the cache is cleared, rather than recomputed on every Inertia response.
     *
     * @return array<string, array<string, string>>
     */
    private function routeSegments(): array
    {
        return Cache::rememberForever('inertia.route_segments', function (): array {
            $default = LaravelLocalization::getDefaultLocale();
            $base = Lang::get('routes', [], $default);

            $out = [];

            foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $locale) {
                if ($locale === $default) {
                    continue;
                }

                $target = Lang::get('routes', [], $locale);
                $dict = [];

                foreach ($base as $key => $basePattern) {
                    $baseSegs = explode('/', $basePattern);
                    $targetSegs = explode('/', $target[$key] ?? $basePattern);

                    foreach ($baseSegs as $i => $segment) {
                        $localised = $targetSegs[$i] ?? $segment;

                        // Only record words that actually differ; skip {param}
                        // placeholders (group slugs are content, never translated).
                        if ($segment !== $localised && ! str_starts_with($segment, '{')) {
                            $dict[$segment] = $localised;
                        }
                    }
                }

                $out[$locale] = $dict;
            }

            return $out;
        });
    }
}
