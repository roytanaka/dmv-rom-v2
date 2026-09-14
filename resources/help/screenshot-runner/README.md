# Help screenshots

Screenshots for help articles are scripted, not taken by hand (PRD #516,
[ADR-0025](../../../docs/adr/0025-help-centre.md)). One step script per article
records what each shot shows; one shared runner replays it. Re-shooting an
article is one command, so a shot never drifts silently from the chrome — the
script is the record of intent, and a wrong shot is replaced against it.

The runner needs a running app and a browser, so it runs on a developer's
machine, not in CI or the autonomous sandbox. There is no screenshot CI job.

## Run it

Start the app locally with the demo data seeded (the runner logs in as seeded
Personas), then name an article by its slug:

```
resources/help/screenshot-runner/run.sh getting-started
```

The runner logs in as the script's Persona, sets a 1280 px desktop viewport,
walks the steps, and writes the shots to `public/help-images/<slug>/`. Override
`BASE_URL` for a non-default Sail port; override `DEV_PASSWORD` if the seeded
password differs from the default.

## Write a step script

One file per article, named `<slug>.steps.sh` beside the runner. It is sourced,
not run: it declares the Persona and the opening page, then lists steps in order.

```sh
persona amara.abara@dmv.test
start /dashboard

nav /dashboard 01           # load a page, then shoot it
act highlightHelpLink 02    # run one in-page helper, then shoot it
```

- `persona <email>` — the seeded account to log in as (see the table below).
- `start <path>` — the page to open before the first step.
- `nav <path> <NN>` — navigate to a page, then take shot `NN`.
- `act <helper> <NN>` — run one helper from `page-helpers.js`, then take shot `NN`. A
  helper opens an overlay, follows an in-page link, or scrolls the shot's subject under
  the sticky tab strip; anything a plain `nav` cannot show.
- Leave `NN` off a `nav` or `act` line to take no shot. Use it to reload a page and
  close a dialog, or to open a Schedule before the helper that frames the shot.

Keep an article to one screen of reading, so a handful of shots. The comment
after each step is the note to yourself about what the shot should show; the
article's own caption is what the reader sees.

## Personas

Shoot each article as the account that does the task, so the chrome in the shot
matches the reader's. All Personas share the dev password `password`. The
browser keeps its cookies between runs, so the runner signs the previous Persona
out before it signs the script's Persona in; two articles shot back to back never
share a session.

| Article kind                 | Persona                 | Email                     |
| ---------------------------- | ----------------------- | ------------------------- |
| Member tasks                 | Full-standing Docents   | `amara.abara@dmv.test`    |
| Officer tasks (Chair)        | Chair of Docents        | `oliver.bennett@dmv.test` |
| Officer tasks (Scheduler)    | Scheduler of Docents    | `james.tremblay@dmv.test` |
| Officer tasks (Secretary)    | Secretary of Executive  | `elena.rossi@dmv.test`    |
| Officer tasks (Statistician) | Statistician of Docents | `ravi.singh@dmv.test`     |
| Support and Role-switcher    | Support operator        | `operator@dmv.test`       |
| Super-tier (President) view  | President               | `margaret.chen@dmv.test`  |

## Viewport and naming

- **Viewport:** 1280 px wide desktop. No mobile shots.
- **Language:** English chrome only. The one exception is the _Change your
  language_ article, whose subject is the French switch.
- **Names:** two-digit `NN.png` in step order — `01.png`, `02.png` — in the
  article's folder. An article references a shot by bare filename in its
  Markdown, so the number is the whole link.

## The Radix-overlay workaround

agent-browser cannot click a Radix (reka-ui) overlay open — a menu or dialog
stays shut. So a step that needs an open overlay does not `nav` or click; it
`act`s an in-page helper (`page-helpers.js`). Most dialogs here are opened by a
button's own click handler, so the helper calls the button's DOM `click()`. A
Radix menu opens on a pointer or key event instead, so its helper drives the
menu's keyboard contract: focus the trigger, then press Enter. Add a helper when
an article needs a new overlay open, and keep each one small — the runner
injects the whole file into the page.

A dialog taller than the 800 px viewport comes out clipped. Hand-shoot that one
PNG at a taller viewport and say so in the step script's header.

## When a shot comes out wrong

Replace that one PNG by hand and leave the step script as it is. The script
records what the shot should show; the file on disk is allowed to be a hand-made
stand-in when the runner fights the chrome. Re-run the script when the feature
changes, then hand-replace again only where you have to.
