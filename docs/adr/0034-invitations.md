---
status: accepted
date: 2026-10-10
accepted: 2026-10-10
---

# Invitations: an org-wide feature, not a Group capability

From the grilling session for [#698](https://github.com/roytanaka/dmv-rom-v2/issues/698). The old app has an "e-Vite" tool. A Member creates an occasion, invites Members, and each Guest accepts or declines. It is used for the Annual Meeting, the Service Awards, and small outings. There were 162 occasions from 2016 to 2026, with a median of 85 Guests and a maximum of 604. In the old app it is a committee in the sidebar. The committee's roster has no meetings, documents, or hours. Its only job is to let about a dozen people manage every occasion and invite all Members.

[ADR-0010](0010-group-model.md) says a feature is a capability a **Group** switches on, and [ADR-0011](0011-authorization-model.md) keeps authority per-Group. Invitations does not follow that pattern. This ADR says why. See `GLOSSARY.md` for **Invitation**, **Host**, **Guest**, **Reply** and **Option**.

## Decision

1. **Invitations is an org-wide feature.** An Invitation belongs to its **Host**, not to a Group. There is no `has_invitations` capability, no Host role on a Membership, and no Invitations Group. The entry point is a top-bar item, "Invitations", between News and Directory. A tester asked for this placement.

2. **Any Member whose Category gives Full access may host.** This is a faithful port. The old app lets the same Members host. Restricting hosting to a Group role was rejected: the hosts are spread across the org, and no Group runs the feature.

3. **Who a Host may invite reuses the Broadcast Audience rule** ([ADR-0024](0024-emailing-model.md) §5). A Host picks Guests from the Audiences they could email. Any Host may pick from the Groups they belong to. An **org-wide sender** may also pick All Members and any Group. To invite 500 Members is a mass email, so the mass-email rule applies. *Deviation:* in the old app, only the Invitations roster could invite all Members. Here any Chair of an active Group can also do it, because a Chair is an org-wide sender.

4. **The super-tier may manage every Invitation.** It does this through the usual `Gate::before` grant. This replaces the old Invitations roster. The roster is not carried over.

5. **One Host per Invitation.** There are no co-hosts. The old app had none.

## Considered options

- **A Group capability with a Host role.** It fits ADR-0010. It was rejected because the old app gives hosting to every Full Member, and a faithful port keeps that.
- **An Invitations Group that holds a stewardship.** It was rejected because the Group would exist only to hold a permission. The org-wide sender rule and the super-tier already cover the two powers the old roster gave.

## Consequences

- The Invitations Reminder keeps the four-day lead from ADR-0024 §7. That lead is fixed for the feature, because no Group owns its settings.
- Who can invite All Members now depends on which Groups hold the `org_mail` stewardship. Only seeders set that today. [#815](https://github.com/roytanaka/dmv-rom-v2/issues/815) asks for a page to manage it.
- Past occasions from the old app are not imported.
