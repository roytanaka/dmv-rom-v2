---
status: proposed
date: 2026-05-28
---

# User switching and support impersonation

## Context

The app distinguishes roles (volunteer, administrator today; finer-grained roles like committee chairs and schedulers are anticipated — see ADR-0001 and `CONTEXT.md § Roles today`). Two distinct needs have surfaced around "acting as another user," and they are easy to conflate but have different threat models:

1. **Developer testing.** The maintainer needs to verify role-gated behaviour and UX across every role without maintaining a password for each seeded test profile and logging in and out constantly. Recreating role edge cases by hand on volunteer-pace spare time is the cost this avoids.

2. **Production support.** The volunteer base skews 70–80 years old. Phone support is dramatically more effective when the supporter can see the same screen state the volunteer sees ("I can't find my schedule" is far faster to resolve by viewing their account than by narrating clicks). The maintainer is also a volunteer and cannot reproduce every real-world account state on their own time, so seeing the real account is a genuine operational need, not a convenience.

These are **two features**, not one. Conflating them produces either an unsafe dev tool shipped to production or an over-locked support tool that defeats its purpose. This ADR separates them and defines the safety contract for each.

Relevant constraints:
- The data is more sensitive than "email and phone." Schedules reveal when a person is away from home; volunteer records may carry emergency contacts and accessibility notes.
- Hosting is in Canada specifically for data residency (ADR-0002), which puts **PIPEDA** in scope. PIPEDA does not forbid impersonation, but expects PII access to be purpose-limited, consented, and accountable. A silent "become anyone" backdoor is the posture it disfavours; a consented, logged, time-boxed support session is squarely defensible.
- The dominant real risk is **not** bulk PII theft. It is (a) loss of accountability ("who actually did this?") and (b) amplification (one phished admin = every account). Both are addressed by design below.

## Decision

Build **two separate capabilities** with different gates.

### 1. Dev user-switcher (non-production only)

A convenience for `local` and `staging` to switch between **seeded fake profiles**, one per role type, to test authorization and UX.

- **Environment is the hard boundary.** The feature must be *absent* in production, not merely hidden. Enforce in two independent layers (belt-and-suspenders):
  - The route is only **registered** when `app()->environment('local', 'staging')` — in production the route does not exist (router returns 404 automatically).
  - The controller *also* calls `abort_unless(app()->environment('local', 'staging'), 404)` — so even if a future refactor registers the route everywhere, the handler still refuses in production.
- UI may be a floating dev widget (Vercel-toolbar style) rather than typed URLs. **The widget is presentation only; the security boundary is the server-side route gate, never the visibility of the button.**
- Mechanism: Laravel's `Auth::login($user)`. No package (see "Considered alternatives").
- Seeded profiles use predictable logins (e.g. `admin@example.test`, `coordinator@example.test`) with a known dev password; Faker fills surrounding data, fixed values fill the login accounts.
- Operates on fake seed data only, so there is no PII or consent dimension.

### 2. Production support impersonation ("view-as")

A deliberately narrower, consented, audited capability for helping real volunteers.

- **View-as, not full become, by default.** The session is read-mostly: the supporter can navigate the volunteer's screens but cannot perform destructive or identity-changing actions (change password, change email, delete account, etc.). This covers the overwhelming majority of phone support and removes the worst failure modes. A full-impersonation capability, if ever truly needed, is a separate, more-gated decision — not granted by this ADR.
- **Just-in-time consent via a support code.** The volunteer initiates: a "Get help" action shows a short-lived (~15 min) numeric code on *their* screen, which they read to the supporter over the phone; the supporter enters it to open a time-boxed view-as session. This gives consent + authentication + expiry in one mechanism an elderly user can operate ("read me the number on your screen"). No standing backdoor exists — access only opens when the volunteer opens it. (A settings-based standing pre-consent toggle is a possible secondary path, deferred.)
- **Every session is audit-logged** — who, whom, start, end, duration. Non-negotiable. This is the control that converts "backdoor" into "defensible support tool" for PIPEDA, volunteer trust, and the maintainer's own protection.
- **Persistent, loud banner** for the entire session ("You are viewing Jane Doe's account — End session").
- **Restricted initiator — an explicit, narrowly-granted permission.** Starting a session requires holding a dedicated *initiate-support-session* permission — not merely being an administrator, and not implied by any officer or executive tier. It is modelled as an explicit named permission in the authorization model (the same mechanism used for other cross-cutting abilities), granted to the maintainer and any designated support helpers. This is deliberate least privilege: a supporter needs only to *open a consented, read-mostly view-as session* — they should not need broad administrative power to do phone support.
- Time-boxed; the session auto-expires.

