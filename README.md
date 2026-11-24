# Santa's Naughty & Nice List - FPP Plugin

An FPP (Falcon Pi Player) plugin that fetches a naughty/nice list from a SaaS platform and scrolls it across LED matrix panels.

## Requirements

- FPP 6.0+
- Raspberry Pi with LED matrix panels
- [rpi-rgb-led-matrix](https://github.com/hzeller/rpi-rgb-led-matrix) with Python bindings
- LED matrix panels connected via GPIO

## Installation

1. In FPP, go to **Content Setup → Plugin Manager**
2. Install from GitHub: `baelinc/naughty_nice_list`
3. Edit `config/settings.conf` with your SaaS API URL and panel configuration

## Configuration

Edit `/home/fpp/media/plugins/fpp-santa-list/config/settings.conf`:

```bash
SANTA_API_URL="https://your-saas-site.com"
SANTA_API_KEY="your-api-key"
SANTA_PANEL_ROWS=32
SANTA_PANEL_COLS=64
SANTA_CHAIN_LENGTH=1
SANTA_PARALLEL=1
SANTA_GPIO_SLOWDOWN=2
```

## FPP Commands

After installation, three commands are available in the FPP Scheduler and Playlists:

| Command | Description |
|---|---|
| **Santa's List - Start** | Fetches the list and starts the LED display |
| **Santa's List - Stop** | Stops the display and clears panels |
| **Santa's List - Clear Panels** | Turns off all LED panels |

## API Format

The plugin expects your SaaS endpoint `GET /api/santa/list` to return JSON in one of these formats:

```json
{
  "nice": ["Alice", "Bob"],
  "naughty": ["Charlie", "Dave"]
}
```

or:

```json
[
  {"name": "Alice", "status": "nice"},
  {"name": "Charlie", "status": "naughty"}
]
```

## Panel Wiring

Standard rpi-rgb-led-matrix GPIO wiring applies. See the [rpi-rgb-led-matrix wiring guide](https://github.com/hzeller/rpi-rgb-led-matrix/blob/master/wiring.md).
