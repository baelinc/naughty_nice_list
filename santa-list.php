<?php
/**
 * Santa's List FPP Plugin — Settings Page
 * Accessible in FPP under: Content Setup → Santa's Naughty & Nice List
 */

$plugin_dir  = __DIR__;
$plugin_name = basename(__DIR__);
$self_url    = 'plugin.php?plugin=' . urlencode($plugin_name) . '&page=santa-list.php';
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
    $message = 'Settings saved successfully!';
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
<style>
    *, *::before, *::after { box-sizing: border-box; }

    .sl-wrap {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        max-width: 720px;
        margin: 0 auto;
        padding: 0 0 48px;
        color: #1a1a1a;
        font-size: 15px;
        line-height: 1.5;
    }

    /* ── Hero Header ───────────────────────────────────────────────── */
    .sl-hero {
        position: relative;
        background: linear-gradient(135deg, #8b0000 0%, #c0392b 40%, #196f3d 100%);
        padding: 32px 28px 28px;
        margin-bottom: 24px;
        overflow: hidden;
        border-radius: 0 0 20px 20px;
    }
    .sl-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }
    .sl-hero-inner {
        position: relative;
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .sl-hero-emblem {
        width: 68px;
        height: 68px;
        background: rgba(255,255,255,0.15);
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2em;
        flex-shrink: 0;
        backdrop-filter: blur(4px);
    }
    .sl-hero-text h1 {
        margin: 0 0 4px;
        font-size: 1.5em;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.02em;
        text-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .sl-hero-text p {
        margin: 0;
        font-size: 0.85em;
        color: rgba(255,255,255,0.8);
    }
    .sl-hero-badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        padding: 5px 14px;
        font-size: 0.78em;
        font-weight: 700;
        color: rgba(255,255,255,0.9);
        white-space: nowrap;
        backdrop-filter: blur(4px);
    }

    /* ── Snowflake decorations ─────────────────────────────────────── */
    .sl-hero-flakes {
        position: absolute;
        top: 10px; right: 20px;
        font-size: 1.1em;
        opacity: 0.2;
        letter-spacing: 8px;
        pointer-events: none;
        user-select: none;
    }

    /* ── Content padding ───────────────────────────────────────────── */
    .sl-content { padding: 0 20px; }

    /* ── Notices ───────────────────────────────────────────────────── */
    .sl-notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 18px;
        font-size: 0.9em;
        font-weight: 500;
    }
    .sl-notice-icon { font-size: 1.1em; flex-shrink: 0; margin-top: 1px; }
    .sl-notice--success {
        background: #f0faf3;
        border: 1px solid #a3d9b4;
        color: #1a5e31;
    }
    .sl-notice--error {
        background: #fff5f5;
        border: 1px solid #f5a9a9;
        color: #8b1a1a;
    }

    /* ── Update banner ─────────────────────────────────────────────── */
    .sl-update-banner {
        background: linear-gradient(135deg, #fff8e1, #fffde7);
        border: 1.5px solid #f9a825;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 18px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }
    .sl-update-icon { font-size: 1.6em; flex-shrink: 0; }
    .sl-update-title { font-weight: 700; color: #bf6000; margin-bottom: 4px; }
    .sl-update-banner a { color: #1a4a9e; font-weight: 500; }
    .sl-update-banner pre {
        background: rgba(0,0,0,0.05);
        border-radius: 6px;
        padding: 8px 10px;
        font-size: 0.78em;
        white-space: pre-wrap;
        margin: 10px 0 0;
        max-height: 100px;
        overflow-y: auto;
        color: #444;
    }

    .sl-up-to-date {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f0faf3;
        border: 1px solid #a3d9b4;
        border-radius: 10px;
        padding: 10px 16px;
        margin-bottom: 18px;
        font-size: 0.875em;
        color: #1a5e31;
        font-weight: 500;
    }
    .sl-up-to-date a { margin-left: auto; color: #555; font-size: 0.9em; white-space: nowrap; }

    /* ── Cards ─────────────────────────────────────────────────────── */
    .sl-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e5e5e5;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.04);
        overflow: hidden;
        margin-bottom: 16px;
    }
    .sl-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: #fafafa;
        border-bottom: 1px solid #efefef;
        font-size: 0.75em;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #666;
    }
    .sl-card-header-icon {
        font-size: 1.3em;
        line-height: 1;
    }
    .sl-card-body { padding: 20px 20px 16px; }

    /* ── Form fields ───────────────────────────────────────────────── */
    .sl-field { margin-bottom: 18px; }
    .sl-field:last-child { margin-bottom: 0; }
    .sl-field label {
        display: block;
        font-size: 0.8em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #444;
        margin-bottom: 6px;
    }
    .sl-field input {
        width: 100%;
        padding: 10px 13px;
        border: 1.5px solid #e0e0e0;
        border-radius: 9px;
        font-size: 0.925em;
        color: #1a1a1a;
        background: #fff;
        transition: border-color 0.15s, box-shadow 0.15s;
        appearance: none;
    }
    .sl-field input:focus {
        border-color: #c0392b;
        box-shadow: 0 0 0 3px rgba(192,57,43,0.12);
        outline: none;
    }
    .sl-field input::placeholder { color: #bbb; }
    .sl-hint {
        font-size: 0.8em;
        color: #999;
        margin-top: 5px;
        line-height: 1.4;
    }
    .sl-hint code {
        background: #f4f4f4;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 0.95em;
        color: #555;
    }

    /* ── Two-col grid for overlay names ─────────────────────────────── */
    .sl-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    @media (max-width: 520px) { .sl-grid-2 { grid-template-columns: 1fr; } }

    /* ── Buttons ───────────────────────────────────────────────────── */
    .sl-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 11px 24px;
        border: none;
        border-radius: 10px;
        font-size: 0.9375em;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
        letter-spacing: -0.01em;
    }
    .sl-btn:active { transform: scale(0.97); }
    .sl-btn--primary {
        background: linear-gradient(135deg, #c0392b, #a93226);
        color: #fff;
        box-shadow: 0 2px 8px rgba(192,57,43,0.35);
    }
    .sl-btn--primary:hover {
        background: linear-gradient(135deg, #a93226, #922b21);
        box-shadow: 0 4px 12px rgba(192,57,43,0.4);
    }
    .sl-btn--secondary {
        background: #fff;
        color: #333;
        border: 1.5px solid #ddd;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .sl-btn--secondary:hover { background: #f8f8f8; border-color: #ccc; }
    .sl-btn--sm { padding: 7px 14px; font-size: 0.82em; }

    /* ── Save row ──────────────────────────────────────────────────── */
    .sl-save-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        background: #fafafa;
        border-top: 1px solid #efefef;
    }
    .sl-save-hint { font-size: 0.8em; color: #aaa; }

    /* ── Status pill ───────────────────────────────────────────────── */
    .sl-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 16px;
        border-radius: 20px;
        font-size: 0.875em;
        font-weight: 600;
    }
    .sl-status-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .sl-status--running  { background: #f0faf3; color: #1a5e31; border: 1px solid #a3d9b4; }
    .sl-status--running .sl-status-dot { background: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.2); }
    .sl-status--stopped  { background: #f7f7f7; color: #888;    border: 1px solid #e0e0e0; }
    .sl-status--stopped .sl-status-dot { background: #bbb; }
    .sl-status--warning  { background: #fff8e1; color: #bf6000; border: 1px solid #f9c94e; }
    .sl-status--warning .sl-status-dot { background: #f9a825; box-shadow: 0 0 0 3px rgba(249,168,37,0.2); }

    /* ── Version footer ────────────────────────────────────────────── */
    .sl-version-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .sl-version-info { font-size: 0.82em; color: #aaa; line-height: 1.6; }
    .sl-version-info strong { color: #555; }
    .sl-version-info .sl-sep { margin: 0 6px; }

    /* ── Divider ───────────────────────────────────────────────────── */
    .sl-divider { height: 1px; background: #efefef; margin: 4px 0 16px; }

    /* Squash FPP's own body background bleed */
    .sl-wrap a { color: #c0392b; }
    .sl-wrap a:hover { color: #922b21; }
</style>

<div class="sl-wrap">

    <!-- ── Hero ───────────────────────────────────────────────────────────── -->
    <div class="sl-hero">
        <div class="sl-hero-flakes">❄ ❄ ❄ ❄ ❄</div>
        <div class="sl-hero-inner">
            <div class="sl-hero-emblem">🎅</div>
            <div class="sl-hero-text">
                <h1>Santa's Naughty &amp; Nice List</h1>
                <p>FPP Plugin &nbsp;·&nbsp; LED Panel Display Controller</p>
            </div>
            <div class="sl-hero-badge">v<?= htmlspecialchars($current_version) ?></div>
        </div>
    </div>

    <div class="sl-content">

        <!-- ── Notices ──────────────────────────────────────────────────── -->
        <?php if ($message): ?>
        <div class="sl-notice sl-notice--success">
            <span class="sl-notice-icon">✅</span>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($update_error): ?>
        <div class="sl-notice sl-notice--error">
            <span class="sl-notice-icon">⚠️</span>
            <span>Update check failed: <?= htmlspecialchars($update_error) ?></span>
        </div>
        <?php endif; ?>

        <!-- ── Update status ────────────────────────────────────────────── -->
        <?php if ($update_available && $latest_version): ?>
        <div class="sl-update-banner">
            <div class="sl-update-icon">🆕</div>
            <div>
                <div class="sl-update-title">Update available — v<?= htmlspecialchars($latest_version) ?></div>
                <div style="font-size:.88em;color:#555;">You have v<?= htmlspecialchars($current_version) ?> installed.
                    <a href="<?= htmlspecialchars($release_url ?? '#') ?>" target="_blank">View release on GitHub →</a>
                </div>
                <?php if ($release_notes): ?>
                <pre><?= htmlspecialchars(mb_substr($release_notes, 0, 500)) ?><?= strlen($release_notes) > 500 ? '…' : '' ?></pre>
                <?php endif; ?>
                <div style="margin-top:12px;font-size:.85em;color:#666;">
                    <strong>To update:</strong> download the repo ZIP, extract <code>fpp/fpp-santa-list/</code>,
                    and replace <code>/home/fpp/media/plugins/naughty_nice_list/</code> on this device.
                </div>
            </div>
        </div>
        <?php elseif ($latest_version): ?>
        <div class="sl-up-to-date">
            ✅ <strong>Up to date</strong>&nbsp; v<?= htmlspecialchars($current_version) ?> is the latest release.
            <a href="<?= htmlspecialchars($self_url) ?>&amp;check_update=1">Check again</a>
        </div>
        <?php else: ?>
        <div style="margin-bottom:16px;">
            <a href="<?= htmlspecialchars($self_url) ?>&amp;check_update=1" class="sl-btn sl-btn--secondary sl-btn--sm">🔄 Check for Updates</a>
        </div>
        <?php endif; ?>

        <!-- ── Settings form ────────────────────────────────────────────── -->
        <form method="post" action="<?= htmlspecialchars($self_url) ?>">

            <!-- WordPress Connection -->
            <div class="sl-card">
                <div class="sl-card-header">
                    <span class="sl-card-header-icon">🌐</span> WordPress Connection
                </div>
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
                               placeholder="Paste key from WordPress → Santa's List → Settings" required />
                        <p class="sl-hint">Copy from <strong>WordPress Admin → Santa's List → Settings → FPP API Key</strong>.</p>
                    </div>
                    <div class="sl-field" style="max-width:220px;">
                        <label for="poll_interval">Poll Interval (seconds)</label>
                        <input type="number" id="poll_interval" name="poll_interval"
                               value="<?= cfg('SANTA_POLL_INTERVAL', '120') ?>" min="30" max="3600" />
                        <p class="sl-hint">How often to fetch the latest names. 120&nbsp;=&nbsp;every 2 minutes.</p>
                    </div>
                </div>
            </div>

            <!-- FPP Overlay Settings -->
            <div class="sl-card">
                <div class="sl-card-header">
                    <span class="sl-card-header-icon">💡</span> FPP Overlay Settings
                </div>
                <div class="sl-card-body">
                    <p class="sl-hint" style="margin:0 0 16px;">These must match the <strong>Pixel Overlay Model</strong> names in
                        FPP → Channel Outputs → Pixel Overlays.</p>
                    <div class="sl-grid-2">
                        <div class="sl-field">
                            <label for="title_overlay">Title Panel Overlay</label>
                            <input type="text" id="title_overlay" name="title_overlay"
                                   value="<?= cfg('SANTA_TITLE_OVERLAY', 'SantaTitle') ?>" />
                            <p class="sl-hint">Displays "Santa's Naughty &amp; Nice List" (static).</p>
                        </div>
                        <div class="sl-field">
                            <label for="names_overlay">Names Panel Overlay</label>
                            <input type="text" id="names_overlay" name="names_overlay"
                                   value="<?= cfg('SANTA_NAMES_OVERLAY', 'SantaNames') ?>" />
                            <p class="sl-hint">Scrolls the names list.</p>
                        </div>
                    </div>
                    <div class="sl-divider"></div>
                    <div class="sl-field">
                        <label for="fpp_url">FPP API URL</label>
                        <input type="text" id="fpp_url" name="fpp_url"
                               value="<?= cfg('SANTA_FPP_URL', 'http://localhost') ?>" />
                        <p class="sl-hint">Usually <code>http://localhost</code>. Change only if FPP runs on a different device.</p>
                    </div>
                </div>
            </div>

            <!-- Save row -->
            <div class="sl-card" style="margin-bottom:16px;">
                <div class="sl-save-row">
                    <button type="submit" name="save_settings" class="sl-btn sl-btn--primary">
                        💾&nbsp; Save Settings
                    </button>
                    <span class="sl-save-hint">Changes take effect on the next daemon poll cycle.</span>
                </div>
            </div>

        </form>

        <!-- ── Daemon Status ─────────────────────────────────────────────── -->
        <div class="sl-card">
            <div class="sl-card-header">
                <span class="sl-card-header-icon">⚙️</span> Daemon Status
            </div>
            <div class="sl-card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <?php if ($daemon_running): ?>
                    <span class="sl-status sl-status--running">
                        <span class="sl-status-dot"></span> Running <span style="font-weight:400;color:#2e7d52;">(PID&nbsp;<?= htmlspecialchars($daemon_pid) ?>)</span>
                    </span>
                    <?php elseif (file_exists($pid_file)): ?>
                    <span class="sl-status sl-status--warning">
                        <span class="sl-status-dot"></span> PID file exists but process not found
                    </span>
                    <?php else: ?>
                    <span class="sl-status sl-status--stopped">
                        <span class="sl-status-dot"></span> Not running
                    </span>
                    <?php endif; ?>
                    <span style="font-size:.8em;color:#aaa;">Start/stop via FPP Scheduler or Playlist commands.</span>
                </div>
            </div>
        </div>

        <!-- ── Version footer ────────────────────────────────────────────── -->
        <div class="sl-card">
            <div class="sl-card-body" style="padding:14px 20px;">
                <div class="sl-version-footer">
                    <div class="sl-version-info">
                        <strong>Installed:</strong> v<?= htmlspecialchars($current_version) ?>
                        <?php if ($latest_version): ?>
                        <span class="sl-sep">·</span>
                        <strong>Latest:</strong> v<?= htmlspecialchars($latest_version) ?>
                        <?php endif; ?>
                        <?php if (!empty($cached['fetched_at'])): ?>
                        <span class="sl-sep">·</span>
                        Checked <?= round((time() - $cached['fetched_at']) / 60) ?>m ago
                        <?php endif; ?>
                    </div>
                    <a href="<?= htmlspecialchars($self_url) ?>&amp;check_update=1" class="sl-btn sl-btn--secondary sl-btn--sm">
                        🔄 Check Now
                    </a>
                </div>
            </div>
        </div>

    </div><!-- .sl-content -->
</div><!-- .sl-wrap -->
