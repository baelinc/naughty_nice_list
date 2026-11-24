#!/bin/bash
# Santa's List - Stop Script
# Stops the display service and clears the LED panels

PID_FILE="/tmp/santa_display.pid"

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID"
        sleep 1
        # Force kill if still running
        kill -9 "$PID" 2>/dev/null
    fi
    rm -f "$PID_FILE"
    echo "Santa's List display stopped"
else
    # Try to find and kill by process name
    pkill -f "display_list.py" 2>/dev/null
    echo "Santa's List display stopped (no PID file found)"
fi

# Clear the panels
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
"$PLUGIN_DIR/scripts/clear_matrix.sh"
