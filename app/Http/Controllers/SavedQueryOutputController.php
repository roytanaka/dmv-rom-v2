<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailSavedQueryResultsRequest;
use App\Models\Member;
use App\Models\SavedQuery;
use App\Policies\SavedQueryPolicy;
use App\Support\CsvExport;
use App\Support\Notices\SavedQueryResultsNoticeWriter;
use App\Support\SavedQueries\SavedQueryFields;
use App\Support\SavedQueries\SavedQueryRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A Saved query run's outputs (#924, ADR-0038 §7): CSV, Labels and Email results. Each covers the
 * whole query, as the Legacy app's did. Member administration only, through the run ability of
 * {@see SavedQueryPolicy}. The membership number is never an output column, so none
 * of the three can carry it.
 */
class SavedQueryOutputController extends Controller
{
    /** Avery 5160: three across, ten down. */
    public const LABELS_PER_SHEET = 30;

    /** The Legacy app's printer choices; the Xerox sits a little higher on the sheet. */
    public const PRINTERS = ['xerox', 'inkjet', 'laser'];

    /** The output columns as CSV, named after the query and the date. */
    public function csv(SavedQueryRunner $runner, SavedQuery $savedQuery): StreamedResponse
    {
        Gate::authorize('run', $savedQuery);

        $columns = $savedQuery->output_columns;
        $rows = [array_map(fn (string $column) => SavedQueryFields::columnLabel($column), $columns)];

        foreach ($runner->rows($savedQuery) as $row) {
            $rows[] = array_map(fn (string $column) => $row['cells'][$column] ?? '', $columns);
        }

        $name = Str::slug($savedQuery->name) ?: 'saved-query';

        return CsvExport::download("{$name}-".now()->toDateString().'.csv', $rows);
    }

    /** The mailing labels ({@see Member::mailingLabel()}), in sheets of thirty. */
    public function labels(Request $request, SavedQueryRunner $runner, SavedQuery $savedQuery): Response
    {
        Gate::authorize('run', $savedQuery);

        $printer = in_array($request->query('printer'), self::PRINTERS, true) ? $request->query('printer') : 'laser';

        $labels = $runner->query($savedQuery)
            ->get(['id', 'first_name', 'last_name', 'address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country'])
            ->map(fn (Member $member) => $member->mailingLabel());

        return Inertia::render('officer/members/queries/Labels', [
            'query' => $savedQuery->only('id', 'name'),
            'printer' => $printer,
            'printers' => self::PRINTERS,
            'sheets' => $labels->chunk(self::LABELS_PER_SHEET)->map(fn ($sheet) => $sheet->values())->values(),
        ]);
    }

    /**
     * One Notice per chosen Member with the note and a link to the run; a Member with the
     * no-email flag or no address is skipped.
     */
    public function emailResults(EmailSavedQueryResultsRequest $request, SavedQueryResultsNoticeWriter $notices, SavedQuery $savedQuery): RedirectResponse
    {
        $notices->write($savedQuery, $request->validated('member_ids'), $request->validated('note'), $request->user());

        return back();
    }
}
