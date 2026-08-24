# After-shift entry — prototype for [#405](https://github.com/roytanaka/dmv-rom-v2/issues/405)

Throwaway. Nothing here ships. It exists to answer one question:

> **Where does the per-shift visitor count get entered?**

## Run it

```
pnpm prototype
```

Opens on <http://localhost:5199>. No Sail, no database, no login.

- **Arrow keys** or the bar's `‹ ›` cycle the four variants.
- The bar also switches **Group** (three collection shapes) and **viewer** (volunteer / officer).
- The URL carries all three — `?variant=C&group=docents&as=officer` — so a view is shareable and survives reload.
- The clock is pinned to **Mon 24 Aug 2026, 16:58**, two minutes from the end of a shift, so the sign-out moment is always on screen.

## What is already decided, and is therefore not what the variants disagree about

[#404](https://github.com/roytanaka/dmv-rom-v2/issues/404) settled the record itself. Every variant obeys all of it:

- Two nullable integers on a **Sign-up**: `visitor_count`, and `extra_interaction_count` for tour-leading Groups only.
- Collection is a **per-Group** setting, not a `ShiftKind` one. Nine Groups of ten collect; Reception collects nothing.
- **Required at the entry surface.** The save button stays disabled until a number is typed — the forcing function that separates legacy's 96–98% fill rate from its 54–59% one.
- The volunteer enters at **sign-out**, from shortly before the shift ends, for **28 days**. A Group **officer** may correct any count with no deadline.
- [#401](https://github.com/roytanaka/dmv-rom-v2/issues/401): there is **no attendance** to record. This is the visitor count and nothing else.

The variants disagree about **where**, and only that.

## The three Groups in the bar

| Group          | Shape       | Why it is here                                                                        |
| -------------- | ----------- | ------------------------------------------------------------------------------------- |
| Visitor Guides | one number  | The common case — 6 of the 9 collecting Groups                                        |
| Docents        | two numbers | Tour-leading. Legacy's second box is labelled _"Visitor interactions excluding tour"_ |
| Reception      | none        | Every variant has to disappear cleanly, not render an empty form                      |

## The four variants

|       | Variant                       | Surface                                    | Actor          |
| ----- | ----------------------------- | ------------------------------------------ | -------------- |
| **A** | Inline on the Agenda          | `/groups/{slug}/scheduling/{id}`           | both           |
| **B** | Close-out view for a Schedule | `/groups/{slug}/scheduling/{id}/close-out` | officer only   |
| **C** | My Calendar                   | `/calendar`                                | volunteer only |
| **D** | On the Hours tab              | `/groups/{slug}/hours`                     | both           |

## What the prototype exposed

Five things that were not visible from the ticket.

1. **A Schedule is a month, and the volunteer's window is 28 days.** A Shift's `schedule_id` is mandatory and its date must fall inside the Schedule's range ([ADR-0021](../../../../docs/adr/0021-scheduling-first-pass.md) §1–2). So a shift from three weeks ago is still fillable but is often on **last month's Schedule** — a different page. Variants A and B are both Schedule-shaped and cannot show it. Both draw a dashed footnote where the missing row would be.
2. **My Calendar is not a new destination.** `nav.personal.calendar` is already a top-bar Zone A route rendering `ComingSoon` (`routes/web.php`), beside the `hours` stub spec [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) has just made real. ADR-0021 §7 already assigned this exact question to it: _"no 'My shifts' filter on a Group's Schedule … that is My Calendar's question."_ Variant C fills a slot that was reserved, which is much cheaper than the ticket assumed.
3. **Only C can carry a prompt.** A, B and D can only prompt someone already inside a Group page. C is a top-bar destination, so it can carry a count. It is also the only variant that can show a shift the viewer took on **another Group's `open` Shift** — the hardcoded Visitor Wayfinders row.
4. **B is a desk surface.** The close-out table scrolls sideways on a phone (`b-closeout-phone.png`). That is fine for an officer and disqualifying for a volunteer, and B is officer-only regardless — it can never ship alone.
5. **Officer correction is nearly free on A.** A pencil per seat chip, no new route, no deadline. On a two-number Group the chips get dense (`a-agenda-officer-docents.png`), which is the cost.

## Screenshots

In `prototype-harness/screenshots/`. Captured at 1280×900 except the two named `-phone` (390×844).

## Shape of the code

- `AfterShiftPrototype.vue` — the host and the `?variant=` switcher
- `PrototypeBar.vue` — the floating bar
- `PrototypeNote.vue` — the dashed annotation above each variant
- `CountFields.vue` — the only thing the variants share: the number boxes
- `Variant*.vue` — one per option, free to throw out the layout
- `fixtures.ts` / `types.ts` — in-memory data, a fixed clock, and the window arithmetic

English only, no translation keys, no tests, no server. Saving mutates the fixture in memory; switching Group is the reset.
