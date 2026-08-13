import { assertEquals } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import {
  isRateLimited,
  isDailyEmailCapReached,
  incrementDailyEmailCount,
  isIpRateLimited,
} from '../src/ratelimit.ts';

Deno.test('isRateLimited allows the first 3 requests for an email', async () => {
  const kv = await openKv(':memory:');
  const email = 'novato@mclair.com.br';
  assertEquals(await isRateLimited(kv, email), false);
  assertEquals(await isRateLimited(kv, email), false);
  assertEquals(await isRateLimited(kv, email), false);
  kv.close();
});

Deno.test('isRateLimited blocks the 4th request within the window', async () => {
  const kv = await openKv(':memory:');
  const email = 'insistente@mclair.com.br';
  await isRateLimited(kv, email);
  await isRateLimited(kv, email);
  await isRateLimited(kv, email);
  assertEquals(await isRateLimited(kv, email), true);
  kv.close();
});

Deno.test('isRateLimited tracks different emails independently', async () => {
  const kv = await openKv(':memory:');
  const emailA = 'pessoa-a@mclair.com.br';
  const emailB = 'pessoa-b@mclair.com.br';
  await isRateLimited(kv, emailA);
  await isRateLimited(kv, emailA);
  await isRateLimited(kv, emailA);
  assertEquals(await isRateLimited(kv, emailB), false);
  kv.close();
});

Deno.test('isDailyEmailCapReached is false with no sends and does not itself increment', async () => {
  const kv = await openKv(':memory:');
  assertEquals(await isDailyEmailCapReached(kv), false);
  assertEquals(await isDailyEmailCapReached(kv), false); // calling it twice doesn't count as 2 sends
  kv.close();
});

Deno.test('incrementDailyEmailCount raises the count; isDailyEmailCapReached trips at 50', async () => {
  const kv = await openKv(':memory:');
  for (let i = 0; i < 50; i++) {
    await incrementDailyEmailCount(kv);
  }
  assertEquals(await isDailyEmailCapReached(kv), true);
  kv.close();
});

Deno.test('isIpRateLimited allows the first 20 requests for an IP', async () => {
  const kv = await openKv(':memory:');
  const ip = '203.0.113.10';
  for (let i = 0; i < 20; i++) {
    assertEquals(await isIpRateLimited(kv, ip), false);
  }
  kv.close();
});

Deno.test('isIpRateLimited blocks the 21st request', async () => {
  const kv = await openKv(':memory:');
  const ip = '203.0.113.11';
  for (let i = 0; i < 20; i++) {
    await isIpRateLimited(kv, ip);
  }
  assertEquals(await isIpRateLimited(kv, ip), true);
  kv.close();
});

Deno.test('isIpRateLimited tracks different IPs independently', async () => {
  const kv = await openKv(':memory:');
  const ipA = '203.0.113.20';
  const ipB = '203.0.113.21';
  for (let i = 0; i < 20; i++) {
    await isIpRateLimited(kv, ipA);
  }
  assertEquals(await isIpRateLimited(kv, ipA), true);
  assertEquals(await isIpRateLimited(kv, ipB), false);
  kv.close();
});
