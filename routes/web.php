<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupEmptyDeskSettingsController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\GroupReminderSettingsController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\MailStatusController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NoEmailFlagController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SignUpController;
use App\Http\Controllers\SuperTierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// Root lands on login for guests, dashboard for authenticated members.
// The login page (auth/Login) is the front door; '/' just routes to it.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Localized routes (ADR-0008). The group prefix is '' for English (canonical root)
// and 'fr' for French; transRoute() resolves each segment per-locale from
// lang/{en,fr}/routes.php. So 'dashboard' ↔ '/dashboard' and '/fr/tableau-de-bord'
// resolve the same page. Routes are added French-second as features land.
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localize'],
], function () {
    Route::get(LaravelLocalization::transRoute('routes.dashboard'), function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    // Route stubs (#109). Representative Zone A (personal) and Zone C (officer)
    // routes, each with a French twin whose segments are translated words —
    // exercising the translated-segment pipeline end-to-end before the real pages
    // exist. They all render one shared "coming soon" placeholder.
    $stubRoutes = [
        // Zone A — personal
        'calendar', 'documents', 'profile', 'renew',
        // Utility — the top bar's Help destination (#194); a placeholder until
        // the help surface lands.
        'help',
        // Zone C — officer/admin
        'officer.members', 'officer.communications', 'officer.reports',
        'officer.flash-messages', 'officer.settings',
    ];

    foreach ($stubRoutes as $name) {
        Route::get(LaravelLocalization::transRoute("routes.$name"), fn () => Inertia::render('ComingSoon'))
            ->middleware('auth')
            ->name($name);
    }

    // My Hours (#409, PRD #406, ADR-0022 §8). A Member's own hours, gathered from every
    // Group they have hours in, broken out by month across a fiscal year with a year-to-date
    // total — the one home for the renewal question that is not inside any Group. Reads the
    // authenticated Member alone (§4); the fiscal-year window is a `?fy=` query param that
    // defaults to the current fiscal year. Replaces the earlier ComingSoon stub.
    Route::get(LaravelLocalization::transRoute('routes.hours'), [HoursController::class, 'mine'])
        ->middleware('auth')->name('hours');

    // Member directory (#169, PRD #167). The living roster, readable by every
    // logged-in member; the payload routes through MemberResource::directoryCollection
    // so no row carries contact PII. Replaces the earlier ComingSoon stub.
    Route::get(LaravelLocalization::transRoute('routes.directory'), [MemberController::class, 'index'])
        ->middleware('auth')->name('directory');

    // Member profile (#172, ADR-0017, PRD #167). The read profile over the
    // centralized MemberResource, whose allowlist gates contact PII behind the
    // `viewContact` ability — the page never reasons about authority client-side.
    // Bilingual per ADR-0008: /members/{member} ↔ /fr/benevoles/{member}.
    Route::get(LaravelLocalization::transRoute('routes.members.show'), [MemberController::class, 'show'])
        ->middleware('auth')->name('members.show');

    // Org-wide news feed (#155, ADR-0017 §5). The read is open to every logged-in
    // member regardless of their Groups — one feed, localized chrome. Writes live
    // on the non-localized seam routes below; this is the canonical feed page,
    // replacing the earlier ComingSoon stub now that the feature has landed.
    Route::get(LaravelLocalization::transRoute('routes.news'), [NewsController::class, 'index'])
        ->middleware('auth')->name('news');

    // Group detail page (#188, PRD #186). Resolves the Group by its slug (content,
    // as-authored even under /fr/ — only the /groups segment is translated, ADR-0008)
    // and renders the committee shell + Overview tab. The {section} segment selects
    // the active tab; an unknown slug 404s via slug route-model binding.
    Route::get(LaravelLocalization::transRoute('routes.groups.show'), [GroupController::class, 'show'])
        ->middleware('auth')->name('groups.show');

    // A Schedule permalink (#353, PRD #352, ADR-0021 §1). The Scheduling section's
    // read surface: a Schedule addressed by id under its owning Group (bound by slug),
    // rendered through the same committee shell as `groups.show`. The read audience —
    // a draft is admin-only, a published Schedule follows the Group's listing
    // visibility — is enforced in the controller via the SchedulePolicy.
    Route::get(LaravelLocalization::transRoute('routes.groups.scheduling.show'), [GroupController::class, 'showSchedule'])
        ->middleware('auth')->name('groups.scheduling.show');

    // A Group's fiscal-year hours report (#411, PRD #406, ADR-0022 §5). A Member × twelve-month
    // matrix with the Group's own hours and its subtree hours side by side. A separate
    // addressable route rather than a mode of the Hours tab, so it is linkable and the CSV
    // export can be its sibling. The read audience — Chair or Statistician of the Group or any
    // ancestor, or super-tier — is enforced in the controller via the HoursRecordPolicy's
    // viewReports gate.
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.report'), [HoursController::class, 'report'])
        ->middleware('auth')->name('groups.hours.report');
    // The CSV sibling (#414, ADR-0022 §8). Same numbers, same `viewReports` gate — the export is
    // never a way around the gate that keeps per-Member hours from ordinary Members.
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.report.csv'), [HoursController::class, 'reportCsv'])
        ->middleware('auth')->name('groups.hours.report.csv');

    // The three officer surfaces a Chair or Statistician reaches for after the fiscal-year
    // matrix (#412, PRD #406, ADR-0022 §8): a month picker (one month's entries across the
    // Group), a Member History (one person's hours in this Group over time), and the two
    // Member × twelve-month summaries kept apart — Member Extra Hours (rows carrying no
    // Meeting) and Member Meeting Hours (rows carrying one). Each is a separate addressable
    // route so it can be linked in an email, and each is gated identically to the fiscal-year
    // report — Chair or Statistician of the Group or any ancestor, or super-tier — via the
    // HoursRecordPolicy's viewReports gate, enforced in the controller.
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.month'), [HoursController::class, 'month'])
        ->middleware('auth')->name('groups.hours.month');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.member'), [HoursController::class, 'memberHistory'])
        ->middleware('auth')->name('groups.hours.member');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.extra'), [HoursController::class, 'extraSummary'])
        ->middleware('auth')->name('groups.hours.extra');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.meetings'), [HoursController::class, 'meetingSummary'])
        ->middleware('auth')->name('groups.hours.meetings');
    // Their CSV siblings (#414, ADR-0022 §8), each behind the same viewReports gate as its page.
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.month.csv'), [HoursController::class, 'monthCsv'])
        ->middleware('auth')->name('groups.hours.month.csv');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.member.csv'), [HoursController::class, 'memberHistoryCsv'])
        ->middleware('auth')->name('groups.hours.member.csv');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.extra.csv'), [HoursController::class, 'extraSummaryCsv'])
        ->middleware('auth')->name('groups.hours.extra.csv');
    Route::get(LaravelLocalization::transRoute('routes.groups.hours.meetings.csv'), [HoursController::class, 'meetingSummaryCsv'])
        ->middleware('auth')->name('groups.hours.meetings.csv');

    // The six DMV-wide fiscal-year reports (#413, PRD #406, ADR-0022 §8) — the single output the
    // whole Hours feature exists to produce, the fiscal-year statistics the ROM asks the DMV for.
    // Org-wide, not scoped to any {group}: they are always rooted at the DMV root Group. The read
    // audience — a Chair, Secretary, or Statistician of the DMV root, the Records stewardship, or
    // the super-tier — is enforced in the controller via the HoursRecordPolicy's viewOrgReports
    // gate; an ordinary Member, and a Chair of any other Group, reach none of them. Each is a
    // separate addressable route so the CSV export (#414) can be its sibling; the words are
    // translated in the French twin.
    Route::get(LaravelLocalization::transRoute('routes.hours.committee-summary'), [HoursController::class, 'committeeSummary'])
        ->middleware('auth')->name('hours.committee-summary');
    Route::get(LaravelLocalization::transRoute('routes.hours.committee-detailed'), [HoursController::class, 'committeeDetailed'])
        ->middleware('auth')->name('hours.committee-detailed');
    // Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the department's headline
    // visitor number, Groups × twelve months over a fiscal year. Org-wide like the six above, but
    // the named exception to their officer gate: open to any signed-in Member (viewVisitorSummary),
    // enforced in the controller. The words are translated in the French twin.
    Route::get(LaravelLocalization::transRoute('routes.hours.visitor-summary'), [HoursController::class, 'visitorSummary'])
        ->middleware('auth')->name('hours.visitor-summary');
    Route::get(LaravelLocalization::transRoute('routes.hours.ranked'), [HoursController::class, 'rankedHours'])
        ->middleware('auth')->name('hours.ranked');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-hours'), [HoursController::class, 'zeroHours'])
        ->middleware('auth')->name('hours.zero-hours');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-shift-hours'), [HoursController::class, 'zeroShiftHours'])
        ->middleware('auth')->name('hours.zero-shift-hours');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-extra-hours'), [HoursController::class, 'zeroExtraHours'])
        ->middleware('auth')->name('hours.zero-extra-hours');
    // The six CSV siblings (#414, ADR-0022 §8), each behind the same viewOrgReports gate as its page.
    Route::get(LaravelLocalization::transRoute('routes.hours.committee-summary.csv'), [HoursController::class, 'committeeSummaryCsv'])
        ->middleware('auth')->name('hours.committee-summary.csv');
    Route::get(LaravelLocalization::transRoute('routes.hours.committee-detailed.csv'), [HoursController::class, 'committeeDetailedCsv'])
        ->middleware('auth')->name('hours.committee-detailed.csv');
    // Summary Visitor Interactions CSV (#452, ADR-0023 §6) — the visitors report's export sibling.
    // Open to any signed-in Member (viewVisitorSummary), exactly as its page is, unlike the six
    // officer-gated exports above it.
    Route::get(LaravelLocalization::transRoute('routes.hours.visitor-summary.csv'), [HoursController::class, 'visitorSummaryCsv'])
        ->middleware('auth')->name('hours.visitor-summary.csv');
    Route::get(LaravelLocalization::transRoute('routes.hours.ranked.csv'), [HoursController::class, 'rankedCsv'])
        ->middleware('auth')->name('hours.ranked.csv');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-hours.csv'), [HoursController::class, 'zeroHoursCsv'])
        ->middleware('auth')->name('hours.zero-hours.csv');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-shift-hours.csv'), [HoursController::class, 'zeroShiftHoursCsv'])
        ->middleware('auth')->name('hours.zero-shift-hours.csv');
    Route::get(LaravelLocalization::transRoute('routes.hours.zero-extra-hours.csv'), [HoursController::class, 'zeroExtraHoursCsv'])
        ->middleware('auth')->name('hours.zero-extra-hours.csv');
});

