---
status: accepted
date: 2026-11-05
accepted: 2026-11-05
---

# Objects as a capability with their own tab, and Tours edited on theirs

From a grilling session on 2026-11-04 and 2026-11-05. It makes **Objects** a Group capability, `has_objects`, with an **Objects** tab where Members browse them and officers manage them. It gives Objects and **Tours** a link to a **Folder** in the Document library. It ports the Gallery Interpreters' favourites and the Object usage report. It moves the Tours cards from Settings to the Tours tab, the same move Objects makes. It **amends [ADR-0026 §3](0026-gallery-interpreters-self-serve-shifts.md)** (the sign-up rule and the gate), **[ADR-0027 §2](0027-group-settings-tab.md)** (the setting/record rule), **[ADR-0036 §5](0036-group-administration.md)** (six capability flags become seven) and **[ADR-0037 §1](0037-group-tab-visibility.md)** (one new row in the tab matrix). See `GLOSSARY.md` for **Object**, **Favourite** and **Tour**.

The rule for the legacy parts is the faithful-port rule: keep the Legacy app's design, and deviate only for a security defect or a fundamental flaw. Each deviation is listed in §11.

## Context

Objects are the main thing Gallery Interpreters (GI) work with. In v2 today an Object is a name, an active flag and a sort order ([ADR-0026 §3](0026-gallery-interpreters-self-serve-shifts.md)). An officer edits the list in a card on the Settings tab. A Member sees it only in the sign-up and self-serve dialogs on the Scheduling tab. The gate is the scheduling capability: a Group without `has_scheduling` cannot have Objects.

That gate is wrong for the second Group that keeps objects. Outreach's `outreachObjects` go with presentations, not shifts. GI's legacy menus show what a Member does with Objects outside a shift: read the Object's information package, and post a favourite with a note on why they like it. The information packages are Documents in v2 ([ADR-0030](0030-document-library.md)). Nothing links an Object to them.

Tours have the same shape one step ahead. A Tour has its own tab ([ADR-0033](0033-tours-and-tour-qualifications.md)), but an officer edits Tours and the Tour rules in two cards on the Settings tab. ADR-0027 §2's rule, "a setting moves, a record stays", put them there. Tours are the one list with its own tab that is edited on a different tab. Objects would be the second as soon as they get a tab. Docents' tour data sheets are Folders named after Tours ([ADR-0030 §2](0030-document-library.md)), with no link between the two.

## Decision

### 1. `has_objects`, the seventh capability

**A Group switches Objects on with its own flag, `has_objects`.** Objects no longer depend on `has_scheduling`. The super-tier sets the flag on the structure form in DMV Settings, beside the other six ([ADR-0036 §5](0036-group-administration.md)).

- **Reserving an Object on a Sign-up needs both flags on.** With `has_scheduling` off, the Objects tab still works, and nothing can reserve an Object.
- **The flag cannot go off while any future Sign-up in the Group carries an Object.** The form blocks the save and gives the count. This follows the role guard of ADR-0036 §5. The officer removes the Objects from those Sign-ups first, or waits. Past Sign-ups and the Object rows stay. The tab and its routes return 404 while the flag is off, as for every other capability.

**Seeded on:** Gallery Interpreters only. Outreach stays off until its presentations work is specced. Docents and Guides du ROM stay off, because their objects are Folders.

### 2. The name stays "Object"

**The capability, the tab and the record are called Object.** GI and Outreach both lend out real museum handling objects, and their Members say "object". Favourites and information packages make sense only for museum objects. The general part of the model already works for any lendable thing: a Sign-up reserves it, it cannot be double-booked, and an off-site kind widens the hold. If a Group someday lends something that is not a museum object, a per-Group display label can be added then (§12).

### 3. The Objects tab

**A new Group tab, Objects, placed after Tours.** It shows while `has_objects` is on. Its row in the [ADR-0037 §1](0037-group-tab-visibility.md) matrix copies the Tours row:

| Tab     | Non-member       | Member | Officer extras                 |
| ------- | ---------------- | ------ | ------------------------------ |
| Objects | join prompt (§2) | yes    | Scheduler/Chair manage Objects |

**What a Member sees:**

