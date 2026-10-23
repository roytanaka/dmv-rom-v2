---
status: accepted
date: 2026-10-10
accepted: 2026-10-10
---

# Tours and tour qualifications for Docents and GDR

From the same grilling session as [ADR-0032](0032-docents-gdr-bookings.md). In the old app, a Docents or Guides du ROM (GDR) daily-tour slot is a label, and the tours behind it are concrete. "Museum Highlights" is one tour. "Gallery/Theme" lets the docent give any of 48 tours, and the docent picks one at sign-up from the tours they are qualified for. A group tour also names a concrete tour. In v2 a **shift kind** is only the slot label, so there is nowhere to put a concrete tour and nothing to hang a qualification on. [ADR-0021](0021-scheduling-first-pass.md) §3 decided that qualifications hang off the kind and built none of it, and left open (#351 B3) whether Docents' kinds are kinds or tours. This ADR answers both, as a faithful port. See `GLOSSARY.md` for **Tour**, **Qualification** and **Last vet date**.

## Decision

1. **Each Group with tours keeps its own Tour list.** A Tour has a name, an active flag, and an **open to all** flag. Docents (76 active tours) and GDR (38) have one. A shift kind maps to one or more Tours. This replaces the old app's slot-to-tour mapping table and keeps the shift kind as the slot label. Making every Tour a shift kind was rejected: it removes the "pick your tour" slot, which is two thirds of Docents' 2026 sign-ups.

2. **A Sign-up records its Tour.** When a Shift's kind maps to one Tour, the Sign-up takes it. When it maps to several, the Member picks one from those they may give. A Booking names one Tour ([ADR-0032](0032-docents-gdr-bookings.md)).

3. **A Qualification is a Member holding a Tour.** It carries an active flag and a **Last vet date**. An inactive qualification is kept but counts for nothing. The Last vet date is shown, never enforced: the old app has no expiry and no "due for vetting" list, and its dates are entered in batches.

4. **The Vetting role keeps qualifications.** v2 already has the role, behind the Group's vetting capability, and nothing uses it yet. The old app calls the same power Education (Docents) and Evaluation (GDR). The Chair implies it. There are two screens: **by Tour** (who may give this Tour) and **by Member** (which Tours this Member gives). Each can add, remove, and change the Last vet date.

5. **Who sees qualifications.** Members of the Group see who gives each Tour and their Last vet dates, as on the old app's Who's Who tour lists.

6. **Enforcement.** A Member needs an active qualification for the Tour to take a Shift themselves, to receive a Booking's Request mail, and to be named as a substitute. A Tour flagged open to all needs none; this replaces the old app's hard-coded tour ids (Museum Highlights, Custom (Group), Family, ROMCAP for Docents; Le choix du guide and Les trésors for GDR). A **Scheduler** or **Booker** may still place any Member. The old app allows it, and about 15% of 2025 group-tour seats rely on it. *Deviation, security:* the old app checks qualification only in the browser. Here the server checks it.

7. **Status rules, run when a Membership status changes.** Docents: a new Trainee gets only the trainee tour, "Museum Highlights – New Docents"; becoming Full activates Museum Highlights; Emeritus, Resigned or Deceased makes every qualification inactive; LOA changes nothing. The old code's comment says LOA deactivates, but the code does not, and that is what Docents live with. GDR: becoming Full adds its two starter tours; LOA, Emeritus, Resigned or Deceased makes every qualification inactive. Which Tours are the trainee and starter Tours is a per-Group setting, not a hard-coded id. *Deviation, cheap:* the old GDR code deletes the rows, and re-adding them duplicates rows. Here they are made inactive, so history is kept.

## Consequences

- The content catalog's Sections (legacy's `Category → Section → Tour`, [ADR-0010](0010-group-model.md)) are not built. The Tour list is flat. Sections can be added later without reshaping Tours.
- The Docents and GDR demo data must seed Tours, the kind-to-Tour mapping, and qualifications, or no Docent can sign up on staging.
- Moving the old app's qualifications across is the legacy-data migration's job.
- The old app's separate "Is this a vetting tour?" mark on a sign-up is not ported. It never updates the Last vet date.
