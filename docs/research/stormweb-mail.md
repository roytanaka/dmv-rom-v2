---
status: research
date: 2026-09-06
ticket: "#459"
feeds: mail-provider ADR (promised by ADR-0002), delivery-mechanics ticket
---

# Stormweb mail capabilities

**Question.** What does Stormweb give us for sending mail, and does it fit our volume?

This note collects the facts from Stormweb's public documentation, DirectAdmin's documentation (Stormweb's shared-hosting control panel is DirectAdmin, not cPanel), public DNS, Laravel's docs, and the private archaeology notes. It is sanitized: no account identifiers, addresses, hostnames, or real names. Where a fact can only come from Stormweb support or from a test on the live account, it is listed under "Needs a human" rather than guessed.

## Answer

Stormweb's own mail is workable for our volume, with two conditions: the app must **throttle** itself, and it should send over **authenticated SMTP**, not `sendmail`/`mail()`.

- **Transport.** Authenticated SMTP submission exists for any mailbox on the domain (`mail.<domain>`, port 587, STARTTLS, username = full address). `sendmail` also exists — it is DirectAdmin's Exim, and the legacy app's `mail()` has been riding it for years. Use Laravel's `smtp` mailer with a dedicated app mailbox. Reason: over SMTP the daily-limit rejection is synchronous (the connection is refused, Laravel throws); over `sendmail`, Exim *accepts* the message and fails it later at routing, so the app believes it sent and the failure surfaces only as a bounce. SMTP also gives us a controllable envelope sender, which is what SPF/DMARC alignment and bounce delivery hang on.
- **The cap.** Two caps, from two different owners. (1) DirectAdmin enforces a **per-hosting-account daily send limit** — the 4000/day the legacy notes saw in the panel. It counts **per recipient**, not per message, on current DirectAdmin: one message with ninety BCC recipients is ninety sends. It sums every mailbox *and every script* under the account. It cannot be raised from our side of the panel. (2) Stormweb's Acceptable Use Policy states **500 emails per hour per account**; the policy's remedy is suspension/termination. DirectAdmin has no built-in hourly limiter, so whether 500/hr is technically enforced or policy-only is a question for support. Peak legacy day (2666) sits at two-thirds of the daily cap; a full-roster mailing (~500 recipients) plus the nightly reminders would breach 500/hr if sent as one burst. The legacy app's own cap check was commented out; the new app needs a real one.
- **Size.** Stormweb advertises attachments "up to 512MB"; DirectAdmin's Exim ships with `message_size_limit` at 20 MB. Assume 20 MB until support confirms, and design for links rather than attachments anyway (Gmail and Outlook cap inbound at 25–35 MB).
- **Domain authentication.** All three records exist for the DMV domain today, checked in public DNS on 2026-09-06: SPF with a hard fail (`-all`), a DKIM key at DirectAdmin's default selector, and a DMARC record at `p=none` with no reporting address. DNS is hosted on Stormweb's nameservers and is editable from the Stormweb client area's DNS Management; DirectAdmin handles DKIM. So "unpredictable deliverability" (ADR-0001) is no longer explained by missing records — the remaining variables are shared-IP reputation and, for `mail()`/`sendmail` sends, an envelope sender that does not align with the domain.
- **Bounces.** Exim returns bounces to the envelope sender (Return-Path). Over authenticated SMTP that is the app mailbox, so bounces land there as ordinary IMAP mail. Over `sendmail` without `-f`, Exim rewrites the envelope sender to the system account, and bounces effectively vanish. There is no webhook or panel hook; the only "read" path is IMAP, which is out of scope. We will be ignoring bounces in v1, and that is acceptable as long as they land somewhere a human can open.
- **Cron.** DirectAdmin's user-level Cron Jobs take an arbitrary shell command written to the account's crontab; the legacy nightly job is already a shell command (`wget`), not a panel URL-fetch. `* * * * * cd <app> && /usr/local/php84/bin/php artisan schedule:run` will work. The default `php` on the host is 7.4, so the full 8.4 binary path is mandatory in the crontab.

## Findings

### 1. Transport

**Control panel is DirectAdmin.** The archaeology notes record the host as "Stormweb, shared hosting via DirectAdmin control panel", and Stormweb's hosting page shows DirectAdmin among its platform logos (cPanel appears only on the dedicated-server page). Every DirectAdmin fact below therefore applies; cPanel folklore does not.
Sources: archaeology notes § Overview; https://stormweb.ca/webhosting.php

**Authenticated SMTP is available.** Stormweb's plan page lists "SMTP outgoing email service" and "Fully Encrypted SSL/TLS connections" on every plan. Its knowledge-base article for sending through Gmail gives the concrete settings: SMTP server `mail.<yourdomain>`, port 587, "Secured connection using TLS (recommended)", username "your full email address", plus the mailbox password. Port 465 (implicit TLS) is not documented; DirectAdmin's Exim guide shows the default listening set as 25 and 587.
Sources: https://stormweb.ca/webhosting.php ; https://stormweb.ca/knowledgebase/article/39/configuring-an-email-address-in-gmail/ ; https://docs.directadmin.com/other-hosting-services/exim/

**`sendmail` exists and is Exim.** DirectAdmin's Exim documentation uses `/usr/sbin/sendmail -t -i -f user@domain.com` as the per-user PHP `sendmail_path`. The legacy app has sent exclusively through PHP `mail()` (which shells out to that binary) for years, so the path is known-good on this account. Laravel's `sendmail` mailer defaults to `/usr/sbin/sendmail -bs -i` (`config/mail.php` in this repo).
Sources: https://docs.directadmin.com/other-hosting-services/exim/ ; archaeology notes § Email, § Outbound integrations; `config/mail.php`

**Which mailer.** `smtp`, pointed at `mail.<domain>:587` with STARTTLS and a dedicated application mailbox. The deciding facts:

| Concern | `smtp` (auth'd mailbox) | `sendmail` / `mail()` |
|---|---|---|
| Behaviour at the daily cap | DirectAdmin: "the smtp-auth send will return an invalid password error" — Laravel throws `TransportException`, synchronously | Exim accepts the message, then `check_limits` fails at routing ("have reach your daily email limit of N emails" / "Unrouteable address") — the app sees success, the failure becomes a bounce |
| Envelope sender / Return-Path | The authenticated mailbox (DirectAdmin ≥ 1.680 blocks sender spoofing on auth'd sessions) | Defaults to `<system user>@<server hostname>` unless `-f` is set; `-f` is fixed in a per-user `php.ini` `sendmail_path` and "can't be overridden by individual PHP scripts" |
| SPF/DMARC alignment | Envelope domain = DMV domain | Envelope domain = server hostname → SPF passes on the server's record but does not align with the From domain |
| Portability | Same config from any host | Host-specific binary |

Sources: https://docs.directadmin.com/other-hosting-services/preventing-spam/outgoing-spam.html ; https://forum.directadmin.com/threads/all-emails-being-rejected.39747/ ; https://forum.directadmin.com/threads/php-and-exim-return-path.32933/ ; https://forum.directadmin.com/threads/mail-sending-problems-from-cms-since-da-1-680.77939/ ; https://www.php.net/manual/en/function.mail.php

Not verified: that outbound port 587 from PHP on the web server to `mail.<domain>` is open (it is the same box, but shared hosts sometimes firewall loopback SMTP-auth). See "Needs a human".

### 2. The cap

**Two caps with two owners.**

*DirectAdmin per-account daily limit.* The legacy notes observed "2666 emails sent on 2026-05-08 alone (with a 4000/day cap)" in the DirectAdmin usage stats. DirectAdmin's documentation describes this limit as "a daily limit for the maximum number of total sends all accounts and scripts under this User can send from the server, combined". It is set by the server administrator (`/etc/virtual/limit` globally, `/etc/virtual/limit_<user>` per account) or from Reseller Level → E-Mail Limit; a hosting customer can see usage at User Level → E-Mail Accounts → E-Mail Usage but cannot raise the number. DirectAdmin staff on the forum: "it is just a daily limit … not a per-hour feature", and it "stops sending emails when it hits the daily limit; it doesn't wait until the end of the day."
Sources: archaeology notes § Email; https://docs.directadmin.com/other-hosting-services/preventing-spam/outgoing-spam.html ; https://forum.directadmin.com/threads/maximum-amount-of-emails-per-user-and-server-per-hour.43681/

*Stormweb AUP hourly limit.* "a maximum of 500 emails per hour per account". Bulk mail is allowed with affirmative opt-in consent; the policy's remedy for violations is "suspension or termination of your service". The AUP does not say how the 500 is counted or whether it is enforced in software.
Source: https://stormweb.ca/acceptable-use-policy.php

**How recipients are counted.** Per recipient. DirectAdmin 1.703's changelog introduces the Exim macro `SEND_LIMIT_COUNT_RECIPIENTS` with default `$recipients_list`, "meaning all recipients are counted by default"; a DirectAdmin administrator on the forum: the new configuration "counts only unique message IDs for bandwidth … but total number of recipients for the send limit." Since 1.702, mail between mailboxes on the same server also counts ("local-to-local emails will be properly accounted for"); 1.703 added an opt-out macro for hosts that want the old behaviour. So **one message to ninety BCC recipients is ninety sends**, unless Stormweb has chosen the backwards-compatible macro — a question for support, or one test.
Sources: https://docs.directadmin.com/changelog/version-1.703.html ; https://docs.directadmin.com/changelog/version-1.702.html ; https://forum.directadmin.com/threads/number-of-outgoing-emails-is-incorrect.82292/

**What happens at the cap.** Rejection, not queueing, and not suspension (that is the AUP's remedy for the hourly figure, not DirectAdmin's for the daily one).
- SMTP-auth: "the smtp-auth send will return an invalid password error, even if a valid password is provided."
- Script/`sendmail`: Exim's `check_limits` perl hook fails the router; the log reads "You (…) have reach your daily email limit of N emails" and the sender receives an "Unrouteable address" bounce.
- Notification: DirectAdmin emails the account ("Emails will be sent out, notifying the Admin's and DA Users upon the limit being hit"); since 1.45.0 per-mailbox limits also notify the mailbox.
- Reset: documented only as "daily". The exact reset moment (server-local midnight vs. the tally schedule) is not in the docs.
Sources: https://docs.directadmin.com/other-hosting-services/preventing-spam/outgoing-spam.html ; https://forum.directadmin.com/threads/all-emails-being-rejected.39747/ ; https://docs.directadmin.com/changelog/version-1.45.0.html

**Fit against our volume.**
- Legacy peak 2666/day was counted the same way (one `mail()` call per recipient), so it is a like-for-like 67% of 4000. Headroom exists but is not generous on a reminder-heavy day that coincides with an all-roster announcement (~500) plus renewal notices.
- The daily cap is shared with the other sites and mailboxes on the same hosting account (a WordPress install and 13 mailboxes, per the notes). Human mail from those mailboxes eats the same budget.
- 500/hr means any bulk send above a few hundred recipients must be chunked across scheduler ticks. ADR-0002 already commits to "chunked across cron-triggered passes"; this is the number that sizes the chunks.
- The legacy app's `_okToSendEmails` guard has its enforcement body commented out — the cap was observed, never enforced. The rebuild needs a real per-day and per-hour budget (a counter table, or the rate limiter) so that the app degrades to "deferred to tomorrow" rather than to a wall of transport exceptions.

### 3. Size

Stormweb's plan page: attachments "up to 512MB". DirectAdmin's Exim documentation carries a section titled "How to allow messages larger than 20 MB", i.e. the shipped `message_size_limit` is 20 MB unless the host raised it. The two statements are compatible only if Stormweb overrode the default; treat 20 MB as the working ceiling until confirmed. For outbound design it barely matters: keep any attachment small (a few MB) and send links for documents, both because of recipient-side limits and because every byte of a 90-recipient mailing is multiplied by ninety.
Sources: https://stormweb.ca/webhosting.php ; https://docs.directadmin.com/other-hosting-services/exim/

### 4. Authentication of the domain

**What exists today (public DNS, 2026-09-06).**
- SPF: present, ends in `-all` (hard fail), lists the host's addresses.
- DKIM: present at selector `x` — DirectAdmin's default selector — so DKIM signing is enabled for the domain in DirectAdmin.
- DMARC: present, `p=none`, no `rua` reporting address.
- Nameservers: Stormweb's.

**Who manages what.** Stormweb's plan page lists "SPF, DKIM, DMARC records" as included anti-forgery protections. Its knowledge-base article on junk-folder placement walks through adding a DMARC record from the *Stormweb client area* → hosting plan → DNS Management (record type TXT → DMARC, with strict SPF and DKIM alignment options). DirectAdmin's user-level path for DKIM is E-Mail Manager → E-Mail Accounts → Enable DKIM (only offered when the host sets `dkim=2`); DirectAdmin writes the SPF record and the DKIM key into the zone automatically. DirectAdmin's own guidance before adding DMARC: SPF must end in `-all` and DKIM must be set up — both are true here.
Sources: https://stormweb.ca/webhosting.php ; https://stormweb.ca/knowledgebase/article/26/email-from-my-domain-is-going-to-the-junk-mail-folder/ ; https://docs.directadmin.com/other-hosting-services/email/reducing-sending-spam-score.html ; https://forum.directadmin.com/threads/how-to-enable-dkim-spf-for-server-domain.66593/

**Why ADR-0001 called deliverability "unpredictable".** The archaeology notes say only "Deliverability on shared hosting via `mail()` is mediocre" and recommend a transactional service post-cutover; they record no incident. With all three records in place, the plausible residual causes are (a) shared-IP reputation on the host, which we cannot control, and (b) `mail()` sends whose envelope sender is the server hostname, not the domain — SPF then passes on the wrong domain and DMARC alignment fails. (b) disappears with authenticated SMTP. (a) is the one argument left for an external transactional provider, and it is an empirical question: watch DMARC reports (add a `rua`) for a month after cutover.
Source: archaeology notes § Email

### 5. Bounces

- Exim delivers bounces to the envelope sender (Return-Path). DirectAdmin's Exim docs discuss trimming bounces (`bounce_return_message = false`) but there is no panel-level bounce hook, webhook, or export.
- Over authenticated SMTP the envelope sender is the app mailbox; bounces arrive there as normal mail, readable in webmail or over IMAP. Laravel's `Message` exposes `returnPath()` and `sender()` if the Return-Path should differ from From (e.g. a `bounces@` mailbox).
- Over `sendmail`/`mail()` without `-f`, the envelope sender is the system account at the server hostname; bounces go to that system account's local mailbox, which nobody reads. That is the legacy situation.
- DirectAdmin counts bounces and system messages under a separate "unknown" limit (`limit_unknown`); a bounce storm therefore does not consume the account's daily budget, but is itself capped.
- Nothing in v1 reads bounces. What we are ignoring: hard bounces will pile up in the app mailbox; a human must occasionally empty it and fix addresses by hand. Inbound is out of scope by ticket definition.
Sources: https://docs.directadmin.com/other-hosting-services/exim/ ; https://api.laravel.com/docs/12.x/Illuminate/Mail/Message.html ; https://forum.directadmin.com/threads/php-and-exim-return-path.32933/ ; https://forum.directadmin.com/threads/all-emails-being-rejected.39747/

### 6. Cron

- Stormweb's plan page lists "Cron Job scheduling" and "SSH/SFTP Access" on every plan. The archaeology notes confirm SSH works and that Composer and Git are installed account-wide.
- DirectAdmin's user-level Cron Jobs page (Advanced Features → Cron Jobs) takes a "command" field that is written verbatim into the account's crontab; DirectAdmin's own hook documentation gives `echo "test" >/dev/null 2>&1` as the example command. It is a shell command, not a URL fetcher.
- The legacy nightly job is already a shell command — `wget` against a URL — proving arbitrary commands execute under this account.
- Laravel needs one entry: `* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1`. On this host `php` resolves to 7.4; PHP 8.4 lives at `/usr/local/php84/bin/php`, so the crontab must use the full path (the deploy scripts already do).
- Cron runs count against the same DirectAdmin resource limits as web requests (Stormweb's 503/508 article: process count, RAM, disk I/O, CPU), which is another reason the bulk-mail chunks should be modest.
Sources: https://stormweb.ca/webhosting.php ; https://docs.directadmin.com/developer/hooks/cron.html ; https://laravel.com/docs/12.x/scheduling#running-the-scheduler ; https://stormweb.ca/knowledgebase/article/40/resource-limit-errors/ ; archaeology notes § Hosting

## Implications for the mail-provider ADR

Not a decision, just what these facts do to the options:

- Stormweb's own SMTP is viable at our volume *with* an app-side budget (≤ 4000/day shared with the rest of the account, ≤ 500/hr) and chunked bulk sends. Canadian residency and zero cost hold.
- The one thing it cannot promise is inbox placement from a shared IP. A DMARC `rua` on the domain is a cheap way to measure that after cutover before deciding whether an external provider is needed.
- If an external provider is ever chosen, everything above about SPF/DKIM/DMARC still applies — the provider's include/selector goes into the same Stormweb DNS Management page.

## Needs a human

Facts that only Stormweb support, the control panel, or a live test can settle:

1. **Confirm the daily limit.** Ask Stormweb: is the account's DirectAdmin daily send limit 4000, is it per hosting account (all domains and mailboxes combined), and will they raise it on request?
2. **Is 500/hour enforced or policy?** Ask whether the AUP's 500/hr is implemented in Exim (and what the client sees when it trips) or is a policy line enforced by humans after the fact.
3. **Recipient counting on this server.** Ask whether `SEND_LIMIT_COUNT_RECIPIENTS` is at its default (every recipient counts) — or test: send one message to three addresses and watch E-Mail Usage tick by 1 or 3.
4. **Daily-counter reset time.** Observe in E-Mail Usage, or ask. Sizes the "deferred to tomorrow" behaviour.
5. **Message size limit.** Ask which is true: Exim's 20 MB default or the advertised 512 MB.
6. **SMTP from PHP on the same box.** From an SSH session on the account, confirm a STARTTLS-authenticated connection to `mail.<domain>:587` succeeds (and whether 465 exists). This is the go/no-go for the `smtp` mailer choice.
7. **Create the application mailbox** in DirectAdmin (E-Mail Manager) and decide who reads it; it is where bounces and limit notifications will land.
8. **Add a DMARC `rua`** in Stormweb DNS Management so aggregate reports arrive somewhere. Leave `p=none` until the app is the only sender.
9. **Only if the `sendmail` mailer is chosen instead:** ask whether the account has a per-user `php.ini` with `sendmail_path … -f <app mailbox>`, since scripts cannot set the envelope sender any other way.

## Sources

Stormweb
- https://stormweb.ca/webhosting.php — plan features (SMTP, SSL/TLS, SPF/DKIM/DMARC, 512 MB attachments, cron, SSH, PHP versions, DirectAdmin)
- https://stormweb.ca/acceptable-use-policy.php — 500 emails/hour/account, bulk-mail consent rules, suspension/termination
- https://stormweb.ca/knowledgebase/article/39/configuring-an-email-address-in-gmail/ — SMTP host/port/TLS/username
- https://stormweb.ca/knowledgebase/article/26/email-from-my-domain-is-going-to-the-junk-mail-folder/ — DMARC via client-area DNS Management
- https://stormweb.ca/knowledgebase/article/9/someone-is-sending-spam-from-my-email-address/ — support reviews SPF/DKIM/DMARC on ticket
- https://stormweb.ca/knowledgebase/article/40/resource-limit-errors/ — 503/508 resource limits

DirectAdmin
- https://docs.directadmin.com/other-hosting-services/preventing-spam/outgoing-spam.html — per-User and per-mailbox daily limits, scripts included, SMTP-time blocking, notifications
- https://docs.directadmin.com/changelog/version-1.702.html — local-to-local counted
- https://docs.directadmin.com/changelog/version-1.703.html — `SEND_LIMIT_COUNT_RECIPIENTS`, default counts all recipients
- https://docs.directadmin.com/changelog/version-1.45.0.html — mailbox notification on limit
- https://docs.directadmin.com/other-hosting-services/exim/ — 20 MB default, sendmail path, ports, bounce options
- https://docs.directadmin.com/other-hosting-services/email/reducing-sending-spam-score.html — SPF `-all` + DKIM before DMARC
- https://docs.directadmin.com/developer/hooks/cron.html — user cron "command" field
- https://forum.directadmin.com/threads/number-of-outgoing-emails-is-incorrect.82292/ — staff: recipients counted for send limit
- https://forum.directadmin.com/threads/maximum-amount-of-emails-per-user-and-server-per-hour.43681/ — staff: no per-hour feature; stops at limit
- https://forum.directadmin.com/threads/all-emails-being-rejected.39747/ — "have reach your daily email limit" log text, `limit_unknown`
- https://forum.directadmin.com/threads/php-and-exim-return-path.32933/ — envelope sender for PHP mail, `-f` and per-user php.ini
- https://forum.directadmin.com/threads/mail-sending-problems-from-cms-since-da-1-680.77939/ — sender-spoofing block on auth'd SMTP
- https://forum.directadmin.com/threads/how-to-enable-dkim-spf-for-server-domain.66593/ — user-level Enable DKIM, `dkim=2`

Laravel / PHP
- `config/mail.php` (this repo) — `smtp` and `sendmail` mailer defaults
- https://laravel.com/docs/12.x/scheduling#running-the-scheduler — the single cron entry
- https://api.laravel.com/docs/12.x/Illuminate/Mail/Message.html — `returnPath()`, `sender()`
- https://www.php.net/manual/en/function.mail.php — `additional_params` / `-f`, `sendmail_path`

Private
- Archaeology notes (private repo), sections "Email", "Nightly cron", "Outbound integrations", "Hosting" — 2666 sends on the peak day, 4000/day observed cap, `mail()` only, cron is a `wget` shell command, cap check commented out
- Public DNS lookups for the DMV domain, 2026-09-06 — SPF/DKIM/DMARC presence
