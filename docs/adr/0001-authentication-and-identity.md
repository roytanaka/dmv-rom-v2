---
status: accepted
date: 2026-05-15
---

# Authentication and identity for volunteers

## Context

The app supports back-office volunteer work (member CRUD, scheduling, document access) performed on personal devices. Demographics skew older (~500 volunteers); the credential model needs to be memorable and standard.

The rebuild needs a credential model that:

- Provides real security for back-office work
- Plays cleanly with bilingual content, Canadian data residency, and the structural-rebuild "preserve functionality first" stance

## Decision

**One credential: email + password.** Standard Laravel auth (bcrypt-hashed password, email reset link, rate limiting, lockout). Every authenticated route is gated by this credential; there are no secondary scoped credentials.

**One email per user.** Every volunteer has a unique email address. Composite-identity disambiguation (e.g. email + first name) is not part of the model.

**OAuth SSO is deferred.** No third-party identity providers in v1. Revisit in a separate ADR when (a) a real user driver emerges or (b) a partner integration is required.

## Considered alternatives

- **Magic-link / passwordless email everywhere.** Modern, low credential-management burden. Rejected as primary because many older volunteers want a credential they can remember, and transactional email deliverability on the current hosting setup is unpredictable.
- **Composite identity (email + first_name).** Rejected because first_name in the identity key is fragile (name changes, "Bob" vs. "Robert") and it blocks standard Laravel auth, password reset, OTP, and any future OAuth.
- **OAuth SSO with Google/Apple/Microsoft as a peer login method in v1.** Significant complexity (Socialite, provider config, person-picker for residual edge cases) for no stated user driver, and OAuth provider routing has Canadian-residency implications worth thinking through deliberately. Deferred.

## Consequences

- **Credential migration is a separate decision** captured in [ADR-0006](0006-cutover-strategy.md). The auth model assumes a clean transition; the cutover ADR defines the first-login flow that bootstraps each volunteer's new password.
- **Shared-email households must be reconciled before cutover.** The pre-cutover audit sizes the population of duplicate emails. Households that won't provide a second email get a synthetic fallback address (`@dmv-rom.local` or similar).
- **Authorization is out of scope.** This ADR covers authentication and identity only. Role-based authorization (who can see which members, which committee, which documents) is a separate concern, documented in `docs/conventions.md § Authorization` and policy classes.
- **No self-service registration.** Accounts are not publicly created; identity is roster-seeded from the Group/Member spine via data migration. The starter-kit `register` routes, controller, and page have been removed (not merely hidden) — leaving a callable sign-up route would contradict the roster-seeded model and mirror the legacy app's "callable route behind a hidden link" weakness. The path for someone without an account is "contact the office" (surfaced on the login screen); admin-created accounts, if ever needed, are a member-admin (Records stewardship) feature, not public registration.
- **Standard Laravel auth is the implementation path.** Breeze or Fortify, depending on whether the team wants pre-built Inertia auth scaffolding (Breeze) or headless primitives (Fortify). Specific package choice is implementation-time.

## Open implementation questions

These are tactical and don't gate the decision, but the implementing engineer should resolve them deliberately:

- Session lifetime and idle timeout for the main app.
- Rate-limit and lockout thresholds (Laravel defaults are a reasonable starting point).
- 2FA / MFA for high-privilege accounts (committee chairs, admins). Probably yes, probably as a future ADR if non-trivial.
- Account lifecycle: who provisions new volunteer accounts, how offboarding works, how inactive accounts are handled.
