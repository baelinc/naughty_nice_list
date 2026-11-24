#!/bin/bash
# Santa's List - Main Daemon
# Runs as a background loop: fetches the list from WordPress and updates FPP overlays.
# Started by start_santa.sh, stopped by stop_santa.sh.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/config/settings.conf"
PID_FILE="/tmp/santa_daemon.pid"
LIST_CACHE="/tmp/santa_list_cache.json"
LOG_FILE="/tmp/santa_daemon.log"

# Load config
source "$CONFIG_FILE"

# Defaults
SANTA_POLL_INTERVAL="${SANTA_POLL_INTERVAL:-120}"
SANTA_FPP_URL="${SANTA_FPP_URL:-http://localhost}"

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') $*" | tee -a "$LOG_FILE"
}

# -----------------------------------------------------------------------
# FPP Overlay API helpers
# -----------------------------------------------------------------------

fpp_overlay_start() {
    local overlay="$1"
    curl -s -X GET "$SANTA_FPP_URL/api/overlays/model/$overlay/state/1" > /dev/null 2>&1
}

fpp_overlay_stop() {
    local overlay="$1"
    curl -s -X GET "$SANTA_FPP_URL/api/overlays/model/$overlay/state/0" > /dev/null 2>&1
}

fpp_overlay_clear() {
    local overlay="$1"
    curl -s -X GET "$SANTA_FPP_URL/api/overlays/model/$overlay/clear" > /dev/null 2>&1
}

fpp_overlay_text() {
    # Send a text effect to an FPP pixel overlay model
    # Args: overlay_name  text  direction  speed  font_size  color_r  color_g  color_b
    local overlay="$1"
    local text="$2"
    local direction="${3:-up}"       # up, down, left, right, none
    local speed="${4:-50}"
    local font_size="${5:-14}"
    local r="${6:-255}"
    local g="${7:-255}"
    local b="${8:-255}"

    # Map direction names to FPP scroll direction values
    local scroll_dir
    case "$direction" in
        up)     scroll_dir="up" ;;
        down)   scroll_dir="down" ;;
        left)   scroll_dir="left" ;;
        right)  scroll_dir="right" ;;
        static) scroll_dir="none" ;;
        *)      scroll_dir="up" ;;
    esac

    local payload
    payload=$(python3 -c "
import json, sys
data = {
    'Text': sys.argv[1],
    'Direction': sys.argv[2],
    'Font': 'osdmono',
    'FontSize': int(sys.argv[3]),
    'Color': '#%02x%02x%02x' % (int(sys.argv[4]), int(sys.argv[5]), int(sys.argv[6])),
    'Speed': int(sys.argv[7]),
    'Antialiased': False
}
print(json.dumps(data))
" "$text" "$scroll_dir" "$font_size" "$r" "$g" "$b" "$speed")

    curl -s -X POST \
         -H "Content-Type: application/json" \
         -d "$payload" \
         "$SANTA_FPP_URL/api/overlays/model/$overlay/text" > /dev/null 2>&1
}

# -----------------------------------------------------------------------
# Data fetch
# -----------------------------------------------------------------------

fetch_list() {
    local tmp_file="/tmp/santa_list_new.json"
    local http_code

    http_code=$(curl -s -w "%{http_code}" \
        -H "X-Santa-API-Key: $SANTA_API_KEY" \
        "$SANTA_WP_URL/wp-json/santa-list/v1/list" \
        -o "$tmp_file")

    if [ "$http_code" != "200" ]; then
        log "ERROR: Failed to fetch list (HTTP $http_code)"
        return 1
    fi

    if [ ! -s "$tmp_file" ]; then
        log "ERROR: Empty response from WordPress"
        return 1
    fi

    mv "$tmp_file" "$LIST_CACHE"
    log "List fetched OK"
    return 0
}

fetch_config() {
    local tmp_file="/tmp/santa_config.json"
    local http_code

    http_code=$(curl -s -w "%{http_code}" \
        -H "X-Santa-API-Key: $SANTA_API_KEY" \
        "$SANTA_WP_URL/wp-json/santa-list/v1/config" \
        -o "$tmp_file")

    if [ "$http_code" = "200" ] && [ -s "$tmp_file" ]; then
        mv "$tmp_file" /tmp/santa_config_cache.json
    fi
}

get_config_value() {
    local key="$1"
    local default="$2"
    if [ -f /tmp/santa_config_cache.json ]; then
        python3 -c "
import json, sys
try:
    d = json.load(open('/tmp/santa_config_cache.json'))
    print(d.get(sys.argv[1], sys.argv[2]))
except:
    print(sys.argv[2])
" "$key" "$default"
    else
        echo "$default"
    fi
}

# -----------------------------------------------------------------------
# Display update
# -----------------------------------------------------------------------

update_display() {
    if [ ! -f "$LIST_CACHE" ]; then
        log "No list cache yet, skipping display update"
        return
    fi

    # Get display settings (local override or from WordPress)
    local direction="${SANTA_SCROLL_DIRECTION:-$(get_config_value scroll_direction up)}"
    local speed="${SANTA_SCROLL_SPEED:-$(get_config_value scroll_speed 50)}"
    local names_per_screen="${SANTA_NAMES_PER_SCREEN:-$(get_config_value names_per_screen 5)}"
    local title_text
    title_text=$(get_config_value title_text "Santa's Naughty & Nice List")

    # --- Title panel ---
    fpp_overlay_start "$SANTA_TITLE_OVERLAY"
    fpp_overlay_text "$SANTA_TITLE_OVERLAY" "$title_text" "none" "50" "16" "255" "50" "50"

    # --- Names panel ---
    # Build the names text from cache
    local names_text
    names_text=$(python3 - <<'PYEOF'
import json, sys

cache_file = '/tmp/santa_list_cache.json'
names_per = int(sys.argv[1]) if len(sys.argv) > 1 else 5

try:
    with open(cache_file) as f:
        data = json.load(f)
except Exception as e:
    print("No data")
    sys.exit(0)

nice    = data.get('nice', [])
naughty = data.get('naughty', [])

# Interleave nice and naughty, label each
parts = []
for item in nice:
    parts.append(f"  ★ {item['name']} (Nice)  ")
for item in naughty:
    parts.append(f"  ★ {item['name']} (Naughty)  ")

if not parts:
    print("  No names yet - be first!  ")
else:
    print("".join(parts))
PYEOF
)

    fpp_overlay_start "$SANTA_NAMES_OVERLAY"
    fpp_overlay_text "$SANTA_NAMES_OVERLAY" "$names_text" "$direction" "$speed" "12" "255" "255" "255"

    log "Display updated: $(echo "$names_text" | wc -c) chars, direction=$direction"
}

# -----------------------------------------------------------------------
# Main loop
# -----------------------------------------------------------------------

log "Santa Daemon starting (PID $$, poll interval: ${SANTA_POLL_INTERVAL}s)"
echo $$ > "$PID_FILE"

# Initial fetch
fetch_config
fetch_list
update_display

# Poll loop
while true; do
    sleep "$SANTA_POLL_INTERVAL"

    # Re-read config in case it changed
    source "$CONFIG_FILE"

    fetch_config
    if fetch_list; then
        update_display
    else
        log "Fetch failed, keeping existing display"
    fi
done
