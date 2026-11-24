#!/bin/bash
# Santa's List - Start Script
# Fetches the naughty/nice list from the SaaS platform and starts the LED display

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/config/settings.conf"

# Load config
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
fi

# Defaults (override in settings.conf)
API_URL="${SANTA_API_URL:-http://localhost:8080}"
API_KEY="${SANTA_API_KEY:-}"
DISPLAY_BINARY="${SANTA_DISPLAY_BINARY:-/home/fpp/media/plugins/fpp-santa-list/bin/santa_display}"
PANEL_ROWS="${SANTA_PANEL_ROWS:-32}"
PANEL_COLS="${SANTA_PANEL_COLS:-64}"
CHAIN_LENGTH="${SANTA_CHAIN_LENGTH:-1}"
PARALLEL="${SANTA_PARALLEL:-1}"
GPIO_SLOWDOWN="${SANTA_GPIO_SLOWDOWN:-2}"

# Stop any existing instance
"$PLUGIN_DIR/scripts/stop_santa.sh" 2>/dev/null

# Fetch the list from SaaS
LIST_FILE="/tmp/santa_list.json"
if [ -n "$API_KEY" ]; then
    curl -s -H "Authorization: Bearer $API_KEY" "$API_URL/api/santa/list" -o "$LIST_FILE"
else
    curl -s "$API_URL/api/santa/list" -o "$LIST_FILE"
fi

if [ $? -ne 0 ] || [ ! -s "$LIST_FILE" ]; then
    echo "ERROR: Failed to fetch Santa's list from $API_URL" >&2
    exit 1
fi

# Start the display
nohup python3 "$PLUGIN_DIR/scripts/display_list.py" \
    --list "$LIST_FILE" \
    --rows "$PANEL_ROWS" \
    --cols "$PANEL_COLS" \
    --chain "$CHAIN_LENGTH" \
    --parallel "$PARALLEL" \
    --slowdown "$GPIO_SLOWDOWN" \
    > /tmp/santa_display.log 2>&1 &

echo $! > /tmp/santa_display.pid
echo "Santa's List display started (PID: $(cat /tmp/santa_display.pid))"
