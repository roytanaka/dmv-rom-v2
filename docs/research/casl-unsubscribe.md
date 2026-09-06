# CASL and the unsubscribe question

Research note for [#461](https://github.com/roytanaka/dmv-rom-v2/issues/461). Does Canada's anti-spam law (CASL) require an unsubscribe mechanism in mail the app sends to the DMV's own Members?

This is research, not legal advice. Section quotations are from the consolidated Act and regulations on the Justice Laws Website as of 2026-09-06.

## Answer

CASL regulates only *commercial electronic messages* (CEMs): messages where it is reasonable to conclude that one purpose is to encourage participation in a commercial activity. A message that merely runs the volunteer program is not a CEM, so consent, sender identification and the unsubscribe mechanism do not apply to it. On that reading, **shift reminders** for a shift the Member signed up for, **resignation Notices** to a chair, and **one Member writing to another** through the app are not CEMs and need no unsubscribe link. **Committee Broadcasts** are not CEMs either as long as they stay on committee business (meetings, schedules, training, volunteer opportunities). The line is crossed the moment a Broadcast offers or promotes something for money, including a paid group trip or a ticketed event, even at cost and even for a third party; CASL says the expectation of profit does not matter. For that message, the DMV's membership relationship supplies *implied consent* (good for the membership plus two years) but does **not** waive the other two duties: the message must identify the sender with a mailing address and contact route, and must carry a working unsubscribe mechanism honoured within ten business days. So: unsubscribe is **not required** for reminders, Notices and member-to-member mail; **required** for any Broadcast that sells or promotes a paid thing; and **advisable** as a standing footer on Broadcasts generally, because Broadcasts are officer-composed free text and the app cannot tell in advance which ones will cross the line. A policy that keeps commercial content out of Broadcasts (pointing it to the newsletter, which has its own unsubscribe) is the alternative that avoids building anything.

## Sources

Primary, read directly:

- Act: *An Act to promote the efficiency and adaptability of the Canadian economy by regulating certain activities that discourage reliance on electronic means of carrying out commercial activities…*, S.C. 2010, c. 23 ("CASL"). https://laws-lois.justice.gc.ca/eng/acts/E-1.6/FullText.html
- Electronic Commerce Protection Regulations, SOR/2013-221 (Governor in Council; "ECPR"). https://laws-lois.justice.gc.ca/eng/regulations/SOR-2013-221/FullText.html
- Electronic Commerce Protection Regulations (CRTC), SOR/2012-36 ("CRTC Regs"). https://laws-lois.justice.gc.ca/eng/regulations/SOR-2012-36/FullText.html
- ISED / fightspam.gc.ca, "Getting consent to send email". https://ised-isde.canada.ca/site/canada-anti-spam-legislation/en/getting-consent-send-email
- ISED / fightspam.gc.ca, "Canada's anti-spam legislation" overview. https://ised-isde.canada.ca/site/canada-anti-spam-legislation/en/canadas-anti-spam-legislation
- Competition Bureau, "Frequently asked questions about Canada's anti-spam legislation". https://competition-bureau.canada.ca/how-we-foster-competition/compliance-and-enforcement/frequently-asked-questions-about-canadas-anti-spam-legislation

CRTC guidance, cited from search-engine excerpts only. crtc.gc.ca sat behind a Cloudflare bot challenge for every fetch path tried (curl, headless and headed browser, PDF variants), so these quotations were not verified against the live page and should be re-checked by a human before anyone relies on the wording:

- CRTC, "Frequently Asked Questions about Canada's Anti-Spam Legislation". https://crtc.gc.ca/eng/com500/faq500.htm
- CRTC, "Guidance on Implied Consent". https://crtc.gc.ca/eng/com500/guide.htm
- CRTC, Compliance and Enforcement Information Bulletin CRTC 2012-548 (identification and unsubscribe form). https://crtc.gc.ca/eng/archive/2012/2012-548.htm

## 1. What makes a message a commercial electronic message

### The test

CASL s. 1(2):

> a commercial electronic message is an electronic message that, having regard to the content of the message, the hyperlinks in the message to content on a website or other database, or the contact information contained in the message, it would be reasonable to conclude has as its purpose, or one of its purposes, to encourage participation in a commercial activity, including an electronic message that (a) offers to purchase, sell, barter or lease a product, goods, a service, land or an interest or right in land; (b) offers to provide a business, investment or gaming opportunity; (c) advertises or promotes anything referred to in paragraph (a) or (b); or (d) promotes a person, including the public image of a person, as being a person who does anything referred to in any of paragraphs (a) to (c), or who intends to do so.

CASL s. 1(1):

> commercial activity means any particular transaction, act or conduct or any regular course of conduct that is of a commercial character, whether or not the person who carries it out does so in the expectation of profit …

Three things follow from the text.

- The purpose test is "one of its purposes", so mixed messages count. A committee update with one paragraph selling tickets is a CEM.
- Profit is irrelevant. A cost-recovery trip or a fundraising ticket is still "of a commercial character".
- Content, links and contact details are all considered (s. 1(2) opening words). A link to a ticket vendor can make the message commercial even if the body is neutral.

ISED's overview page puts it the same way: "CASL focuses on commercial electronic messages, which are those that encourage participation in a commercial activity whether or not there is an expectation of profit." The ISED consent page describes CEMs as carrying "commercial or promotional information, such as marketing, sales, offers, solicitations or similar activities".

The Act says nothing about non-commercial messages. If a message is not a CEM, s. 6 (consent, identification, unsubscribe) never engages. Nothing in CASL, the ECPR or the CRTC Regs imposes an unsubscribe duty on non-commercial mail.

### Applied to the four mail types

| Mail type | CEM? | Reasoning |
|---|---|---|
| Shift reminder for a shift the Member signed up for | No | Administers unpaid volunteer work. No product, service, or opportunity is offered or promoted. Even if it were somehow a CEM, ECPR s. 3(b) removes s. 6 entirely for a message "sent in response to a request … or is otherwise solicited by the person to whom the message is sent"; a reminder about a shift the Member asked for is solicited. |
| Committee Broadcast (officer to committee members) | Not by default | Committee business (meetings, rotas, training, calls for volunteers) encourages participation in volunteering, not in a commercial activity. It becomes a CEM if one purpose is to sell or promote something for money; see question 4. |
| Resignation Notice to a committee chair | No | Factual administrative notice about a membership event. No commercial purpose. (If it were a CEM, s. 6(6)(d)(ii) would still waive consent for a message that "solely … provides notification of factual information about … the ongoing subscription, membership, account, loan or similar relationship of the person to whom the message is sent", but identification and unsubscribe would remain; that clause only matters if the message is commercial in the first place.) |
| One Member writing to another through the app | Not by content | The Member composes it; the content is whatever they write. Two safeguards beyond "it isn't commercial": s. 6(5)(a) removes s. 6 for a CEM "sent by or on behalf of an individual to another individual with whom they have a personal or family relationship", with "personal relationship" defined in ECPR s. 2(b) as individuals who "have had direct, voluntary, two-way communications and it would be reasonable to conclude that they have a personal relationship" (factors include shared interests, frequency of contact, having met in person). Fellow volunteers on a committee often meet that description, but not always. Note s. 6(1) prohibits anyone who would "send or cause or permit to be sent" a CEM, so the DMV as operator is implicated if a Member uses the feature to sell something. That is a terms-of-use point, not an unsubscribe point. |

## 2. The existing non-business relationship provision

### What it grants: implied consent

CASL s. 6(1)(a) accepts consent "whether the consent is express or implied". Section 10(9) lists when consent is implied:

> consent is implied only if (a) the person who sends the message … has an existing business relationship or an existing non-business relationship with the person to whom it is sent; …

Section 10(13) defines the non-business relationship:

> existing non-business relationship means a non-business relationship between the person to whom the message is sent and any of the other persons referred to in that subsection … arising from
> (a) a donation or gift made by the person to whom the message is sent to any of those other persons within the two-year period immediately before the day on which the message was sent, where that other person is a registered charity as defined in subsection 248(1) of the Income Tax Act, a political party or organization, or a person who is a candidate … for publicly elected office;
> (b) volunteer work performed by the person to whom the message is sent for any of those other persons, or attendance at a meeting organized by that other person, within the two-year period immediately before the day on which the message was sent, where that other person is a registered charity as defined in subsection 248(1) of the Income Tax Act, a political party or organization or a person who is a candidate …; or
> (c) membership, as defined in the regulations, by the person to whom the message is sent, in any of those other persons, within the two-year period immediately before the day on which the message was sent, where that other person is a club, association or voluntary organization, as defined in the regulations.

ECPR s. 7 supplies the definitions for paragraph (c):

> 7 (1) … membership is the status of having been accepted as a member of a club, association or voluntary organization in accordance with its membership requirements.
> (2) … a club, association or voluntary organization is a non-profit organization that is organized and operated exclusively for social welfare, civic improvement, pleasure or recreation or for any purpose other than personal profit, if no part of its income is payable to, or otherwise available for the personal benefit of, any proprietor, member or shareholder of that organization …

The two-year clock for membership starts when the membership ends. CASL s. 10(14)(b): "in the case of a membership, the period is considered to begin on the day that the membership terminates." So a current Member is covered for the whole membership plus two years after resignation or lapse.

ISED, "Getting consent to send email": "Charitable or non-for-profit organizations may have implied consent in certain situations, such as when the recipient has made a donation or been a member of or volunteer with the organization." The same page notes that implied consent is "generally time-limited" (two years for the relationships above).

CRTC FAQ (search excerpt, unverified): "Consent under CASL is implied if you have an existing non-business relationship with the recipient. An existing non-business relationship is created when a person makes a donation or gift to the registered charity, performs volunteer work or attends a meeting organized by the charity."

For the DMV, two routes plausibly apply and both give the same two-year implied consent:

- s. 10(13)(c) if the DMV itself is a "club, association or voluntary organization" under ECPR s. 7(2) (a non-profit run for a purpose other than personal profit, no income to members). A volunteer association with accepted members fits that description on its face.
- s. 10(13)(b) if the Member's volunteer work is treated as performed for a *registered charity*. That depends on whether the DMV or the ROM holds charitable registration under Income Tax Act s. 248(1); confirm with the DMV's officers rather than assume.

### What it does not grant: the other two duties stand

Implied consent satisfies only s. 6(1)(a). Section 6(1)(b) separately requires that "the message complies with subsection (2)", and s. 6(2) requires that the message:

> (a) set out prescribed information that identifies the person who sent the message and the person — if different — on whose behalf it is sent; (b) set out information enabling the person to whom the message is sent to readily contact one of the persons referred to in paragraph (a); and (c) set out an unsubscribe mechanism in accordance with subsection 11(1).

Section 11 sets the shape of the unsubscribe mechanism:

> 11 (1) The unsubscribe mechanism … must (a) enable the person to whom the commercial electronic message is sent to indicate, at no cost to them, the wish to no longer receive any commercial electronic messages, or any specified class of such messages, from the person who sent the message … using (i) the same electronic means by which the message was sent, or (ii) if using those means is not practicable, any other electronic means …; and (b) specify an electronic address, or link to a page on the World Wide Web that can be accessed through a web browser, to which the indication may be sent.
> (2) … must ensure that the electronic address or World Wide Web page referred to in paragraph (1)(b) is valid for a minimum of 60 days after the message has been sent.
> (3) … must ensure that effect is given to an indication sent in accordance with paragraph (1)(b) without delay, and in any event no later than 10 business days after the indication has been sent, without any further action being required on the part of the person who so indicated.

CRTC Regs s. 2 lists the identification content: the name under which the sender carries on business, and "the mailing address, and either a telephone number …, an email address or a web address" of the sender or the person on whose behalf it is sent. CRTC Regs s. 3 requires that this information and the unsubscribe mechanism be "set out clearly and prominently" and that the mechanism "be able to be readily performed". CRTC Bulletin 2012-548 (search excerpt, unverified) gives the canonical example: "a link in an email that takes the user to a web page where he or she can unsubscribe from receiving all or some types of CEMs from the sender", and says "readily performed" means "accessed without difficulty or delay, and should be simple, quick, and easy for the consumer to use".

ISED's consent page lists the three duties together, "business name and sender identification", "current mailing address plus phone/email/website", and "an unsubscribe mechanism", with no carve-out for implied-consent senders. The CRTC FAQ (search excerpt, unverified) says the same for the charity relationship: "The CEM must still respect the other two requirements – it must contain the identification information and unsubscribe mechanism."

So the ticket's framing is correct: the membership relationship answers the consent question for CEMs and nothing else.

## 3. Registered charities and volunteer bodies

### The one real exemption: charity fundraising

ECPR s. 3(g) removes s. 6 entirely (consent, identification *and* unsubscribe) for a CEM:

> (g) that is sent by or on behalf of a registered charity as defined in subsection 248(1) of the Income Tax Act and the message has as its primary purpose raising funds for the charity;

Two limits:

- It is for *registered charities* only. A non-profit or volunteer association without charitable registration gets nothing from it. CRTC FAQ (search excerpt, unverified): "The fundraising exemption of the Governor in Council regulations applies only to registered charities and does not include non-profit organizations." Also: "CASL's provisions relating to sending commercial electronic messages apply to activities of non-profit organizations."
- The *primary purpose* must be raising funds for the charity. CRTC FAQ (search excerpt, unverified): "The 'primary purpose' of a CEM means the main reason or main purpose of the CEM. There could be a secondary or additional purpose to the message, but the principal purpose of the CEM must be to raise funds for the charity." A ticketed fundraiser might qualify; a members' trip sold at cost does not raise funds for anyone.

The Competition Bureau's FAQ, which governs the false-or-misleading-representation side of CASL, adds that its provisions "do include the raising of funds for charitable or other non-profit purposes", so a charity that does send fundraising CEMs is still on the hook for accuracy.

### Other exclusions worth knowing, none of which is a general volunteer-body exemption

- ECPR s. 3(a): messages between an "employee, representative, consultant or franchisee of an organization" and another such person "and the message concerns the activities of the organization". "Representative" is undefined; whether committee officers and members mailing each other about DMV business qualify is untested, and this note does not rely on it.
- ECPR s. 3(b): messages "sent in response to a request, inquiry or complaint or … otherwise solicited by the person to whom the message is sent". Covers shift reminders and anything the Member asked for.
- CASL s. 6(5)(a) with ECPR s. 2(b): personal relationship between individuals (question 1).
- CASL s. 6(6)(d): consent-only waiver for factual notices about an ongoing membership. Identification and unsubscribe still apply.

### Does the DMV's status matter?

For the four mail types in scope, no: they are not CEMs, so no exemption is needed. Status matters only at the edge (question 4), and in two directions:

- Charitable registration (of the DMV, or arguably of the ROM if the DMV acts on its behalf) unlocks ECPR s. 3(g) for genuine fundraising messages, and s. 10(13)(b) implied consent from volunteer work.
- Being a non-profit "club, association or voluntary organization" under ECPR s. 7(2) unlocks s. 10(13)(c) implied consent from membership.

Either way, implied consent is what the DMV gets. Neither status exempts a paid-trip Broadcast from identification and unsubscribe unless it is charity fundraising.

## 4. Where a message could cross the line, and the minimum compliant handling

### The edge case

An officer's Broadcast that promotes a paid group trip or a ticketed event is a CEM. It "offers to … sell … a service" or "advertises or promotes" one (s. 1(2)(a), (c)); "one of its purposes" is enough; profit is irrelevant (s. 1(1)); and the seller being a third party (a tour operator, a venue) changes nothing, because s. 1(2)(c) covers promotion of anyone's offer. Neither the DMV's non-profit nature nor its membership relationship takes the message outside s. 6. A ticketed *fundraiser* run by a registered charity, with fundraising as the primary purpose, is the only variant that escapes via ECPR s. 3(g).

Risk-wise: s. 6 violations carry administrative monetary penalties of up to $1,000,000 per violation for an individual and $10,000,000 for any other person (CASL s. 20(4)); the penalty must be proportionate, and factors include the nature and scope of the violation, history, and ability to pay (s. 20(3)). Enforcement in practice has targeted mass senders, not volunteer associations mailing their own members, but the statute does not distinguish.

### Minimum compliant handling

For any Broadcast that is a CEM, the message must satisfy all of s. 6(2):

1. **Consent.** Implied under s. 10(9)(a) via s. 10(13)(c) membership (or (b) volunteer work, if a registered charity). Current Members are covered; lapsed Members for two years after termination (s. 10(14)(b)). Recipients outside that window (a Broadcast to non-members) would need express consent under s. 10(1).
2. **Identification** (s. 6(2)(a)-(b), CRTC Regs s. 2): the DMV's name, its mailing address, and at least one of telephone, email or web address, set out "clearly and prominently" (CRTC Regs s. 3). Contact details must remain valid for 60 days (s. 6(3)).
3. **Unsubscribe** (s. 6(2)(c), s. 11, CRTC Regs s. 3): a link to a web page or an email address, at no cost, "readily performed", valid for 60 days, honoured within 10 business days with no further action by the Member. Section 11(1)(a) allows the mechanism to cover "any specified class" of CEMs, so an "unsubscribe from Broadcasts" opt-out is enough; it does not have to silence the account.

Two ways to get there, either of which is defensible:

- **Policy route (no build).** Officers may not use Broadcasts to sell or promote paid things; such items go to the newsletter, which already carries its own unsubscribe. This keeps every app-sent message non-commercial and the unsubscribe question moot. It depends on officers following the rule, and the app cannot enforce it on free text.
- **Footer route (small build).** Every Broadcast carries a fixed footer with the DMV's name, mailing address and contact route, plus an unsubscribe link (or `mailto:`) that sets a per-Member "no Broadcasts" flag and is honoured on the next send. Shift reminders, Notices and member-to-member mail are unaffected because they are not CEMs, and an unsubscribed Member still receives them. This is cheap insurance against the officer who forgets the policy. If built, it needs: the flag honoured within 10 business days (immediately, in practice); the link valid at least 60 days; no login wall or extra steps that would fail "readily performed"; and the footer present on every Broadcast rather than only ones flagged commercial, since the sender cannot reliably self-classify.

If the footer route is taken, the mechanism could also carry the s. 11(3) obligation across officer turnover: a Member's opt-out is against the DMV as sender, not against the individual officer, so it must persist regardless of who composes the next Broadcast.

## Risk

The paid-trip and ticketed-event case is the only realistic way app mail becomes a CEM, and it is a real one: ordinary volunteer associations do organise trips and sell event tickets to their members, the "one of its purposes" test catches a single promotional paragraph, cost-recovery pricing does not help, and promoting someone else's paid event counts too. Implied consent from membership means the DMV would not be sending *unsolicited* mail, which is the conduct CASL was written against, but the identification and unsubscribe duties are strict and apply anyway. The exposure is low-probability, high-ceiling. Dropping the self-service unsubscribe link is safe for reminders, Notices and member-to-member mail; for Broadcasts, pair the decision with either a written rule keeping commercial content out of them or a fixed footer that carries identification and a Broadcast-only opt-out. Re-verify the CRTC FAQ wording quoted above against the live page before citing it externally.
