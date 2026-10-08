---
name: whats-new
description: Draft a "What's new" email for a shipped spec and save it as a labelled Gmail draft.
disable-model-invocation: true
---

# whats-new

Turn one shipped spec into a short "What's new" email for the DMV internal team, saved as a
Gmail draft under the `DMV/What's new` label. The user reviews and sends it. Invoked as
`/whats-new <spec issue number or URL>`.

Readers are volunteers and staff, not developers. Every claim in the email describes what
**shipped**, which can differ from what the spec asked for.

## 1. Find what shipped

1. Read the spec: `gh issue view <n>`.
2. List its tickets: `gh issue list --search "parent-issue:roytanaka/dmv-rom-v2#<n>" --state all`.
3. Read the PRs that closed them, and any later PR that amends the feature
   (`gh pr list --state merged --search "<feature words>"`). Read the ADRs the spec names
   for amendment notes dated after the spec.

Done when you hold a list of every user-visible change, each traced to a merged PR, and a
list of every spec item that changed or was cut before shipping.

## 2. Check past emails

1. `list_labels`. Find the ID of `DMV/What's new`. Create it with `create_label` when it is
   missing.
2. Sent emails: `search_threads` with `label:<id>`, then `get_thread` (`PLAIN_TEXT`) on each
   one that touches this feature or its neighbours. `search_threads` never returns drafts.
3. Unsent drafts: `list_drafts`, and read each one whose subject starts `DMV v2, what's new:`.

Done when every past statement that this release changes is listed with its email date.
These go in the email's "Changed since last time" section.

## 3. Write the email

Fill the shape in [Email shape](#email-shape). Write it in plain English: short sentences,
active voice, one idea per bullet. Run the repo's `humanizer` skill over it. Name DMV people
by role.

Done when the TL;DR alone tells a reader what they can now do, and every bullet traces
to step 1 or step 2.

## 4. Save the draft

1. `create_draft` with `subject`, `htmlBody`, and a plain-text `body`. Leave the recipients
   empty: the user adds them.
2. `label_thread` with the draft's `threadId` and the label ID. `update_draft` returns a
   new `threadId`: label it again after every edit.

Done when the draft exists with the label. Report the `viewUrl` and list any spec item you
left out on purpose.

## Email shape

Subject: `DMV v2, what's new: <feature name>`. The fixed prefix makes past emails easy to find
even without the label.

Sections, in this order. Drop any section with nothing to say.

1. **TL;DR**: two or three sentences. What the reader can now do, and where to find it.
2. **What's new**: one bullet per feature, a bold name first, then one line.
3. **Changed since last time**: each item says what the earlier email said, what is true
   now, and the earlier email's date.
4. **Try it on staging**: the staging URL, the Persona to pick in the Role-switcher, and
   three to six numbered things to try.
5. **To do**: what this release leaves out, one line each.
6. **Find a bug?**: report it with the Send feedback button on staging.

Length: under 350 words. Use `<h3>` headings, `<ul>`/`<ol>` lists and `<strong>` in
`htmlBody`. Keep the markup plain: no styling and no images.
