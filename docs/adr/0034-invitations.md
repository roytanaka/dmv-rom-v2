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

3. **Bulk Guest picks reuse the Broadcast Audience rule** ([ADR-0024](0024-emailing-model.md) §5). A Host picks whole Audiences only from those they could email. Any Host may pick the Groups they belong to. An **org-wide sender** may also pick All Members and any Group. To invite 500 Members is a mass email, so the mass-email rule applies. Any Host may also add any single current Member, as in the old app. *Deviation:* the old app hid "Choose All" from everyone but the Invitations roster, in the browser only. Here the server enforces the rule, and any Chair of an active Group can also invite all Members, because a Chair is an org-wide sender.

4. **The super-tier may manage every Invitation.** It does this through the usual `Gate::before` grant. This replaces the old Invitations roster. The roster is not carried over.

5. **One Host per Invitation.** There are no co-hosts. The old app had none.

6. **An Invitation is Draft, Open, or Closed.** A new Invitation is a Draft, and only its Host sees it. Sending moves it to Open. A Host may move an Open Invitation back to Draft to hide it. It is Closed after the occasion's date, computed from that date. Delete is a soft delete. Past Invitations stay in the Host's list. This replaces the old `visible` and `Active` flags. *Deviation:* the old app set `visible` when the Host clicked "Mail out", even if the Host then cancelled.

7. **Sending reuses Broadcasts and Deliveries.** The Host edits a prefilled mail. One mail goes to each Guest, with Reply-To the Host. The no-email flag applies. A Host may re-send, to all Guests or to Guests with No reply. The Host's text stays in the language it was written in, and the app's own text follows each Guest's locale ([ADR-0004](0004-chrome-only-translation.md)). *Deviation:* the old app sent one mail with the Guests on Bcc.

8. **Changes after sending send no mail.** A changed date, a removed Guest, a deleted Invitation, and a Guest added late all send nothing automatically, as in the old app. The Host uses the re-send or email-Guests action. Removing a Guest also removes their Reply. *Deviation:* the old app keeps two lists that drift apart.

9. **The Guest limit is enforced on the server.** The limit counts Accepted Replies. It does not count Options. A Decline frees a spot. A Host may go over the limit. There is no waitlist. The Host gets one mail when the limit is reached. The old `overflow` counter is dropped, because it counted page views.

10. **Guests reply until Closed.** A Host may set a Reply after Closed, to record an answer given at the door.

11. **Only the Host and the super-tier see the guest list and Replies.** A Guest sees only their own Reply. The page shows no email addresses. The Host mails Accepted Guests through the app. The Host's view has totals and Print, and it lists Guests with No reply. There is no CSV. *Deviation:* the old app has no server check on these screens.

12. **The Invitations Reminder is a window with a sent record** ([ADR-0024](0024-emailing-model.md) §7). The daily pass writes one Delivery per Invitation and Guest, inside the 4-day lead, for Accepted Replies on Open Invitations. A Guest who accepts inside the window still gets one. A Guest who has since declined does not. The Delivery expires at the occasion's start. The mail is in the Guest's locale and names their Options, the description, and the Host. The old per-event `reminder` flag is dropped: the old app ignores it.

13. **The page has two tabs.** Every Member sees "Invitations" in the top bar, and it opens on **My invitations**. **Hosting** shows only to Members who may host.

14. **Not carried over.** The Applicants guest option, which no row uses. The "Executive Hosts" page and the committee's Assistants and Secretary menus. My Calendar entries wait for [#898](https://github.com/roytanaka/dmv-rom-v2/issues/898).

15. **Invitations need no Beta-specific work.** Beta does not exist yet. When it does, its mail setting ([ADR-0035](0035-beta-and-cutover.md) §6) must also stop Invitation mail and Reminders. Sending still moves an Invitation to Open. No screen adds Beta-only wording. The site-wide alert bar ([#899](https://github.com/roytanaka/dmv-rom-v2/issues/899)) and tester instructions cover this.

## Considered options

- **A Group capability with a Host role.** It fits ADR-0010. It was rejected because the old app gives hosting to every Full Member, and a faithful port keeps that.
- **An Invitations Group that holds a stewardship.** It was rejected because the Group would exist only to hold a permission. The org-wide sender rule and the super-tier already cover the two powers the old roster gave.

## Consequences

- The Invitations Reminder keeps the four-day lead from ADR-0024 §7. That lead is fixed for the feature, because no Group owns its settings.
- Who can invite All Members now depends on which Groups hold the `org_mail` stewardship. Only seeders set that today. [#815](https://github.com/roytanaka/dmv-rom-v2/issues/815) asks for a page to manage it.
- Past occasions from the old app are not imported.
