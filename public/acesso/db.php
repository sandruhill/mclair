<?php
// PDO connection + schema bootstrap. config.php (deploy-time generated,
// never committed) must define DB_HOST/DB_NAME/DB_USER/DB_PASS before this
// runs.

function acessoDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Mirrors Deno KV's key/value/expiry model as a single generic table —
    // every rate limiter, code store, and OAuth state in this app is just a
    // row here. Idempotent, so it's safe to run on every request.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS kv_store (
            kv_key VARCHAR(191) PRIMARY KEY,
            value VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $pdo;
}
