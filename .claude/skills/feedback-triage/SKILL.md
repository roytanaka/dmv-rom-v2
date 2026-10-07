---
name: feedback-triage
description: |
    Turn new Feedback items from staging into GitHub tickets. Use when the user
    asks to check, read, or triage feedback.
---

# feedback-triage

Testers send Feedback items from staging ([ADR-0029](../../../docs/adr/0029-tester-feedback.md)).
They live in the `feedback` database on Stormweb, which staging deploys never reset.
The Support-operator copies the useful ones into GitHub by hand. This skill does that
copy and then writes the outcome back to each item.

Do the steps in order. Step 3 is the approval gate: write to GitHub and staging only
after it.

## Running the scripts

Both scripts are piped over SSH into the staging app folder. Stormweb's default `php`
is 7.4, so call the 8.4 binary by its full path. Run from the repo root:

```bash
ssh dmvromca@dmv-rom.ca 'cd ~/domains/staging.dmv-rom.ca/dmv-rom-v2 && /usr/local/php84/bin/php -- <args>' < .claude/skills/feedback-triage/<script>.php
```

- `list.php [status ...]` — read-only. Prints items with full context, comments, and
  screenshot paths. With no args, it prints `new` and `confirmed` items.
- `mark.php <id> <status> "<comment>" ["<name>"]` — sets the status and adds a comment
  signed with the name, or `Support-operator` when there is no name. Use a name only when
  the user asks for one. Statuses: `new`, `confirmed`, `fixed`, `wont-fix`, `duplicate`.
  The arguments sit inside the single-quoted SSH command, so write each `'` in a
  comment as `'\''`.

To view a screenshot, `scp` its path into the scratchpad and Read it.

## 1. Read the items

Run `list.php`. For each `new` item, read the message, the page, the comments, and any
screenshots. Open the code behind the page when the message alone leaves the change unclear.
The page is where the Tester sent the item from. It is not always the subject of the item.

Done when every `new` item has a one-line summary of what the Tester wants.

## 2. Sort each item

Check open and closed issues for each item first (`gh issue list --state all --search "<words>"`).
Then put the item in exactly one bucket:

| Bucket          | When                                                                             | Item status | GitHub                                                                |
| --------------- | -------------------------------------------------------------------------------- | ----------- | --------------------------------------------------------------------- |
| **Ticket**      | A clear bug or a small, specified change                                         | `confirmed` | Issue, `ready-for-agent`                                              |
| **Decision**    | It changes who sees what, changes scope, or the user must choose between designs | `confirmed` | Issue, `needs-triage`, with the decision and your recommended default |
| **Too big**     | It needs several tickets or an ADR                                               | `confirmed` | None. List it for the user to spec                                    |
| **Legacy data** | The fix is data the legacy import brings over                                    | `confirmed` | None. List it for the user's migration plan                           |
| **Duplicate**   | An issue already covers it                                                       | `duplicate` | Comment on the existing issue only if the item adds detail            |
| **Won't fix**   | Wrong, out of scope, or already works                                            | `wont-fix`  | None                                                                  |

Merge items that are one change into one ticket.

Done when every `new` item has a bucket and a reason.

## 3. Get approval

Show the user one table: item number, bucket, proposed ticket title or reason, and the
comment you will write on the item. Wait for approval. Apply the user's changes.

## 4. Write the tickets

Create the issues with `gh` (see [`docs/agents/issue-tracker.md`](../../../docs/agents/issue-tracker.md)).
Match the shape of recent tickets: `## What to build`, `## Acceptance criteria`, and
`## Blocked by` when it applies. Use the words in `CONTEXT.md`.

The repo is public. Name the source as a link to the item on staging:
`[Feedback item N](https://staging.dmv-rom.ca/feedback/N) on staging`. Never write `#N`
for a Feedback item: GitHub links `#N` to issue or PR N. Describe what the
Tester reported in your own words, without the Tester's name or Member name. Leave the
screenshots on staging and describe what they show.

Done when every Ticket and Decision item has an issue number.

## 5. Write back to staging

Run `mark.php` once per item with the approved status and comment. Comment shapes:

- Ticket or Decision: `Ticket #<issue>.`
- Too big: `Logged for a larger piece of work.`
- Legacy data: `Fixed by the legacy data import.`
- Duplicate: `Duplicate of #<issue>.`
- Won't fix: one sentence with the reason.

Done when every approved item shows its new status in `list.php`.

## 6. Close the loop

For each `confirmed` item whose comment names a ticket, check the ticket
(`gh issue view <n> --json state,closedByPullRequestsReferences`). The fix is on staging
when a closing PR shows `baseRefName` `staging` and state `MERGED` in `gh pr view <pr>
--json baseRefName,state`, because every push to `staging` deploys. Then propose `fixed`
with the comment `Fixed in #<issue>. Check it on staging.`
Run `mark.php` for the ones the user approves.

## 7. Report

Give the user: issues created, items marked, and the Too big and Legacy data lists.
