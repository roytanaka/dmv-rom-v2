# Context

## Issues ready for the agent

!`gh issue list --state open --label ready-for-agent --limit 100 --json number,title,labels --jq '[.[] | {number, title, labels: [.labels[].name]}]'`

This index is the sole source of truth for what work exists. Do not run your own
unfiltered query — if the index is empty, there is nothing to do. It carries
titles only; you pull the body and comments for the one issue you pick.

## Recent commits (last 10)

!`git log --oneline -10`

Shown so your commit message matches the repo's existing style.

# Task

You are an autonomous coding agent working through issues one at a time in this
repo (a Laravel 12 + Inertia + Vue 3 rebuild).

Read `CLAUDE.md` for the hard rules, and `@.sandcastle/CODING_STANDARDS.md` for
how this sandbox differs from a developer's machine — **there is no Sail here**,
so run `php artisan`, `vendor/bin/pest`, and `vendor/bin/pint` directly.

## Priority order

Pick the highest-priority issue in the index that is **not blocked**. An issue
is blocked when its body or comments name another issue as a prerequisite
("Blocked by #N", "Depends on #N") and that issue is still open — check with
`gh issue view <N> --json state`.

1. Anything labelled `bug` — broken behaviour
2. **Tracer bullets** — thin end-to-end slices that prove an approach works;
   the issue says so in its title or body
3. Everything else

Within a tier, take the **lowest issue number** — oldest first, so the choice is
the same every run.

## Workflow

1. **Explore** — read the issue in full (`gh issue view <ID> --comments`) and
   pull in the parent PRD if referenced. Read the source files and existing
   tests around the change. You are done exploring when you can name every file
   you expect to change and the existing tests that cover that behaviour.

2. **Plan** — write the vertical slice as an ordered list of behaviours. Each
   one becomes a single RGR cycle in Execute. Keep the list as short as the
   issue allows.

3. **Claim** — now that you know the issue is workable, take it off the queue so
   the next iteration does not pick it again:
   `gh issue edit <ID> --remove-label ready-for-agent`

4. **Execute** — work the list from step 2 one behaviour at a time, RGR, never
   all-tests-then-all-code. Every behaviour on the list gets its own cycle:

    - **Red** — one failing test for this behaviour, exercising the public
      interface (HTTP routes, Inertia responses, model API). Verify the failure
      is the one you expect before writing code.
    - **Green** — the minimal code that passes it. Write what this test needs and
      nothing further.
    - **Refactor** — only once green. Extract duplication, simplify interfaces,
      re-run the tests after each step.

    Follow the patterns in `docs/conventions.md` and the ADRs that name the
    subsystem you are touching.

5. **Verify** — run the full gate from `@.sandcastle/CODING_STANDARDS.md` and
   fix every failure. The gate must be green before you commit.

6. **Commit** — one commit. The message must:
    - Start with a conventional-commit prefix (`feat:`, `fix:`, `chore:`,
      `refactor:`, `docs:`, `test:`) matching the change
    - Reference the issue (`Refs #<ID>`) and any parent PRD
    - Summarise the change and the decisions behind it
    - Carry no `Co-Authored-By` trailer (repo convention)

## Rules

- One issue per iteration. Stop after the commit.
- The reviewer agent pushes and opens the PR, and the merge closes the issue —
  so leave the branch local, and leave the issue open.
- Commit only code you would merge: dead code deleted, unfinished notes
  resolved, no commented-out blocks.
- If you get blocked — missing context, a test you cannot make pass, an external
  dependency, or a change that would need a new package — stop and hand it back:
  re-add `ready-for-agent`, comment on the issue explaining exactly what blocked
  you, and make no commit. A clean hand-back is a good outcome; a commit that
  papers over the blocker is not.

# Done

If the index at the top is empty, or every issue in it is blocked, make no
commit and output the completion signal:

<promise>COMPLETE</promise>