- The active Objects in sort order. There is no detail page per Object.
- Each Object's linked Folder (§5), if it has one.
- An **Out** badge when the Object is held at any time from now to the end of the seventh day ahead. The badge gives the first hold, "Out Tue 11:00". This is the [ADR-0026 §3](0026-gallery-interpreters-self-serve-shifts.md) clash query read the other way, and the hold follows the off-site rule of §4. The badge is hidden while `has_scheduling` is off.
- The Group's **Favourites** (§8).

**What an officer adds:** add, rename, reorder, deactivate and reactivate Objects, and pick each Object's Folder. These come from the Objects card on Settings, which is removed (§4).

### 4. A list that has its own tab is edited on that tab

**[ADR-0027 §2](0027-group-settings-tab.md) gains one rule:** a list that has its own tab is edited on that tab, not on Settings. The rule "a setting moves, a record stays" still holds for everything else.

- **The Objects card leaves Settings** for the Objects tab.
- **The Tours card and the Tour rules card leave Settings** for the Tours tab. Their gates do not change: Vetting (with Chair) manages Tours, and Chair manages the Tour rules ([ADR-0033](0033-tours-and-tour-qualifications.md)).
- Shift kinds stay on Settings. They have no tab of their own.

This is the same reason ADR-0027 gave for keeping the About text on the Overview: the officer edits a thing in the place where it is read.

### 5. A Folder link on each Object and each Tour

**`objects.folder_id` and `tours.folder_id`: nullable, set by the officer from the Group's own Folders.** The Objects and Tours tabs show the link to Members, and the Member opens the Folder in the Document library. The reader's access to the Folder is checked as usual. A Member who cannot read the Folder sees the name without a link.

- **The library never depends on Objects or Tours.** The dependency goes one way and is nullable. When a Folder is deleted, the link clears.
- **The library keeps its own names.** [ADR-0030 §2](0030-document-library.md) accepted that a Tour's name exists twice, once as the Tour and once as the Folder. The link does not change that. A rename still needs two edits.
- **The demo seed links each Docents Tour to its Folder of the same name.** The seeder already builds both.

### 6. Who manages

**A new `ObjectPolicy`, gated on `has_objects`.** `manage` lets the Group's Scheduler and Chair add, rename, reorder, deactivate and link Objects, and remove any Favourite (§8). `view` lets the Group's Members see the tab. `SchedulePolicy::manageObjects` is removed. No new role. When Outreach is specced, it decides whether its presentations maintainer needs this right.

### 7. Reserving Objects

**[ADR-0026 §3](0026-gallery-interpreters-self-serve-shifts.md)'s sign-up rule becomes:** when `has_objects` is on and the Group has an active Object, a self-serve Sign-up must carry at least one. The clash rule and the off-site hold do not change.

**The picker marks taken Objects before save.** When the dialog has a start and a length, it asks the server which Objects are held then. Each held Object shows as disabled, with "Out at :when". The server check on save stays, and it is still the rule. The picker only shows the answer early.

### 8. Favourites

**A Favourite is one Member's note on one Object: the Object and a "why I like it" text of up to 255 characters.** It is ported from legacy GI's `giFavourite` and `giFavouriteLikes`.

- **Any Member of the Group adds a Favourite**, one per Member per Object.
- **Every Member of the Group sees every Favourite** on the Objects tab: the Object, the note, the poster's name and the like count.
- **A Member likes a Favourite once.** There is no unlike. The poster's own like is added when they post, so the poster cannot like it again. This is the legacy design.
- **The poster edits or removes their own Favourite.** An Object manager (§6) may remove any Favourite. The server checks both.
- **A Favourite on an inactive Object is hidden.** The row stays and shows again if the Object comes back.

### 9. The Object usage report

**A port of legacy GI's Object Usage Statistics, under the Hours tab's reports**, beside the Tour Summary ([ADR-0032 §12](0032-docents-gdr-bookings.md)). It uses the same shape as the Tour Summary: a print page and a CSV export, one fiscal year (April to March) selected with `?fy=YYYY`, and the `viewReports` gate (Chair or Statistician of the Group or an ancestor, and the super-tier).

- **It counts units, not visitors and not Sign-ups.** For each Object, it adds up the units of every Sign-up that carries the Object. A unit is the Shift's length divided by the Group's `self_serve_unit_minutes`. Legacy sums `gischeduledTours.Count`, which is units.
- **Rows are Object × shift kind.** Columns are the year total and one column per month.
- **Only past months count.** Legacy skips the current month and later. Sign-out is not required: legacy counts every filled row.

