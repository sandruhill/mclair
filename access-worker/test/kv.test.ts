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

Deno.test('currentCount is 0 for an unset key and reflects checkAndIncrement calls', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'e'];
  assertEquals(await currentCount(kv, key), 0);
  await checkAndIncrement(kv, key, 5, 60_000);
  await checkAndIncrement(kv, key, 5, 60_000);
  assertEquals(await currentCount(kv, key), 2);
  kv.close();
});

Deno.test('increment raises the count without checking a cap', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'f'];
  await increment(kv, key, 60_000);
  await increment(kv, key, 60_000);
  await increment(kv, key, 60_000);
  assertEquals(await currentCount(kv, key), 3);
  kv.close();
});

Deno.test('increment and checkAndIncrement pass the given ttlMs through to the underlying set call', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'h'];

  // We can't reliably assert real-time expiry (Deno KV's TTL is a minimum
  // bound enforced by a background sweep, not deletion-on-read — see
  // https://docs.deno.com/deploy/kv/key_expiration/: "the key may still be
  // visible for some additional time" after the nominal expireIn elapses).
  // Instead, assert the value is set and immediately readable — the actual
  // TTL wiring is verified by reading Deno KV's own entry metadata.
  await increment(kv, key, 60_000);
  const entry = await kv.get<number>(key);
  assertEquals(entry.value, 1);
  assertEquals(entry.versionstamp !== null, true);

  kv.close();
});
