<?php
require_once __DIR__ . '/config.php';

function cmsDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

// Lazily adds the profile/force-password-change columns (same idempotent
// pattern as cmsRateLimitTable in auth.php -- no migration tooling here).
function cmsEnsureUserProfileColumns(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = $pdo->query("SHOW COLUMNS FROM cmstest_users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('display_name', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN display_name VARCHAR(190) NULL AFTER username");
    }
    if (!in_array('avatar_url', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER display_name");
    }
    if (!in_array('password_changed_at', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN password_changed_at DATETIME NULL");
        // Backfill existing accounts as "already changed" so the forced popup
        // only applies going forward, to newly-created or admin-reset accounts.
        $pdo->exec("UPDATE cmstest_users SET password_changed_at = NOW() WHERE password_changed_at IS NULL");
    }
    if (!in_array('login_count_since_reset', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN login_count_since_reset INT UNSIGNED NOT NULL DEFAULT 0");
    }
}

// Signals the server-side cron (see ~/mclair-build/rebuild.sh) to rebuild and
// republish the static site. Just drops a flag file — actual build/deploy
// happens outside PHP since exec()/shell_exec() are disabled on this host.
function queueRebuild(): void {
    $dir = '/home/u229450165/mclair-build/queue';
    if (!is_dir($dir)) return;
    @touch($dir . '/pending');
}
