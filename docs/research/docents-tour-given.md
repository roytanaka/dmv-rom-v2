# Should a Docents or Guides du ROM Shift record the tour given?

- **Issue:** [#568](https://github.com/roytanaka/dmv-rom-v2/issues/568)
- **Status:** findings + recommendation. Changes no code.
- **Related:** [#567](https://github.com/roytanaka/dmv-rom-v2/issues/567) (shift-kind maintenance, one layer only), [ADR-0021 §3](../adr/0021-scheduling-first-pass.md), [ADR-0010](../adr/0010-group-model.md).

## The question, in two layers

Legacy Docents (and Guides du ROM, same shape) run a tour slot with **two** descriptive layers:

1. **Sign-up label** — the slot's label from the weekly template: `Museum Highlights`, `Gallery/Theme`, `Group Tour`. This is what a Docent commits to on the roster, and it is what v2 already models as a `ShiftKind`. The demo seeder carries exactly this list — `DemoSeeder::HIGHLIGHTS_TOUR`, `GALLERY_TOUR`, `GROUP_TOUR`, and GDR's `Le choix du guide` (_"the guide's choice"_) and `Visite de groupe` (`database/seeders/DemoSeeder.php:121-189`).
2. **Tour given** — on a `Gallery/Theme` slot the Docent then gives _one_ specific gallery tour out of roughly fifty (`Ancient Egypt & Nubia`, `China`, …). The legacy `docentToTour` table maps each sign-up label to the tours allowed beneath it. Stand-alone and exhibition tours carry a label of their own, so for them layer 1 and layer 2 collapse to one.

v2 models only layer 1. A `Gallery/Theme` Shift cannot say which gallery the tour covered. This document decides whether layer 2 should be ported, and where it lives if so.

### Provenance and a limitation to name up front

The legacy PHP source tree is **not** in this repository. This document reasons from the legacy research already distilled into the repo — `CONTEXT.md`'s _Legacy vocabulary_ section and the scheduling ADRs, which themselves cite the legacy paths (`gdr.php:492`, `walker.php`, `servicesp.php:9769`, and the schema inventory on branch `research/legacy-scheduling-schema`, [#325](https://github.com/roytanaka/dmv-rom-v2/issues/325)). Where a claim rests on `docentToTour` specifically — which no in-repo document quotes line for line — it is flagged as needing primary-source confirmation in the migration plan, not asserted from source this agent has read.

## 1. What reads the tour-given value in legacy?

Separate two things that the question runs together: the **mapping table** `docentToTour` (read _at pick time_ to constrain and vet the choice) and the **stored per-shift value** (the tour the Docent actually gave, recorded on the dated row).

- **Statistics and reports.** These aggregate by _count_ and by _committee_, never by which gallery. `Count` for Docents and GDR means the _quantity_ of tours given, not their identity (`CONTEXT.md:286`). The org-level report and Detailed Committee Statistics read `Visitors`, the per-shift visitor number — not the tour given (`CONTEXT.md:292`). GDR's only finer breakdown is `Visitorsfr` / `Visitorspq` / `Visitorsto` / `Visitorsroc` / `Visitorsoth` — visitor _origin_, by region, GDR-only, read by one fiscal-year PDF (`gdr.php:5298`, via `CONTEXT.md:299`) — and still not the tour. **No documented report groups by the specific tour.**
- **Reminders.** A reminder names the shift the volunteer signed up for — the label and its date and time, which is layer 1. Nothing indicates the reminder resolves the layer-2 pick, and in v2 a Reminder is a per-`(Shift, Member)` window ([ADR-0024](../adr/0024-emailing-model.md)) that carries no tour identity of its own.
- **Factsheets.** A tour factsheet is tour _content_, keyed by tour in the content catalog (`Category → Section → Tour`, [ADR-0010](../adr/0010-group-model.md)). Reading a factsheet reads the catalog — not a value stored on a scheduling row.
- **Vetting.** The clearance check reads `docentToTour` — the _mapping_ — to limit a Docent to tours they may give and to confirm the pick is allowed. This is a **gate at pick time against the mapping**, not a reader of the stored historical value. [ADR-0021 §3](../adr/0021-scheduling-first-pass.md) records the same shape: the qualification hangs off the kind, spoken as _"she's cleared for Highlights."_

**Finding.** The `docentToTour` _mapping_ is read (to gate the pick); the _stored per-shift tour-given value_ has **no confirmed reader** among reports, statistics, reminders, or factsheets. It is close kin to the `Interactions` / _extra interaction count_ precedent — a per-shift value volunteers have filled for years that no report touches (`CONTEXT.md:293`) — with one difference: tour-given also feeds the vetting gate _at entry_. So it is not purely dead; it is a live input to a check, but its _historical_ record has no confirmed reader. **Flag for the migration plan:** confirm against the `docentToTour` source before trusting this as complete.

## 2. Do Gallery Interpreters need it?

**No.** Gallery Interpreters' list is already one layer — the gallery _is_ the shift kind. [ADR-0021 §2](../adr/0021-scheduling-first-pass.md) settles exactly this with its _one descriptive axis_ rule: _"A Group whose kinds are places names them that way."_ A GI Shift is fully described by its `ShiftKind`; there is no coarse-label-then-fine-pick step, so there is nothing to record on the Sign-up.

This scopes the second layer to the tour-leading Groups whose sign-up label is deliberately coarse: **Docents** (`Gallery/Theme`) and **GDR** (`Le choix du guide`, which literally names the pick). Outreach is the neighbouring case — its kinds are _presentations_ (`outreachGalleryTheme`), the other half of the [ADR-0021 line 249](../adr/0021-scheduling-first-pass.md) open question — and the same recommendation covers it.

## 3. Options and recommendation

Three homes are on the table for layer 2.

- **(a) Port nothing. The kind is enough.** The faithful-port rule cuts against this — legacy _does_ record the tour, and dropping it loses fifty-way granularity and the vetting gate's target. But nothing in the first pass reads the stored value, and [ADR-0021 §2](../adr/0021-scheduling-first-pass.md) already gave the Shift _"no free-text note."_ Named as a deliberate deviation from a faithful port — see Recommendation below — not an oversight.
- **(b) A nullable free-text or picked value on the Sign-up, filled by the Docent.** Rejected as the _home_. A free string re-runs the entity-versus-string argument [ADR-0021 §3](../adr/0021-scheduling-first-pass.md) already settled for `ShiftKind`: _"a free-typed string cannot carry a qualification requirement."_ The ~50 tours are a curated, per-Group, translated-as-content list; typing them per shift produces drift and cannot anchor the vetting gate.
- **(c) Point it at the content catalog's Tours, when that capability exists.** Recommended as the long-term home. The tour given is a **nullable foreign key from the Sign-up** — per volunteer, per seat, the same place `visitor_count` lives because two Docents on one `capacity: 2` slot may give different galleries (`CONTEXT.md:82`) — **to a content-catalog `Tour`** ([ADR-0010](../adr/0010-group-model.md)). The vetting gate rides along: `docentToTour` becomes the label/kind → allowed-`Tour` eligibility relation, resolved by the deferred credential PRD ([ADR-0021 §3](../adr/0021-scheduling-first-pass.md), and _Deliberately out of the first pass_).

**Why (c) also resolves the [ADR-0021 line 249](../adr/0021-scheduling-first-pass.md) open question.** That question asked whether Docents' shift kinds are `ShiftKind` rows or the content catalog's Tours, and warned that either the `ShiftKind` rows duplicate the ~50 tours or a Shift's kind points into another capability's data. The two-layer reading dissolves the dilemma: keep the layers apart.

- **Layer 1** — the coarse sign-up label — stays the **`ShiftKind`** (what you sign up to staff).
- **Layer 2** — the fine tour — becomes a **`Sign-up` → `Tour`** reference (what you actually gave).

Neither column has to be both. Stand-alone and exhibition tours, where label equals tour, get a `ShiftKind` _and_ a matching catalog `Tour`; the FK is what records which gallery was given under an umbrella `Gallery/Theme` slot.

**Recommendation:** **(a) now, (c) as the decided destination.** The scheduling first pass builds nothing — `ShiftKind` stays layer 1, and the Shift keeps its promised absence of a free-text note. The tour given is not dropped as a _concept_: its home is fixed as a nullable `Sign-up` → catalog-`Tour` FK, arriving with the Docents content-catalog capability. Until then the rule is **do-not-destroy**: v2 has no column for it yet, so the legacy import must preserve the value out of band rather than discard it.

## 4. What the legacy import must do

Under the recommended **(a) now / (c) later** path:

- **Do not drop the tour given.** v2 has no home for it yet, so **park it**: the migration plan retains, per historical Docents and GDR shift, the picked tour, keyed so a later backfill can attach it to the Sign-up once the `Sign-up` → `Tour` FK exists.
- **Map the coarse label to a `ShiftKind`.** Do **not** expand the ~50 tours into `ShiftKind` rows — that conflates the two layers and re-opens [line 249](../adr/0021-scheduling-first-pass.md). Stand-alone and exhibition labels become a `ShiftKind` now and a matching catalog `Tour` later.
- **Convert the encoding.** Tour names are legacy latin1 / cp1252; normalise to utf8mb4 on the way in (`CLAUDE.md`, _Character encoding_).

Under **(b)** the import would write the picked tour straight into a Sign-up free-text column — cheap, but it strands the value outside the catalog and outside the vetting relation, which is the design (c) exists to avoid.

The migration plan, not this document or an ADR, owns the data movement: [ADR-0021](../adr/0021-scheduling-first-pass.md) takes _facts_ about legacy from research, never the data itself.

## Recommendation in one line

Record nothing in the scheduling first pass; fix the tour given's home as a nullable `Sign-up` → content-catalog `Tour` FK for the Docents content-catalog capability, and have the legacy import park the picked tours until that FK exists.

## ADR text to add if the maintainer accepts it

Append to [ADR-0021](../adr/0021-scheduling-first-pass.md), resolving the open item under _Deliberately out of the first pass_ ("Whether Docents' (and Outreach's) shift kinds are `ShiftKind` rows or the content catalog's Tours"):

> **Resolved ([#568](https://github.com/roytanaka/dmv-rom-v2/issues/568)).** The open item conflated two layers Docents and GDR keep distinct: a **coarse sign-up label** (`Gallery/Theme`, `Le choix du guide`) and the **fine tour given** beneath it (one gallery of ~50, via legacy `docentToTour`). They get different homes. The **label stays the `ShiftKind`** — what a Member signs up to staff. The **tour given becomes a nullable `Sign-up` → content-catalog `Tour` FK** — what the Member actually gave, per seat, beside `visitor_count` — carrying the vetting gate as the label→`Tour` eligibility relation. So `ShiftKind` rows never duplicate the tour list and a `ShiftKind` never points into the content catalog; the two columns stop competing for one meaning. **Built now: nothing** — no first-pass Group has a content catalog, and [§2](0021-scheduling-first-pass.md)'s _no free-text note on the Shift_ holds. The tour given arrives with the Docents content-catalog capability ([ADR-0010](0010-group-model.md), `Category → Section → Tour`); until then the value is not modelled and the **legacy import parks the picked tours for a later backfill** rather than discarding them. Gallery Interpreters are unaffected — their kind _is_ the gallery, one layer, per [§2](0021-scheduling-first-pass.md)'s one-descriptive-axis rule.