// Internal design-system reference page. Login-only (auth) but available in all
// environments so Volunteers can be invited to give feedback via a shared link.
// English-only standalone page; see PRD #37.
Route::get('design-system', function () {
    return Inertia::render('DesignSystem');
})->middleware(['auth'])->name('design-system');

// The Mail status page (#492, ADR-0024 §10). Non-localized like the design-system page —
// a super-tier-only operations screen, not member-facing chrome. Access is the
// `view-mail-status` gate, which the controller authorizes: it denies everyone but the
// super-tier Gate::before short-circuit.
Route::get('mail-status', MailStatusController::class)
    ->middleware(['auth'])->name('mail-status');

// Member administration (ADR-0017). Editing a member record is gated by the
// MemberPolicy via the UpdateMemberRequest: self by default, Records or super-tier
// for anyone else. The richer member-admin UI (and its localized routes) lands in
// a later slice.
Route::patch('members/{member}', [MemberController::class, 'update'])
    ->middleware(['auth'])
    ->name('members.update');

// Grant/revoke super-tier (ADR-0017 §1). A dedicated, separately-gated action —
// never a field on a member form. Reserved to super-tier itself via the
// `manage-super-tier` gate in the UpdateSuperTierRequest; everyone else is denied.
Route::put('members/{member}/super-tier', SuperTierController::class)
    ->middleware(['auth'])
    ->name('members.super-tier.update');

