#!/bin/bash
# Santa's List - Stop
# Stops the daemon and clears both FPP overlays.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/config/settings.conf"
PID_FILE="/tmp/santa_daemon.pid"

source "$CONFIG_FILE"
SANTA_FPP_URL="${SANTA_FPP_URL:-http://localhost}"

# Stop daemon
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID"
        sleep 1
        kill -9 "$PID" 2>/dev/null
        echo "Santa daemon stopped (PID $PID)"
    fi
    rm -f "$PID_FILE"
else
    pkill -f "santa_daemon.sh" 2>/dev/null
    echo "Santa daemon stopped"
fi

# Clear overlays
bash "$PLUGIN_DIR/scripts/clear_matrix.sh"
