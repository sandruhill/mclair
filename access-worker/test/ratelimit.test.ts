import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import { isRateLimited } from '../src/ratelimit';

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
