import { checkAndIncrement, currentCount, increment } from './kv.ts';

const WINDOW_MS = 60 * 60 * 1000;
const MAX_REQUESTS = 3;

export function isRateLimited(kv: Deno.Kv, email: string): Promise<boolean> {
  return checkAndIncrement(kv, ['ratelimit', email.toLowerCase()], MAX_REQUESTS, WINDOW_MS).then(
    (allowed) => !allowed
  );
}

const DAY_MS = 24 * 60 * 60 * 1000;
const MAX_DAILY_EMAILS = 50; // Resend free tier is 100/day — leave headroom

function dailyEmailCountKey(): Deno.KvKey {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  return ['daily-email-count', today];
}

// Global cap across all users, not per-email — protects the account's shared
// daily Resend quota from being exhausted by (accidental or malicious) volume.
// Check-only: does NOT increment. Call incrementDailyEmailCount separately, and
// only after an email actually sends — otherwise a Resend failure/timeout still
// burns quota for an email nobody received.
export async function isDailyEmailCapReached(kv: Deno.Kv): Promise<boolean> {
  return (await currentCount(kv, dailyEmailCountKey())) >= MAX_DAILY_EMAILS;
}

export function incrementDailyEmailCount(kv: Deno.Kv): Promise<void> {
  return increment(kv, dailyEmailCountKey());
}

const IP_MAX_REQUESTS = 20;

function ipRateLimitKey(ip: string): Deno.KvKey {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  return ['ip-ratelimit', ip, today];
}

// Per-IP counter on top of the per-email and global-daily limits above — an anonymous
// caller only needs the @mclair.com.br domain suffix (not a real mailbox) to hit
// /solicitar-codigo or /confirmar-codigo, so without this a single source can rotate
// the email local-part to drain the shared daily cap alone. Day-keyed (not a rolling
// hourly window) so a patient single IP can't just wait out repeated TTL resets to
// eventually exhaust the 50/day global cap alone — exhausting it now genuinely needs
// at least 3 distinct source IPs cooperating within the same UTC day.
export function isIpRateLimited(kv: Deno.Kv, ip: string): Promise<boolean> {
  return checkAndIncrement(kv, ipRateLimitKey(ip), IP_MAX_REQUESTS, DAY_MS).then((allowed) => !allowed);
}
