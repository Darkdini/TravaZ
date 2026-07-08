#!/data/data/com.termux/files/usr/bin/bash
#
# One-time setup for running TravianZ on Termux (Android) without Docker.
#
# Installs PHP + MariaDB, initialises the database storage, starts the DB,
# and creates the game database and user. Safe to re-run (idempotent-ish).
#
# Usage:
#   bash termux/setup.sh
#
set -e

# --- Configuration (override via env before running if you like) --------------
DB_NAME="${DB_NAME:-travian}"
DB_USER="${DB_USER:-travianz}"
DB_PASS="${DB_PASS:-travianzpass}"

PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
DB_DATADIR="$PREFIX/var/lib/mysql"

echo "==> TravianZ / Termux setup"
echo "    DB:   $DB_NAME"
echo "    User: $DB_USER"
echo

# --- 1. Packages --------------------------------------------------------------
echo "==> Installing packages (php, mariadb)..."
pkg update -y
pkg install -y php mariadb

# --- 2. Initialise MariaDB data directory (first run only) --------------------
if [ ! -d "$DB_DATADIR/mysql" ]; then
    echo "==> Initialising MariaDB data directory..."
    mariadb-install-db \
        --datadir="$DB_DATADIR" \
        --auth-root-authentication-method=normal
else
    echo "==> MariaDB data directory already initialised, skipping."
fi

# --- 3. Start MariaDB (if not already running) --------------------------------
if ! mysqladmin ping --silent 2>/dev/null; then
    echo "==> Starting MariaDB..."
    mariadbd-safe --datadir="$DB_DATADIR" >"$PREFIX/tmp/mariadb.log" 2>&1 &

    echo -n "    waiting for MariaDB to accept connections"
    for i in $(seq 1 30); do
        if mysqladmin ping --silent 2>/dev/null; then
            echo " ok"
            break
        fi
        echo -n "."
        sleep 1
    done
else
    echo "==> MariaDB already running."
fi

# --- 4. Create database + user ------------------------------------------------
echo "==> Creating database and user..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo
echo "==> Done!"
echo
echo "Next steps:"
echo "  1. Start the game server:   bash termux/start.sh"
echo "  2. Open in your browser:    http://localhost:8080/install"
echo
echo "  In the installer use these database settings:"
echo "     Host:     127.0.0.1"
echo "     Port:     3306"
echo "     Database: $DB_NAME"
echo "     User:     $DB_USER"
echo "     Password: $DB_PASS"
