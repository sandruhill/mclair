// access-worker/test/index.test.ts
import { describe, it, expect, vi, afterEach } from 'vitest';
import { env } from 'cloudflare:test';
import worker from '../src/index';

function postJson(path: string, body: unknown): Request {
  return new Request(`https://worker.test${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

describe('worker fetch handler', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('serves the signup page on GET /', async () => {
    const res = await worker.fetch(new Request('https://worker.test/'), env);
    expect(res.status).toBe(200);
    expect(await res.text()).toContain('mclair.com.br');
  });

  it('responds 404 for unknown routes', async () => {
    const res = await worker.fetch(new Request('https://worker.test/nope'), env);
    expect(res.status).toBe(404);
  });

  it('rejects /solicitar-codigo for a non-mclair email', async () => {
    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email: 'kelly@gmail.com', githubUsername: 'kelly' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);
  });

  it('rejects /solicitar-codigo with no GitHub username', async () => {
    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email: 'kelly@mclair.com.br', githubUsername: '' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);
  });

  it('completes the full signup flow: request code, confirm, add collaborator', async () => {
    let sentCode = '';
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = input.toString();
        if (url.includes('api.resend.com')) {
          const body = JSON.parse((init?.body as string) ?? '{}');
          const match = /(\d{6})/.exec(body.html);
          sentCode = match ? match[1] : '';
          return new Response('{}', { status: 200 });
        }
        if (url.includes('/users/')) {
          return new Response('{}', { status: 200 }); // GitHub username exists
        }
        if (url.includes('/collaborators/')) {
          if (init?.method === 'PUT') return new Response('{}', { status: 201 });
          return new Response('{}', { status: 404 }); // not yet a collaborator
        }
        return new Response('unexpected url in test', { status: 500 });
      })
    );

    const solicitar = await worker.fetch(
      postJson('/solicitar-codigo', { email: 'kelly@mclair.com.br', githubUsername: 'kellypinheiro' }),
      env
    );
    expect((await solicitar.json<{ ok: boolean }>()).ok).toBe(true);
    expect(sentCode).toMatch(/^\d{6}$/);

    const confirmar = await worker.fetch(
      postJson('/confirmar-codigo', {
        email: 'kelly@mclair.com.br',
        code: sentCode,
        githubUsername: 'kellypinheiro',
      }),
      env
    );
    const data = await confirmar.json<{ ok: boolean; message?: string }>();
    expect(data.ok).toBe(true);
    expect(data.message).toContain('admin');
  });

  it('tells the person they already have access instead of re-inviting', async () => {
    let sentCode = '';
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = input.toString();
        if (url.includes('api.resend.com')) {
          const body = JSON.parse((init?.body as string) ?? '{}');
          const match = /(\d{6})/.exec(body.html);
          sentCode = match ? match[1] : '';
          return new Response('{}', { status: 200 });
        }
        if (url.includes('/users/')) return new Response('{}', { status: 200 });
        if (url.includes('/collaborators/')) return new Response(null, { status: 204 }); // already a collaborator
        return new Response('unexpected url in test', { status: 500 });
      })
    );

    await worker.fetch(
      postJson('/solicitar-codigo', { email: 'ja-tem@mclair.com.br', githubUsername: 'jatem' }),
      env
    );
    const res = await worker.fetch(
      postJson('/confirmar-codigo', { email: 'ja-tem@mclair.com.br', code: sentCode, githubUsername: 'jatem' }),
      env
    );
    const data = await res.json<{ ok: boolean; message?: string }>();
    expect(data.ok).toBe(true);
    expect(data.message).toContain('já tem acesso');
  });

  it('rejects confirmation with a wrong code', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 200 })));
    await worker.fetch(
      postJson('/solicitar-codigo', { email: 'ana@mclair.com.br', githubUsername: 'ana' }),
      env
    );
    const res = await worker.fetch(
      postJson('/confirmar-codigo', { email: 'ana@mclair.com.br', code: '000000', githubUsername: 'ana' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);
  });

  it('rejects confirmation for a GitHub username that does not exist', async () => {
    let sentCode = '';
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = input.toString();
        if (url.includes('api.resend.com')) {
          const body = JSON.parse((init?.body as string) ?? '{}');
          const match = /(\d{6})/.exec(body.html);
          sentCode = match ? match[1] : '';
          return new Response('{}', { status: 200 });
        }
        if (url.includes('/users/')) return new Response('{}', { status: 404 }); // does not exist
        return new Response('unexpected url in test', { status: 500 });
      })
    );

    await worker.fetch(
      postJson('/solicitar-codigo', { email: 'bruno@mclair.com.br', githubUsername: 'usuario-inventado' }),
      env
    );
    const res = await worker.fetch(
      postJson('/confirmar-codigo', {
        email: 'bruno@mclair.com.br',
        code: sentCode,
        githubUsername: 'usuario-inventado',
      }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);
  });
});
