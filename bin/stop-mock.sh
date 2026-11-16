#!/usr/bin/env bash
# Stop the local Fleetbase mock receiver.
PIDFILE="/tmp/opencode/fleetbase_mock.pid"

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  PID="$(cat "$PIDFILE")"
  echo "Stopping Fleetbase mock (pid $PID) ..."
  kill "$PID" && echo "Fleetbase mock stopped"
  rm -f "$PIDFILE"
elif curl -s -o /dev/null --max-time 2 http://127.0.0.1:9099/; then
  PID="$(ss -ltnp 2>/dev/null | grep ':9099' | grep -oE 'pid=[0-9]+' | head -1 | cut -d= -f2)"
  if [ -n "$PID" ]; then
    echo "Stopping Fleetbase mock (pid $PID) ..."
    kill "$PID" && echo "Fleetbase mock stopped"
  else
    echo "Fleetbase mock is listening on 9099 but its pid was not found" >&2
  fi
else
  echo "Fleetbase mock not running"
fi
