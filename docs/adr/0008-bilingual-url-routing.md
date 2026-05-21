---
status: accepted
date: 2026-05-20
---

# Bilingual URL routing strategy

The rebuild is bilingual (English / French). This ADR fixes the public **shape of HTTP routes** for the two languages and the rules for how a volunteer reaches the right URL. It is independent of [ADR-0004 (reserved)](./), which will cover the content-side bilingual decisions (suffix-column storage, save-time policy for translatable fields).

## Decision

**English-canonical, French-prefixed, translated path segments.** English URLs live at the root (e.g. `/volunteers/123`). French URLs live under a `/fr/` prefix with their path segments translated via Laravel lang files (e.g. `/fr/benevoles/123`). One resource has two URLs that differ in both prefix and segment vocabulary.

The complete rules:

1. **English is the default locale.** No prefix; canonical URLs.
2. **French is additive under `/fr/`.** Only registered French routes exist. Routes are added French-second as features become available in French.
3. **Translated path segments.** `/fr/benevoles/123`, not `/fr/volunteers/123`. Mappings live in `lang/en/routes.php` + `lang/fr/routes.php` and are resolved by the routing package.
4. **No per-record translated slugs in v1.** Dynamic resources use IDs only — `/fr/exhibits/123`, never `/fr/exhibits/vikings-au-rom`. The schema does not need `slug_en` / `slug_fr` columns. Per-record slugs are additive later if a use case appears.
5. **Pattern applies uniformly to all routes**, including pre-auth (`/login` ↔ `/fr/connexion`, `/password/reset` ↔ `/fr/mot-de-passe/reinitialisation`).
6. **Default on first arrival is English.** No `Accept-Language` header detection. A bilingual volunteer base in Toronto is fluent in English; the cost of getting first-arrival language wrong is low.
7. **Language switching via a toolbar selector.** Clicking "Français" navigates to the French equivalent of the current page **and** writes the user's locale preference to their account (when logged in). The reverse for "English."
8. **URL is authoritative on direct visit.** A volunteer with saved-French preference clicking a bookmarked English URL gets the English page. No automatic redirect; the toolbar selector is the user-initiated escape hatch.
9. **Post-login redirect uses the user's saved locale preference.** Login form at `/login` (English-rendered, because that's the URL); on success, redirect to `/fr/dashboard` if their preference is French.
10. **System-generated URLs use the recipient's saved locale.** Password reset emails, notification emails, calendar links — built with the recipient's preference at generation time.
11. **Missing French route returns 404 with a request-translation CTA.** When a French route doesn't exist for a feature that's only shipped in English, direct visits to `/fr/...` return 404. The 404 page is locale-aware: under `/fr/`, it shows a French message distinguishing "page not yet translated" from "page not found," and offers a "request translation" link. The toolbar's "Français" link is conditionally rendered — only present on pages that have a French equivalent.
12. **Implementation package: `mcamara/laravel-localization`** with `hideUrlAndAcceptHeader => true` for the default locale, translated routes enabled, and Accept-Language detection disabled.

## Considered alternatives

- **No locale in URL; language from session/account preference (Pattern A in the design discussion).** One canonical URL per resource; the URL never communicates language. Simpler model, but loses the explicit "this URL is the French version" property, makes content-as-a-French-document (e.g. an uploaded French PDF's metadata page) ambiguous, and diverges from how ROM's own digital presence (rom.on.ca) is structured. Rejected.
- **Locale prefix for both languages — `/en/...` and `/fr/...` (Pattern C/D).** Symmetric, but loses the "English bookmarks never need to change" property of an English-canonical root. Adds `/en/` noise to every URL for the dominant language. Rejected.
- **English-canonical with untranslated French slugs — `/fr/volunteers/123`.** Cheaper to implement (no `lang/{locale}/routes.php` translation table), but reads as a translation-of-an-English-app rather than a French URL. Mismatch with ROM's own convention. Rejected.
- **Per-record translated slugs in v1.** Prettier French URLs (`/fr/expositions/vikings-au-rom`), but requires `slug_en` / `slug_fr` schema, slug history for renames, and gives no SEO value to an authenticated internal app. Deferred.
- **Automatic redirect from English URLs to the user's preferred locale.** Every page load checks account preference and 302s if mismatched. Rejected because it breaks bookmarks and creates redirect loops on language-switcher clicks.
- **Accept-Language detection on first arrival.** Rejected for v1 because the user base reads English fluently, the complexity isn't justified, and the toolbar selector covers the case without it.

## Consequences

- **`lang/en/routes.php` and `lang/fr/routes.php` are real files** containing the URI-segment translation table. Every translatable route segment is keyed there. Conventions doc should note this alongside the existing `__()` guidance.
- **Translatable routes are declared once and resolved per-locale by the package**, via the `LaravelLocalization::transRoute('routes.volunteers.show')` pattern. Routes file structure follows the package's examples.
- **The toolbar language switcher is built using `LaravelLocalization::getLocalizedURL($locale)`** which knows the correct French URL for the current page, including translated slugs. Hide the switcher entry for the alternate locale when no equivalent route is registered.
- **Volunteer model gains a `locale` column** (default `'en'`). The post-login redirect and system-generated URLs read it. The toolbar selector writes it (when authenticated).
- **The 404 controller is locale-aware.** Under a `/fr/` prefix, it renders a French "not translated yet" message and a request-translation CTA. The mechanism for the CTA (internal feedback form, email to a coordinator, GitHub issue) is an implementation detail.
- **Adding a French version of an existing English feature is a structured task**: register the French route(s), add the `lang/fr/routes.php` entries for any new segments, translate the `lang/fr/*.php` UI strings, fill in `_fr` content columns where applicable. The toolbar "Français" link begins appearing automatically on those pages.
- **Future ADR-0004 (bilingual content storage)** addresses what happens when a French route exists but `title_fr` is empty on a specific record. That's a content fallback question, separate from this ADR's route-existence question.
- **Route caching** (`php artisan route:cache`) needs the package's locale-aware handling. The package documents the pattern; configure it during initial install rather than retrofitting later.
- **Test coverage**: every translatable route should have a smoke test that hits both `/x` and `/fr/x-translated` and asserts the resolved Eloquent model is the same. Easy to add as a parameterized test once the first translated route lands.
