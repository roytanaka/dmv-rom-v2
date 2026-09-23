# Writing help articles

How to write a help article for the in-app help centre ([ADR-0025](adr/0025-help-centre.md)).
An article ships in the same pull request as the feature it explains. The
`help-article` skill (`.claude/skills/help-article/`) does the whole job in one
invocation; this guide is the standard it writes to and the reference a reviewer
checks against.

Help articles are chrome ([ADR-0004](adr/0004-chrome-only-translation.md)): the
project authors them, they are the same for every Volunteer, and they ship in
both locales. The English and Canadian-French files land together.

## Where the pieces live

| Piece           | Location                                           |
| --------------- | -------------------------------------------------- |
| Manifest entry  | `app/Help/HelpManifest.php` (`catalog()`)          |
| English article | `resources/help/en/<slug>.md`                      |
| French article  | `resources/help/fr/<slug>.md`                      |
| Step script     | `resources/help/screenshot-runner/<slug>.steps.sh` |
| Screenshots     | `public/help-images/<slug>/NN.png`                 |

The `slug` is one stable string. It names both Markdown files, the step script,
and the screenshot folder. Use it in the manifest entry; do not invent a second
name.

The screenshot folder is `help-images`, not `help`, on purpose. A folder under
`public/` that shares its name with a route shadows that route on Apache: the
server redirects `/help` to `/help/`, finds a real directory, and answers 403
before Laravel sees the request. A Pest test fails on any `public/` folder that
matches the first segment of a route.

## Article shape

One task per article. One screen of reading. If a page holds two tasks, write
two articles.

A task article has this shape:

1. **An H1 title.** The title is the first `#` heading. It is not stored in the
   manifest; the renderer reads it from the file. Name the task, not the page —
   `# Sign up for a shift`, not `# The schedule page`.
2. **A one-line purpose.** One sentence under the title. It says what the reader
   does here and why.
3. **Numbered steps.** The task in order, one instruction per step.
4. **One screenshot per step.** Each step that changes the screen shows the
   result. The caption carries the meaning (see [Screenshots](#screenshots)).
   The image links land with the shots at publish time, not in the draft (a
   Pest test fails on a link to a screenshot that is not on disk).
5. **A closing "what next" line.** One sentence at the end. It links the reader
   to the next article or tells them the task is done.

### Linking another article

Link an article by its slug: `[Sign up for a shift](sign-up-for-a-shift)`. The
renderer turns a bare slug into that article's help URL in the reader's language
(`/help/<slug>` or `/fr/aide/<slug>`). Use the article's title as the link text.
Both locales link the same slug, with the title in that locale.

Never name an article in italics or quotes. A Member opens a link in one step, but
not a name. A Pest test fails on an italic span, on an article title in quotes, and
on a link to a slug the manifest does not list. A section overview's closing
line links its first task article the same way.

### Section overview shape

Each section leads with one overview article, marked `isOverview: true` in the
manifest. It orients the reader; it does not walk a task. Keep it to a short
intro line and one short paragraph per area of the section. `getting-started` is
the model: see `resources/help/en/getting-started.md`.

An overview title never repeats its section label. The breadcrumb reads
"Help › <section> › <title>", so a title equal to the section label shows the
same crumb twice. Name what the section is for — "What Scheduling is for", not
"Scheduling". A catalogue-integrity test enforces this in both locales.

## Sentence rules

Instructional copy follows Simplified Technical English:

- Keep every sentence under 20 words.
- Write in the active voice. "Select your name", not "Your name should be
  selected".
- Write in the present tense. "The page reloads", not "the page will reload".
- Give one instruction per sentence.

Run the `/humanizer` skill on the English article before you commit. It reads a
Member; it must not sound like a chatbot.

## Words

Use the project's own words. The glossary in `CONTEXT.md` fixes them — Volunteer,
Member, Group, Shift, Scheduler, Chair. Match the case and spelling there. Do not
drift to a synonym the glossary avoids. If the word you need is not in the
glossary, that is a signal: either you are inventing language the project does
not use, or there is a real gap to raise.

## Callouts

A callout is a blockquote that opens with a bold label:

> **Note:** Drafts stay hidden from the index until their screenshots land.

> **Tip:** Switch your language from your name in the top bar.

Use `**Note:**` for something the reader must know and `**Tip:**` for something
that helps. Keep a callout to one or two sentences. Use them sparingly.

## Screenshots

Screenshots are scripted, not taken by hand. One step script per article records
what each shot shows; one shared runner replays it. The runner needs a running
app and a browser, so it runs on a developer's machine, not in CI or the
autonomous sandbox. See `resources/help/screenshot-runner/README.md` for the
runner, the step-script syntax, the Radix-overlay workaround, and the **Persona
table** — shoot each article as the account that does the task, so the chrome in
the shot matches the reader's.

**Naming.** Two-digit `NN.png` in step order — `01.png`, `02.png` — in the
article's folder. An article links a shot by bare filename, so the number is the
whole link: `![...](01.png)`.

**Captions.** The caption is the alt text. It carries the meaning, because there
are no drawn annotations on the image. Write what the reader should see:
`![The schedule with one open shift highlighted](02.png)`, not `![Screenshot](02.png)`.

**In the sandbox, do not run the runner.** Write the step script that records
the shots the article needs, but do not add the image links to the draft yet: an
integrity test fails on a link to a screenshot that is not on disk. Hand the
screenshot pass to a developer, who runs the runner on a machine with the app,
adds the image links and captions, and flips the article from draft to published.

## The draft-to-publish flow

A new article lands as `status: draft`. Drafts are hidden from the index and the
top-bar "?" link, but they still render at their URL, so a reviewer reads them on
staging with real chrome. The article stays draft until its screenshots land; a
developer takes the shots, checks the article against the live chrome, and flips
the entry to `status: published` in the same or a follow-up pull request.

## French copy

The French article is machine-translated in the same pull request as the English
one and shipped unreviewed. Set `fr: FrenchState::MachineTranslated` on the
manifest entry (the default). Match the English file heading-for-heading and
step-for-step. Use the project's French words from `CONTEXT.md`.

When a human reviews the French copy against the English, they set the entry to
`fr: FrenchState::Reviewed`. Do not set `Reviewed` yourself for a
machine-translated file.

## The manifest entry

Add one `HelpArticle` to `catalog()` in `app/Help/HelpManifest.php`. A task
article in the Directory section, needing no role, mapped to the directory page:

```php
new HelpArticle(
    'find-a-volunteer',
    HelpSection::Directory,
    requires: [],
    status: ArticleStatus::Draft,
    fr: FrenchState::MachineTranslated,
    route: 'directory',
),
```

- `requires` is the Required-role badge. Each string is a role token from
  `HelpManifest::requirableRoles()` — a `Role` value, or `super_tier` /
  `support_operator`. An empty array means every Member.
- `route` is the name of the page route the article documents. It maps the
  top-bar "?" to this article. Null means the article maps to no page.
- Add the `HelpSection` case first if the section is new, with its label in
  `lang/en/help.php` and `lang/fr/help.php`.

A Pest test keeps the manifest and the files honest: every entry needs both
locale files, and every referenced screenshot must exist. Run `vendor/bin/pest`
before you commit.
