#!/bin/bash
# Santa's List - Clear Panels
# Stops and clears both FPP pixel overlay models.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/config/settings.conf"

source "$CONFIG_FILE"
SANTA_FPP_URL="${SANTA_FPP_URL:-http://localhost}"
SANTA_TITLE_OVERLAY="${SANTA_TITLE_OVERLAY:-SantaTitle}"
SANTA_NAMES_OVERLAY="${SANTA_NAMES_OVERLAY:-SantaNames}"

# Clear text and stop both overlay models
for overlay in "$SANTA_TITLE_OVERLAY" "$SANTA_NAMES_OVERLAY"; do
    curl -s -X GET "$SANTA_FPP_URL/api/overlays/model/$overlay/clear" > /dev/null 2>&1
    curl -s -X GET "$SANTA_FPP_URL/api/overlays/model/$overlay/state/0" > /dev/null 2>&1
    echo "Cleared overlay: $overlay"
done
