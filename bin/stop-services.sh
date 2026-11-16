#!/usr/bin/env bash
# Stop Juvo serve + Fleetbase mock. Leaves MariaDB running unless
# STOP_DB=1 is set (shared with other projects).
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

ss -ltnp 2>/dev/null | grep -q ':8000' && {
  PID="$(ss -ltnp 2>/dev/null | grep ':8000' | grep -oE 'pid=[0-9]+' | head -1 | cut -d= -f2)"
  [ -n "$PID" ] && kill "$PID" && echo "Juvo (pid $PID) stopped" || echo "Juvo pid not found"
} || echo "Juvo not running"

"$ROOT/bin/stop-mock.sh"

if [ "${STOP_DB:-0}" = "1" ]; then
  DBDIR="$HOME/.local/mariadb"
  if [ -f "$DBDIR/run/mysqld.pid" ]; then
    kill "$(cat "$DBDIR/run/mysqld.pid")" && echo "MariaDB stopped"
  fi
else
  echo "MariaDB left running (set STOP_DB=1 to stop it)"
fi
