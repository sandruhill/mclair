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

// Global cap across all users, not per-email — protects the account's shared
// daily Resend quota from being exhausted by (accidental or malicious) volume.
export async function isDailyEmailCapReached(kv: KVNamespace): Promise<boolean> {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  const key = `daily-email-count:${today}`;
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= MAX_DAILY_EMAILS) return true;
  await kv.put(key, String(count + 1), { expirationTtl: DAY_SECONDS });
  return false;
}
