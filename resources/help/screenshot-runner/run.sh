#!/usr/bin/env bash
#
# Help screenshot runner (PRD #516, ADR-0025, #521).
#
#   resources/help/screenshot-runner/run.sh <article-slug>
#
# Takes one article slug, logs in to the local app as that article's Persona,
# walks its step script in a 1280 px desktop browser, and writes NN.png files
# into the article's public screenshot folder (public/help/<slug>/).
#
# It needs a running app and a browser, so it runs on a developer's machine, not
# in the autonomous sandbox. It shoots English chrome only and draws no
# annotations — the caption carries the meaning. When a shot comes out wrong,
# replace that one PNG by hand; the step script stays as the record of intent.
# See README.md beside this file for the Persona table and the step convention.
#
# ── agent-browser coupling ──────────────────────────────────────────────────
# Every call into the agent-browser CLI goes through the browser_* wrappers
# below, so the CLI surface this runner needs is one screenful. agent-browser is
# allowlisted in this repo but nothing was scripted against it before this
# ticket, so confirm the exact subcommands on the first real run and adjust the
# wrappers alone if they differ.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
HELPERS_FILE="$SCRIPT_DIR/page-helpers.js"

# The local app and the seeded dev password. Override BASE_URL for a non-default
# Sail port. The password is the one DemoSeeder hashes for every Persona.
BASE_URL="${BASE_URL:-http://localhost}"
DEV_PASSWORD="${DEV_PASSWORD:-password}"
VIEWPORT_WIDTH=1280
VIEWPORT_HEIGHT=800

# ── agent-browser wrappers ──────────────────────────────────────────────────

browser_goto() { agent-browser goto "$1"; }

browser_viewport() { agent-browser viewport "$1" "$2"; }

browser_shot() {
    mkdir -p "$(dirname "$1")"
    agent-browser screenshot "$1"
}

# Load the in-page helper library into the current page. A navigation drops it,
# so re-inject before every helper call.
browser_inject() { agent-browser eval "$(cat "$HELPERS_FILE")"; }

browser_login() {
    browser_goto "$BASE_URL/login"
    browser_inject
    agent-browser eval "await window.__help.login('$1', '$2')"
}

# Call one named window.__help function on the current page.
browser_call() {
    browser_inject
    agent-browser eval "window.__help.$1()"
}

# ── step-script DSL ─────────────────────────────────────────────────────────
# A step script is sourced, not run. `persona` and `start` set the login and the
# opening page; `nav` and `act` record steps, which the runner replays after it
# has logged in. Each step ends in a shot: `nav <path> <NN>` or `act <fn> <NN>`.

PERSONA_EMAIL=""
START_PATH=""
STEPS=()

persona() { PERSONA_EMAIL="$1"; }
start() { START_PATH="$1"; }
nav() { STEPS+=("goto|$1|$2"); }
act() { STEPS+=("call|$1|$2"); }

# ── main ────────────────────────────────────────────────────────────────────

SLUG="${1:-}"
if [[ -z "$SLUG" ]]; then
    echo "Usage: $0 <article-slug>" >&2
    exit 2
fi

STEP_FILE="$SCRIPT_DIR/$SLUG.steps.sh"
if [[ ! -f "$STEP_FILE" ]]; then
    echo "Error: no step script for '$SLUG' (expected $STEP_FILE)" >&2
    exit 1
fi

OUT_DIR="$REPO_ROOT/public/help/$SLUG"

# shellcheck source=/dev/null
source "$STEP_FILE"

if [[ -z "$PERSONA_EMAIL" ]]; then
    echo "Error: $STEP_FILE declares no persona" >&2
    exit 1
fi

echo "Shooting '$SLUG' as $PERSONA_EMAIL into public/help/$SLUG/"

browser_login "$PERSONA_EMAIL" "$DEV_PASSWORD"
browser_viewport "$VIEWPORT_WIDTH" "$VIEWPORT_HEIGHT"
[[ -n "$START_PATH" ]] && browser_goto "$BASE_URL$START_PATH"

for entry in "${STEPS[@]}"; do
    IFS='|' read -r kind arg shot <<<"$entry"
    case "$kind" in
    goto) browser_goto "$BASE_URL$arg" ;;
    call) browser_call "$arg" ;;
    esac
    browser_shot "$OUT_DIR/$shot.png"
    echo "  $shot.png"
done

echo "Done. Review the shots and hand-replace any the script got wrong."
