// Autonomous agent loop — sequential implement → review → PR → CI → squash-merge.
//
// Per iteration (one issue):
//   1. Host forks a fresh branch off the latest `staging`.
//   2. Implementer (Opus) picks the highest-priority `ready-for-agent` issue,
//      implements it red-green-refactor, runs the full CI gate, commits.
//   3. Reviewer (Sonnet) reviews + fixes in the SAME sandbox, re-runs the gate,
//      pushes the branch, and opens a PR into `staging`.
//   4. Host owns the merge gate: waits for CI (`gh pr checks --watch`), and on
//      green squash-merges; otherwise leaves the PR open for manual review.
//   5. Loop. Serialized so each issue builds on merged work (no branch races).
//
// See docs/adr/0016-autonomous-agent-loop.md for the why.
//
// Run:  pnpm run sandcastle   (= npx tsx .sandcastle/main.ts)
// Needs: Docker Desktop running, .sandcastle/.env filled, a clean `staging`,
//        and at least one `ready-for-agent` issue.

import * as sandcastle from '@ai-hero/sandcastle';
import { docker } from '@ai-hero/sandcastle/sandboxes/docker';
import { execSync } from 'node:child_process';

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

const MAX_ITERATIONS = 10;
const TARGET_BRANCH = 'staging';

// Sandbox setup, mirroring .github/workflows/ci.yml. composer + pnpm install
// give the agent vendor/ and a node_modules with correct Linux bindings. The
// host node_modules is macOS-arch and is deliberately NOT copied in (that would
// break esbuild/rollup/lightningcss native bindings), so there is no
// copyToWorktree here — the sandbox installs cleanly each iteration.
//
// onSandboxReady commands run in PARALLEL, so this must be a single sequential
// chain: key:generate needs vendor/ (composer) in place first, and Vite needs
// node_modules (pnpm). One command keeps the order deterministic.
const hooks = {
    sandbox: {
        onSandboxReady: [
            {
                command:
                    'composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader && ' +
                    'pnpm install --frozen-lockfile && ' +
                    'cp .env.example .env && php artisan key:generate',
                timeoutMs: 900_000,
            },
        ],
    },
};

// ---------------------------------------------------------------------------
// Host helpers
// ---------------------------------------------------------------------------

function sh(command: string): string {
    return execSync(command, {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    }).trim();
}

