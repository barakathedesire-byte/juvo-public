#!/usr/bin/env bash
# Start the local Fleetbase mock receiver on 127.0.0.1:9099.
# It records every request to /tmp/opencode/fleetbase_mock.log and answers
# 201 with a fake order id, so the Bagisto integration can be exercised
# without a real Fleetbase instance.
set -e
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PIDFILE="/tmp/opencode/fleetbase_mock.pid"

if curl -s -o /dev/null --max-time 2 http://127.0.0.1:9099/; then
  echo "Fleetbase mock already running on 127.0.0.1:9099"
  exit 0
fi

echo "Starting Fleetbase mock on 127.0.0.1:9099 ..."
nohup python3 "$ROOT/bin/fleetbase-mock.py" >/tmp/opencode/fleetbase_mock.out 2>&1 &
echo $! > "$PIDFILE"
sleep 1

if curl -s -o /dev/null --max-time 2 http://127.0.0.1:9099/; then
  echo "Fleetbase mock is up (pid $(cat "$PIDFILE"), log: /tmp/opencode/fleetbase_mock.log)"
else
  echo "Fleetbase mock failed to start; see /tmp/opencode/fleetbase_mock.out" >&2
  exit 1
fi
