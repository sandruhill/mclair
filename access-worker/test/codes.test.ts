import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  verifyAndConsumeCode,
  isVerifyAttemptLimited,
} from '../src/codes';

describe('isValidMclairEmail', () => {
  it('accepts a valid mclair.com.br address', () => {
    expect(isValidMclairEmail('kelly@mclair.com.br')).toBe(true);
  });

  it('is case-insensitive on the domain', () => {
    expect(isValidMclairEmail('kelly@MCLAIR.COM.BR')).toBe(true);
  });

  it('rejects other domains', () => {
    expect(isValidMclairEmail('kelly@gmail.com')).toBe(false);
  });

  it('rejects malformed input', () => {
    expect(isValidMclairEmail('not-an-email')).toBe(false);
  });
});

describe('generateCode', () => {
  it('returns a 6-digit numeric string', () => {
    expect(generateCode()).toMatch(/^\d{6}$/);
  });
});

describe('storeCode / verifyAndConsumeCode', () => {
  it('verifies a code that was just stored', async () => {
    await storeCode(env.CODES, 'kelly@mclair.com.br', '123456');
    expect(await verifyAndConsumeCode(env.CODES, 'kelly@mclair.com.br', '123456')).toBe(true);
  });

  it('rejects a wrong code', async () => {
    await storeCode(env.CODES, 'kelly2@mclair.com.br', '123456');
    expect(await verifyAndConsumeCode(env.CODES, 'kelly2@mclair.com.br', '999999')).toBe(false);
  });

  it('is single-use — the same code cannot be verified twice', async () => {
    await storeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    const first = await verifyAndConsumeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    const second = await verifyAndConsumeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    expect(first).toBe(true);
    expect(second).toBe(false);
  });

  it('rejects when no code was ever stored for that email', async () => {
    expect(await verifyAndConsumeCode(env.CODES, 'nunca-pediu@mclair.com.br', '123456')).toBe(false);
  });
});

describe('isVerifyAttemptLimited', () => {
  it('allows the first 5 checks, blocks the 6th', async () => {
    const email = 'tentativas@mclair.com.br';
    for (let i = 0; i < 5; i++) {
      expect(await isVerifyAttemptLimited(env.CODES, email)).toBe(false);
    }
    expect(await isVerifyAttemptLimited(env.CODES, email)).toBe(true);
  });

  it('force-deletes the stored code once the cap is hit', async () => {
    const email = 'apagado@mclair.com.br';
    await storeCode(env.CODES, email, '123456');
    for (let i = 0; i < 5; i++) {
      await isVerifyAttemptLimited(env.CODES, email);
    }
    expect(await isVerifyAttemptLimited(env.CODES, email)).toBe(true);
    // The code should be gone even though it was never actually guessed.
    expect(await verifyAndConsumeCode(env.CODES, email, '123456')).toBe(false);
  });

  it('resets when a fresh code is issued, so a legitimate user is not stuck locked out', async () => {
    const email = 'segunda-chance@mclair.com.br';
    await storeCode(env.CODES, email, '111111');
    for (let i = 0; i < 5; i++) {
      await isVerifyAttemptLimited(env.CODES, email);
    }
    expect(await isVerifyAttemptLimited(env.CODES, email)).toBe(true); // capped

    // Person requests a new code, as the error message tells them to.
    await storeCode(env.CODES, email, '222222');
    expect(await isVerifyAttemptLimited(env.CODES, email)).toBe(false); // fresh budget
    expect(await verifyAndConsumeCode(env.CODES, email, '222222')).toBe(true);
  });
});
