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
// Retries up to 5 attempts on a lost race (concurrent requests reading the
// same stale count at the same instant — e.g. a double-clicked submit
// button, or a browser silently retrying a slow request), with a small
// random jitter delay between attempts so retries don't all collide again
// immediately; if all 5 attempts lose the race, fails closed — treats it as
// "blocked" rather than risk letting racing requests both through. This is
// the shared building block behind every rate/attempt limiter in codes.ts
// and ratelimit.ts.
export async function checkAndIncrement(
  kv: Deno.Kv,
  key: Deno.KvKey,
  max: number,
  ttlMs: number
): Promise<boolean> {
  for (let attempt = 0; attempt < 5; attempt++) {
    const entry = await kv.get<number>(key);
    const count = entry.value ?? 0;
    if (count >= max) return false;
    if (await tryCommit(kv, entry, key, count + 1, ttlMs)) return true;
    if (attempt < 4) {
      await new Promise((resolve) => setTimeout(resolve, Math.random() * 10));
    }
  }
  return false;
}

// NOTE: `currentCount` reads keys written by `increment` below, which stores
// its value as a `Deno.KvU64` (required by the native `sum` mutation). Only
// call `currentCount`/`increment` on keys that are EXCLUSIVELY written via
// `increment` (currently: the daily-email-count key in ratelimit.ts) — never
// on a key also written via `checkAndIncrement`'s plain-number `.set()`,
// which would corrupt this reader.
//
// Upgrade path: if a future redeploy ever needs to run this `sum`-based
// `increment` against a Deno KV that already has an OLDER build's
// plain-number value at the same key, `sum` throws (TypeError: non-U64
// value). Since `daily-email-count` keys are date-scoped and this project
// has no persistent KV in production yet, this can't happen on first
// deploy — but if it ever matters, delete the stale key once before
// cutover, or seed it as a `Deno.KvU64` ahead of time.
export async function currentCount(kv: Deno.Kv, key: Deno.KvKey): Promise<number> {
  const entry = await kv.get<Deno.KvU64>(key);
  return entry.value ? Number(entry.value.value) : 0;
}

// Increments a counter unconditionally (no cap check — the caller already
// checked one separately, e.g. the daily email cap is checked before sending
// and incremented only after a real send succeeds). Uses Deno KV's native
// atomic `sum` mutation instead of a read-then-conditionally-write pattern:
// `sum` is applied server-side with no read-modify-write race at all, so
// every concurrent call is guaranteed to land — unlike a get-then-set retry
// loop, which silently drops writes once its retries are exhausted under
// real concurrent load. This requires the counter to be stored as a
// `Deno.KvU64` (Deno KV's requirement for `sum` operands).
//
// ponytail: no `ttlMs` param here (unlike `checkAndIncrement`) because Deno
// KV's `sum`/`min`/`max` mutations do not support `expireIn` at all — only
// `set` does (confirmed against Deno's own `Deno.KvMutation` type, and by
// observing the field is silently no-op'd if force-added at runtime). The
// sole caller, `incrementDailyEmailCount`, is unaffected: its key already
// embeds the UTC date (`dailyEmailCountKey()`), so each day's counter is
// naturally abandoned once the date rolls over — no TTL is needed for
// correctness. The only cost is that old daily-count rows (tens of bytes
// each) are never swept from KV. Upgrade path if that ever matters: a
// scheduled cleanup deleting `['daily-email-count', *]` keys older than a
// few days.
export async function increment(kv: Deno.Kv, key: Deno.KvKey): Promise<void> {
  await kv.atomic().mutate({ type: 'sum', key, value: new Deno.KvU64(1n) }).commit();
}
