#!/bin/bash
# Santa's List - Stop
# Stops the display service and clears the LED panels

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPTS_DIR="$PLUGIN_DIR/scripts"

exec "$SCRIPTS_DIR/stop_santa.sh"
