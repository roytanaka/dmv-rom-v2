---
status: accepted
date: 2026-05-15
---

# Authentication and identity for volunteers

## Context

The app serves two distinct usage modes with different security and UX requirements:

- **Back-office work** (member CRUD, scheduling, document access) — performed on personal devices, requires real authentication.
- **Kiosk tap-in for shift attendance** — performed on shared physical devices set up at program locations, where many volunteers sign in sequentially on the same machine. Demographics skew older (~500 volunteers); tap-in needs to be fast and forgiving.

The rebuild needs a credential model that:

- Provides real security for back-office work
- Preserves a fast tap-in UX for kiosk shift attendance
- Plays cleanly with bilingual content, Canadian data residency, and the structural-rebuild "preserve functionality first" stance

## Decision

**Two credentials, two scopes.**

1. **Main-app login: email + password.** Standard Laravel auth (bcrypt-hashed password, email reset link, rate limiting, lockout). Used anywhere a volunteer is doing real back-office work.
2. **Kiosk tap-in: name-picker + short per-user PIN.** Each volunteer has their own PIN. The kiosk shows a roster, the volunteer picks themselves, then enters their PIN. PIN scope is bounded to attendance/shift operations — a PIN cannot perform privileged actions in the main app. PIN is hashed at rest, rate-limited, and lockout-protected.
3. **Email OTP at the kiosk as an accessibility fallback.** Volunteers who don't have or don't want a PIN can request a one-time code by email and enter it at the kiosk. Same scope bounds as the PIN.

**One email per user.** Every volunteer has a unique email address. Composite-identity disambiguation (e.g. email + first name) is not part of the model.

**OAuth SSO is deferred.** No third-party identity providers in v1. Revisit in a separate ADR when (a) a real user driver emerges or (b) a partner integration is required.

## Considered alternatives

- **PIN-only across both scopes.** Familiar to users, kiosk UX is great, but the credential space is too small for the main app's blast radius. Rejected for the main app; kept (with hashing/rate-limiting/lockout) for the kiosk scope where blast radius is bounded.
- **One credential everywhere (email + password at the kiosk too).** Simpler model. Rejected because typing a real password on a shared tablet is bad UX and bad security (shoulder-surfing, sticky-key residue).
- **Magic-link / passwordless email everywhere.** Modern, low credential-management burden. Rejected as a primary model because mid-shift kiosk users can't wait for an email, and many older volunteers want a credential they can remember.
- **Composite identity (email + first_name).** Rejected because first_name in the identity key is fragile (name changes, "Bob" vs. "Robert") and it blocks standard Laravel auth, password reset, OTP, and any future OAuth.
- **OAuth SSO with Google/Apple/Microsoft as a peer login method in v1.** Significant complexity (Socialite, provider config, person-picker for residual edge cases) for no stated user driver, and OAuth provider routing has Canadian-residency implications worth thinking through deliberately. Deferred.

## Consequences

- **Credential migration is a separate decision** captured in [ADR-0006](0006-cutover-strategy.md). The auth model assumes a clean transition; the cutover ADR defines the first-login flow that bootstraps each volunteer's new password and PIN.
- **Shared-email households must be reconciled before cutover.** The pre-cutover audit sizes the population of duplicate emails. Households that won't provide a second email get a synthetic fallback address (`@dmv-rom.local` or similar).
- **The kiosk PIN is a new credential users have to learn.** Onboarding flow must include "set your PIN" alongside "set your password" the first time a volunteer signs into the new app.
- **Authorization is out of scope.** This ADR covers authentication and identity only. Role-based authorization (who can see which members, which committee, which documents) is a separate concern, documented in `docs/conventions.md § Authorization` and policy classes.
- **Standard Laravel auth is the implementation path.** Breeze or Fortify, depending on whether the team wants pre-built Inertia auth scaffolding (Breeze) or headless primitives (Fortify). Specific package choice is implementation-time.

## Open implementation questions

These are tactical and don't gate the decision, but the implementing engineer should resolve them deliberately:

- PIN format: 4-digit numeric, 6-digit numeric, or short alphanumeric? Trade-off between brute-force resistance and tap-friendliness.
- Session lifetime and idle timeout for main app vs. kiosk (kiosk sessions should be very short or single-action).
- Rate-limit and lockout thresholds (Laravel defaults are a reasonable starting point).
- 2FA / MFA for high-privilege accounts (committee chairs, admins). Probably yes, probably as a future ADR if non-trivial.
- Account lifecycle: who provisions new volunteer accounts, how offboarding works, how inactive accounts are handled.
