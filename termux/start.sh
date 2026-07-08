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

# Prefer the mariadb-named client tools; fall back to the mysql* aliases.
DBADMIN="$(command -v mariadb-admin || command -v mysqladmin)"

# Resolve project root (parent of this script's directory).
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# --- Writable temp / session directories --------------------------------------
# PHP's compiled default session.save_path (/var/lib/php/sessions) does not
# exist on Termux, so session_start() fails with "Cannot create lock -
# Permission denied", which also breaks login. Point PHP at a writable dir.
TZ_TMP="$PREFIX/tmp"
TZ_SESSIONS="$PROJECT_ROOT/var/sessions"
mkdir -p "$TZ_TMP" "$TZ_SESSIONS" "$PROJECT_ROOT/GameEngine/Prevention"
export TMPDIR="$TZ_TMP"

# --- Ensure MariaDB is running ------------------------------------------------
if ! "$DBADMIN" ping --silent 2>/dev/null; then
    echo "==> Starting MariaDB..."
    mariadbd-safe --datadir="$DB_DATADIR" >"$TZ_TMP/mariadb.log" 2>&1 &
    for i in $(seq 1 30); do
        "$DBADMIN" ping --silent 2>/dev/null && break
        sleep 1
    done
fi

# --- Detect MariaDB's Unix socket ---------------------------------------------
# When the DB host is "localhost", mysqli/PDO ignore the port and connect via a
# Unix socket. PHP's compiled default socket path does not match MariaDB's on
# Termux, which fails with "No such file or directory". Detect the real socket
# and hand it to PHP so a "localhost" host works too (127.0.0.1 uses TCP).
DBCLIENT="$(command -v mariadb || command -v mysql)"
DB_SOCKET="$("$DBCLIENT" -u root -N -B -e 'SELECT @@socket;' 2>/dev/null | head -n1)"
SOCKET_OPTS=()
if [ -n "$DB_SOCKET" ]; then
    SOCKET_OPTS=(-d "mysqli.default_socket=$DB_SOCKET" -d "pdo_mysql.default_socket=$DB_SOCKET")
    echo "==> MariaDB socket: $DB_SOCKET"
fi

echo "==> Starting PHP server on http://localhost:$PORT"
echo "    Installer: http://localhost:$PORT/install"
echo "    Press Ctrl+C to stop."
echo

# -d flags: sensible limits + Termux-writable temp/session dirs. Opcache is
# disabled to avoid its file-lock creation under a read-only cache dir.
exec php \
    -d memory_limit=256M \
    -d max_execution_time=120 \
    -d display_errors=0 \
    -d session.save_path="$TZ_SESSIONS" \
    -d sys_temp_dir="$TZ_TMP" \
    -d upload_tmp_dir="$TZ_TMP" \
    -d opcache.enable=0 \
    -d opcache.enable_cli=0 \
    "${SOCKET_OPTS[@]}" \
    -S 0.0.0.0:"$PORT" \
    termux/router.php
