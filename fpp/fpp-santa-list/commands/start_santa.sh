#!/bin/bash
# Santa's List - Start
# Starts the background daemon that fetches the list and drives the FPP overlays.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="/tmp/santa_daemon.pid"

# Check if already running
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "Santa daemon already running (PID $PID)"
        exit 0
    fi
    rm -f "$PID_FILE"
fi

# Start daemon in background
nohup bash "$PLUGIN_DIR/scripts/santa_daemon.sh" > /tmp/santa_daemon.log 2>&1 &
DAEMON_PID=$!

# Give it a moment to write its own PID
sleep 1

if kill -0 "$DAEMON_PID" 2>/dev/null; then
    echo "Santa's List daemon started (PID $DAEMON_PID)"
else
    echo "ERROR: Daemon failed to start. Check /tmp/santa_daemon.log" >&2
    exit 1
fi