// Set/clear the no-email flag (#483, ADR-0024 §9). A dedicated, member-administration-
// gated action — never a field on a member form. Records or super-tier only, via the
// `administer-members` gate in the UpdateNoEmailFlagRequest; a Chair or the Member
// themself is denied.
Route::put('members/{member}/no-email-flag', NoEmailFlagController::class)
    ->middleware(['auth'])
    ->name('members.no-email-flag.update');

// News feed mutations (#155, ADR-0017 §5). The non-localized write seam: posting,
// editing, and deleting are each structurally authorized in their Form Request,
// which delegates to the NewsPolicy — a news-editor of the posting Group, only
// while its announcements capability is on. The localized read lives on the `news`
// route above.
Route::post('news', [NewsController::class, 'store'])
    ->middleware(['auth'])
    ->name('news.store');
Route::patch('news/{news}', [NewsController::class, 'update'])
    ->middleware(['auth'])
    ->name('news.update');
Route::delete('news/{news}', [NewsController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('news.destroy');

// Group officer edits (#191, PRD #186). The first group-officer write seam:
// inline About Us text and the curated banner selection, structurally authorized
// in UpdateGroupRequest, which delegates to the GroupPolicy — a Secretary or Chair
// of the Group (plus the super-tier). The localized read lives on `groups.show`.
// The {group} param binds by slug, as everywhere.
Route::patch('groups/{group}', [GroupController::class, 'update'])
    ->middleware(['auth'])
    ->name('groups.update');

// Group officer meetings CRUD (#193, PRD #186). The Group's own-data write seam:
// adding, editing, and deleting meetings (with their agenda / minutes / report
// links and the published/hidden toggle), each structurally authorized in its Form
// Request, which delegates to the MeetingPolicy — a Secretary or Chair of the Group
// (plus the super-tier), only while the Group's meetings capability is on. Store
// nests under the owning Group (bound by slug); edit/delete bind the meeting by id.
// The members-only read lives on `groups.show`.
Route::post('groups/{group}/meetings', [MeetingController::class, 'store'])
    ->middleware(['auth'])
    ->name('meetings.store');
Route::patch('meetings/{meeting}', [MeetingController::class, 'update'])
    ->middleware(['auth'])
    ->name('meetings.update');
Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('meetings.destroy');

// Group scheduling authoring (#354, PRD #352, ADR-0021 §1). The Scheduler's write
// seam for a Group's Schedules: create a draft, edit its fields, publish / un-publish
// it (a `state` transition on the edit path), and delete it. Each is structurally
// authorized in its Form Request, which delegates to the SchedulePolicy — a Scheduler
// or Chair of the Group (plus the super-tier), only while the Group's scheduling
// capability is on. Store nests under the owning Group (bound by slug); edit/delete
// bind the Schedule by id. The read surface lives on `groups.scheduling.show`.
Route::post('groups/{group}/schedules', [ScheduleController::class, 'store'])
    ->middleware(['auth'])
    ->name('schedules.store');
Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])
    ->middleware(['auth'])
    ->name('schedules.update');
Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('schedules.destroy');

