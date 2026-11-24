#!/usr/bin/env python3
"""
Santa's Naughty & Nice List - LED Matrix Display
Scrolls the naughty/nice list across LED panels
"""

import argparse
import json
import sys
import time
import signal

try:
    from rgbmatrix import RGBMatrix, RGBMatrixOptions, graphics
except ImportError:
    print("ERROR: rgbmatrix library not found. Install rpi-rgb-led-matrix Python bindings.", file=sys.stderr)
    sys.exit(1)

running = True

def signal_handler(sig, frame):
    global running
    running = False

signal.signal(signal.SIGTERM, signal_handler)
signal.signal(signal.SIGINT, signal_handler)


def load_list(list_file):
    with open(list_file, 'r') as f:
        data = json.load(f)
    # Expected format: {"naughty": ["Name1", "Name2"], "nice": ["Name3", "Name4"]}
    # or a flat list: [{"name": "...", "status": "naughty|nice"}, ...]
    entries = []
    if isinstance(data, dict):
        for name in data.get('nice', []):
            entries.append((name, 'nice'))
        for name in data.get('naughty', []):
            entries.append((name, 'naughty'))
    elif isinstance(data, list):
        for item in data:
            entries.append((item.get('name', ''), item.get('status', 'nice')))
    return entries


def build_scroll_text(entries):
    """Build a single scrolling string from the list entries."""
    parts = []
    for name, status in entries:
        emoji = "🎄" if status == 'nice' else "🎅"
        label = "NICE" if status == 'nice' else "NAUGHTY"
        parts.append(f"  {name}: {label}  *  ")
    return "".join(parts) if parts else "  No list loaded  "


def run_display(args, entries):
    options = RGBMatrixOptions()
    options.rows = args.rows
    options.cols = args.cols
    options.chain_length = args.chain
    options.parallel = args.parallel
    options.gpio_slowdown = args.slowdown
    options.disable_hardware_pulsing = False

    matrix = RGBMatrix(options=options)
    canvas = matrix.CreateFrameCanvas()

    font = graphics.Font()
    # Try to load a font; fall back to default
    font_path = "/opt/fpp/src/fonts/4x6.bdf"
    try:
        font.LoadFont(font_path)
    except Exception:
        pass  # Will use default

    nice_color = graphics.Color(0, 255, 0)      # Green for nice
    naughty_color = graphics.Color(255, 0, 0)    # Red for naughty
    white = graphics.Color(255, 255, 255)

    scroll_text = build_scroll_text(entries)
    text_width = len(scroll_text) * 6  # Approx width per char
    pos = matrix.width

    while running:
        canvas.Clear()

        # Draw scrolling text
        # Alternate color per word based on content
        x = pos
        for name, status in entries:
            color = nice_color if status == 'nice' else naughty_color
            label = f"  {name}: {'NICE' if status == 'nice' else 'NAUGHTY'}  *"
            char_width = graphics.DrawText(canvas, font, x, matrix.height - 2, color, label)
            x += char_width

        canvas = matrix.SwapOnVSync(canvas)

        pos -= 1
        if pos < -text_width:
            pos = matrix.width

        time.sleep(0.03)  # ~33fps scroll speed

    matrix.Clear()


def main():
    parser = argparse.ArgumentParser(description="Santa's List LED Display")
    parser.add_argument('--list', required=True, help='Path to JSON list file')
    parser.add_argument('--rows', type=int, default=32, help='Panel rows')
    parser.add_argument('--cols', type=int, default=64, help='Panel cols')
    parser.add_argument('--chain', type=int, default=1, help='Chain length')
    parser.add_argument('--parallel', type=int, default=1, help='Parallel chains')
    parser.add_argument('--slowdown', type=int, default=2, help='GPIO slowdown')
    args = parser.parse_args()

    entries = load_list(args.list)
    if not entries:
        print("No entries found in list file.", file=sys.stderr)
        sys.exit(1)

    print(f"Loaded {len(entries)} entries. Starting display...")
    run_display(args, entries)


if __name__ == '__main__':
    main()
