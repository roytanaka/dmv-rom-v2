<?php

namespace App\Http\Controllers;

use App\Models\DocumentDownload;
use App\Models\Group;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Document download log (#754, spec #290 story 55, ADR-0030): every row the download route
 * writes, newest first, so the super-tier can answer "who opened this file, and when?" when a
 * file with PII leaks. Read-only; there is no write route.
 *
 * Filters are query parameters (Group, Member, a date range read as whole days in the org
 * timezone, and a filename search), so a filtered list has a URL. A malformed value is ignored,
 * not an error. A row outlives its Document, Member and Group: the filename is the snapshot, the
 * Document link is dropped once the Document is gone, and a missing Member or Group sends null.
 */
class DocumentDownloadLogController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request): Response
    {
        // The gate returns false for everyone; only the super-tier Gate::before short-circuit
        // grants it. A Group's Librarian or Chair is not enough.
        Gate::authorize('view-document-downloads');

        $filters = [
            'group' => $this->queryId($request, 'group'),
            'member' => $this->queryId($request, 'member'),
            'from' => $this->queryDate($request, 'from'),
            'to' => $this->queryDate($request, 'to'),
            'q' => $this->queryString($request, 'q'),
        ];

        $downloads = DocumentDownload::query()
            ->with(['member', 'group', 'document:id'])
            ->when($filters['group'], fn (Builder $query, int $id) => $query->where('group_id', $id))
            ->when($filters['member'], fn (Builder $query, int $id) => $query->where('member_id', $id))
            ->when($filters['from'], fn (Builder $query, string $date) => $query->where('downloaded_at', '>=', $this->startOfOrgDay($date)))
            ->when($filters['to'], fn (Builder $query, string $date) => $query->where('downloaded_at', '<', $this->startOfOrgDay($date)->addDay()))
            ->when($filters['q'], fn (Builder $query, string $q) => $query->whereLike('original_filename', '%'.addcslashes($q, '%_\\').'%'))
            ->orderByDesc('downloaded_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (DocumentDownload $download) => [
                'id' => $download->id,
                'downloadedAt' => $download->downloaded_at->toIso8601String(),
                'memberName' => $download->member?->fullName(),
                'groupName' => $download->group?->name,
                'filename' => $download->original_filename,
                'documentHref' => $download->document ? route('documents.download', $download->document, false) : null,
            ]);

        return Inertia::render('DocumentDownloads', [
            'downloads' => $downloads,
            'filters' => $filters,
            'groups' => Group::query()
                ->whereIn('id', DocumentDownload::query()->select('group_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Group $group) => ['value' => $group->id, 'label' => $group->name]),
            'members' => Member::query()
                ->whereIn('id', DocumentDownload::query()->select('member_id'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Member $member) => ['value' => $member->id, 'label' => $member->fullName()]),
            'listHref' => route('officer.document-downloads', [], false),
        ]);
    }

    /** A positive integer id from the query string, or null. */
    private function queryId(Request $request, string $key): ?int
    {
        $value = $this->queryString($request, $key);

        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** A real calendar date in `Y-m-d` form from the query string, or null. */
    private function queryDate(Request $request, string $key): ?string
    {
        $value = $this->queryString($request, $key);

        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    /** A trimmed, non-empty string from the query string, or null. */
    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Midnight of a `Y-m-d` day in the org timezone, as UTC (the stored zone). */
    private function startOfOrgDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, config('app.org_timezone'))->startOfDay()->utc();
    }
}