// Group Reminder settings (#486, PRD #479, ADR-0024 §7). The Scheduling section's Reminders
// block — on/off and lead days — edited by a Scheduler or Chair. One dedicated endpoint, bound
// to the Group by slug, structurally authorized in UpdateReminderSettingsRequest, which
// delegates to the SchedulePolicy's `updateReminders` gate (scheduling on, actor a schedule
// admin). A plain member is refused before the write.
Route::patch('groups/{group}/reminder-settings', [GroupReminderSettingsController::class, 'update'])
    ->middleware(['auth'])
    ->name('groups.reminders.update');

// Group empty-desk settings (#487, PRD #479, ADR-0024 §7). The Scheduling section's empty-desk
// block — the alert on/off, the look-ahead days, and which shift kinds to watch — edited by a
// Scheduler or Chair. One dedicated endpoint, bound to the Group by slug, structurally authorized
// in UpdateEmptyDeskSettingsRequest, which delegates to the SchedulePolicy's `updateEmptyDeskAlert`
// gate (scheduling on, actor a schedule admin). A plain member is refused before the write.
Route::patch('groups/{group}/empty-desk-settings', [GroupEmptyDeskSettingsController::class, 'update'])
    ->middleware(['auth'])
    ->name('groups.empty-desk.update');

// Shift authoring (#356, PRD #352, ADR-0021 §2). The Scheduler's write seam for the
// Shifts on a Schedule: add a Shift (times, capacity, optional kind, audience), edit it
// (raise capacity, adjust times within range), and delete it (cancelling, never silent).
// Each is structurally authorized in its Form Request, which delegates to the
// ShiftPolicy — the same schedule-admin gate the SchedulePolicy uses. Store nests under
// the owning Schedule (bound by id); edit/delete bind the Shift by id.
Route::post('schedules/{schedule}/shifts', [ShiftController::class, 'store'])
    ->middleware(['auth'])
    ->name('shifts.store');
// Bulk-create / bulk-delete Shifts (#362, ADR-0021 §2). A month of Shifts in one form
// run — kind, start/end time, capacity, days of week, a date range — and the same filter
// to take them back out (legacy's skip-dates job without a field). N single writes plus a
// report: skip-and-report, never all-or-nothing, never a privileged path. Both nest under
// the owning Schedule and answer to the same schedule-admin gate as a single write.
Route::post('schedules/{schedule}/shifts/bulk', [ShiftController::class, 'bulkStore'])
    ->middleware(['auth'])
    ->name('shifts.bulk-store');