### Relationship between the gates

For the dev switcher, the environment check and any permission check combine with **AND**, never OR. The environment gate is the outer, non-negotiable wall (feature absent in production). A permission check is at most an *inner refinement within* dev/staging (e.g. only seeded-admin profiles see the switcher). A permission check must **never** be the sole guard, because that would ship the auth-bypass to production and rest its safety entirely on the permission system being flawless forever.

The production support tool is a different feature with its own consent + audit + role gates, as above — it is not the dev switcher with a production flag flipped on.

## Considered alternatives

- **One tool gated only by a super-admin permission, available everywhere (the initial proposal).** Rejected for the dev switcher: a permission-only guard ships a complete auth-bypass to production whose safety depends solely on the permission system never having a bug, and it is circular — the tool used to test permissions would depend on the permission system being correct. Permission checks answer "who is allowed," not "should this exist here"; for this feature both questions must be answered, environment first. (A permission gate remains correct *inside* the non-prod boundary and for *initiating* the production support tool.)
- **Full impersonation ("become") in production from day one.** Rejected as the default. Most support is read-only; full become carries the destructive-action and password-change risks for marginal added benefit. Reserved as a separate future decision if a concrete need appears.
- **No production tool; reproduce issues from staging only.** Rejected. The maintainer is a volunteer; reproducing every real account state by hand is exactly the spare-time cost this avoids, and elderly-user phone support is materially better with shared screen state.
- **Admin can silently impersonate without volunteer consent.** Rejected on PIPEDA and trust grounds. The just-in-time code makes access volunteer-initiated and self-evidently consensual.
- **A package (e.g. `lab404/laravel-impersonate`).** Deferred per the rule of three (`docs/architecture.md`) and the "flag packages for review" rule (CLAUDE.md). The dev switcher is a small controller over `Auth::login()`; the production tool's value is in the consent/audit/view-as constraints, which a generic package does not provide out of the box. Revisit if the mechanics grow.

## Consequences

### What this enables
- Fast role/UX testing across every role in dev/staging without per-profile login juggling.
- Effective phone support for an elderly user base, with the supporter seeing real screen state.
- A privacy posture that is defensible under PIPEDA: consented, time-boxed, audited, purpose-limited.

### What this requires
- The role system must exist first — both capabilities are downstream of roles being modelled (ADR-0001 defers the role representation to "when the second role-bearing feature lands"; this may be that moment). Seeding "one profile per role" presupposes role types exist. The authorization model now being defined supplies both the role types the dev switcher seeds against and the explicit *initiate-support-session* permission the support tool gates on.
- A seeder producing one fixed-login profile per role for non-production.
- Server-side environment gating on the dev-switcher route (two layers).
- For the production tool: a support-code issue/verify flow, a time-boxed view-as session mechanism, an audit log table, a session banner, and the explicit *initiate-support-session* permission.
- The view-as session must enforce read-mostly restrictions at the policy/gate layer, not just in the UI.

### What we accept
- The production support tool is real PII-access surface. We accept this deliberately, mitigated by consent + audit + view-as + expiry + restricted initiator. The audit log is the accountability backstop.
- Maintenance of a bespoke support flow rather than a package, in exchange for the consent/audit/view-as semantics we specifically want.

### Open implementation questions
- Who holds the *initiate-support-session* permission in practice (the maintainer alone at first; whether designated volunteer supporters are granted it later).
- Code length / lifetime / rate-limiting specifics for the support code.
- Precise boundary of "read-mostly" — which actions are blocked in view-as.
- Audit log retention period and whether the impersonated volunteer can see their own support-session history (a trust-positive option).
- Whether a standing pre-consent toggle is ever added alongside the just-in-time code.
- Whether full "become" is ever needed beyond view-as.

## References

- ADR-0001 — roles and identity (role representation deferred; this feature depends on it)
- ADR-0002 — Canadian data residency / PIPEDA scope
- The authorization model (forthcoming ADR) — the *initiate-support-session* gate is an explicit named permission defined there; the restricted-initiator design here depends on it. Cross-link to be wired when that ADR publishes.
- `CONTEXT.md § Roles today` — anticipated finer-grained roles
- `docs/architecture.md` — rule of three; package-flagging
- CLAUDE.md § Hard rules — never bypass authorization checks for document downloads (the view-as read-mostly boundary must not become a path around document policies)
