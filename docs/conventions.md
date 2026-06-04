# Conventions

How we write code in this project. When in doubt, follow the patterns here over what you might do elsewhere.

## Naming

- **Models:** singular, PascalCase (`Member`, `Document`, `Committee`).
- **Tables:** plural, snake_case (`members`, `documents`, `committees`).
- **Pivot tables:** singular models in alphabetical order (`committee_member`, not `members_committees`).
- **Columns:** snake_case. Foreign keys are `{model}_id` (`committee_id`, `uploaded_by_id`).
- **Routes:** kebab-case (`/committees/{committee}/documents`).
- **Vue components:** PascalCase files (`MemberList.vue`, `DocumentUploader.vue`).
- **Vue pages:** PascalCase files in `Pages/`, organized by feature (`Pages/Members/Index.vue`, `Pages/Documents/Show.vue`).
- **Services:** PascalCase ending in `Service` (`DocumentService`, `SchedulingService`).
- **Form requests:** PascalCase ending in the action (`StoreMemberRequest`, `UpdateDocumentRequest`).
- **Policies:** singular model name + `Policy` (`DocumentPolicy`).
- **shadcn-vue components:** PascalCase, kept in `resources/js/Components/ui/{component}/` as the CLI generates them. Don't rename or relocate.

## Controllers

- Resource controllers when they fit (`MemberController` with `index`, `show`, `store`, `update`, `destroy`).
- Single-action invokable controllers for one-off endpoints (`DownloadDocumentController`).
- Controllers do four things: validate (via form request), authorize (via policy), call a service, return a response. That's it.

```php
public function store(StoreDocumentRequest $request, DocumentService $documents)
{
    $this->authorize('create', Document::class);
    $document = $documents->upload($request->validated(), $request->file('file'));
    return redirect()->route('documents.show', $document)
        ->with('success', __('documents.uploaded'));
}
```

## Validation

- Form requests live in `app/Http/Requests/`.
- One form request per controller action that accepts input.
- Validation rules go in the form request, not the controller.
- Custom messages come from translation files (bilingual support).

## Authorization

- Every model with access restrictions has a policy.
- Policies live in `app/Policies/`.
- Use `$this->authorize()` in controllers or `Gate::authorize()` in services.
- For Inertia responses, use `can` props to drive UI:

```php
return Inertia::render('Documents/Show', [
    'document' => $document,
    'can' => [
        'edit' => $request->user()->can('update', $document),
        'delete' => $request->user()->can('delete', $document),
    ],
]);
```

## Documents

This is the most important convention in the project — get it right.

**Storage:**
- Documents live in `storage/app/documents/`, never in `public/`.
- Disk filenames are UUIDs with no extension (`7f3a9b2c-4d8e-11ee-be56-0242ac120002`).
- The MIME type and original extension are stored in the database, not the disk filename.

**Database fields (minimum):**
```
id
original_filename       // "March 2024 Board Minutes.pdf" — what the user uploaded
storage_path            // "documents/7f3a9b2c-..." — opaque, internal
mime_type               // "application/pdf"
size_bytes
uploaded_by_id          // FK to users
uploaded_at
committee_id            // FK to committees, nullable
visibility              // enum: 'public', 'members', 'committee'
title_en, title_fr      // optional human-curated titles, bilingual
description_en, description_fr  // optional
```

**Upload flow:**
1. User submits a file via a form.
2. Form request validates (size, MIME type allowlist, max length on filename).
3. Controller calls `DocumentService::upload($validated, $file)`.
4. Service generates a UUID, sanitizes the original filename for storage in DB (preserves spaces and accents, strips dangerous characters), saves the file with the UUID name, creates the DB record, returns the model.

**Download flow:**
1. User hits `GET /documents/{document}/download`.
2. Controller authorizes via `DocumentPolicy@download`.
3. Controller logs the access.
4. Controller calls `Storage::download($document->storage_path, $document->original_filename)`. Laravel sets `Content-Disposition` automatically with the friendly filename.