Route::delete('schedules/{schedule}/shifts/bulk', [ShiftController::class, 'bulkDestroy'])
    ->middleware(['auth'])
    ->name('shifts.bulk-destroy');
Route::patch('shifts/{shift}', [ShiftController::class, 'update'])
    ->middleware(['auth'])
    ->name('shifts.update');
Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('shifts.destroy');

// Sign-up write seam (#357, PRD #352, ADR-0021 §Sign-up). A Member taking a Shift and
// dropping it, from the same place they signed up. Each is structurally authorized in its
// Form Request, which delegates to the SignUpPolicy — the two floors and the Shift's
// `audience` on the way in, owning the seat on the way out. Take nests under the Shift
// (bound by id); drop binds the Sign-up by id. The read surface lives on
// `groups.scheduling.show`; the cancellation email to the Group's Schedulers lands with #358.
Route::post('shifts/{shift}/sign-ups', [SignUpController::class, 'store'])
    ->middleware(['auth'])
    ->name('sign-ups.store');
Route::delete('sign-ups/{signUp}', [SignUpController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('sign-ups.destroy');

// Sign-out write seam (#445, PRD #443, ADR-0023 §5). Recording the after-the-shift numbers on
// a Sign-up — a Member filing how many visitors they served, from the inline Agenda panel and
// (later) the outstanding panel and an Officer's correction, all onto this one route. Bound by
// the Sign-up id; structurally authorized in its Form Request, which delegates to
// SignUpPolicy::record — the seat-holder's own seat, from five minutes before the Shift ends —
// and whitelists the fields, so whose seat is written is the route binding, never the body.
Route::patch('sign-ups/{signUp}', [SignUpController::class, 'record'])
    ->middleware(['auth'])
    ->name('sign-ups.record');

// Officer assignment write seam (#359, PRD #352, ADR-0021 §Sign-up). A Scheduler placing a
// named Member on a Shift directly — Reception's whole operating model. A distinct actor
// from the self-service Sign-up above (a Scheduler seating someone else), so a separate
// seam, but it writes the same ordinary Sign-up row. Structurally authorized in its Form
// Request, which delegates to SignUpPolicy::assign — the schedule-admin gate on the actor,
// both floors on the placed Member, and (deliberately) not the `audience`. Officer removal
// reuses `sign-ups.destroy` above, whose ownership-gated email keeps it silent.
Route::post('shifts/{shift}/assignments', [AssignmentController::class, 'store'])
    ->middleware(['auth'])
    ->name('assignments.store');
// Bulk-place / bulk-remove a Member's Sign-ups (#363, PRD #352, ADR-0021 §5). The Scheduler's
// labour-saver that retires Reception's fortnight: a Member placed across every Shift a filter
// names (days of week + time, a date range, an interval of weekly or biweekly), and the same
// filter to take them back out. N single writes plus a report — skip-and-report, never
// all-or-nothing. Both nest under the owning Schedule and answer to the schedule-admin gate;
// the interval lives in the form, never in a row (no recurrence column, no phase anchor).
Route::post('schedules/{schedule}/assignments/bulk', [AssignmentController::class, 'bulkStore'])
    ->middleware(['auth'])
    ->name('assignments.bulk-store');
Route::delete('schedules/{schedule}/assignments/bulk', [AssignmentController::class, 'bulkDestroy'])
    ->middleware(['auth'])
    ->name('assignments.bulk-destroy');

// Group officer roster CRUD (#192, PRD #186). The roster write seam: adding a
// member, changing standing (including a leave window), assigning / revoking roles,
// resigning (a soft status change via update), and the added-in-error hard-remove
// (a true row delete). Each is structurally authorized in its Form Request, which
// delegates to the GroupMemberPolicy — a Secretary or Chair of the Group (plus the
// super-tier). Add nests under the owning Group (bound by slug); change/remove bind
// the membership by id. The read surface lives on `groups.show`.
Route::post('groups/{group}/members', [GroupMemberController::class, 'store'])
    ->middleware(['auth'])
    ->name('group-members.store');
Route::patch('memberships/{membership}', [GroupMemberController::class, 'update'])
    ->middleware(['auth'])
    ->name('group-members.update');
Route::delete('memberships/{membership}', [GroupMemberController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('group-members.destroy');

// Extra-hours entry (#408, PRD #406, ADR-0022 §2). A Member records the hours they spent
// helping a Group, on that Group's Hours tab — additive, floored at zero, current or
// previous month only. Structurally authorized in StoreHoursRecordRequest, which delegates
// to the HoursRecordPolicy (any participating Member, on any Group they can open). The
// hours are always written for the authenticated session, never a member id in the body —
// the security deviation from legacy (ADR-0022 §4). Nests under the owning Group (bound by
// slug); the read surface lives on `groups.show`.
Route::post('groups/{group}/hours', [HoursController::class, 'store'])
    ->middleware(['auth'])
    ->name('hours.store');

// Recalculate scheduled hours from Sign-ups (#410, PRD #406, ADR-0022 §2). A Chair or
// Scheduler of a scheduling Group runs "recalculate this month": every Member's scheduled
// hours for the month are replaced (never added) with the summed durations of the past Shifts
// they hold a Sign-up on, the Group's `hours_multiplier` applied once. Structurally authorized
// in RecalculateHoursRequest, which delegates to the HoursRecordPolicy's `recalculate` gate —
// the scheduling gate, not the hours-entry one — and bounds the month to the current fiscal
// year. Nests under the owning Group (bound by slug); the read surface lives on `groups.show`.
Route::post('groups/{group}/hours/recalculate', [HoursController::class, 'recalculate'])
    ->middleware(['auth'])
    ->name('hours.recalculate');

// Dev/QA role-switcher (ADR-0009 dev half, PRD #220). Layer one of the two-layer
// environment boundary: the become/stop endpoints are registered only outside
// production, so in production they do not exist (404) regardless of any UI state.
// The controller re-checks the environment as layer two. Non-localized, like the
// other write seams — this is a dev tool, not chrome, and is English-only per
// ADR-0009. Stop needs only an active impersonation session, so both sit behind
// plain `auth`; the tier gate lives in the controller.
if (! app()->environment('production')) {
    Route::post('impersonate', [ImpersonationController::class, 'start'])
        ->middleware('auth')
        ->name('impersonation.start');
    Route::delete('impersonate', [ImpersonationController::class, 'stop'])
        ->middleware('auth')
        ->name('impersonation.stop');
}

// Audience endpoints (#484, ADR-0024 §5). The two reads the composer sheet fetches:
// an index of the Audiences the actor may pick in a context (each with a label and a
// count), and a show of one Audience's resolved rows (id, name, photo, standing — never
// an address). Both resolve every Audience on the server from the Group model, enforcing
// the picker rule; the browser names the Audience and never posts a recipient list. Data
// endpoints (JSON), non-localized like the other seams: the `context`/`subject` query
// pair names the surface, `parameter` pins a parameterised Audience, and `removed[]` /
// `added[]` carry the picker's per-Member edits.
Route::get('audiences', [AudienceController::class, 'index'])
    ->middleware(['auth'])
    ->name('audiences.index');
Route::get('audiences/{audience}', [AudienceController::class, 'show'])
    ->middleware(['auth'])
    ->name('audiences.show');

// The Broadcast send path (#488, ADR-0024 §4, §6). The composer posts its context, the
// Audience to resolve, the picker's per-Member edits, the subject, body, and up to two
// attachments; the server re-resolves the Audience, writes the sent record and one Delivery
// per recipient plus the sender's copy, and returns the queued count and the skipped names.
// Nothing sends in the request. A data endpoint (JSON), non-localized like the Audience seams.
Route::post('broadcasts', [BroadcastController::class, 'store'])
    ->middleware(['auth'])
    ->name('broadcasts.store');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Honest language-boundary fallback (ADR-0008). A /fr/ URL whose French route is
// not registered returns a locale-aware "not translated yet" page (404) rather
// than silently rendering the English page. The polished 404 with a request-
// translation CTA is a later ADR-0008 slice; this is just the honest boundary.
Route::fallback(function (Request $request) {
    $locale = $request->segment(1);
    $supported = array_keys(LaravelLocalization::getSupportedLocales());

    if ($locale !== LaravelLocalization::getDefaultLocale() && in_array($locale, $supported, true)) {
        return Inertia::render('NotTranslated', ['locale' => $locale])
            ->toResponse($request)
            ->setStatusCode(404);
    }

    abort(404);
});
