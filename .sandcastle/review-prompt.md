# TASK

Review the changes on branch `{{BRANCH}}`, improve clarity/consistency/
maintainability while preserving exact functionality, then open a pull request
into `{{TARGET_BRANCH}}`.

# CONTEXT

## Branch diff

!`git diff {{TARGET_BRANCH}}...{{BRANCH}}`

## Commits on this branch

!`git log {{TARGET_BRANCH}}..{{BRANCH}} --oneline`

The implementer references the issue it worked on as `Refs #<ID>` in its commit
message — read it from the log above; you need it for the PR body.

# REVIEW PROCESS

1. **Understand the change**: read the diff and commits to understand the intent.
2. **Analyze for improvements**: reduce unnecessary complexity and nesting;
   eliminate redundant code/abstractions; improve naming; consolidate related
   logic; remove comments that restate obvious code; avoid nested ternaries.
   Prefer clarity over brevity.
3. **Check correctness**: does the implementation match the issue's intent? Are
   edge cases and both languages (English + French) handled? Are new behaviours
   covered by tests? Any unsafe casts, injection, or credential leaks?
4. **Apply project standards**: follow `@.sandcastle/CODING_STANDARDS.md`,
   `CLAUDE.md`, and `docs/conventions.md`. Eloquent only — never raw SQL.
5. **Preserve functionality**: change only *how* the code works, never *what* it
   does.

# EXECUTION

1. If you find improvements, make them directly on this branch. If the code is
   already clean, make no changes.
2. Run the full gate and ensure it is green before opening the PR:
   `vendor/bin/pint --test` · `pnpm exec eslint .` · `pnpm run format:check` ·
   `pnpm run typecheck` · `pnpm run build` · `vendor/bin/pest`
3. If you made review fixes, commit them with a conventional-commit prefix
   (e.g. `refactor:` or `fix:`) and no `Co-Authored-By` trailer.
4. Push the branch and open the PR into `{{TARGET_BRANCH}}`:
   ```
   git push -u origin {{BRANCH}}
   gh pr create --base {{TARGET_BRANCH}} --head {{BRANCH}} \
     --title "<conventional-commit prefix>: <short summary>" \
     --body "Closes #<ID>

   <one-paragraph summary of the change and any review notes>

   🤖 sandcastle"
   ```
   The PR body **must** contain `Closes #<ID>` so the merge closes the issue.

Once the PR is open, output <promise>COMPLETE</promise>.
