<?php
/**
 * Santa's List FPP Plugin — Settings Page
 * Accessible in FPP under: Content Setup → Plugin Manager → Santa's List → Settings
 */

$plugin_dir  = dirname(__DIR__);
$config_file = $plugin_dir . '/config/settings.conf';
$version_file= $plugin_dir . '/pluginInfo.json';
$message     = '';
$msg_type    = 'success';

// ── Current installed version ────────────────────────────────────────────────
$current_version = '1.0.0';
if (file_exists($version_file)) {
    $info = json_decode(file_get_contents($version_file), true);
    $current_version = $info['version'] ?? '1.0.0';
}

// ── GitHub update check (cached 12 h in /tmp) ────────────────────────────────
$update_available = false;
$latest_version   = null;
$release_url      = null;
$release_notes    = null;
$update_error     = null;

$cache_file = '/tmp/santa_fpp_update_cache.json';
$cache_ttl  = 43200; // 12 hours

$check_now = isset($_GET['check_update']);

if ($check_now || !file_exists($cache_file) || (time() - filemtime($cache_file)) > $cache_ttl) {
    // Fetch from GitHub API
    $api_url = 'https://api.github.com/repos/baelinc/naughty_nice_list/releases/latest';
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 8,
            'user_agent'    => 'fpp-santa-list/' . $current_version,
            'header'        => "Accept: application/vnd.github+json\r\n",
            'ignore_errors' => true,
        ]
    ]);
    $raw = @file_get_contents($api_url, false, $ctx);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        file_put_contents($cache_file, json_encode([
            'fetched_at'    => time(),
            'tag_name'      => $data['tag_name']     ?? null,
            'html_url'      => $data['html_url']     ?? null,
            'body'          => $data['body']         ?? null,
            'zipball_url'   => $data['zipball_url']  ?? null,
            'published_at'  => $data['published_at'] ?? null,
        ]));
    } else {
        $update_error = 'Could not reach GitHub. Check network connectivity.';
    }
}

if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if (!empty($cached['tag_name'])) {
        $latest_version = ltrim($cached['tag_name'], 'v');
        $release_url    = $cached['html_url']    ?? null;
        $release_notes  = $cached['body']        ?? null;

        // Find a santa-list.zip asset, or fall back to zipball
        $update_available = version_compare($latest_version, $current_version, '>');
    }
}

// ── Handle form save ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = [
        'SANTA_WP_URL'        => trim($_POST['wp_url']        ?? ''),
        'SANTA_API_KEY'       => trim($_POST['api_key']       ?? ''),
        'SANTA_FPP_URL'       => trim($_POST['fpp_url']       ?? 'http://localhost'),
        'SANTA_TITLE_OVERLAY' => trim($_POST['title_overlay'] ?? 'SantaTitle'),
        'SANTA_NAMES_OVERLAY' => trim($_POST['names_overlay'] ?? 'SantaNames'),
        'SANTA_POLL_INTERVAL' => (int)($_POST['poll_interval'] ?? 120),
    ];

    $lines = ["# Santa's List FPP Plugin - Configuration\n# Auto-saved from FPP settings page.\n\n"];
    foreach ($fields as $key => $val) {
        $lines[] = $key . '="' . addslashes($val) . '"' . "\n";
    }
    file_put_contents($config_file, implode('', $lines));
    $message = '✅ Settings saved!';
}

