---
status: accepted
date: 2026-10-07
accepted: 2026-10-07
---

# The Document library: one capability, arranged by each Group

From a grilling session on 2026-10-07. [ADR-0010](0010-group-model.md) listed two capabilities for files: the **document library** and the **content catalog**. Legacy research shows both are the same thing from a reader's view: files in a hierarchy that a Group arranges the way it likes. Legacy's general library is Topic → Sub-topic → file. Docents' Data Sheets are Category → Section → Tour → file. Outreach, Gallery Interpreters, Guides du ROM and ROMWalks each keep a near copy of the Data Sheets pattern. This ADR merges them into one **Document library** and settles its shape. See `GLOSSARY.md` for **Document library**, **Document**, **Folder**, **Tag** and **Librarian**.

## Decision

1. **The content catalog merges into the Document library.** There is one capability, `has_documents`. The `has_content_catalog` flag and the Content Maintainer role are removed. The **Librarian** uploads, arranges, tags, replaces and deletes Documents in their Group.

2. **Tour records stay out of the library.** In legacy, a Docents Tour is a folder of Data Sheets and also the thing a Docent is vetted on, signs up for, and is counted under in stats. The library takes only the files. The Tour as a schedulable, vetted thing is a **Shift kind** ([ADR-0021](0021-scheduling-first-pass.md) §3). This answers ADR-0021's open question: Docents' shift kinds are `ShiftKind` rows. A Librarian names folders after tours, so the list of tour names exists twice. A rename needs two edits. We accept that cost so the library does not depend on scheduling.

3. **Folders.** Each Group's library is a tree of **Folders**, at most 5 levels deep. The limit is one constant in code. It guards the screens against sprawl, not the data model, and we raise it when a Group needs more. Legacy never went past 3 levels. A Document sits in exactly one Folder, or at the library root.

4. **Tags.** A Group defines its own **Tags**. A Document can carry many. Readers filter the library by Tag. Tags give the views that cut across folders: Data Sheets' Required, Highlights and Script flags, the Museum Highlights tour, and Outreach's split by file type. A Document is never in two folders.

5. **Visibility is set on top-level Folders.** There are two values: `group` (members of the owning Group) and `members` (every signed-in Member). Subfolders and Documents inherit the setting from their top-level Folder, like legacy Sub-topics inherited from their Topic. The library root is `group`. There is no `public` value until a real case needs one.

6. **Parentage never grants a read.** A parent Group's Chair reads a child's library only by joining the child. [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md) holds, and [ADR-0022](0022-hours-and-statistics-model.md)'s Hours exception does not extend to Documents. Documents can hold PII. The committee asks that every sub-group has Documents ([#308](https://github.com/roytanaka/dmv-rom-v2/issues/308)). That means turning `has_documents` on for those Groups.

7. **A Document is a file or a link.** Legacy has 85 library rows that are URLs. A link Document opens its URL after the same access check as a file download.

8. **Replace keeps the Document.** A Librarian can upload a new file over a Document. Its id, link, Folder and Tags stay the same. The old file is deleted. There is no version history.

9. **No "Original" flag.** Legacy kept editable source files apart from published ones. Here a source file is an ordinary Document. A Librarian who wants to keep sources from readers puts them in a `group` folder.

10. **No folder-scoped roles.** Legacy Section Heads edit only their own Section's Data Sheets. Here the Librarian role covers the whole library, and former Section Heads become Librarians. The Section Head's stats scope is a separate question for the stats work.

11. **Per-Group only.** Every Document belongs to one Group (`group_id`). DMV-wide Documents, including legacy's How-To, wait for the DMV root Group ([#288](https://github.com/roytanaka/dmv-rom-v2/issues/288)). How-To then moves under Help.

12. **Limits.** One upload is at most 1.5 GB, so every legacy file fits. The largest legacy file is 1.2 GB. 99% of legacy files are under 41 MB. Allowed types: pdf, doc/docx, xls/xlsx, ppt/pptx, jpg/png/webp/gif, txt, csv, mp4, mp3, zip. Uploads show a progress bar. There is no chunked upload and no virus scan. Add chunking only when a Librarian hits failed large uploads. The legacy migration copies files to disk directly and skips the upload limit.

13. **The storage layer ships first.** Upload, storage, the gated download, the access log and the legacy redirect map are built once ([ADR-0003](0003-document-storage-architecture.md)). The library UI builds on them. Meetings and Schedules published as files reuse the same layer.

## Considered alternatives

- **Keep a separate content catalog of structured Tour records.** Rejected. Documents would wait on the Docents scheduling work, and every other Group gets the same plain library anyway.
- **Folders that are the Tour records.** Rejected. The library would depend on scheduling, and a Group without tours gets fields it does not use.
- **Unlimited folder depth.** Rejected for the screens, not the data. Breadcrumbs wrap and the move-to-folder picker gets hard to scan on a phone.
- **Folders only, no Tags.** Rejected. Museum Highlights and the Data Sheets flags need a view across folders, and three legacy Groups show the same need.
- **Visibility per Document.** Rejected. Legacy set it per Topic, and a Librarian sets it once per folder instead of once per file.
- **Parent Chair reads a child's library without joining.** Rejected. It is the silent cross-Group read ADR-0019 exists to stop.
- **Chunked or resumable uploads now.** Deferred. It needs a server package and a client widget, or about 100 lines written by hand. About 20 legacy files are over 250 MB.

## Consequences

- **Amends [ADR-0003](0003-document-storage-architecture.md).** Ownership is `group_id`, not `committee_id`. Visibility moves to Folders with the values `group` and `members`. A Document may be a link. The size and type questions are answered. (Amendment note added there.)
- **Amends [ADR-0010](0010-group-model.md).** The content catalog leaves the capability set, and the Docents Section is no longer the demonstrated scoped role. (Amendment note added there.)
- **Answers [ADR-0021](0021-scheduling-first-pass.md)'s open question** on Docents' shift kinds. (Amendment note added there.)
- `docs/conventions.md` §Documents still says `committee_id` and the old visibility values. The Documents spec updates it.
- Legacy has 19.5k files on disk but about 4.2k active library rows. What happens to unreferenced files belongs to the legacy data migration, not this ADR.

## References

- [ADR-0003](0003-document-storage-architecture.md): document storage
- [ADR-0010](0010-group-model.md): Group model and capabilities
- [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md): parentage never grants a content read
- [ADR-0021](0021-scheduling-first-pass.md): Shift kinds
- [#290](https://github.com/roytanaka/dmv-rom-v2/issues/290), [#308](https://github.com/roytanaka/dmv-rom-v2/issues/308)
