<?php
require_once __DIR__ . '/kv.php';

const CODE_TTL_SECONDS = 15 * 60;
const MAX_VERIFY_ATTEMPTS = 5;

function isValidMclairEmail(string $email): bool {
    return (bool) preg_match('/^[^\s@]+@mclair\.com\.br$/i', trim($email));
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

function codeKey(string $email): string {
    return 'code:' . strtolower($email);
}

function verifyAttemptsKey(string $email): string {
    return 'verify-attempts:' . strtolower($email);
}

function storeCode(PDO $pdo, string $email, string $code): void {
    kvSet($pdo, codeKey($email), $code, CODE_TTL_SECONDS);
    // A fresh code means a fresh set of guesses — otherwise someone who
    // legitimately burns through the verify-attempt cap and requests a new
    // code (as instructed) would stay locked out until the old attempt
    // counter's TTL expires on its own.
    kvDelete($pdo, verifyAttemptsKey($email));
}

// Checking the code and consuming it are separate steps, so a caller can
// validate the code without burning it until every other step of the flow
// (e.g. the GitHub username lookup) has also succeeded.
function codeMatches(PDO $pdo, string $email, string $code): bool {
    $stored = kvGet($pdo, codeKey($email));
    return $stored !== null && $stored === $code;
}

function consumeCode(PDO $pdo, string $email): void {
    kvDelete($pdo, codeKey($email));
}

// Called once per confirm request, before the submitted code is even
// looked at: if this email already has MAX_VERIFY_ATTEMPTS on the books,
// block immediately and force-delete the stored code so a fresh one is
// required.
function isVerifyAttemptLimited(PDO $pdo, string $email): bool {
    $allowed = kvCheckAndIncrement($pdo, verifyAttemptsKey($email), MAX_VERIFY_ATTEMPTS, CODE_TTL_SECONDS);
    if (!$allowed) {
        kvDelete($pdo, codeKey($email));
        return true;
    }
    return false;
}
