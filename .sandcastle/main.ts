// Night shift — sequential implement → review → PR → CI → squash-merge loop.
//
// Per iteration (one issue):
//   1. Host forks a fresh branch off the latest `staging`.
//   2. Implementer (Opus) picks the highest-priority `ready-for-agent` issue,
//      implements it red-green-refactor, runs the full CI gate, commits.
//   3. Reviewer (Sonnet) reviews + fixes in the SAME sandbox, re-runs the gate,
//      pushes the branch, and opens a PR into `staging`.
//   4. Host owns the merge gate: waits for CI (`gh pr checks --watch`), and on
//      green squash-merges; otherwise leaves the PR open for the morning.
//   5. Loop. Serialized so each issue builds on merged work (no branch races).
//
// See docs/adr/0016-autonomous-night-shift-agents.md for the why.
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
const hooks = {
    sandbox: {
        onSandboxReady: [
            {
                command: 'composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader',
                timeoutMs: 600_000,
            },
            { command: 'pnpm install --frozen-lockfile', timeoutMs: 600_000 },
            {
                command: 'cp .env.example .env && php artisan key:generate',
                timeoutMs: 60_000,
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

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

for (let iteration = 1; iteration <= MAX_ITERATIONS; iteration++) {
    console.log(`\n=== Iteration ${iteration}/${MAX_ITERATIONS} ===\n`);

    // Always fork off the latest staging so each issue builds on merged work.
    sh(`git checkout ${TARGET_BRANCH}`);
    sh(`git pull --ff-only`);

    const branch = `agent/night/${Date.now()}`;

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
        console.warn(`CI failed or no PR for ${branch} — leaving it open for the morning review.`);
    }
}

console.log('\nNight shift complete.');
