#!/data/data/com.termux/files/usr/bin/bash
#
# Start TravianZ on Termux: ensures MariaDB is up, then launches the PHP
# built-in web server with the project router.
#
# Usage:
#   bash termux/start.sh            # listens on 0.0.0.0:8080
#   PORT=9000 bash termux/start.sh  # custom port
#
set -e

PORT="${PORT:-8080}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
DB_DATADIR="$PREFIX/var/lib/mysql"

# Resolve project root (parent of this script's directory).
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# --- Ensure MariaDB is running ------------------------------------------------
if ! mysqladmin ping --silent 2>/dev/null; then
    echo "==> Starting MariaDB..."
    mariadbd-safe --datadir="$DB_DATADIR" >"$PREFIX/tmp/mariadb.log" 2>&1 &
    for i in $(seq 1 30); do
        mysqladmin ping --silent 2>/dev/null && break
        sleep 1
    done
fi

echo "==> Starting PHP server on http://localhost:$PORT"
echo "    Installer: http://localhost:$PORT/install"
echo "    Press Ctrl+C to stop."
echo

# -d flags: sensible limits for a phone-hosted, query-heavy game.
exec php \
    -d memory_limit=256M \
    -d max_execution_time=120 \
    -d display_errors=0 \
    -S 0.0.0.0:"$PORT" \
    termux/router.php
