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

  it('rejects a 4th /solicitar-codigo request in the same hour and never calls Resend for it', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const email = 'ratelimited@mclair.com.br';

    for (let i = 0; i < 3; i++) {
      await worker.fetch(postJson('/solicitar-codigo', { email, githubUsername: 'x' }), env);
    }
    const callsAfterThree = fetchMock.mock.calls.length;

    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email, githubUsername: 'x' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);
    // The 4th call must not have hit Resend (or anything else) at all.
    expect(fetchMock.mock.calls.length).toBe(callsAfterThree);
  });

  it('blocks /confirmar-codigo after too many wrong-code guesses and force-deletes the code', async () => {
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
        return new Response('unexpected url in test', { status: 500 });
      })
    );

    const email = 'bruteforce@mclair.com.br';
    await worker.fetch(
      postJson('/solicitar-codigo', { email, githubUsername: 'atacante' }),
      env
    );
    expect(sentCode).toMatch(/^\d{6}$/);

    let lastData: { ok: boolean; error?: string } = { ok: true };
    for (let i = 0; i < 5; i++) {
      const res = await worker.fetch(
        postJson('/confirmar-codigo', { email, code: '000000', githubUsername: 'atacante' }),
        env
      );
      lastData = await res.json();
      expect(lastData.ok).toBe(false);
    }

    // 6th attempt: should now be blocked with the distinct "too many attempts" message.
    const blockedRes = await worker.fetch(
      postJson('/confirmar-codigo', { email, code: '000000', githubUsername: 'atacante' }),
      env
    );
    const blockedData = await blockedRes.json<{ ok: boolean; error?: string }>();
    expect(blockedData.ok).toBe(false);
    expect(blockedData.error).toContain('Muitas tentativas');

    // The ORIGINAL valid code must also now fail — it was force-deleted once the cap hit.
    const correctCodeRes = await worker.fetch(
      postJson('/confirmar-codigo', { email, code: sentCode, githubUsername: 'atacante' }),
      env
    );
    const correctCodeData = await correctCodeRes.json<{ ok: boolean }>();
    expect(correctCodeData.ok).toBe(false);
  });

  it('returns a clean 500 JSON error for a malformed JSON body, not an unhandled exception', async () => {
    const res = await worker.fetch(
      new Request('https://worker.test/solicitar-codigo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{not valid json',
      }),
      env
    );
    expect(res.status).toBe(500);
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);

    const res2 = await worker.fetch(
      new Request('https://worker.test/confirmar-codigo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{also not valid',
      }),
      env
    );
    expect(res2.status).toBe(500);
    const data2 = await res2.json<{ ok: boolean }>();
    expect(data2.ok).toBe(false);
  });

  it('does not consume the code on a nonexistent GitHub username — the same code still works afterward', async () => {
    let sentCode = '';
    let userLookupCount = 0;
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
          userLookupCount++;
          // First lookup: username doesn't exist (typo). Second lookup: it does.
          return new Response('{}', { status: userLookupCount === 1 ? 404 : 200 });
        }
        if (url.includes('/collaborators/')) {
          if (init?.method === 'PUT') return new Response('{}', { status: 201 });
          return new Response('{}', { status: 404 });
        }
        return new Response('unexpected url in test', { status: 500 });
      })
    );

    const email = 'typo@mclair.com.br';
    await worker.fetch(
      postJson('/solicitar-codigo', { email, githubUsername: 'usurio-typo' }),
      env
    );
    expect(sentCode).toMatch(/^\d{6}$/);

    const typoRes = await worker.fetch(
      postJson('/confirmar-codigo', { email, code: sentCode, githubUsername: 'usurio-typo' }),
      env
    );
    const typoData = await typoRes.json<{ ok: boolean }>();
    expect(typoData.ok).toBe(false);

    // Same code, corrected username — must still succeed since the typo attempt
    // didn't consume the code.
    const fixedRes = await worker.fetch(
      postJson('/confirmar-codigo', { email, code: sentCode, githubUsername: 'usuario-typo' }),
      env
    );
    const fixedData = await fixedRes.json<{ ok: boolean }>();
    expect(fixedData.ok).toBe(true);
  });
});
