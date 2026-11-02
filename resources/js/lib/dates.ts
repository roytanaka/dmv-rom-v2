/**
 * A date-only value (`Y-m-d`, e.g. a Last vet date) in the page's language, medium style unless
 * asked otherwise. Read as UTC midnight and formatted in UTC, so the browser's time zone never
 * shifts it a day.
 */
export function formatDateOnly(iso: string, locale: string, style: 'medium' | 'long' = 'medium'): string {
    return new Intl.DateTimeFormat(locale, { dateStyle: style, timeZone: 'UTC' }).format(new Date(`${iso}T00:00:00Z`));
}
