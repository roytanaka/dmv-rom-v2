<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one CSV-download seam for the Hours reports (#414, PRD #406, ADR-0022 §8) — the export
 * half of the "HTML for print, plus CSV" mechanism that replaces legacy's PDF library. Each
 * report hands this its rows already shaped from the same payload the screen renders, so the
 * file carries the same numbers a Chair reads on the page and never a second version to
 * reconcile.
 *
 * A UTF-8 BOM leads the file so a spreadsheet opens accented, as-authored names (latin1 in
 * origin, utf8mb4 now) as the letters they are rather than mojibake. `fputcsv` quotes any cell
 * carrying a comma, quote, or newline, so a Group or Member name never breaks a row.
 */
class CsvExport
{
    /**
     * Stream a list of rows as a CSV file download.
     *
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
