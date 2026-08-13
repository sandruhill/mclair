export function openKv(path?: string): Promise<Deno.Kv> {
  return Deno.openKv(path);
}

// Atomically writes `value` at `key`, but only if nobody else has written to
// `key` since `entry` was read (Deno KV's optimistic-concurrency check).
// Returns whether the write actually happened.
async function tryCommit(
  kv: Deno.Kv,
  entry: Deno.KvEntryMaybe<number>,
  key: Deno.KvKey,
  value: number,
  ttlMs: number
): Promise<boolean> {
  const res = await kv.atomic().check(entry).set(key, value, { expireIn: ttlMs }).commit();
  return res.ok;
}

// Checks a counter against `max` and increments it if under, atomically.
// Retries once on a lost race (two requests reading the same stale count at
// the same instant); if both attempts lose the race, fails closed — treats
// it as "blocked" rather than risk letting two racing requests both through.
// This is the shared building block behind every rate/attempt limiter in
// codes.ts and ratelimit.ts.
export async function checkAndIncrement(
  kv: Deno.Kv,
  key: Deno.KvKey,
  max: number,
  ttlMs: number
): Promise<boolean> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const entry = await kv.get<number>(key);
    const count = entry.value ?? 0;
    if (count >= max) return false;
    if (await tryCommit(kv, entry, key, count + 1, ttlMs)) return true;
  }
  return false;
}

export async function currentCount(kv: Deno.Kv, key: Deno.KvKey): Promise<number> {
  const entry = await kv.get<number>(key);
  return entry.value ?? 0;
}

// Increments a counter unconditionally (no cap check — the caller already
// checked one separately, e.g. the daily email cap is checked before sending
// and incremented only after a real send succeeds). Retried once on a lost
// race; if both attempts lose, under-reports by one rather than throwing —
// an acceptable soft-limit tradeoff for a counter that exists to bound abuse,
// not to bill anyone precisely.
export async function increment(kv: Deno.Kv, key: Deno.KvKey, ttlMs: number): Promise<void> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const entry = await kv.get<number>(key);
    const count = entry.value ?? 0;
    if (await tryCommit(kv, entry, key, count + 1, ttlMs)) return;
  }
}
