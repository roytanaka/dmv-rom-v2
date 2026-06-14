---
status: accepted
date: 2026-05-15
---

# Document storage architecture

## Context

The app needs to manage roughly 40 GB of documents — committee meeting minutes, member records, program materials — uploaded by volunteers and downloaded by other volunteers, with optional human-curated titles.

Document handling has three structural requirements:

1. **Authorization.** Volunteer PII appears in many documents; download URLs must enforce per-document access policy, not rely on URL obscurity.
2. **Stable opaque identifiers.** User-facing filenames (with spaces, accented characters, collisions) are unsuitable as on-disk paths. The system needs identifiers that survive renames and character-encoding edge cases.
3. **Single entity to query.** Cross-cutting operations — search, audit, retention policy, bulk migration — require one `documents` table, not per-feature pointer columns scattered across the schema.

## Decision

The rebuild treats **Document as a first-class entity** with a single `documents` table, opaque disk storage, and a controller-gated download path. The model is:

1. **Files live in `storage/app/documents/`, outside the webroot.** Apache never serves them directly.
2. **Disk filename is a UUID with no extension.** The user-facing filename and MIME type are stored in DB columns and applied at download time via `Content-Disposition: attachment; filename="..."` (Laravel's `Storage::download()` handles this).
3. **Every download routes through a controller.** The controller authenticates the user, authorizes via `DocumentPolicy`, logs the access, then streams the file.
4. **One `documents` table for the whole app.** Schema (minimum) per `docs/conventions.md § Documents`: `id`, `original_filename`, `storage_path`, `mime_type`, `size_bytes`, `uploaded_by_id`, `uploaded_at`, `visibility` enum (`public` / `members` / `committee`), optional `title` / `description` (single-column, rendered as-authored — content is not translated; see [ADR-0004](0004-chrome-only-translation.md)).
5. **Ownership is expressed via multiple explicit nullable FKs** on the documents table — `committee_id`, `program_id`, `meeting_id`, etc. — added per-feature as migration scripts surface them. **Not polymorphic.**
6. **Storage backend is the local filesystem on Stormweb.** Backups are covered by Stormweb's daily off-site backups (see [ADR-0002](0002-stay-on-stormweb-shared-hosting.md)).
7. **Legacy document URLs are preserved via an Apache redirect map** built at migration time (see "Legacy URL preservation" below).

## Considered alternatives

- **Object storage (S3-compatible: Backblaze B2, Cloudflare R2, Wasabi).** Real benefits at scale (signed URLs, dedup, cross-region durability). Rejected for v1 because (a) Canadian data residency requires a Canadian-region provider, narrowing the options and complicating verification, (b) Stormweb's Enterprise package includes the storage we need, (c) the architecture rule against third-party SaaS integrations in v1. Worth revisiting only if Stormweb storage becomes a bottleneck or if we need signed-URL features local FS can't provide.
- **Polymorphic ownership (`owner_type` + `owner_id`).** Flexible but loses query clarity, breaks foreign-key constraints, and is the kind of generic abstraction the project's architecture rules push back against ("No premature abstraction. A pattern earns extraction after appearing three times."). Multiple explicit FKs are more verbose but easier to reason about, easier to index, and easier to validate.
- **Content-addressed storage (hash-based disk filenames).** Gives free deduplication. Rejected because the operational complexity (re-hashing on edit, handling collisions, reference counting before delete) isn't worth the storage savings at ~40 GB scale, and UUID names give us the same opacity benefit without the bookkeeping.
- **Per-feature pointer columns instead of a unified `documents` table.** A common legacy-style pattern. Rejected because the absence of a unified Document entity is what makes cross-cutting operations (search, audit, retention) impossible.
- **Hard-break legacy URLs at cutover.** Rejected because legacy URLs are embedded in old emails, committee wikis, calendar invites, and volunteer-onboarding PDFs we don't control. The migration script already touches every legacy file to copy it — building a redirect map from the same data is near-free at migration time.

## Consequences

### Legacy URL preservation

The migration script that copies legacy files into `storage/app/documents/` also emits an Apache redirect map (or a `RewriteMap` source file) mapping each legacy path to the corresponding new app URL `/documents/{id}/download`. The redirect goes through the new controller, so authorization is enforced at the new endpoint. Volunteers who follow an old link see the standard login flow if they aren't authenticated, then land on their document.

The map persists indefinitely. Old URLs are part of the system's external surface area, and gratuitous breakage isn't justified by maintenance overhead (the map is static, generated once at migration). If we ever leave Apache, porting the map to nginx/other is a known one-time task.

### Visibility defaults are deliberate

The migration script sets each document's `visibility` enum at copy time:

- Program documents whose intended audience is the program's membership → `members` visibility (any logged-in volunteer can read).
- Committee documents → `committee` visibility (only that committee's members can read).
- A small explicit allowlist of intentionally-public documents (e.g., public program brochures linked from the marketing site) → `public` visibility (no auth required, served by the same controller without a policy check).

The default for ambiguous documents is `members`, not `public` — authorization-by-default, with explicit opt-out.

### Schema evolves per-feature, not up-front

The minimum documents schema is committed; ownership FKs (`committee_id`, `program_id`, `meeting_id`, etc.) are added one at a time as per-feature migration scripts encounter them. We accept short-lived schemas (a feature ships, then a later migration adds a new FK column for a new ownership relationship) over a speculative all-up-front schema.

### Storage growth path

Stormweb's Enterprise plan is "unlimited" storage but shared-host I/O is shared-host I/O. If document growth or download volume hits the load-ceiling trigger in [ADR-0002](0002-stay-on-stormweb-shared-hosting.md), object storage (Canadian region) becomes a candidate response. The application layer is decoupled enough that swapping the storage driver later is a localized change (`config/filesystems.php` + a migration to re-key files).

## Open implementation questions

- File size limit per upload, and MIME-type allowlist (start narrow: PDF, common Office, common image; expand on demand).
- Audit log retention policy (every download is logged — for how long?).
- Whether `public` visibility documents bypass the controller entirely (faster) or always route through it for consistent audit (slower but cleaner).
- Virus scanning at upload (defer until a feature need exists; not in v1).

## References

- `docs/architecture.md § Document storage architecture`
- `docs/conventions.md § Documents`
- [ADR-0002](0002-stay-on-stormweb-shared-hosting.md) — Stormweb hosting + storage trade-offs
