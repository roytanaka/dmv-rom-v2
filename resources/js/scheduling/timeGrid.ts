/**
 * The minute grid every time picker offers (#639, ADR-0028). Nobody schedules to the minute:
 * Shift and Meeting times step in fives, self-serve starts in fifteens (ADR-0026 §2). The
 * server's `App\Rules\OnMinuteGrid` enforces the same grid.
 */

/** The default step, in minutes. */
export const DEFAULT_STEP_MINUTES = 5;

/**
 * The minutes a picker offers: every multiple of `step` in the hour. A `current` minute off
 * the grid (an older record) is kept on the list so opening it never changes the time.
 */
export function minuteOptions(step: number, current: number | null = null): number[] {
    const options = Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step);

    if (current !== null && !options.includes(current)) {
        options.push(current);
        options.sort((a, b) => a - b);
    }

    return options;
}

/** Split an `HH:mm` value into numbers; an empty value into nulls. */
export function splitTime(value: string): { hour: number | null; minute: number | null } {
    const match = /^(\d{2}):(\d{2})/.exec(value);

    return match ? { hour: Number(match[1]), minute: Number(match[2]) } : { hour: null, minute: null };
}

/** Join an hour and minute into the zero-padded `HH:mm` the forms send. */
export function joinTime(hour: number, minute: number): string {
    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}