### 10. Trainers stay out

Legacy GI keeps a list of Trainers, which is a flag on a GI Member that the Chair sets. It is a role question for GI, not an Objects question, and it is not decided here (§12).

### 11. Deviations from the Legacy app

1. **A Favourite has no gallery.** Legacy pairs each favourite with a gallery from `giGalleryTheme`, and keeps one per Object and gallery. v2 has no gallery record: galleries became Folders and shift kinds. A gallery picker would tie Favourites to the shape of the library. A Favourite is the Object and the note, one per Member per Object. The import adds the legacy gallery name to the front of the note, so the text is not lost.
2. **The server checks who may edit or remove a Favourite.** Legacy hides the buttons and checks nothing on the server.
3. **An Object manager may remove any Favourite.** Legacy gives no one this right. A shared, Member-written list needs a moderator.

### 12. Not decided here

- **Trainers** (§10).
- **A per-Group display label** for Objects (§2). Add it when a Group lends something that is not a museum object.
- **Outreach.** Its objects go with presentations. Turning `has_objects` on for Outreach, and who manages its Objects, wait for that spec.

## Interaction with the import

- `giArtefact` rows with `hasObject = 1` → Objects. This does not change ([ADR-0026 §10](0026-gallery-interpreters-self-serve-shifts.md)).
- `giFavourite` → Favourites. The legacy gallery name goes at the front of the note (§11). If a Member has more than one favourite on the same Object, the import keeps the earliest and appends the other notes to it.
- `giFavouriteLikes` → likes. The import skips the poster's own like when it would duplicate the like added on post.
- **v2-only** (add to the [ADR-0036](0036-group-administration.md) list): `has_objects`, `objects.folder_id`, `tours.folder_id`.

## Considered alternatives

- **Keep Objects behind `has_scheduling`.** Rejected. Outreach keeps objects for presentations, not shifts. A flag of their own costs one column and gets an admin home in DMV Settings for free.
- **A general name, such as Item or Resource.** Rejected for now (§2). "Item" is vague, "Resource" is IT language, and neither fits Favourites or information packages.
- **A Tour or an Object that owns and creates its Folder.** Rejected. ADR-0030 kept the library free of scheduling, and this would merge them again.
- **Leave the Tours and Objects cards on Settings.** Rejected. An officer would edit a list on one tab and read it on another. The ADR-0027 rule was written before any list had its own tab.
- **A Favourite with a Folder as its gallery.** Rejected (§11).
- **An unlike toggle.** Rejected. It costs almost nothing, but legacy has no unlike, and no Member has asked for one.
- **A usage report that counts visitors.** Rejected. Legacy counts units, and the report answers how much each Object is out.

## Consequences

- **A seventh capability flag**, `has_objects`, on the structure form in DMV Settings. GI's seed turns it on.
- **A new Objects tab**, after Tours, and a new row in the ADR-0037 matrix.
- **New tables:** `favourites` and `favourite_likes`. **New columns:** `groups.has_objects`, `objects.folder_id`, `tours.folder_id`.
- **`ObjectPolicy`** replaces `SchedulePolicy::manageObjects`.
- **Settings loses three cards:** Objects, Tours and Tour rules. Each goes to its tab.
- **The self-serve and sign-up dialogs** ask the server which Objects are held before save.
- **A new report under Hours:** Object usage, print and CSV.
- **Help articles** ([ADR-0025](0025-help-centre.md)): a new Objects article. Re-shoot `objects-and-off-site-stations`, `tours-and-qualifications`, `group-settings` and `write-your-own-shift`.

## References

- [ADR-0026](0026-gallery-interpreters-self-serve-shifts.md): Objects, the clash rule and the off-site hold. §3 amended here.
- [ADR-0027](0027-group-settings-tab.md): the Settings tab. §2 amended here.
- [ADR-0030](0030-document-library.md): the Document library and the twice-named Tours.
- [ADR-0032](0032-docents-gdr-bookings.md): the Tour Summary, the pattern for §9.
- [ADR-0033](0033-tours-and-tour-qualifications.md): Tours and the Tours tab.
- [ADR-0036](0036-group-administration.md): capability flags, the role guard and the v2-only list. §5 amended here.
- [ADR-0037](0037-group-tab-visibility.md): the tab matrix. §1 amended here.
