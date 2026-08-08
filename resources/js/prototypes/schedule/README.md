# Schedule prototype — #330

**Throwaway. Do not merge.** This branch exists to be looked at and argued with, then
deleted. Nothing here is production code: no tests, no translations, no server, no
error handling beyond what makes it run.

> Three variants of the opened Schedule, switchable via `?variant=`, mounted on the
> existing `/groups/{slug}/scheduling` section.

## Run it

```bash
pnpm install
pnpm prototype          # → http://localhost:5199
```

That is the standalone harness — the variants with the app's real stylesheet and real
shadcn-vue components, but no sidebar, no login, no database. It binds to every
interface, so `http://<your-lan-ip>:5199` works from a phone, which is the only honest
way to judge point 5.

The same components also mount inside the **real Group page** — start Sail and
`pnpm dev`, then visit any scheduling Group's Scheduling tab
(`/groups/reception/scheduling`). That tab is a muted "soon" stub in every non-dev
build; only `import.meta.env.DEV` makes it navigable.

## The bar

Fixed at the bottom, deliberately ugly so it never reads as part of the design.

| axis                                 | values                                                        | URL param   |
| ------------------------------------ | ------------------------------------------------------------- | ----------- |
| variant                              | `A` Agenda · `B` Calendar · `C` Matrix (`←` / `→` also cycle) | `variant`   |
| fixture                              | Reception · Visitor Guides · Wayfinders event · Empty draft   | `data`      |
| audience                             | Member · Non-member · Scheduler                               | `as`        |
| other Groups' `open` Shifts          | on/off                                                        | `foreign=1` |
| schedule list (#328's landing state) | on/off                                                        | `list=1`    |

Every axis is in the URL, so a specific screen is shareable and reload-stable.
Sign up / drop mutate the fixture **in memory** — reload, or switch fixture and back,
to reset.

## The three variants

|                  | bet                                                                                                                                                                                                                                                                                                      | cost                                                                                                 |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| **A — Agenda**   | A Schedule is read one day at a time, so chronological order and plain rows beat any encoding. Identical on a phone and a desktop.                                                                                                                                                                       | The _shape_ of a month is invisible. You cannot see "the last week is bare" without scrolling it.    |
| **B — Calendar** | The real question is spatial — which Saturdays am I on, where are the holes — and a month answers that at a glance. Descends from what Visitor Guides already does in legacy, where an empty slot button _is_ the sign-up affordance.                                                                    | Needs a legend. Detail moves into a day sheet. Renders the 3-day event as a mostly-empty month grid. |
| **C — Matrix**   | For anything with structure, the pattern _is_ the information. Days across, time **or** activity down — one component with an axis swap, which is legacy's Activities-Across and Activities-Down unified. The only variant that draws Adrian's "three-dimensional intersect" without collapsing an axis. | 30 columns on a month. On a phone you see four days at a time.                                       |

## What is deliberately not here

- **Translations.** English hardcoded. Every string would be a key (ADR-0004).
- **A real backend.** Fixtures only; the Group named in the page banner has nothing to
  do with the Schedule below it.
- **Patterns / recurring anything.** That is #329. Where it shows up in the drawing is
  the empty state's second button ("Copy from…") — a placeholder for #329's answer,
  not a proposal.
- **Anything past the audience/authoring axes.** Swap, reminders, attendance, print.

## Files

|                                                                   |                                                                           |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `SchedulePrototype.vue`                                           | host — URL state, keyboard, variant switch                                |
| `PrototypeBar.vue`                                                | the bottom bar                                                            |
| `VariantAgenda.vue` / `VariantCalendar.vue` / `VariantMatrix.vue` | the three                                                                 |
| `ScheduleIndex.vue`                                               | the section's list state (#328 already decided it; here for context only) |
| `fixtures.ts`                                                     | four seeded fixtures                                                      |
| `helpers.ts`                                                      | dates, the viewer's floors, in-memory sign-up                             |
| `types.ts`                                                        | the model as #324/#326/#327 left it                                       |
| `../../../../prototype-harness/`                                  | the standalone runner + screenshots                                       |
