#!/bin/bash
# Santa's List - Clear Matrix Script
# Sends a blank/black frame to all LED panels to turn them off

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/config/settings.conf"

# Load config
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
fi

PANEL_ROWS="${SANTA_PANEL_ROWS:-32}"
PANEL_COLS="${SANTA_PANEL_COLS:-64}"
CHAIN_LENGTH="${SANTA_CHAIN_LENGTH:-1}"
PARALLEL="${SANTA_PARALLEL:-1}"
GPIO_SLOWDOWN="${SANTA_GPIO_SLOWDOWN:-2}"

# Run a short Python snippet to send a black frame and exit
python3 - <<EOF
import sys
try:
    from rgbmatrix import RGBMatrix, RGBMatrixOptions
    options = RGBMatrixOptions()
    options.rows = $PANEL_ROWS
    options.cols = $PANEL_COLS
    options.chain_length = $CHAIN_LENGTH
    options.parallel = $PARALLEL
    options.gpio_slowdown = $GPIO_SLOWDOWN
    options.disable_hardware_pulsing = False
    matrix = RGBMatrix(options=options)
    matrix.Clear()
    print("Matrix cleared")
except Exception as e:
    print(f"Error clearing matrix: {e}", file=sys.stderr)
    sys.exit(1)
EOF
