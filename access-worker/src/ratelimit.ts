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
