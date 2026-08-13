import { assertEquals } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  codeMatches,
  consumeCode,
  isVerifyAttemptLimited,
} from '../src/codes.ts';

Deno.test('isValidMclairEmail accepts a valid mclair.com.br address', () => {
  assertEquals(isValidMclairEmail('kelly@mclair.com.br'), true);
});

Deno.test('isValidMclairEmail is case-insensitive on the domain', () => {
  assertEquals(isValidMclairEmail('kelly@MCLAIR.COM.BR'), true);
});

Deno.test('isValidMclairEmail rejects other domains', () => {
  assertEquals(isValidMclairEmail('kelly@gmail.com'), false);
});

Deno.test('isValidMclairEmail rejects malformed input', () => {
  assertEquals(isValidMclairEmail('not-an-email'), false);
});

Deno.test('generateCode returns a 6-digit numeric string', () => {
  assertEquals(/^\d{6}$/.test(generateCode()), true);
});

Deno.test('codeMatches confirms a code that was just stored', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly@mclair.com.br', '123456'), true);
  kv.close();
});

Deno.test('codeMatches rejects a wrong code', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly2@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly2@mclair.com.br', '999999'), false);
  kv.close();
});

Deno.test('codeMatches does not consume the code — it stays valid after checking', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly3@mclair.com.br', '123456');
  await codeMatches(kv, 'kelly3@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly3@mclair.com.br', '123456'), true);
  kv.close();
});

Deno.test('consumeCode makes a subsequent codeMatches call fail', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly4@mclair.com.br', '123456');
  await consumeCode(kv, 'kelly4@mclair.com.br');
  assertEquals(await codeMatches(kv, 'kelly4@mclair.com.br', '123456'), false);
  kv.close();
});

Deno.test('codeMatches rejects when no code was ever stored for that email', async () => {
  const kv = await openKv(':memory:');
  assertEquals(await codeMatches(kv, 'nunca-pediu@mclair.com.br', '123456'), false);
  kv.close();
});

Deno.test('isVerifyAttemptLimited allows the first 5 attempts', async () => {
  const kv = await openKv(':memory:');
  const email = 'tentativas@mclair.com.br';
  for (let i = 0; i < 5; i++) {
    assertEquals(await isVerifyAttemptLimited(kv, email), false);
  }
  kv.close();
});

Deno.test('isVerifyAttemptLimited blocks the 6th attempt and force-deletes the code', async () => {
  const kv = await openKv(':memory:');
  const email = 'travado@mclair.com.br';
  await storeCode(kv, email, '123456');
  for (let i = 0; i < 5; i++) {
    await isVerifyAttemptLimited(kv, email);
  }
  assertEquals(await isVerifyAttemptLimited(kv, email), true);
  assertEquals(await codeMatches(kv, email, '123456'), false);
  kv.close();
});

Deno.test('storeCode resets the attempt counter so a fresh code gives a fresh budget', async () => {
  const kv = await openKv(':memory:');
  const email = 'segunda-chance@mclair.com.br';
  await storeCode(kv, email, '111111');
  for (let i = 0; i < 5; i++) {
    await isVerifyAttemptLimited(kv, email);
  }
  assertEquals(await isVerifyAttemptLimited(kv, email), true); // capped
  await storeCode(kv, email, '222222'); // fresh code issued
  assertEquals(await isVerifyAttemptLimited(kv, email), false); // budget reset
  kv.close();
});
