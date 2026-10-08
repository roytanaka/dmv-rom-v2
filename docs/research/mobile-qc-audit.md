# Mobile QC audit

- **Issue:** [#738](https://github.com/roytanaka/dmv-rom-v2/issues/738), under the sweep [#737](https://github.com/roytanaka/dmv-rom-v2/issues/737)
- **Status:** findings. Changes no code.
- **Run:** 2026-10-08, staging at `012facfb` (includes PR #744, the dialog fix for #739). Pages were measured on `49f42bb7`; #744 changes the shared dialog components and the dialogs in Roster, Scheduling and the calendar, so the page rows still hold. The dialog rerun confirmed the Roster and French page overflows are still there.
- **Screenshots:** [`mobile-qc-audit/`](mobile-qc-audit/). Names are `<locale>-<viewport>-<screen>.jpg`. Long pages are full-page captures.

## Method

`agent-browser` drove a local copy of the app on a fresh demo seed, logged in as `margaret.chen@dmv.test` (President, super-tier, so every officer tool and report shows).

- **Viewports:** 360×640, 390×844, 390×667 (short screen), 768×1024.
- **Locales:** English and French (`/fr/...`).
- **Coverage:** 31 pages (8 combinations each, 244 checks) and 32 dialogs, sheets and menus (256 checks). See [Coverage](#coverage).

An in-page script measured each screen. It recorded:

- the page width against the viewport (sideways page scroll),
- elements past the left or right edge that no clipping box contains,
- boxes that scroll sideways and hide content (scroll strips),
- text cut off in inputs, selects, buttons and table cells,
- tap targets under 44×44px, and under 24×24px (the WCAG 2.2 minimum),
- the share of text under 16px,
- fixed and sticky elements and their height,
- for an open dialog, sheet or menu: whether it runs off the screen, whether it scrolls, and which buttons the user cannot reach.

Every defect below was confirmed by a screenshot. The script also raised false alarms, which were dropped:

- Radix selects keep a hidden 1px native `<select>` behind the visible control. It reads as "truncated" but users never see it.
- "Sign up" on a shift signs the user up at once. It opens no dialog.

The Help ledger and the Design System page are internal tools. Their rows are kept for completeness and rated cosmetic.

## Severity

- **Blocks the task:** the user cannot finish what they came to do.
- **Hard to use:** the user can finish, but only by finding a hidden scroll or zooming.
- **Cosmetic:** looks wrong but does not stop the user.

## Findings

Groups A to K each map to one ticket. **Covered by** names an existing sub-issue of #737, or "none" when a new ticket is needed.

### Fixed by #744 (dialogs)

The first pass ran before #744 landed. These defects were real then and are gone now. The rerun on `012facfb` found every dialog fitting and scrolling at all four viewports.

| Screen | Route | Viewport | Locale | Defect (before #744) | Screenshot | Severity |
|---|---|---|---|---|---|---|
| New / Edit meeting | `/groups/executive/meetings` | 360×640, 390×667, 390×844 | EN, FR | Dialog is 912px tall; title off the top; Save and Cancel off the bottom; no scroll | [before](mobile-qc-audit/en-390x844-meeting-new-before-744.jpg) · [after](mobile-qc-audit/en-390x844-meeting-new.jpg) | Blocks the task |
| Bulk-create shifts | `/groups/docents/scheduling/2` | 360×640, 390×667 | EN, FR | Create shifts and Cancel off screen; hour select 0px wide; To date past the edge at 360 | [before](mobile-qc-audit/en-390x667-bulk-shifts-before-744.jpg) · [after](mobile-qc-audit/en-390x667-bulk-shifts.jpg) | Blocks the task |
| Bulk sign-ups | `/groups/docents/scheduling/2` | 360×640, 390×667 | EN, FR | Cancel off screen; no scroll; To date past the edge at 360 | n/a | Blocks the task |
| Add member | `/groups/docents/roster` | 360×640, 390×667 | EN, FR | Title, Add to group and Cancel off screen | [before](mobile-qc-audit/en-360x640-roster-add-before-744.jpg) | Blocks the task |
| Send feedback | Help menu, any page | 360×640, 390×667 | EN, FR | Title and Close off the top; no scroll | n/a | Hard to use |
| Manage member | `/groups/docents/roster` | 360×640, 390×667, 390×844 | EN, FR | "Leave ends" date runs past the right edge | n/a | Hard to use |
| New / Edit shift | `/groups/docents/scheduling/2` | 360×640 | EN, FR | Dialog 3px taller than the screen | n/a | Cosmetic |

### A. Top bar links sit in a scroll strip

**Covered by:** #741

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Every app page | all | 360×640, 390×667, 390×844 | EN, FR | My Hours, My Calendar, News and Directory sit in a 382px strip that shows 186–216px. Two links show; "My Calendar" is cut mid-word; no scrollbar hints at the rest | [shot](mobile-qc-audit/en-390x844-dashboard.jpg) | Hard to use |

### B. Sticky top bar takes 64px

**Covered by:** #740

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Every app page | all | all | EN, FR | The 64px top bar is sticky at every width | [shot](mobile-qc-audit/en-390x844-dashboard.jpg) | Hard to use |

### C. Group pages pin a second 76px bar

**Covered by:** none

#740 hides the top bar only. On Group pages the section bar (section dropdown + Email) is also sticky, so 140px stays pinned. That is 21% of a 667px screen.

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Group Overview, Members, Scheduling, Documents, Hours, Meetings, Settings | `/groups/{group}/{section}` | all phone sizes | EN, FR | Sticky section bar, 76px, under the 64px top bar | [shot](mobile-qc-audit/en-390x844-g-roster.jpg) | Hard to use |

### D. Wide tables push the whole page sideways

**Covered by:** none

The tables have no scroll wrapper of their own, so the whole page scrolls sideways, top bar included.

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Group Hours | `/groups/docents/hours` | 360×640, 390×667, 390×844 | EN, FR | Page is 508px wide | n/a | Hard to use |
| Group hours report (fiscal year) | `/groups/docents/hours/report` | 360×640, 390×667, 390×844 | EN, FR | Page is 618px wide | [shot](mobile-qc-audit/en-390x844-g-hours-report.jpg) | Hard to use |
| Group extra hours | `/groups/docents/hours/extra` | 360×640, 390×667, 390×844 | EN, FR | Page is 608px wide | n/a | Hard to use |
| Group meeting hours | `/groups/docents/hours/meetings` | 360×640, 390×667, 390×844 | EN, FR | Page is 600px wide | n/a | Hard to use |
| Group hours by month | `/groups/docents/hours/month` | 360×640, 390×667, 390×844 | FR | Page is 414px wide | n/a | Hard to use |
| Committee summary | `/hours/committee-summary` | phone sizes; 768×1024 in FR | EN, FR | Page is 694px wide | n/a | Hard to use |
| Committee detailed | `/hours/committee-detailed` | all four | EN, FR | Page is 791px wide, also on a tablet | [shot](mobile-qc-audit/en-768x1024-org-committee-detailed.jpg) | Hard to use |
| Visitor interactions | `/hours/visitor-interactions` | phone sizes; 768×1024 in FR | EN, FR | Page is 697px wide | n/a | Hard to use |
| Ranked hours | `/hours/ranked` | 360×640, 390×667, 390×844 | FR | Page is 426px wide | n/a | Hard to use |
| My Hours | `/fr/heures` | 360×640, 390×667, 390×844 | FR | Page is 404px wide; the French headers ("SUPPLÉMENTAIRES") widen the table | [shot](mobile-qc-audit/fr-390x844-my-hours.jpg) | Hard to use |

### E. Tables hide columns in a sideways strip

**Covered by:** none

These tables scroll inside their own box, so the page does not move, but the hidden columns carry the row actions and contact details. Touch screens show no scrollbar.

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Group Members (roster) | `/groups/docents/roster` | all four | EN, FR | Table is 740px (801px FR) in a 328px box. Contact, Standing and the row ⋯ menu (Manage, Resign) are off to the right | [shot](mobile-qc-audit/en-390x844-g-roster.jpg) | Hard to use |
| Directory | `/directory` | 360×640, 390×667, 390×844 | EN, FR | Table is 488px in a 328px box; Standing column hidden | [shot](mobile-qc-audit/en-390x844-directory.jpg) | Hard to use |

### F. Page rows run past the edge

**Covered by:** none

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Profile settings | `/settings/profile` | 360×640, 390×667, 390×844 | EN, FR | The photo row (avatar, Choose File, hint) does not wrap. Page is 493px wide; the hint is cut off | [shot](mobile-qc-audit/en-360x640-settings-profile.jpg) | Hard to use |
| Group Members toolbar | `/groups/docents/roster` | 360×640, 390×667, 390×844 | EN, FR | "Show past members", Add member and Email do not wrap. Page is 395px wide; the checkbox label wraps to three lines | [shot](mobile-qc-audit/en-390x844-g-roster.jpg) | Cosmetic |

### G. French labels overflow button rows

**Covered by:** none

French labels are longer. These rows fit in English and break in French. #648 (closed) fixed the Scheduling header in English only.

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Group Scheduling list | `/fr/groupes/docents/horaire` | 360×640, 390×667, 390×844 | FR | Modifier / Dépublier / Supprimer do not wrap. "Supprimer" runs past the card; page is 398px wide | [shot](mobile-qc-audit/fr-390x844-g-scheduling.jpg) | Hard to use |
| News | `/fr/nouvelles` | 360×640 | FR | Modifier / Supprimer run past the edge; page is 371px wide | [shot](mobile-qc-audit/fr-360x640-news.jpg) | Cosmetic |
| Group Documents | `/fr/groupes/docents/documents` | 360×640 | FR | The category filter and "Catégories" button do not wrap; page is 367px wide | [shot](mobile-qc-audit/fr-360x640-g-documents.jpg) | Cosmetic |

### H. Menus taller than the screen do not scroll

**Covered by:** none

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Email audience menu | `/groups/docents` (any Group page, Email ▾) | 360×640, 390×667 | EN, FR | The menu is 461px tall, starts at 300px and does not scroll. Audiences below the fold (cohorts, "Pick people…") cannot be picked | [shot](mobile-qc-audit/en-390x667-g-email-menu.jpg) | Blocks the task |
| Directory "Filter by list" | `/directory` | 360×640, 390×667 | EN, FR | The list scrolls but its top edge sits 26px above the screen | [shot](mobile-qc-audit/en-390x667-dir-list-filter.jpg) | Cosmetic |

### I. Tap targets under 44px

**Covered by:** none

Buttons use the 36px default height almost everywhere, so most pages have more small targets than large ones. The worst cases are under 24px, the WCAG 2.2 minimum.

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Top bar | all | all | EN, FR | Sidebar toggle 36×36, Help 50×40, Account 40×40 | [shot](mobile-qc-audit/en-390x844-dashboard.jpg) | Hard to use |
| Every page | all | all | EN, FR | Default buttons are 36px tall (Email, Edit, Drop, Change banner, sort headers); report tabs are 35px tall | n/a | Cosmetic |
| Skills settings | `/settings/skills` | all | EN, FR | 23 checkboxes are about 20×20 | [shot](mobile-qc-audit/en-390x844-settings-skills.jpg) | Cosmetic |
| Group Settings | `/groups/docents/settings` | all | EN, FR | 9 controls under 24px (checkboxes, switches) | [shot](mobile-qc-audit/en-390x844-g-settings.jpg) | Cosmetic |
| Email composer, "Pick people" | Group page, Email ▾ › Pick people… | all | EN, FR | 26 checkboxes under 24px; the close × is small and faint | [shot](mobile-qc-audit/en-390x667-g-composer.jpg) | Cosmetic |
| Edit meeting | `/groups/executive/meetings` | all | EN, FR | The Agenda / Minutes / Report labels take a fixed column; the link inputs are 172px wide | [shot](mobile-qc-audit/en-390x844-meeting-edit.jpg) | Cosmetic |

### J. Text under 16px

**Covered by:** #742

The audit counted every element that owns text, on all pages at 390×844 in English:

| Size | Elements | Share |
|---|---|---|
| 13px (`text-xs`) | 949 | 16% |
| 16px (`text-sm`) | 3,807 | 65% |
| 18px (`text-base`) | 961 | 16% |
| 20px and up | 107 | 2% |

Most running text is 16px, not the 18px `--text-base`. Pages with the most 13px text: Directory (49%), Group meeting hours (43%), Group Documents (34%), Group Members (29%).

### K. Internal tools overflow

**Covered by:** none

| Screen | Route | Viewport | Locale | Defect | Screenshot | Severity |
|---|---|---|---|---|---|---|
| Design System | `/design-system` | 360×640, 390×667, 390×844 | EN | The "Main" section nav runs past the edge (page 401px); code samples and the token list scroll sideways | [shot](mobile-qc-audit/en-390x844-design-system.jpg) | Cosmetic |
| Help ledger | `/help-status` | all | EN | The article table is 1055px in a 310px box | n/a | Cosmetic |

## Clean

No overflow, cut-off content or unreachable control at any viewport, in either locale (small tap targets aside):

- **Pages:** Dashboard, Member profile, Help, Help article, Password settings, Coming-soon stubs, Group Overview, Schedule detail, Group Meetings, Group hours by member, Zero hours, Mail status.
- **Dialogs and menus after #744:** sidebar sheet, Help menu, Account menu, Send feedback, section dropdown, Change banner, email composer, New meeting, New / Edit schedule, Bulk sign-ups, Bulk shifts, New / Edit shift, Place a member, Write my shift, Message member, document Categories / Add link / New folder / Edit / Move / Delete, roster row menu and Manage, Directory group filter.

At 768×1024 only D (Committee detailed, and two org reports in French), E (Roster) and the tap-target rows remain.

## Coverage

| Area | Checked |
|---|---|
| Top-level pages | Dashboard, My Hours, Directory, Member profile, News, Help, Help article, Settings (Profile, Password, Skills), a Coming-soon stub (Calendar; Documents, Profile, Renew and the Officer stubs render the same page) |
| Group sections (Docents; Meetings on Executive) | Overview, Members, Scheduling list, Schedule detail, Documents, Hours, Settings, Meetings |
| Reports | Group: fiscal year, by month, by member, extra, meetings. DMV: committee summary, committee detailed, ranked, zero hours, visitor interactions |
| Internal (English only) | Design System, Mail status, Help ledger |
| Dialogs, sheets and menus | The 32 listed under [Clean](#clean) and [H](#h-menus-taller-than-the-screen-do-not-scroll) |

Not checked:

- **Feedback pages** (`/feedback`). They read the staging feedback database ([ADR-0029](../adr/0029-tester-feedback.md)), which a local copy cannot reach.
- **Login, forgot and reset password.** These are guest pages; check them once while logged out.
- **Confirms that use `window.confirm`** (resign member, delete meeting, delete schedule, delete news). They are browser dialogs, so the app cannot break their layout.
- **The schedule calendar view's day dialog.** It needs a calendar-view toggle the script did not drive.