// Run a command for its output, swallowing failures (non-zero exit -> '').
// Used by the cleanup checks below, where "command failed" is just "no".
function tryCapture(command: string): string {
    try {
        return execSync(command, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    } catch {
        return '';
    }
}

// ---------------------------------------------------------------------------
// Worktree cleanup
// ---------------------------------------------------------------------------
//
// sandcastle.createSandbox({ branch }) materializes a git worktree under
// .sandcastle/worktrees/ for every iteration. sandbox.close() tears down the
// Docker sandbox but NOT the worktree, and `gh pr merge --delete-branch` only
// removes the *remote* branch — the local branch can't be deleted while it's
// still checked out in the leftover worktree. So worktrees + local branches
// pile up one per iteration. This prunes them.
//
// HARD INVARIANT: never delete un-pushed work. A worktree is removed only when
// it is provably safe — it is clean (no uncommitted/untracked changes) AND its
// work is preserved elsewhere (branch pushed to origin, OR its PR is merged, OR
// the branch has no unique commits over the target branch). Anything else is
// left intact and logged, so a crashed-mid-edit or never-pushed iteration stays
// recoverable by hand. `git worktree remove` is called WITHOUT --force, so git
// itself refuses to remove a dirty worktree as a backstop even if the explicit
// checks somehow miss it.
//
// Called once at startup (to sweep crash-recovery leftovers from prior runs)
// and after each iteration's merge gate (to clean up the iteration that just
// landed). Idempotent: only ever removes worktrees that pass the safety check.
function pruneSandcastleWorktrees(): void {
    sh('git worktree prune');

    // Parse `git worktree list --porcelain` into { path, branch } records,
    // keeping only our sandcastle/* worktrees.
    const out = tryCapture('git worktree list --porcelain');
    const trees: { path: string; branch: string }[] = [];
    let path = '';
    for (const line of out.split('\n')) {
        if (line.startsWith('worktree ')) {
            path = line.slice('worktree '.length);
        } else if (line.startsWith('branch refs/heads/')) {
            const branch = line.slice('branch refs/heads/'.length);
            if (branch.startsWith('sandcastle/')) trees.push({ path, branch });
        }
    }

    for (const { path, branch } of trees) {
        const dirty = tryCapture(`git -C "${path}" status --porcelain`) !== '';
        if (dirty) {
            console.warn(`Keeping ${branch}: worktree has uncommitted changes.`);
            continue;
        }

        const pushed = tryCapture(`git ls-remote --heads origin "${branch}"`) !== '';
        const merged = tryCapture(`gh pr list --head "${branch}" --state merged --json number -q '.[0].number'`) !== '';
        const noUniqueCommits = tryCapture(`git rev-list --count ${TARGET_BRANCH}..${branch}`) === '0';
        if (!(pushed || merged || noUniqueCommits)) {
            console.warn(`Keeping ${branch}: has un-pushed commits and no merged PR.`);
            continue;
        }

        try {
            sh(`git worktree remove "${path}"`); // no --force: dirty-tree backstop
            tryCapture(`git branch -D "${branch}"`); // may already be gone (--delete-branch)
            console.log(`Pruned worktree + branch ${branch}.`);
        } catch {
            console.warn(`Could not remove worktree for ${branch} — leaving it for manual review.`);
        }
    }
}

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

// Safety net: sweep any worktrees left behind by a previous run that crashed
// or was interrupted before its per-iteration cleanup ran.
pruneSandcastleWorktrees();

for (let iteration = 1; iteration <= MAX_ITERATIONS; iteration++) {
    console.log(`\n=== Iteration ${iteration}/${MAX_ITERATIONS} ===\n`);

    // Always fork off the latest staging so each issue builds on merged work.
    // --prune drops the stale origin/sandcastle/* tracking ref left behind
    // when the previous iteration's PR merged with --delete-branch.
    sh(`git checkout ${TARGET_BRANCH}`);
    sh(`git fetch --prune origin`);
    sh(`git pull --ff-only`);

    const branch = `sandcastle/${Date.now()}`;

    // One sandbox shared by implementer and reviewer, on the same named branch.
    const sandbox = await sandcastle.createSandbox({
        branch,
        sandbox: docker(),
        hooks,
    });

    try {
        const implement = await sandbox.run({
            name: 'implementer',
            maxIterations: 1,
            agent: sandcastle.claudeCode('claude-opus-4-8'),
            promptFile: './.sandcastle/implement-prompt.md',
        });

        if (!implement.commits.length) {
            console.log('No commits — backlog empty or all remaining issues blocked. Stopping.');
            break;
        }
        console.log(`Implemented on ${branch} (${implement.commits.length} commit(s)).`);

        // Reviewer reviews + fixes, then pushes the branch and opens the PR.
        // {{TARGET_BRANCH}} is auto-injected from the host's active branch (staging).
        await sandbox.run({
            name: 'reviewer',
            maxIterations: 1,
            agent: sandcastle.claudeCode('claude-sonnet-4-6'),
            promptFile: './.sandcastle/review-prompt.md',
            promptArgs: { BRANCH: branch },
        });
        console.log('Review complete; PR opened against staging.');
    } finally {
        await sandbox.close();
    }

    // Merge gate: wait for CI, squash-merge on green, else leave the PR for review.
    try {
        console.log(`Waiting for CI on ${branch}...`);
        execSync(`gh pr checks ${branch} --watch --fail-fast`, { stdio: 'inherit' });
        sh(`gh pr merge ${branch} --squash --delete-branch`);
        console.log(`Merged ${branch} into ${TARGET_BRANCH}.`);
    } catch {
        console.warn(`CI failed or no PR for ${branch} — leaving it open for manual review.`);
    }

    // Clean up this iteration's worktree (and any other now-safe leftovers).
    // A merged iteration is removed here; a CI-failed-but-pushed one has its
    // local worktree removed while the PR/branch stay on GitHub for review.
    pruneSandcastleWorktrees();
}

console.log('\nAutonomous loop complete.');