// ── Read current config ───────────────────────────────────────────────────────
$config = [];
if (file_exists($config_file)) {
    foreach (file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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

// ── Daemon status ─────────────────────────────────────────────────────────────
$pid_file   = '/tmp/santa_daemon.pid';
$daemon_running = false;
$daemon_pid     = null;
if (file_exists($pid_file)) {
    $pid = trim(file_get_contents($pid_file));
    $result = shell_exec("kill -0 $pid 2>/dev/null; echo $?");
    if (trim($result) === '0') {
        $daemon_running = true;
        $daemon_pid     = $pid;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Santa's List — FPP Settings</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 680px;
            margin: 24px auto;
            padding: 0 16px 40px;
            background: #f4f4f5;
            color: #1a1a1a;
            font-size: 15px;
        }

        /* Header */
        .sl-header {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 55%, #1a7a44 100%);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 22px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sl-header-icon { font-size: 2.4em; line-height: 1; }
        .sl-header h1  { margin: 0 0 2px; font-size: 1.3em; font-weight: 700; }
        .sl-header p   { margin: 0; font-size: 0.8em; opacity: .85; }

        /* Cards */
        .sl-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 1px 6px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .sl-card-header {
            padding: 13px 20px;
            background: #fafafa;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            font-size: .8em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #888;
        }
        .sl-card-body { padding: 20px; }

        /* Update banner */
        .sl-update-banner {
            background: #fff8e1;
            border: 1.5px solid #f9a825;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .sl-update-banner-icon { font-size: 1.5em; line-height: 1; flex-shrink: 0; }
        .sl-update-banner strong { color: #e65100; }
        .sl-update-banner a { color: #1565c0; }
        .sl-update-banner pre {
            background: #fff3e0;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: .8em;
            white-space: pre-wrap;
            margin: 8px 0 0;
            max-height: 120px;
            overflow-y: auto;
        }

        .sl-up-to-date {
            background: #e8f5e9;
            border: 1px solid rgba(39,174,96,.35);
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 18px;
            font-size: .9em;
            color: #1b5e20;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Notice */
        .sl-notice {
            border-radius: 8px;
            padding: 11px 16px;
            margin-bottom: 16px;
            font-size: .9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sl-notice--success { background:#e8f5e9; border:1px solid rgba(39,174,96,.35); color:#1b5e20; }
        .sl-notice--error   { background:#fdf2f0; border:1px solid rgba(192,57,43,.3);  color:#922b21; }

        /* Form fields */
        .sl-field { margin-bottom: 16px; }
        .sl-field label {
            display: block;
            font-size: .8125em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #555;
            margin-bottom: 5px;
        }
        .sl-field input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 8px;
            font-size: .9em;
            color: #1a1a1a;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .sl-field input:focus {
            border-color: #c0392b;
            box-shadow: 0 0 0 3px rgba(192,57,43,.1);
            outline: none;
        }
        .sl-hint {
            font-size: .8em;
            color: #888;
            margin-top: 5px;
        }

        /* Buttons */
        .sl-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 11px 22px;
            border: none;
            border-radius: 9px;
            font-size: .9375em;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, transform .1s;
        }
        .sl-btn:active { transform: scale(.98); }
        .sl-btn--primary { background: #c0392b; color: #fff; }
        .sl-btn--primary:hover { background: #a93226; }
        .sl-btn--secondary {
            background: #fff;
            color: #333;
            border: 1px solid rgba(0,0,0,.15);
        }
        .sl-btn--secondary:hover { background: #f5f5f5; }

        /* Status pill */
        .sl-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: .875em;
            font-weight: 600;
        }
        .sl-status--running  { background: #e8f5e9; color: #1b5e20; border: 1px solid rgba(39,174,96,.3); }
        .sl-status--stopped  { background: #f5f5f5; color: #888;    border: 1px solid rgba(0,0,0,.12); }
        .sl-status--warning  { background: #fff8e1; color: #e65100; border: 1px solid rgba(249,168,37,.4); }

        /* Version row */
        .sl-version-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .sl-version-info { font-size: .875em; color: #555; }
        .sl-version-info strong { color: #1a1a1a; }
    </style>
</head>
<body>

<div class="sl-header">
    <div class="sl-header-icon">🎅</div>
    <div>
        <h1>Santa's List — FPP Plugin</h1>
        <p>LED panel display controller · v<?= htmlspecialchars($current_version) ?></p>
    </div>
</div>

<?php if ($message): ?>
<div class="sl-notice sl-notice--success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($update_error): ?>
<div class="sl-notice sl-notice--error">⚠️ Update check failed: <?= htmlspecialchars($update_error) ?></div>
<?php endif; ?>

<!-- ── Update status ───────────────────────────────────────────────────── -->
<?php if ($update_available && $latest_version): ?>
<div class="sl-update-banner">
    <div class="sl-update-banner-icon">🆕</div>
    <div>
        <strong>Update available: v<?= htmlspecialchars($latest_version) ?></strong>
        (you have v<?= htmlspecialchars($current_version) ?>)<br>
        <a href="<?= htmlspecialchars($release_url ?? '#') ?>" target="_blank">View release on GitHub →</a>
        <?php if ($release_notes): ?>
        <pre><?= htmlspecialchars(mb_substr($release_notes, 0, 600)) ?><?= strlen($release_notes) > 600 ? '…' : '' ?></pre>
        <?php endif; ?>
        <div style="margin-top:10px;">
            <strong>To update:</strong> download the repo ZIP from GitHub,
            extract <code>fpp/fpp-santa-list/</code>, and replace
            <code>/home/fpp/media/plugins/fpp-santa-list/</code> on this device.
        </div>
    </div>
</div>
<?php elseif ($latest_version): ?>
<div class="sl-up-to-date">
    ✅ <strong>Up to date</strong> — v<?= htmlspecialchars($current_version) ?> is the latest release.
    &nbsp;
    <a href="?check_update=1" style="font-size:.85em;color:#555;">Check again</a>
</div>
<?php else: ?>
<div style="margin-bottom:14px;">
    <a href="?check_update=1" class="sl-btn sl-btn--secondary" style="font-size:.85em;">🔄 Check for Updates</a>
</div>
<?php endif; ?>

<!-- ── Settings form ──────────────────────────────────────────────────── -->
<form method="post">

    <div class="sl-card">
        <div class="sl-card-header">WordPress Connection</div>
        <div class="sl-card-body">
            <div class="sl-field">
                <label for="wp_url">WordPress Site URL</label>
                <input type="url" id="wp_url" name="wp_url"
                       value="<?= cfg('SANTA_WP_URL') ?>"
                       placeholder="https://your-site.com" required />
                <p class="sl-hint">No trailing slash. Must have the Santa's List WordPress plugin installed.</p>
            </div>
            <div class="sl-field">
                <label for="api_key">API Key</label>
                <input type="text" id="api_key" name="api_key"
                       value="<?= cfg('SANTA_API_KEY') ?>"
                       placeholder="From WordPress → Santa's List → Settings" required />
                <p class="sl-hint">Copy from WordPress Admin → Santa's List → Settings → FPP API Key.</p>
            </div>
            <div class="sl-field">
                <label for="poll_interval">Poll Interval (seconds)</label>
                <input type="number" id="poll_interval" name="poll_interval"
                       value="<?= cfg('SANTA_POLL_INTERVAL', '120') ?>" min="30" max="3600" />
                <p class="sl-hint">How often to fetch the latest names. 120 = every 2 minutes.</p>
            </div>
        </div>
    </div>

    <div class="sl-card">
        <div class="sl-card-header">FPP Overlay Settings</div>
        <div class="sl-card-body">
            <p class="sl-hint" style="margin-top:0;">These must match the Pixel Overlay Model names configured in
               FPP → Channel Outputs → Pixel Overlays.</p>
            <div class="sl-field">
                <label for="title_overlay">Title Panel Overlay Name</label>
                <input type="text" id="title_overlay" name="title_overlay"
                       value="<?= cfg('SANTA_TITLE_OVERLAY', 'SantaTitle') ?>" />
                <p class="sl-hint">Shows "Santa's Naughty &amp; Nice List" (static).</p>
            </div>
            <div class="sl-field">
                <label for="names_overlay">Names Panel Overlay Name</label>
                <input type="text" id="names_overlay" name="names_overlay"
                       value="<?= cfg('SANTA_NAMES_OVERLAY', 'SantaNames') ?>" />
                <p class="sl-hint">Scrolls the names list.</p>
            </div>
            <div class="sl-field">
                <label for="fpp_url">FPP API URL</label>
                <input type="text" id="fpp_url" name="fpp_url"
                       value="<?= cfg('SANTA_FPP_URL', 'http://localhost') ?>" />
                <p class="sl-hint">Usually <code>http://localhost</code>. Change only if FPP is on a different device.</p>
            </div>
        </div>
    </div>

    <button type="submit" name="save_settings" class="sl-btn sl-btn--primary">💾 Save Settings</button>

</form>

<!-- ── Status ────────────────────────────────────────────────────────── -->
<div class="sl-card" style="margin-top:18px;">
    <div class="sl-card-header">Daemon Status</div>
    <div class="sl-card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <?php if ($daemon_running): ?>
            <span class="sl-status sl-status--running">🟢 Running (PID <?= htmlspecialchars($daemon_pid) ?>)</span>
            <?php elseif (file_exists($pid_file)): ?>
            <span class="sl-status sl-status--warning">⚠️ PID file exists but process not found</span>
            <?php else: ?>
            <span class="sl-status sl-status--stopped">⬜ Not running</span>
            <?php endif; ?>
            <span style="font-size:.8em;color:#888;">Use FPP Scheduler or Playlist commands to start/stop.</span>
        </div>
    </div>
</div>

<!-- ── Version / update check footer ─────────────────────────────────── -->
<div class="sl-card">
    <div class="sl-card-body">
        <div class="sl-version-row">
            <div class="sl-version-info">
                Installed: <strong>v<?= htmlspecialchars($current_version) ?></strong>
                <?php if ($latest_version): ?>
                &nbsp;·&nbsp; Latest on GitHub: <strong>v<?= htmlspecialchars($latest_version) ?></strong>
                <?php endif; ?>
                <?php if (!empty($cached['fetched_at'])): ?>
                &nbsp;·&nbsp; <span style="color:#bbb;">Checked <?= round((time() - $cached['fetched_at']) / 60) ?>m ago</span>
                <?php endif; ?>
            </div>
            <a href="?check_update=1" class="sl-btn sl-btn--secondary" style="font-size:.8em;padding:7px 14px;">
                🔄 Check Now
            </a>
        </div>
    </div>
</div>

</body>
</html>
