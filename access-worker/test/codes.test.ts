import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import { isValidMclairEmail, generateCode, storeCode, verifyAndConsumeCode } from '../src/codes';

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
