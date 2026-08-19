<?php
// Generic key/value helpers backing every rate limiter, code store, and
// OAuth state in this app — the PHP/MySQL equivalent of the Deno KV helpers
// this system used to run on (kv.ts). Where Deno KV needed an
// optimistic-concurrency retry loop (no real row locks available), MySQL
// gives us actual `SELECT ... FOR UPDATE` locks, so every operation here is
// a single transaction with no retry logic needed.

function kvGet(PDO $pdo, string $key): ?string {
    $stmt = $pdo->prepare('SELECT value FROM kv_store WHERE kv_key = ? AND expires_at > NOW()');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}

function kvSet(PDO $pdo, string $key, string $value, int $ttlSeconds): void {
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $pdo->prepare('REPLACE INTO kv_store (kv_key, value, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$key, $value, $expires]);
}

function kvDelete(PDO $pdo, string $key): void {
    $stmt = $pdo->prepare('DELETE FROM kv_store WHERE kv_key = ?');
    $stmt->execute([$key]);
}

// Checks a counter against $max and increments it if under, atomically via
// a row lock. Returns whether the increment happened (false = blocked).
function kvCheckAndIncrement(PDO $pdo, string $key, int $max, int $ttlSeconds): bool {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT value, expires_at FROM kv_store WHERE kv_key = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $count = 0;
        if ($row && strtotime($row['expires_at']) > time()) {
            $count = (int) $row['value'];
        }

        if ($count >= $max) {
            $pdo->commit();
            return false;
        }

        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = $pdo->prepare('REPLACE INTO kv_store (kv_key, value, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$key, (string) ($count + 1), $expires]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Unconditional atomic increment (no cap check — caller already checked
// separately). Used only for the daily email counter, whose key already
// embeds the UTC date, so a generous fixed TTL is fine — the key is
// naturally abandoned once the date rolls over.
function kvIncrement(PDO $pdo, string $key, int $ttlSeconds = 172800): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT value FROM kv_store WHERE kv_key = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $count = $row ? (int) $row['value'] : 0;
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = $pdo->prepare('REPLACE INTO kv_store (kv_key, value, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$key, (string) ($count + 1), $expires]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function kvCurrentCount(PDO $pdo, string $key): int {
    $stmt = $pdo->prepare('SELECT value FROM kv_store WHERE kv_key = ? AND expires_at > NOW()');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (int) $row['value'] : 0;
}
