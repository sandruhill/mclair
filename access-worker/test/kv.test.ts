import { assertEquals } from 'jsr:@std/assert';
import { openKv, checkAndIncrement, currentCount, increment } from '../src/kv.ts';

Deno.test('checkAndIncrement allows requests under the cap', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'a'];
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  kv.close();
});

Deno.test('checkAndIncrement blocks once the cap is reached', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'b'];
  await checkAndIncrement(kv, key, 3, 60_000);
  await checkAndIncrement(kv, key, 3, 60_000);
  await checkAndIncrement(kv, key, 3, 60_000);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), false);
  kv.close();
});

Deno.test('checkAndIncrement tracks different keys independently', async () => {
  const kv = await openKv(':memory:');
  const keyA: Deno.KvKey = ['test', 'c'];
  const keyB: Deno.KvKey = ['test', 'd'];
  await checkAndIncrement(kv, keyA, 1, 60_000);
  assertEquals(await checkAndIncrement(kv, keyA, 1, 60_000), false);
  assertEquals(await checkAndIncrement(kv, keyB, 1, 60_000), true);
  kv.close();
});

// `currentCount` now reads its key as a `Deno.KvU64` (see Finding 2: it
// pairs with `increment`'s atomic `sum` mutation), so it must only be
// exercised here against `increment`-written keys, not `checkAndIncrement`
// ones — `checkAndIncrement` stores a plain number via `.set()`, a different
// on-disk representation that `currentCount` would misread.
Deno.test('currentCount is 0 for an unset key and reflects increment calls', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'e'];
  assertEquals(await currentCount(kv, key), 0);
  await increment(kv, key);
  await increment(kv, key);
  assertEquals(await currentCount(kv, key), 2);
  kv.close();
});

Deno.test('increment raises the count without checking a cap', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'f'];
  await increment(kv, key);
  await increment(kv, key);
  await increment(kv, key);
  assertEquals(await currentCount(kv, key), 3);
  kv.close();
});

// Regression test for Finding 2: the old get-then-conditionally-set
// implementation of `increment` had a 2-attempt optimistic-concurrency retry
// that silently dropped writes once both attempts lost their race — under
// real concurrent load (e.g. 50 concurrent calls), the final count landed
// far below 50. `increment` now uses Deno KV's native atomic `sum` mutation,
// which has no read-modify-write race at all, so every concurrent call must
// land.
Deno.test('increment does not drop writes under real concurrency', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'concurrent'];
  const CONCURRENT_CALLS = 50;

  await Promise.all(Array.from({ length: CONCURRENT_CALLS }, () => increment(kv, key)));

  assertEquals(await currentCount(kv, key), CONCURRENT_CALLS);
  kv.close();
});

// Finding 6, retargeted: the literal spec asked for a test proving `increment`
// passes ttlMs through to the underlying atomic `sum` mutation's `expireIn`.
// That's not possible to write honestly — Deno KV's `sum`/`min`/`max`
// mutations do not support `expireIn` at all (only `set` does; confirmed
// against Deno's own `Deno.KvMutation` type, where the `sum` variant has no
// `expireIn` field). `increment` was changed to no longer take a `ttlMs`
// param at all instead of silently accepting one Deno KV would discard (see
// the `ponytail:` comment on `increment` in src/kv.ts for why that's safe).
// The function that still legitimately carries TTL semantics on the same
// underlying atomic-commit code path is `checkAndIncrement` (via
// `tryCommit`'s `.set(key, value, { expireIn })`), and a bug there (e.g.
// passing 60 instead of 60_000) would be just as dangerous — it backs every
// rate/attempt limiter in the app. So this test proves the real ttlMs value
// reaches the real Deno.Kv API call for THAT function instead.
Deno.test('checkAndIncrement passes the given ttlMs through to the underlying atomic set call', async () => {
  const kv = await openKv(':memory:');
  let capturedExpireIn: number | undefined;

  const realAtomic = kv.atomic.bind(kv);
  kv.atomic = () => {
    const op = realAtomic();
    const realSet = op.set.bind(op);
    op.set = (key: Deno.KvKey, value: unknown, options?: { expireIn?: number }) => {
      capturedExpireIn = options?.expireIn;
      return realSet(key, value, options);
    };
    return op;
  };

  await checkAndIncrement(kv, ['test', 'ttl-check'], 5, 12_345);
  assertEquals(capturedExpireIn, 12_345);

  kv.close();
});
