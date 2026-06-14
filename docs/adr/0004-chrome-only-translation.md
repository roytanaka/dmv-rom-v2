---
status: accepted
date: 2026-06-13
---

# Chrome-only translation; content renders as-authored

[ADR-0008](0008-bilingual-url-routing.md) reserved this slot for "the content-side bilingual decisions (suffix-column storage, save-time policy for translatable fields)." This ADR fills it — and decides the opposite of what that phrasing anticipated: **content is not translated at all.**

## Decision

**The app translates only its _chrome_. Volunteer-authored _content_ renders as-authored, in whatever language it was written, identically in both locales.**

- **Chrome** — the frame's own words: UI labels, navigation, buttons, validation messages, system emails. Authored by the project, the same for every Volunteer. **Translated.** Source of truth is Laravel `lang/en/*.php` + `lang/fr/*.php`, surfaced to Vue by [`laravel-vue-i18n`](https://github.com/xiCO2k/laravel-vue-i18n), which reads those same files (no second message store on the JS side). Interpolation uses Laravel's `:placeholder` syntax.
- **Content** — anything a Volunteer types into a DB row: Group names, news posts, document titles, meeting notes. **Not translated.** Single column, rendered verbatim. There are **no `_en`/`_fr` content columns** anywhere in the schema. A Group named "Docents" reads "Docents" under French chrome; "Guides du ROM" reads "Guides du ROM" under English chrome.

The two layers can meet in one sentence via interpolation — a content value dropped into a translated template — without making the value translatable: e.g. the nav a11y label `"Toggle :group subgroups"` → `"Basculer les sous-groupes de :group"`, with `:group` filled by the as-authored Group name.

This boundary extends into URLs (already implied by [ADR-0008](0008-bilingual-url-routing.md)): a route's structural segments are chrome and translate (`/groups` → `/fr/groupes`, `/schedule` → `/horaire`); a Group's slug is content and stays as-authored in both locales (`/fr/groupes/docents/school-visits`).

### Translation content workflow

- **Canadian French (`fr-CA`)** is the target — wording, date, and number conventions. Not France French. The app's locale code is `fr`; the content behind it is Canadian French.
- The French `lang/fr/*.php` files are **machine-translated as a baseline and shipped** — an unverified Canadian-French string beats falling back to English.
- **Exception — institutional/legal strings use ROM's official French verbatim, never machine translation.** Three keys: the **land acknowledgement**, the **inclusion statement**, and the **department name**. They live in a dedicated `lang/{en,fr}/institutional.php` that the machine-translation step **skips by filename** — a structural wall, not a per-string annotation. Official French for the land acknowledgement and inclusion statement is in hand; the department name **stays English in both locales** ("ROM Department of Museum Volunteers") until ROM provides approved French.

## Considered alternatives

- **Suffix-column content storage (`title_en`/`title_fr`, a `title` accessor per locale)** — the approach the reserved ADR-0004 slot, ADR-0003, and the conventions doc all originally assumed. Rejected: it doubles the authoring burden on a ~500-volunteer org with no translation staff, and most content (a meeting note, a news blurb) is written once in one language and never has a second-language counterpart. The honest model is "content is in the language its author wrote it," not "every field is bilingual."
- **Machine-translating everything, including institutional strings.** Rejected for the institutional set specifically: a land acknowledgement and an inclusion statement carry legal and relational weight where ROM's exact approved wording is the only acceptable text.
- **Two message stores (Laravel lang files for PHP, a separate JS catalogue).** Rejected — `laravel-vue-i18n` reads the Laravel files directly, keeping one source of truth, consistent with ADR-0008's `lang/{locale}/routes.php` already living there.

## Consequences

- **No `_en`/`_fr` content columns are ever added.** Documents get a single optional `title` / `description`. This **corrects** stale references that predate this decision: ADR-0003 §Decision(4), ADR-0008 consequences (the "fill `_fr` content columns" task step and the "`title_fr` empty" fallback note), `conventions.md` §Documents and §i18n, and `architecture.md` §Bilingual — all updated to drop suffix-column content.
- **The nav rendering path must keep DB-sourced names out of the translation lookup.** Structural nodes carry a `labelKey` (translated); Group nodes carry a plain `name` (as-authored). The current `chrome/fixture.ts` keys Group names (`nav.group.docents`) as a stand-in; real data replaces those with literal names.
- **`resources/js/chrome/messages.ts` is replaced** by the `laravel-vue-i18n` bridge. Its keys migrate into namespaced `lang/{en,fr}/*.php`; the file is deleted once nothing imports it. The a11y interpolation label is the first consumer.
- **Active locale comes from the URL** (`mcamara/laravel-localization`, per ADR-0008), shared to the frontend as an Inertia prop so the bridge boots in the right locale on first paint. No SSR — this is a client-rendered Inertia SPA.
- **Dates and numbers format with `fr-CA`** (Carbon / `Intl`), not a generic `fr`.
- **The reserved ADR-0004 scope is closed.** There is no separate "bilingual content storage" ADR coming — the answer is "we don't store content bilingually."