**Sanitization rules for original_filename:**
- Allowed: letters (including accented), numbers, spaces, hyphens, underscores, periods, parentheses, apostrophes.
- Stripped: null bytes, control characters, path traversal sequences (`..`, `/`, `\`), reserved Windows names (`CON`, `PRN`, etc.) get a suffix.
- Length capped at 200 characters.

## Internationalization

- Every user-facing string goes through `__('key')` (PHP) or the i18n helper in Vue.
- Translation keys are namespaced by feature: `members.profile.title`, `documents.upload.success`.
- Both `lang/en/*.php` and `lang/fr/*.php` are updated together. Never ship English-only.
- Translatable model fields use suffix convention: `title_en`, `title_fr`. The model has a `title` accessor that returns the right one based on the current locale.

### URL routing

Bilingual URL strategy is fixed by [ADR-0008](adr/0008-bilingual-url-routing.md). Read it before touching routes.

- English is the default locale and lives at the root (`/volunteers/123`). French lives under `/fr/` with **translated path segments** (`/fr/benevoles/123`). Routes are added French-second as features become available.
- Route segment translations live in `lang/en/routes.php` and `lang/fr/routes.php` alongside the UI string files. Every translatable segment is keyed there.
- Declare translatable routes via `LaravelLocalization::transRoute('routes.volunteers.show')` — the `mcamara/laravel-localization` package resolves them per-locale.
- Build language-switcher links with `LaravelLocalization::getLocalizedURL($locale)`. Hide the alternate-locale link on pages where no equivalent route is registered.
- No per-record translated slugs in v1 — dynamic resources use IDs (`/fr/exhibits/123`, not `/fr/exhibits/vikings-au-rom`). No `slug_en`/`slug_fr` columns.
- The `Volunteer` model has a `locale` column (default `'en'`). Post-login redirects and system-generated URLs (password resets, notification emails, calendar links) read it; the toolbar selector writes it when authenticated.
- Direct visits are URL-authoritative — no auto-redirect based on saved preference. Bookmarks always render the locale in their URL.
- A `/fr/...` request for an untranslated route returns a locale-aware 404 with a "request translation" CTA, not the English page.
- Every translatable route should have a smoke test that hits both `/x` and `/fr/x-translated` and asserts the resolved Eloquent model is the same.

## Frontend (Vue + Inertia)

- One page component per route, in `resources/js/Pages/{Feature}/{Action}.vue`.
- Page components receive Inertia props. They don't fetch their own data.
- Reusable UI lives in `resources/js/Components/`.
- Forms use Inertia's `useForm()` helper. Don't roll your own form state.
- Composition API only. No Options API.
- `<script setup>` syntax.
- Tailwind for all styling. No scoped `<style>` blocks unless absolutely necessary.
- Use shadcn-vue components from `resources/js/components/ui/` for UI primitives (Button, Dialog, DropdownMenu, Input, Select, etc.). Add new ones with `pnpm dlx shadcn-vue@latest add <component>` (run on the host, not in Sail). The CLI reads `components.json`; its `aliases` must stay `@/`-prefixed (e.g. `@/components/ui`) so the CLI can resolve them via tsconfig `paths` — bare paths break newer CLI versions.
- Edit shadcn-vue component source freely when needed — they live in our repo and we own them. Document non-trivial customizations in a comment at the top of the file.

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  committee: Object,
  can: Object,
})

const form = useForm({
  name: props.committee.name,
})

const submit = () => form.put(route('committees.update', props.committee.id))
</script>
```

### Styling and the design system

- **Heritage-blue accent.** The brand's single interactive hue is exposed as Tailwind utilities via `@theme inline` in `resources/css/app.css`: `rom-slate`, `rom-slate-50`, `rom-slate-300`, `rom-slate-700`. Use `text-rom-slate` / `hover:text-rom-slate-700` for links and interactive accents, `bg-rom-slate-50` for the slate-tinted wash, and `ring-rom-slate` / `border-rom-slate` for accented focus. Reference these tokens rather than arbitrary hex or neutral greys so the accent stays consistent and a future token change propagates everywhere. The `--color-info{,-bg}` tokens carry the same value but belong to the status family (badges/banners) — use the `info` utilities for status, the `rom-slate` utilities for interactive accents.
- **Icons — Phosphor.** The icon library is [`@phosphor-icons/vue`](https://github.com/phosphor-icons/vue) (Lucide is fully removed). Following the upstream shadcn-vue pattern, import the specific icon you need by name and render it directly — e.g. `import { PhMagnifyingGlass } from '@phosphor-icons/vue'` then `<PhMagnifyingGlass class="h-4 w-4" />`. Phosphor's component names are `Ph` + the PascalCase glyph name. Use the **regular** weight (the package default) — don't set `weight=` unless a specific case demands it. There is no `Icon.vue` name-string wrapper; a Phosphor-backed semantic wrapper can be reintroduced if a product screen needs to pick icons by name at runtime. The `NavItem.icon` type (`resources/js/types/index.ts`) aliases a Phosphor icon component rather than importing a library-specific type.
- **Square by default.** Components square their corners at the component level (`rounded-none` in the `cva` base, e.g. `resources/js/components/ui/button/index.ts`). The `--radius` ramp and the `rounded-*` utilities stay defined as a deliberate escape hatch for the rare element that should round — don't zero the ramp to enforce squareness. True circles (`rounded-full`, e.g. avatars) stay round.
- **Consume tokens, never hard-code.** Components reference the CSS-variable-backed utilities (`bg-primary`, `text-rom-slate`, `text-destructive`, `border-input`, …) rather than literal hex values or pixel radii, so a token change in `resources/css/app.css` propagates everywhere.
- **Badge tone taxonomy.** The `Badge` component (`resources/js/components/ui/badge/`) carries the status-chip tone system in its `badgeVariants` cva: `default` (solid `bg-primary`), `secondary` (neutral), `info` (`bg-rom-slate-50` / `text-rom-slate`), `success`/`warning`/`destructive` (the soft-tint pattern — a tinted `-bg` surface with coloured text), and `outline`. `info` references the `rom-slate` utilities, which resolve to the same value as the `info` status tokens (`--info` → `--rom-slate`), so the chip is interchangeable with `bg-info-bg` / `text-info`. For the on-tint text, `success` uses its solid token (`text-success`, ≥4.5:1), but `warning` and `destructive` solid hues are too light on their own tint to clear AA, so each uses a dedicated **dark on-tint foreground** (`text-warning-foreground` ≈ 13:1; `text-destructive-tint-foreground` ≈ 5.6:1) — solid `text-warning` / `text-destructive` would fail the small-text floor. **Deliberate divergence:** `destructive` is *soft-tinted* (`bg-destructive-bg`), unlike upstream shadcn's solid-red fill and unlike our own destructive *Button* (which keeps its solid `bg-destructive` fill) — status chips stay calm. The optional `dot` prop renders a leading `rounded-full` circle tone-matched via `bg-current` (the documented true-circle exception to square-by-default).
- **Table — ROM listing identity.** The `Table` component (`resources/js/components/ui/table/`) bakes the ROM listing look into the upstream parts' defaults — classes restyled **in place** (no restructuring) so registry updates stay mergeable. Every table renders it automatically: a 2px black top rule (`border-t-2 border-primary` on the `<table>`), uppercase bold black column labels at the `text-xs` size on a 1px black underline (`text-xs font-bold uppercase text-primary` on `TableHead`; `border-primary` on the `TableHeader` row — no muted-gray header band), 18px row text (`text-base` on the `<table>`, inherited by cells), generous cell padding, hairline neutral row separators, and **no zebra**. Hover tints the **whole row** a quiet neutral (`hover:bg-muted`); the selected row takes the heritage-blue wash (`data-[state=selected]:bg-rom-slate-50`). The `text-xs` uppercase heads are the sanctioned passive-label exception to the 15px floor (same category as `.eyebrow`). The above-table toolbar (filters / search / count / sort) is a **per-screen composition**, not part of the primitive.
- **Responsive type scale.** The upper type steps (`--text-2xl` … `--text-5xl` in `resources/css/app.css`) are fluid via `clamp(min, vw, max)` — they step down toward the min on small screens (≈min at ~375px) and reach the design max by ~640px, so a 42px `h1` / 54px hero don't dominate a phone viewport. This is the single source of truth: size headings with the role-named token (`text-4xl` for a page title, `text-5xl` for a hero) and let the token handle the breakpoints — don't add per-heading responsive size classes. The `xs`–`xl` steps stay fixed (body/UI text doesn't need to scale).
- **Form-field alignment.** Forms default to a **single column** — most readable for the audience, immune to label-wrap misalignment, and bilingual-safe (French labels run ~15–25% longer and wrap where English doesn't). For genuine field pairs (First/Last name, City/Postal), use CSS **subgrid** (`grid-rows-subgrid`) so the label/input/error row tracks stay aligned across columns regardless of wrapping, with no reserved whitespace. Avoid a naive `grid-cols-2` of independent `grid gap-2` fields (a wrapped label in one column pushes its input out of line with its neighbour's) and avoid a fixed label-height reserve (loose whitespace; overflows past two lines, worse in French). *No form primitive enforces this yet — it's the pattern to follow as product forms get built.*
- **`TextLink` vs. `Button variant="link"`.** Both render heritage-blue text, but the underline distinguishes them: `TextLink` (`resources/js/components/TextLink.vue`) is underlined and is for **navigation** — it renders a real Inertia `<Link>`/anchor. `Button variant="link"` is *not* underlined and is for an **action** styled to look link-like — it's a `<button>`. Rule of thumb: goes somewhere → `TextLink`; does something → `Button variant="link"`. (If you need link-styled navigation inside button markup, render the Button `as="a"` / via `asChild` so the element matches the behaviour.)

## Database migrations

- One migration per change. Don't bundle unrelated changes.
- Reversible (`down()` method) unless impossible.
- Foreign keys with explicit `cascadeOnDelete`, `nullOnDelete`, or `restrictOnDelete`.
- Indexes on every foreign key, every column used in `where`, every column used in `order by`.
- All text columns are utf8mb4. Default collation `utf8mb4_unicode_ci`.

## Testing

- Feature tests for controller actions. Hit the route, check the response, check the DB.
- Unit tests for service classes with non-trivial logic.
- Don't test framework code. Don't test getters and setters.
- Tests use `RefreshDatabase` and SQLite for speed when possible (or MariaDB for tests that depend on DB-specific behavior).

## Code style and tooling

PHP, JavaScript, TypeScript, Vue, and CSS are auto-formatted on commit. The toolchain:

- **Pint** (PHP) — Laravel's default rules. Run via `vendor/bin/sail pint` (lint and fix) or `vendor/bin/sail pint --test` (check only, no writes). Sail must be running because there's no host-level PHP.
- **Prettier** (JS/TS/Vue/CSS/JSON/YAML) — config in `.prettierrc`, ignores in `.prettierignore`. Run via `pnpm format` (writes) or `pnpm format:check` (check only).
- **ESLint** (JS/TS/Vue) — flat config in `eslint.config.js`. Run via `pnpm lint`.

A Husky pre-commit hook runs `lint-staged` against staged files only:

- `*.php` → `vendor/bin/sail pint`
- `*.{js,ts,vue}` → `prettier --write` then `eslint --fix`
- `*.css` → `prettier --write`

Auto-fixed files are restaged before the commit completes. If a fix can't be applied automatically (e.g., a real lint error or a syntax error Pint can't normalize), the commit is rejected and you fix it by hand.

The hook is installed automatically by the `prepare` script the first time someone runs `pnpm install`. Sail must be up for PHP commits; if it isn't, start it with `pnpm sail:up`.

**Bypassing the hook** (`git commit --no-verify`) is discouraged and should not be used to ship style violations or lint errors. Reach for it only when the hook itself is broken (e.g., Sail isn't reachable and you need to land an emergency non-PHP fix). Land the fix, then run `pnpm format && pnpm lint` and commit normally on the next change.

## Git

- Branches: `feature/short-description`, `fix/short-description`, `chore/short-description`.
- One logical change per PR. Don't mix refactoring with new features.
- Self-review your diff before requesting review.

### Commit messages

Not Conventional Commits. The style is sentence-led and descriptive, optimized for humans reading `git log` over machine parsing.

**Subject line:**
- Imperative present tense: "Add document upload validation", not "Added" or "Adds".
- Start with a capital letter. No trailing period.
- Aim for ≤ 72 characters.

**Body** (optional, when the subject can't carry the why):
- Blank line after the subject.
- Wrap at ~72 characters.
- Explain *why* the change was made or what tradeoff it resolves, not *what* the diff shows.

**Trailers:**
- Do not append a `Co-Authored-By: Claude` trailer. AI-authored commits are not marked.

Example:

```
Remove redundant sections from CLAUDE.md

Drop the duplicate document-downloads warning (already covered by the
Hard rules) and the standalone Issue tracker section (already listed
under Agent skills).
```
