<?php

namespace App\Help;

use App\Enums\ArticleStatus;
use App\Enums\HelpSection;
use App\Enums\Role;
use Illuminate\Support\Collection;

/**
 * The help manifest (ADR-0025) — one ordered list that is both the reader's index
 * and (with later tickets) the engineer's ledger. Sections appear in the order
 * their articles first appear; within a section the overview article leads, then
 * the task articles in listed order.
 *
 * Resolved from the container, so a test can bind a fixture set of articles the
 * same way the renderer takes a fixture root. Production uses {@see self::catalog()}.
 */
final class HelpManifest
{
    /**
     * @param  list<HelpArticle>|null  $articles  A fixture set for tests; null uses the real catalog.
     */
    public function __construct(private readonly ?array $articles = null) {}

    /**
     * Every article, in display order. The overview of a section is listed first so
     * grouping preserves "overview then tasks" without a second sort.
     *
     * @return list<HelpArticle>
     */
    public function all(): array
    {
        return $this->articles ?? self::catalog();
    }

    /** The article for a slug, or null when nothing maps it (a 404 at the seam). */
    public function find(string $slug): ?HelpArticle
    {
        return $this->collect()->firstWhere('slug', $slug);
    }

    /**
     * The sections that have at least one article, in display order.
     *
     * @return list<HelpSection>
     */
    public function sections(): array
    {
        return $this->collect()
            ->map(fn (HelpArticle $article) => $article->section)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The articles in a section, in display order (overview first).
     *
     * @return list<HelpArticle>
     */
    public function articlesIn(HelpSection $section): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->section === $section)
            ->values()
            ->all();
    }

    /**
     * The sections that have at least one published article — the index's grouping,
     * so a section of only drafts never shows an empty heading.
     *
     * @return list<HelpSection>
     */
    public function publishedSections(): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->status === ArticleStatus::Published)
            ->map(fn (HelpArticle $article) => $article->section)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The published articles in a section, in display order — the index omits drafts.
     *
     * @return list<HelpArticle>
     */
    public function publishedIn(HelpSection $section): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->section === $section && $article->status === ArticleStatus::Published)
            ->values()
            ->all();
    }

    /**
     * The published article a route name maps to, or null. Drives the top-bar "?"
     * (ADR-0025): a match yields the article, anything else the index. Drafts never
     * match, so a route mapped only by a draft resolves to null. When several
     * published articles map one page, a section overview wins over a task article
     * (a Group page opens the Groups overview, not "Record extra hours"); among
     * equals, catalogue order decides.
     */
    public function publishedForRoute(string $routeName): ?HelpArticle
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->route === $routeName
                && $article->status === ArticleStatus::Published)
            ->sortByDesc(fn (HelpArticle $article) => $article->isOverview)
            ->first();
    }

    /** The slug of a section's overview article — the section crumb's destination. */
    public function overviewSlug(HelpSection $section): ?string
    {
        return $this->collect()
            ->first(fn (HelpArticle $article) => $article->section === $section && $article->isOverview)
            ?->slug;
    }

    /**
     * The role tokens a manifest entry may list in `requires`: every {@see Role}
     * value, plus the two tiers that are not Group roles. The badge and its lang
     * labels draw from this set; the integrity test rejects anything outside it.
     *
     * @return list<string>
     */
    public static function requirableRoles(): array
    {
        return [
            ...array_map(fn (Role $role) => $role->value, Role::cases()),
            'super_tier',
            'support_operator',
        ];
    }

    /**
     * Route names the ledger's gap list never counts as a page without an article
     * (ADR-0025 §9). Three kinds of localized GET route are not task pages a Volunteer
     * is walked through:
     *  - the Coming Soon stubs, pages that are not built yet;
     *  - the CSV export twins, each the download sibling of a report page an article covers;
     *  - the auth GET routes, sign-in chrome no article documents.
     * POST-only write seams never reach the gap list at all — it lists GET routes only.
     * The ledger's integrity test keeps this list honest: every name here is a real route.
     *
     * @return list<string>
     */
    public static function ledgerRouteExclusions(): array
    {
        return [
            // Coming Soon stubs (routes/web.php) — pages that do not exist yet.
            'calendar', 'documents', 'profile', 'renew',
            'officer.members', 'officer.communications', 'officer.reports',
            'officer.flash-messages', 'officer.settings',
            // CSV export twins — each rides behind a report page an article covers.
            'groups.hours.report.csv',
            'groups.hours.month.csv',
            'groups.hours.member.csv',
            'groups.hours.extra.csv',
            'groups.hours.meetings.csv',
            'hours.committee-summary.csv',
            'hours.committee-detailed.csv',
            'hours.visitor-summary.csv',
            'hours.ranked.csv',
            'hours.zero-hours.csv',
            'hours.zero-shift-hours.csv',
            'hours.zero-extra-hours.csv',
            // Auth GET routes — sign-in chrome, not task pages.
            'login', 'password.request', 'password.reset', 'password.confirm',
            'verification.notice', 'verification.verify',
        ];
    }

    /**
     * The real catalogue. An article lands draft and flips to published once its
     * screenshots are on disk (ADR-0025); the two Getting started articles shipped first.
     *
     * @return list<HelpArticle>
     */
    private static function catalog(): array
    {
        return [
            new HelpArticle('getting-started', HelpSection::GettingStarted, isOverview: true, status: ArticleStatus::Published, route: 'dashboard'),
            new HelpArticle('change-your-language', HelpSection::GettingStarted, status: ArticleStatus::Published),

            // Volunteer basics (#524) — the everyday pages, one section per app area.
            new HelpArticle('dashboard', HelpSection::Dashboard, isOverview: true, status: ArticleStatus::Published, route: 'dashboard'),

            new HelpArticle('my-hours', HelpSection::MyHours, isOverview: true, status: ArticleStatus::Published, route: 'hours'),
            new HelpArticle('record-extra-hours', HelpSection::MyHours, status: ArticleStatus::Published, route: 'groups.show'),
            new HelpArticle('read-your-hours', HelpSection::MyHours, status: ArticleStatus::Published, route: 'hours'),

            new HelpArticle('directory', HelpSection::Directory, isOverview: true, status: ArticleStatus::Published, route: 'directory'),
            new HelpArticle('find-a-member', HelpSection::Directory, status: ArticleStatus::Published, route: 'directory'),
            new HelpArticle('what-you-can-see-about-a-member', HelpSection::Directory, status: ArticleStatus::Published, route: 'members.show'),

            new HelpArticle('news', HelpSection::News, isOverview: true, status: ArticleStatus::Published, route: 'news'),
            new HelpArticle('read-news', HelpSection::News, status: ArticleStatus::Published, route: 'news'),
            new HelpArticle('post-a-news-item', HelpSection::News, requires: ['news_editor'], status: ArticleStatus::Published, route: 'news'),

            // Groups and Scheduling for members (#525) — what an ordinary Member of a Group does.
            new HelpArticle('groups', HelpSection::Groups, isOverview: true, route: 'groups.show'),
            new HelpArticle('find-your-group', HelpSection::Groups),
            new HelpArticle('group-page-tabs', HelpSection::Groups, route: 'groups.show'),
            // Groups, officer part (#526) — roster and meetings, run by a Group's officers.
            new HelpArticle('manage-your-groups-roster', HelpSection::Groups, requires: ['chair'], status: ArticleStatus::Draft, route: 'groups.show'),
            new HelpArticle('record-a-meeting', HelpSection::Groups, requires: ['secretary'], status: ArticleStatus::Draft, route: 'groups.show'),

            new HelpArticle('scheduling', HelpSection::Scheduling, isOverview: true, route: 'groups.scheduling.show'),
            new HelpArticle('sign-up-for-a-shift', HelpSection::Scheduling, route: 'groups.scheduling.show'),
            new HelpArticle('cancel-a-sign-up', HelpSection::Scheduling, route: 'groups.scheduling.show'),
            new HelpArticle('record-your-visitor-count', HelpSection::Scheduling, route: 'groups.scheduling.show'),
            new HelpArticle('shifts-you-owe-a-number-for', HelpSection::Scheduling, route: 'groups.scheduling.show'),
            new HelpArticle('reminders', HelpSection::Scheduling),
            // Scheduling, officer part (#526) — the schedule-admin tasks a Scheduler or Chair does.
            new HelpArticle('create-a-schedule-and-shifts', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Draft, route: 'groups.scheduling.show'),
            new HelpArticle('add-or-remove-many-shifts', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Draft, route: 'groups.scheduling.show'),
            new HelpArticle('assign-a-member-to-a-shift', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Draft, route: 'groups.scheduling.show'),
            new HelpArticle('correct-a-visitor-count', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Draft, route: 'groups.scheduling.show'),
            new HelpArticle('set-reminders-and-the-empty-desk-alert', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Draft, route: 'groups.scheduling.show'),

            // Hours and reports (#526) — the officer reports and the entry that feeds them.
            new HelpArticle('hours-and-reports', HelpSection::HoursAndReports, isOverview: true, status: ArticleStatus::Draft, route: 'groups.hours.report'),
            new HelpArticle('run-your-groups-hours-report', HelpSection::HoursAndReports, requires: ['statistician', 'chair'], status: ArticleStatus::Draft, route: 'groups.hours.report'),
            new HelpArticle('export-a-report-as-csv', HelpSection::HoursAndReports, status: ArticleStatus::Draft, route: 'groups.hours.report'),
            new HelpArticle('enter-and-correct-hours', HelpSection::HoursAndReports, status: ArticleStatus::Draft, route: 'groups.show'),
            new HelpArticle('the-org-wide-reports', HelpSection::HoursAndReports, requires: ['super_tier'], status: ArticleStatus::Draft, route: 'hours.committee-summary'),

            // Emailing and support (#527, ADR-0024) — the composer, its Audiences, and the Mail
            // status ledger; then the dev Role-switcher, its own small section.
            new HelpArticle('emailing', HelpSection::Emailing, isOverview: true, status: ArticleStatus::Draft),
            new HelpArticle('send-a-direct-message', HelpSection::Emailing, status: ArticleStatus::Draft, route: 'members.show'),
            new HelpArticle('send-a-broadcast', HelpSection::Emailing, status: ArticleStatus::Draft, route: 'groups.show'),
            new HelpArticle('pick-an-audience', HelpSection::Emailing, status: ArticleStatus::Draft, route: 'groups.show'),
            new HelpArticle('attach-a-file', HelpSection::Emailing, status: ArticleStatus::Draft, route: 'groups.show'),
            new HelpArticle('queued-for-n-members', HelpSection::Emailing, status: ArticleStatus::Draft),
            new HelpArticle('read-the-mail-status-page', HelpSection::Emailing, requires: ['super_tier'], status: ArticleStatus::Draft, route: 'mail-status'),

            new HelpArticle('settings', HelpSection::Settings, isOverview: true, status: ArticleStatus::Published, route: 'settings.profile'),
            new HelpArticle('update-your-profile', HelpSection::Settings, status: ArticleStatus::Published, route: 'settings.profile'),
            new HelpArticle('change-your-password', HelpSection::Settings, status: ArticleStatus::Published, route: 'settings.password'),
            new HelpArticle('record-your-skills', HelpSection::Settings, status: ArticleStatus::Published, route: 'settings.skills'),

            // Support (#527, ADR-0009) — the non-production dev Role-switcher, an operator tool.
            new HelpArticle('use-the-role-switcher', HelpSection::Support, requires: ['support_operator'], status: ArticleStatus::Draft),
            new HelpArticle('return-to-yourself', HelpSection::Support, requires: ['support_operator'], status: ArticleStatus::Draft),
        ];
    }

    /** @return Collection<int, HelpArticle> */
    private function collect(): Collection
    {
        return collect($this->all());
    }
}
