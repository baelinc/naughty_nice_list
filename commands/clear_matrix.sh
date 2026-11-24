#!/bin/bash
# Santa's List - Clear Panels
# Turns off all LED panels

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPTS_DIR="$PLUGIN_DIR/scripts"

exec "$SCRIPTS_DIR/clear_matrix.sh"
