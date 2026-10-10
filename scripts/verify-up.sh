#!/usr/bin/env bash
#
# Bring a branch up for a /verify run (#807), isolated from the main checkout.
#
#   scripts/verify-up.sh <git-ref> [name]
#
# Creates a detached worktree at .claude/worktrees/verify-<name>, gives it its own
# vendor copy, node_modules, Vite build and database (dmv_verify_<name>), seeds the
# demo data, and serves it on http://localhost:8091 from a throwaway container on
# the Sail network. The main checkout's database and node_modules are never touched.
# <name> defaults to the ref with any "origin/" prefix dropped. Tear down with
# scripts/verify-down.sh <name>.
#
# Needs the main checkout's Sail stack running (`pnpm sail up -d`).

set -euo pipefail

REF="${1:-}"
if [[ -z "$REF" ]]; then
    echo "Usage: $0 <git-ref> [name]" >&2
    exit 2
fi

NAME="${2:-${REF#origin/}}"
NAME="$(echo "$NAME" | tr -c 'A-Za-z0-9\n' '-' | tr 'A-Z' 'a-z')"
DB="dmv_verify_$(echo "$NAME" | tr '-' '_')"
PORT=8091
URL="http://localhost:$PORT"

# The main checkout: the first entry `git worktree list` prints, wherever this runs from.
MAIN="$(git -C "$(dirname "${BASH_SOURCE[0]}")" worktree list --porcelain | awk 'NR==1 {print $2}')"
WT_REL=".claude/worktrees/verify-$NAME"
WT="$MAIN/$WT_REL"
APP_CONTAINER=dmv-rom-v2-laravel.test-1
DB_CONTAINER=dmv-rom-v2-mariadb-1
SERVE_CONTAINER="verify-$NAME"

step() { echo "==> $*"; }

docker info >/dev/null 2>&1 || {
    echo "Docker is not answering. Run: docker desktop restart" >&2
    exit 1
}
docker ps --format '{{.Names}}' | grep -qx "$APP_CONTAINER" || {
    echo "Sail is not running. Run: pnpm sail up -d (in $MAIN)" >&2
    exit 1
}
if [[ -e "$WT" ]] || docker ps -a --format '{{.Names}}' | grep -qx "$SERVE_CONTAINER"; then
    echo "verify-$NAME already exists. Run: scripts/verify-down.sh $NAME" >&2
    exit 1
fi

step "Worktree $WT_REL at $REF"
git -C "$MAIN" fetch --quiet origin
git -C "$MAIN" worktree add --detach "$WT" "$REF"

step "vendor (copy) and .env ($DB, $URL)"
cp -R "$MAIN/vendor" "$WT/vendor"
sed -e "s|^DB_DATABASE=.*|DB_DATABASE=$DB|" -e "s|^APP_URL=.*|APP_URL=$URL|" "$MAIN/.env" >"$WT/.env"

step "pnpm install and vite build"
(cd "$WT" && CI=true pnpm install --frozen-lockfile --prefer-offline >/dev/null && node_modules/.bin/vite build >/dev/null)

step "Database $DB, migrated and seeded"
ROOT_PW="$(docker exec "$DB_CONTAINER" printenv MYSQL_ROOT_PASSWORD)"
docker exec "$DB_CONTAINER" mariadb -uroot -p"$ROOT_PW" -e \
    "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$DB\`.* TO 'sail'@'%';"
docker exec -w "/var/www/html/$WT_REL" "$APP_CONTAINER" php artisan migrate:fresh --seed --force >/dev/null

step "Serving on $URL"
docker run -d --name "$SERVE_CONTAINER" --network dmv-rom-v2_sail -p "$PORT:$PORT" \
    -v "$WT:/var/www/html" -w /var/www/html -e "APP_URL=$URL" \
    --entrypoint php sail-8.4/app artisan serve --host=0.0.0.0 --port="$PORT" >/dev/null

for _ in $(seq 1 30); do
    if [[ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/login")" == 200 ]]; then
        echo "Up: $URL (worktree $WT_REL, database $DB). Down: scripts/verify-down.sh $NAME"
        exit 0
    fi
    sleep 1
done
echo "Server did not answer on $URL/login. Logs: docker logs $SERVE_CONTAINER" >&2
exit 1
