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

// Signals the server-side cron (see ~/mclair-build/rebuild.sh) to rebuild and
// republish the static site. Just drops a flag file — actual build/deploy
// happens outside PHP since exec()/shell_exec() are disabled on this host.
function queueRebuild(): void {
    $dir = '/home/u229450165/mclair-build/queue';
    if (!is_dir($dir)) return;
    @touch($dir . '/pending');
}
