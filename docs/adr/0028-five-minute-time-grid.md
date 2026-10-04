---
status: accepted
date: 2026-09-26
accepted: 2026-09-26
---

# Times step in 5 minutes

Raised on [#639](https://github.com/roytanaka/dmv-rom-v2/issues/639). It **keeps [ADR-0026 §2](0026-gallery-interpreters-self-serve-shifts.md)** (self-serve starts on the quarter hour) and puts that grid on the same rule.

## Context

Every time picker in the app offered all sixty minutes. Nobody schedules a Shift or a Meeting to the minute, so 55 of those options are noise.

The Scheduling forms already set `step="300"` on native `<input type="datetime-local">` and `<input type="time">`. That did not help. The `step` attribute only validates the value on submit. Chrome's popup still lists every minute, and other browsers mostly ignore it. The Meetings form had no step at all. The server checked a grid only for self-serve starts.

## Decision

**Every Shift and Meeting time steps in 5 minutes. Self-serve starts step in 15.**

1. **One picker.** `TimeField` is an hour select plus a minute select that lists only the grid steps: 12 at 5 minutes, 4 at 15. `DateTimeField` is a date input plus a `TimeField`. They send the same `HH:mm` and `Y-m-d\TH:i` strings the native inputs sent, so the Form Requests and `OrgTime` do not change. A record already off the grid keeps its own minute on the list, so opening it does not change its time.
2. **The server agrees.** `App\Rules\OnMinuteGrid` rejects an off-grid time on every Form Request that takes one. It replaces the self-serve request's private quarter-hour closure.
3. **Guards keep new pickers on the rule.**
    - ESLint (`vue/no-restricted-static-attribute`) rejects `type="time"` and `type="datetime-local"` on `input` and `Input`. CI runs it.
    - `tests/Feature/MinuteGridTest.php` scans `app/Http/Requests` and fails if a request takes `starts_at`, `ends_at`, `held_at`, `starts_time` or `ends_time` without `OnMinuteGrid`.
    - `docs/conventions.md` § Dates and times states the rule.

## Consequences

- The pickers use native `<select>`, like the Roster and Scheduling pickers. Phones show their own wheel, and there is still no shadcn `Select` in the repo.
- A new time field with a name the guard test does not know gets past the test. Add the name to the test's pattern when that happens.
- Legacy records off the grid still load and display. To save one, the editor must move it onto the grid.
