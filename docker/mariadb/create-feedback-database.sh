#!/usr/bin/env bash

# Create the Tester feedback database (ADR-0029) on a new Sail MariaDB volume.
# The entrypoint runs this once, when the volume is empty. For an existing volume,
# see "Feedback database" in README.md.

/usr/bin/mariadb --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS dmv_rom_v2_feedback;
EOSQL

if [ -n "$MYSQL_USER" ]; then
/usr/bin/mariadb --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    GRANT ALL PRIVILEGES ON \`dmv_rom_v2_feedback\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
