---
name: verify
description: |
    Run a branch or PR in the browser to confirm it works. Use for /verify, or to
    see a change working in the real app, not just tests.
---

# verify

Bring the branch up beside the main checkout on :8091, drive it with agent-browser,
take it down. Everything below runs from the repo root, one plain command each.

## 1. Docker

```
docker info
```

A 500 error (or no answer) means Docker Desktop is wedged: run `docker desktop restart`,
then `docker info` again. Sail must be up too (`pnpm sail up -d`).

## 2. Up

```
scripts/verify-up.sh <git-ref>
```

Done when it prints `Up: http://localhost:8091`. It makes worktree
`.claude/worktrees/verify-<name>`, database `dmv_verify_<name>`, container
`verify-<name>`, with demo data seeded. `<name>` is the ref minus `origin/`
(`origin/807-foo` → `807-foo`); pass a second argument to choose it.

## 3. Find an account

```
docker exec -w /var/www/html/.claude/worktrees/verify-<name> dmv-rom-v2-laravel.test-1 php artisan demo:who <group-slug>
```

Lists each membership's email, status and roles, so a Trainee, an LOA, a Resigned
member or a Secretary is one lookup. The named Personas are in the table in
`resources/help/screenshot-runner/README.md`. Every demo password is `password`.

## 4. Drive the page

Use a session of your own (`AGENT_BROWSER_SESSION=verify-<name>`). `page-helpers.js`
puts its helpers on `window.__help`; a navigation drops them, so inject after every
`open`:

```
agent-browser open http://localhost:8091/login
agent-browser eval "$(cat resources/help/screenshot-runner/page-helpers.js)"
agent-browser eval "window.__help.login('amara.abara@dmv.test', 'password')"
```

- **Dialogs**: an agent-browser click never opens a Radix dialog or menu. Open a
  dialog with `window.__help.clickButton('New shift')` (exact button text). For menus
  and why, read "The Radix-overlay workaround" in the runner README.
- **Props**: `window.__help.props()` returns the Inertia page props.
- **Refusal probes**: `window.__help.request('POST', url, body)` returns
  `{status, body}`. Use it to show a 403 or 422 the UI never lets you send; drive every
  write a user can make through the UI, dialogs included.

If a shell refuses `$(cat …)`, put the commands in a script file and run that.

## 5. Down

```
scripts/verify-down.sh <name>
```

Done when container `verify-<name>`, database `dmv_verify_<name>` and the worktree are
all gone.
