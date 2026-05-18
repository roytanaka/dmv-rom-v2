#!/usr/bin/env bash
#
# Deploy procedure for the DMV-ROM rebuild on Stormweb shared hosting.
#
# Invoked by .github/workflows/deploy-staging.yml (and deploy-production.yml
# once slice 3 lands) over SSH, after the workflow has already rsync'd the
# build artifact into the environment's project root.
#
# Usage:  scripts/deploy.sh <environment>
# Where:  <environment> is "staging" or "production".
#
# This script is the single source of truth for the deploy procedure. The
# six steps below match issue #6 / ADR-0007.

set -euo pipefail

ENVIRONMENT="${1:-}"

if [[ "$ENVIRONMENT" != "staging" && "$ENVIRONMENT" != "production" ]]; then
    echo "Error: environment must be 'staging' or 'production' (got: '$ENVIRONMENT')" >&2
    exit 2
fi

# Stormweb's default `php` is 7.4; the app requires 8.4. See CLAUDE.md memory.
PHP_BIN="${PHP_BIN:-/usr/local/php84/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"

ARTISAN="$PHP_BIN artisan"
COMPOSER="$PHP_BIN $COMPOSER_BIN"

echo "==> Deploying to $ENVIRONMENT"
echo "    PHP:      $PHP_BIN"
echo "    Composer: $COMPOSER_BIN"
echo

# Step 1 (rsync) is handled by the workflow itself before this script runs.
# By the time we get here, the build artifact has already landed in the
# project root, with .env / storage/ / bootstrap/cache/ preserved.

echo "==> Step 2: composer install --no-dev --optimize-autoloader"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "==> Step 3: detect pending migrations"
# `migrate:status` prints a table with "Pending" rows when work is queued.
# On a virgin DB it prints either "No migrations found" (table exists, empty)
# or "Migration table not found." to stderr (table doesn't exist yet). Capture
# stdout+stderr so the virgin-DB branch fires on a fresh production deploy.
STATUS_OUTPUT=$($ARTISAN migrate:status 2>&1 || true)
if echo "$STATUS_OUTPUT" | grep -qE '(Pending|No migrations found|Migration table not found)'; then
    HAS_PENDING=1
else
    HAS_PENDING=0
fi

if [[ "$HAS_PENDING" -eq 1 ]]; then
    echo "    Pending migrations detected — wrapping in maintenance mode."

    $ARTISAN down

    # Trap so an interrupted migration still flips maintenance off.
    trap '$ARTISAN up || true' EXIT

    if [[ "$ENVIRONMENT" == "production" ]]; then
        SNAPSHOT_DIR="storage/db-snapshots"
        mkdir -p "$SNAPSHOT_DIR"
        TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
        SNAPSHOT_PATH="$SNAPSHOT_DIR/pre-migrate-$TIMESTAMP.sql"
        echo "==> Step 3a: mysqldump → $SNAPSHOT_PATH (production only)"
        # Read DB creds from .env via artisan tinker — avoids parsing .env in shell.
        DB_HOST="$($ARTISAN tinker --execute='echo config("database.connections.mariadb.host");')"
        DB_PORT="$($ARTISAN tinker --execute='echo config("database.connections.mariadb.port");')"
        DB_DATABASE="$($ARTISAN tinker --execute='echo config("database.connections.mariadb.database");')"
        DB_USERNAME="$($ARTISAN tinker --execute='echo config("database.connections.mariadb.username");')"
        DB_PASSWORD="$($ARTISAN tinker --execute='echo config("database.connections.mariadb.password");')"
        mysqldump \
            --host="$DB_HOST" \
            --port="$DB_PORT" \
            --user="$DB_USERNAME" \
            --password="$DB_PASSWORD" \
            --single-transaction \
            --routines \
            --triggers \
            "$DB_DATABASE" > "$SNAPSHOT_PATH"
    else
        echo "    Skipping mysqldump (staging is disposable)."
    fi

    echo "==> Step 3b: php artisan migrate --force"
    $ARTISAN migrate --force

    $ARTISAN up
    trap - EXIT
else
    echo "    No pending migrations — skipping maintenance-mode wrapping (zero-downtime path)."
fi

echo "==> Step 5: framework caches"
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN view:cache
$ARTISAN event:cache

echo "==> Step 6: queue:restart (no-op today; correct as queues come online)"
$ARTISAN queue:restart

echo
echo "==> Deploy to $ENVIRONMENT complete."
