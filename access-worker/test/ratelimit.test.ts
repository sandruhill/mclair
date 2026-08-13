import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import { isRateLimited, isIpRateLimited } from '../src/ratelimit';

describe('isRateLimited', () => {
  it('allows the first 3 requests for an email', async () => {
    const email = 'novato@mclair.com.br';
    expect(await isRateLimited(env.CODES, email)).toBe(false);
    expect(await isRateLimited(env.CODES, email)).toBe(false);
    expect(await isRateLimited(env.CODES, email)).toBe(false);
  });

  it('blocks the 4th request within the window', async () => {
    const email = 'insistente@mclair.com.br';
    await isRateLimited(env.CODES, email);
    await isRateLimited(env.CODES, email);
    await isRateLimited(env.CODES, email);
    expect(await isRateLimited(env.CODES, email)).toBe(true);
  });

  it('tracks different emails independently', async () => {
    const emailA = 'pessoa-a@mclair.com.br';
    const emailB = 'pessoa-b@mclair.com.br';
    await isRateLimited(env.CODES, emailA);
    await isRateLimited(env.CODES, emailA);
    await isRateLimited(env.CODES, emailA);
    expect(await isRateLimited(env.CODES, emailB)).toBe(false);
  });
});

describe('isIpRateLimited', () => {
  it('allows the first 20 requests for an IP', async () => {
    const ip = '203.0.113.10';
    for (let i = 0; i < 20; i++) {
      expect(await isIpRateLimited(env.CODES, ip)).toBe(false);
    }
  });

  it('blocks the 21st request within the window', async () => {
    const ip = '203.0.113.11';
    for (let i = 0; i < 20; i++) {
      await isIpRateLimited(env.CODES, ip);
    }
    expect(await isIpRateLimited(env.CODES, ip)).toBe(true);
  });

  it('tracks different IPs independently', async () => {
    const ipA = '203.0.113.20';
    const ipB = '203.0.113.21';
    for (let i = 0; i < 20; i++) {
      await isIpRateLimited(env.CODES, ipA);
    }
    expect(await isIpRateLimited(env.CODES, ipA)).toBe(true);
    expect(await isIpRateLimited(env.CODES, ipB)).toBe(false);
  });
});
