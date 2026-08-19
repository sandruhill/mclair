<?php
require_once __DIR__ . '/kv.php';

const RATELIMIT_WINDOW_SECONDS = 60 * 60;
const RATELIMIT_MAX_REQUESTS = 3;

function isRateLimited(PDO $pdo, string $email): bool {
    $allowed = kvCheckAndIncrement($pdo, 'ratelimit:' . strtolower($email), RATELIMIT_MAX_REQUESTS, RATELIMIT_WINDOW_SECONDS);
    return !$allowed;
}

const DAY_SECONDS = 24 * 60 * 60;
const MAX_DAILY_EMAILS = 50;

function dailyEmailCountKey(): string {
    return 'daily-email-count:' . gmdate('Y-m-d');
}

// Global cap across all users, not per-email — protects the mailbox from
// being exhausted by (accidental or malicious) volume. Check-only: does
// NOT increment. Call incrementDailyEmailCount separately, and only after
// an email actually sends — otherwise a send failure/timeout still burns
// quota for an email nobody received.
function isDailyEmailCapReached(PDO $pdo): bool {
    return kvCurrentCount($pdo, dailyEmailCountKey()) >= MAX_DAILY_EMAILS;
}

function incrementDailyEmailCount(PDO $pdo): void {
    kvIncrement($pdo, dailyEmailCountKey());
}

const IP_MAX_REQUESTS = 20;

function ipRateLimitKey(string $ip): string {
    return 'ip-ratelimit:' . $ip . ':' . gmdate('Y-m-d');
}

// Per-IP counter on top of the per-email and global-daily limits above — an
// anonymous caller only needs the @mclair.com.br domain suffix (not a real
// mailbox) to hit /solicitar-codigo or /confirmar-codigo, so without this a
// single source could rotate the email local-part to drain the shared
// daily cap alone. Day-keyed (not a rolling hourly window) so a patient
// single IP can't just wait out repeated TTL resets to eventually exhaust
// the 50/day global cap alone.
function isIpRateLimited(PDO $pdo, string $ip): bool {
    $allowed = kvCheckAndIncrement($pdo, ipRateLimitKey($ip), IP_MAX_REQUESTS, DAY_SECONDS);
    return !$allowed;
}
