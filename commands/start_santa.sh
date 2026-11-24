#!/bin/bash
# Santa's List - Start
# Fetches the naughty/nice list from the SaaS platform and starts the LED display

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPTS_DIR="$PLUGIN_DIR/scripts"

exec "$SCRIPTS_DIR/start_santa.sh"
