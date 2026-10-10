#!/usr/bin/env bash
#
# Tear down a /verify branch (#807): the serve container, the database and the worktree
# that scripts/verify-up.sh created.
#
#   scripts/verify-down.sh <name>
#
# <name> is the one verify-up.sh printed (the ref without "origin/", e.g. staging).

set -euo pipefail

NAME="${1:-}"
if [[ -z "$NAME" ]]; then
    echo "Usage: $0 <name>" >&2
    exit 2
fi

NAME="$(echo "$NAME" | tr -c 'A-Za-z0-9\n' '-' | tr 'A-Z' 'a-z')"
DB="dmv_verify_$(echo "$NAME" | tr '-' '_')"
MAIN="$(git -C "$(dirname "${BASH_SOURCE[0]}")" worktree list --porcelain | awk 'NR==1 {print $2}')"
WT="$MAIN/.claude/worktrees/verify-$NAME"
DB_CONTAINER=dmv-rom-v2-mariadb-1

echo "==> Container verify-$NAME"
docker rm -f "verify-$NAME" >/dev/null 2>&1 || true

echo "==> Database $DB"
ROOT_PW="$(docker exec "$DB_CONTAINER" printenv MYSQL_ROOT_PASSWORD)"
docker exec "$DB_CONTAINER" mariadb -uroot -p"$ROOT_PW" -e "DROP DATABASE IF EXISTS \`$DB\`;"

echo "==> Worktree .claude/worktrees/verify-$NAME"
if [[ -e "$WT" ]]; then
    git -C "$MAIN" worktree remove --force "$WT"
fi
git -C "$MAIN" worktree prune

echo "Down: verify-$NAME"
