#!/data/data/com.termux/files/usr/bin/bash
#
# Reset a TravianZ install on Termux back to a clean state so you can run the
# web installer again (e.g. after forgetting the admin password).
#
# WARNING: this ERASES the game database. All players, villages and progress
# are permanently deleted.
#
# Usage:
#   bash termux/reset.sh
#
set -e

DB_NAME="${DB_NAME:-travian}"
DB_USER="${DB_USER:-travianz}"
DB_PASS="${DB_PASS:-travianzpass}"

PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
DB_DATADIR="$PREFIX/var/lib/mysql"

DBADMIN="$(command -v mariadb-admin || command -v mysqladmin)"
DBCLIENT="$(command -v mariadb || command -v mysql)"

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "!! This will PERMANENTLY ERASE the '$DB_NAME' database (all game progress)."
printf "   Type 'yes' to continue: "
read -r CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

# --- Ensure MariaDB is running ------------------------------------------------
if ! "$DBADMIN" ping --silent 2>/dev/null; then
    echo "==> Starting MariaDB..."
    mkdir -p "$PREFIX/tmp"
    mariadbd-safe --datadir="$DB_DATADIR" >"$PREFIX/tmp/mariadb.log" 2>&1 &
    for i in $(seq 1 30); do
        "$DBADMIN" ping --silent 2>/dev/null && break
        sleep 1
    done
fi

# --- Drop and recreate an empty database --------------------------------------
echo "==> Dropping and recreating database '$DB_NAME'..."
"$DBCLIENT" -u root <<SQL
DROP DATABASE IF EXISTS \`$DB_NAME\`;
CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# --- Remove install state so the installer runs again -------------------------
echo "==> Removing install marker, generated config and sessions..."
rm -f var/installed
rm -f GameEngine/config.php
rm -f var/sessions/sess_* 2>/dev/null || true

echo
echo "==> Reset complete."
echo
echo "Next steps:"
echo "  1. Start the server:  bash termux/start.sh"
echo "  2. Open the installer: http://localhost:8080/install"
echo "  3. Set a NEW admin password you will remember."
