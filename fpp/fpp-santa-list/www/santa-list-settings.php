<?php
/**
 * Santa's List FPP Plugin - Settings Page
 * Accessible in FPP under: Content Setup → Plugin Manager → Santa's List → Settings
 */

$config_file = dirname(__DIR__) . '/config/settings.conf';
$message = '';

// Handle form save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = [
        'SANTA_WP_URL'          => trim($_POST['wp_url'] ?? ''),
        'SANTA_API_KEY'         => trim($_POST['api_key'] ?? ''),
        'SANTA_FPP_URL'         => trim($_POST['fpp_url'] ?? 'http://localhost'),
        'SANTA_TITLE_OVERLAY'   => trim($_POST['title_overlay'] ?? 'SantaTitle'),
        'SANTA_NAMES_OVERLAY'   => trim($_POST['names_overlay'] ?? 'SantaNames'),
        'SANTA_POLL_INTERVAL'   => (int)($_POST['poll_interval'] ?? 120),
    ];

    $lines = ["# Santa's List FPP Plugin - Configuration\n# Auto-saved from FPP settings page.\n\n"];
    foreach ($fields as $key => $val) {
        $lines[] = $key . '="' . addslashes($val) . '"' . "\n";
    }
    file_put_contents($config_file, implode('', $lines));
    $message = 'Settings saved!';
}

// Read current config
$config = [];
if (file_exists($config_file)) {
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (preg_match('/^([A-Z_]+)="?([^"]*)"?$/', trim($line), $m)) {
            $config[$m[1]] = $m[2];
        }
    }
}

function cfg($key, $default = '') {
    global $config;
    return htmlspecialchars($config[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Santa's List Settings</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 20px auto; padding: 0 16px; }
        h1 { color: #c0392b; }
        .field { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; }
        input[type=text], input[type=url], input[type=number] {
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .btn { background: #c0392b; color: #fff; border: none; padding: 10px 24px;
               border-radius: 6px; font-size: 1em; cursor: pointer; }
        .msg { background: #eafaf1; border: 1px solid #27ae60; color: #1e8449;
               padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
        .hint { font-size: 0.85em; color: #666; margin-top: 4px; }
        h2 { margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
    </style>
</head>
<body>
    <h1>🎅 Santa's List Settings</h1>

    <?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <h2>WordPress Connection</h2>

        <div class="field">
            <label for="wp_url">WordPress Site URL</label>
            <input type="url" id="wp_url" name="wp_url" value="<?= cfg('SANTA_WP_URL') ?>"
                   placeholder="https://your-site.com" required />
            <p class="hint">No trailing slash. Must have the Santa's List WordPress plugin installed.</p>
        </div>

        <div class="field">
            <label for="api_key">API Key</label>
            <input type="text" id="api_key" name="api_key" value="<?= cfg('SANTA_API_KEY') ?>"
                   placeholder="From WordPress → Santa's List → Settings" required />
            <p class="hint">Copy this from WordPress Admin → Santa's List → Settings → FPP API Key.</p>
        </div>

        <div class="field">
            <label for="poll_interval">Poll Interval (seconds)</label>
            <input type="number" id="poll_interval" name="poll_interval"
                   value="<?= cfg('SANTA_POLL_INTERVAL', '120') ?>" min="30" max="3600" />
            <p class="hint">How often to fetch the latest names. 120 = every 2 minutes.</p>
        </div>

        <h2>FPP Overlay Settings</h2>
        <p class="hint">These must match the Pixel Overlay Model names you created in FPP →
           Channel Outputs → Pixel Overlays.</p>

        <div class="field">
            <label for="title_overlay">Title Panel Overlay Name</label>
            <input type="text" id="title_overlay" name="title_overlay"
                   value="<?= cfg('SANTA_TITLE_OVERLAY', 'SantaTitle') ?>" />
            <p class="hint">The overlay model that shows "Santa's Naughty & Nice List".</p>
        </div>

        <div class="field">
            <label for="names_overlay">Names Panel Overlay Name</label>
            <input type="text" id="names_overlay" name="names_overlay"
                   value="<?= cfg('SANTA_NAMES_OVERLAY', 'SantaNames') ?>" />
            <p class="hint">The overlay model that scrolls the names.</p>
        </div>

        <div class="field">
            <label for="fpp_url">FPP API URL</label>
            <input type="text" id="fpp_url" name="fpp_url"
                   value="<?= cfg('SANTA_FPP_URL', 'http://localhost') ?>" />
            <p class="hint">Usually http://localhost. Only change if FPP is on a different device.</p>
        </div>

        <button type="submit" name="save_settings" class="btn">💾 Save Settings</button>
    </form>

    <h2>Status</h2>
    <p>
        <?php
        $pid_file = '/tmp/santa_daemon.pid';
        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            $running = shell_exec("kill -0 $pid 2>/dev/null; echo $?");
            if (trim($running) === '0') {
                echo '<strong style="color:#27ae60">✅ Daemon is running</strong> (PID ' . htmlspecialchars($pid) . ')';
            } else {
                echo '<strong style="color:#e67e22">⚠️ Daemon PID file exists but process is not running</strong>';
            }
        } else {
            echo '<strong style="color:#aaa">⬜ Daemon is not running</strong> — use FPP Scheduler or Playlist to start it.';
        }
        ?>
    </p>
</body>
</html>
