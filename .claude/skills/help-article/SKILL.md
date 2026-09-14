---
name: help-article
description: |
    Write the help article for a feature, route, or ticket — the whole job in one
    invocation. Use when you ship a Volunteer-facing feature and CLAUDE.md or the
    PR template asks for a help article: it adds the manifest entry, writes the
    English and Canadian-French articles, writes the screenshot step script, and
    ticks the PR checkbox. Follows ADR-0025 and docs/help-articles.md.
---

# help-article

Write a complete help article for one feature, route, or ticket. The help centre
is chrome ([ADR-0025](../../../docs/adr/0025-help-centre.md)): the article ships
in both locales, in the same pull request as the feature.

Do the steps in order. Do not skip the reading step — the shape and the rules
live in the guide, not here.

## 1. Read the ground truth

- Read `docs/help-articles.md` — the authoring guide. It fixes the article
  shape, the sentence rules, the callout convention, image naming, and the
  draft-to-publish flow.
- Read `app/Help/HelpManifest.php` (`catalog()`) — the manifest, so you match the
  entry shape and place the article in the right section.
- Read one existing article as a model: `resources/help/en/getting-started.md`
  (an overview) or `resources/help/en/change-your-language.md` (a task).
- Read `CONTEXT.md` for the project's words. Use them; do not drift to synonyms.

## 2. Settle the entry

Decide, from the feature and its route:

- **slug** — one stable kebab-case string. It names both Markdown files, the step
  script, and the screenshot folder.
- **section** — a `HelpSection` case. Add a new case (and its `lang/en/help.php`
  and `lang/fr/help.php` label) only if the app area is new.
- **requires** — role tokens from `HelpManifest::requirableRoles()`, or `[]` for
  every Member.
- **route** — the route name the article documents, or null.
- **status** — always `ArticleStatus::Draft` for a new article.
- **fr** — always `FrenchState::MachineTranslated` for a new article.

## 3. Add the manifest entry

Add one `HelpArticle` to `catalog()` in `app/Help/HelpManifest.php`, in section
order (overview first). Use the fields from step 2.

## 4. Write the English article

Write `resources/help/en/<slug>.md` to the shape in the guide: H1 title, one-line
purpose, numbered steps, a closing "what next" line. Follow the sentence rules —
under 20 words, active voice, present tense, one instruction per sentence. Use
`**Note:**` / `**Tip:**` callouts sparingly.

Do **not** add image links yet. A draft has no screenshots on disk, and an
integrity test fails on a link to a missing shot. The image links land at publish
time, with the shots.

## 5. Humanize the English

Run the `/humanizer` skill on the English article. It reads a Member; it must not
sound like a chatbot. Keep every instruction; change only the wording.

## 6. Write the French article

Write `resources/help/fr/<slug>.md`: Canadian-French, machine-translated, matched
to the English file heading-for-heading and step-for-step. Use the project's
French words from `CONTEXT.md`. Leave the entry `fr: MachineTranslated`; a human
sets `Reviewed` later.

## 7. Write the step script

Write `resources/help/screenshot-runner/<slug>.steps.sh`. Declare the Persona and
opening page, then list the steps in order — one shot per step, two-digit names.
See `resources/help/screenshot-runner/README.md` for the syntax, the Persona
table, and the Radix-overlay workaround.

## 8. Tick the PR checkbox

In the pull request body, tick "Help article added" in the help-article section
of the template.

## What this skill does not do

It does not run the screenshot runner. The runner needs a running app and a
browser, so it does not work in CI or the autonomous sandbox. Leave the article
`draft` with no image links. Hand off the screenshot pass: a developer runs
`resources/help/screenshot-runner/run.sh <slug>` on a machine with the app, adds
the image links and captions, checks the article against the live chrome, and
flips the entry to `ArticleStatus::Published`.

## Before you finish

Run `vendor/bin/pest --filter=HelpManifest` — the integrity test checks that the
manifest and the files on disk agree.
