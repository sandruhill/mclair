const WINDOW_SECONDS = 60 * 60;
const MAX_REQUESTS = 3;

export async function isRateLimited(kv: KVNamespace, email: string): Promise<boolean> {
  const key = `ratelimit:${email.toLowerCase()}`;
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= MAX_REQUESTS) return true;
  await kv.put(key, String(count + 1), { expirationTtl: WINDOW_SECONDS });
  return false;
}

const DAY_SECONDS = 24 * 60 * 60;
const MAX_DAILY_EMAILS = 50; // Resend free tier is 100/day — leave headroom

function dailyEmailCountKey(): string {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  return `daily-email-count:${today}`;
}

// Global cap across all users, not per-email — protects the account's shared
// daily Resend quota from being exhausted by (accidental or malicious) volume.
// Check-only: does NOT increment. Call incrementDailyEmailCount separately, and
// only after an email actually sends — otherwise a Resend failure/timeout still
// burns quota for an email nobody received.
export async function isDailyEmailCapReached(kv: KVNamespace): Promise<boolean> {
  const current = await kv.get(dailyEmailCountKey());
  const count = current ? parseInt(current, 10) : 0;
  return count >= MAX_DAILY_EMAILS;
}

export async function incrementDailyEmailCount(kv: KVNamespace): Promise<void> {
  const key = dailyEmailCountKey();
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  await kv.put(key, String(count + 1), { expirationTtl: DAY_SECONDS });
}

const IP_WINDOW_SECONDS = 60 * 60;
const IP_MAX_REQUESTS = 20;

// Per-IP counter on top of the per-email and global-daily limits above — an anonymous
// caller only needs the @mclair.com.br domain suffix (not a real mailbox) to hit
// /solicitar-codigo or /confirmar-codigo, so without this a single source can rotate
// the email local-part to drain the shared daily cap alone. 20/hour is generous for a
// legitimate signup but bounds how much of the 50/day global cap one IP can burn.
export async function isIpRateLimited(kv: KVNamespace, ip: string): Promise<boolean> {
  const key = `ip-ratelimit:${ip}`;
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= IP_MAX_REQUESTS) return true;
  await kv.put(key, String(count + 1), { expirationTtl: IP_WINDOW_SECONDS });
  return false;
}
