#!/usr/bin/env bash
set -e
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT/env.sh"

DBDIR="$HOME/.local/mariadb"
if mysqladmin --socket="$DBDIR/run/mysqld.sock" ping >/dev/null 2>&1; then
  echo "MariaDB already running"
else
  echo "Starting MariaDB on 127.0.0.1:$JUVO_DB_PORT ..."
  nohup mariadbd \
    --datadir="$DBDIR/data" \
    --socket="$DBDIR/run/mysqld.sock" \
    --port="$JUVO_DB_PORT" \
    --bind-address=127.0.0.1 \
    --pid-file="$DBDIR/run/mysqld.pid" \
    >"$DBDIR/log/mysqld.log" 2>&1 &
  sleep 5
  mysqladmin --socket="$DBDIR/run/mysqld.sock" ping
fi

if curl -s -o /dev/null --max-time 3 http://127.0.0.1:8000/; then
  echo "Juvo already running at http://127.0.0.1:8000"
else
  echo "Starting Juvo at http://127.0.0.1:8000 ..."
  cd "$ROOT"
  nohup php artisan serve --host=127.0.0.1 --port=8000 >"$HOME/.local/juvo/serve.log" 2>&1 &
  sleep 3
fi

echo
echo "Storefront : http://127.0.0.1:8000"
echo "Admin      : http://127.0.0.1:8000/login   (admin / admin)"
echo "Vendor     : http://127.0.0.1:8000/vendor/login"
echo "Customer   : http://127.0.0.1:8000/customer/login"

"$ROOT/bin/start-mock.sh"
