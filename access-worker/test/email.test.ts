import { describe, it, expect, vi, afterEach } from 'vitest';
import { sendVerificationCode } from '../src/email';

describe('sendVerificationCode', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns true when Resend responds ok', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => new Response(JSON.stringify({ id: 'abc' }), { status: 200 }))
    );
    const ok = await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '123456');
    expect(ok).toBe(true);
  });

  it('returns false when Resend responds with an error status', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('erro', { status: 422 })));
    const ok = await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '123456');
    expect(ok).toBe(false);
  });

  it('sends the recipient and code in the request body', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '654321');
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const body = JSON.parse(init.body as string);
    expect(body.to).toBe('kelly@mclair.com.br');
    expect(body.html).toContain('654321');
  });

  it('authenticates with the given API key', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    await sendVerificationCode('minha-chave-secreta', 'kelly@mclair.com.br', '123456');
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer minha-chave-secreta');
  });
});
