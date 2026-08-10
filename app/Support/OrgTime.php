<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * The one place the organization's wall clock is converted to storage time.
 *
 * Datetimes are stored in UTC (`config('app.timezone')`) but entered and read in
 * the museum's local time (`config('app.org_timezone')`) — a meeting "at 11am" is
 * 11am at the ROM for every viewer, wherever their device is. The two zones differ
 * by four or five hours depending on the season, so a datetime arriving from a
 * browser without an offset — which is all `<input type="datetime-local">` ever
 * sends — must be pinned to the org zone before it is stored, or it lands hours off.
 *
 * The output side is symmetric but lives on the client: datetimes cross the wire as
 * UTC instants and are formatted with `timeZone: page.props.timezone`.
 */
class OrgTime
{
    /**
     * Read a datetime the user entered on the org's wall clock and return the
     * equivalent UTC instant as `Y-m-d H:i:s`, ready for the validator and the
     * `datetime` cast.
     *
     * A value carrying its own offset (`…Z`, `…-04:00`) is already unambiguous and
     * is converted, not reinterpreted. A non-string or unparseable value is returned
     * untouched so the `date` validation rule — not an exception — rejects it.
     */
    public static function toUtc(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value, config('app.org_timezone'))
                ->utc()
                ->toDateTimeString();
        } catch (InvalidFormatException) {
            return $value;
        }
    }
}
