# TASK

Review the changes on branch `{{BRANCH}}` and improve their clarity,
consistency, and maintainability. Every change you make must be
**behaviour-preserving**: you change _how_ the code works, never _what_ it does.
Once the review is complete, open a pull request into `{{TARGET_BRANCH}}`.

# CONTEXT

## Branch diff

!`git diff {{TARGET_BRANCH}}...{{BRANCH}}`

## Commits on this branch

!`git log {{TARGET_BRANCH}}..{{BRANCH}} --format='%h %s%n%b'`

The implementer references the issue it worked on as `Refs #<ID>` in the commit
body above. Read the ID from there — the PR body depends on it.

## Standards

Read `@.sandcastle/CODING_STANDARDS.md` before you start. **There is no Sail in
this sandbox** — run `php artisan`, `vendor/bin/pest`, and `vendor/bin/pint`
directly. `CLAUDE.md` is already in your context and its hard rules are
non-negotiable.

# REVIEW PROCESS

Open **every file the diff touches and read it end-to-end** — the whole file, not
only the changed hunks. A hunk that reads fine in isolation is how duplicated
logic, a contradicted convention, and a broken caller all get through.

Work through these four passes on each file, then produce the account below.

1. **Correctness** — does the implementation do what the issue asked? Walk the
   edge cases: empty results, missing relations, unauthorized actors, both
   locales. Are the new behaviours actually covered by tests, and do those tests
   exercise the public interface rather than internals?

2. **Security** — every document download routes through a controller with a
   policy check, and user-facing filenames are never stored as disk paths (both
   are hard rules). Beyond those: authorization on every new route and action,
   no raw SQL, no unsafe casts, no credentials or internal IDs leaking into
   Inertia props.

3. **Bilingual** — every user-facing string is translatable, and every new key
   exists in **both** the English and French lang files. Open the files and
   check; a missing French key ships as a raw key to a French user.

4. **Clarity** — reduce nesting and unnecessary complexity, eliminate redundant
   abstractions, improve names, consolidate related logic, drop comments that
   restate the code, replace nested ternaries. Prefer clarity over brevity.
   Match `docs/conventions.md` and any ADR in `docs/adr/` that names the
   subsystem being touched.

**The account.** Before moving to EXECUTION, write out every file in the diff
with one line each: either what you found, or `checked: nothing`. A file you did
not open cannot get a line. This account is the completion criterion for the
review — an empty finding list is a fine result, an unaccounted file is not.

# EXECUTION

1. Apply the fixes from your account directly on this branch. If the account is
   all `checked: nothing`, make no changes and carry on.

2. Run the full gate from `@.sandcastle/CODING_STANDARDS.md` and get it green.
   Remember the gate's tests run on SQLite while CI decides the merge on
   MariaDB — flag anything engine-sensitive in the PR body.

3. If you made fixes, commit them with a conventional-commit prefix
   (`refactor:` or `fix:`) and no `Co-Authored-By` trailer.

4. Push the branch and open the PR:

    ```
    git push -u origin {{BRANCH}}
    gh pr create --base {{TARGET_BRANCH}} --head {{BRANCH}} \
      --title "<conventional-commit prefix>: <short summary>" \
      --body "Closes #<ID>

    <one-paragraph summary of the change, plus any review notes worth a
    human's attention>

    🤖 sandcastle"
    ```

    The PR body **must** contain `Closes #<ID>` — without it the merge leaves the
    issue open.

Once the PR is open, output <promise>COMPLETE</promise>.

# If you cannot finish

If the gate fails on the implementer's code and you cannot fix it
behaviour-preservingly, or the change contradicts a hard rule or an accepted
ADR, do not open the PR. Instead: push nothing, comment on the issue explaining
what is wrong, re-add the `ready-for-agent` label so the next iteration picks it
up, and stop. Say plainly that you stopped and why. Shipping a PR you do not
believe in is the worse outcome.
