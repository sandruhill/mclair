// access-worker/test/index.test.ts
import { describe, it, expect, vi, afterEach } from 'vitest';
import { env } from 'cloudflare:test';
import worker from '../src/index';

function postJson(path: string, body: unknown, extraHeaders?: Record<string, string>): Request {
  return new Request(`https://worker.test${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...extraHeaders },
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

  // Small fix #3: the catch blocks must log the real exception, not swallow it silently —
  // otherwise real bugs are invisible in wrangler tail / the dashboard.
  it('logs the caught exception to console.error instead of swallowing it', async () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    try {
      const res = await worker.fetch(
        new Request('https://worker.test/solicitar-codigo', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: '{not valid json',
        }),
        env
      );
      expect(res.status).toBe(500);
      expect(errorSpy).toHaveBeenCalledTimes(1);
    } finally {
      errorSpy.mockRestore();
    }
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

  // Finding A: a wrong code must never reach the GitHub API at all — otherwise anyone
  // who knows an @mclair.com.br local-part (no valid code needed) could drive
  // githubUserExists and drain GITHUB_ADMIN_TOKEN's shared rate-limit budget.
  it('never calls the GitHub API when the submitted code is wrong', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);

    const email = 'sem-github@mclair.com.br';
    await worker.fetch(
      postJson('/solicitar-codigo', { email, githubUsername: 'qualquer-um' }),
      env
    );

    const res = await worker.fetch(
      postJson('/confirmar-codigo', { email, code: '000000', githubUsername: 'qualquer-um' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);

    const githubCalls = fetchMock.mock.calls.filter(([input]) =>
      input.toString().includes('api.github.com')
    );
    expect(githubCalls.length).toBe(0);
  });

  // Finding B: per-IP cap on /solicitar-codigo.
  it('blocks /solicitar-codigo after 20 requests from the same IP', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const ip = '198.51.100.5';

    for (let i = 0; i < 20; i++) {
      await worker.fetch(
        postJson('/solicitar-codigo', { email: `ip-teste${i}@mclair.com.br`, githubUsername: 'x' }, { 'CF-Connecting-IP': ip }),
        env
      );
    }
    const callsAfter20 = fetchMock.mock.calls.length;

    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email: 'ip-teste-21@mclair.com.br', githubUsername: 'x' }, { 'CF-Connecting-IP': ip }),
      env
    );
    const data = await res.json<{ ok: boolean; error?: string }>();
    expect(data.ok).toBe(false);
    expect(data.error).toContain('Muitas requisições');
    // The 21st call must not have hit Resend (or anything else) at all.
    expect(fetchMock.mock.calls.length).toBe(callsAfter20);
  });

  // Finding B: per-IP cap on /confirmar-codigo, shared with /solicitar-codigo's counter.
  it('blocks /confirmar-codigo after 20 requests from the same IP', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const ip = '198.51.100.6';

    for (let i = 0; i < 20; i++) {
      await worker.fetch(
        postJson(
          '/confirmar-codigo',
          { email: `ip-confirma${i}@mclair.com.br`, code: '000000', githubUsername: 'x' },
          { 'CF-Connecting-IP': ip }
        ),
        env
      );
    }

    const res = await worker.fetch(
      postJson(
        '/confirmar-codigo',
        { email: 'ip-confirma-21@mclair.com.br', code: '000000', githubUsername: 'x' },
        { 'CF-Connecting-IP': ip }
      ),
      env
    );
    const data = await res.json<{ ok: boolean; error?: string }>();
    expect(data.ok).toBe(false);
    expect(data.error).toContain('Muitas requisições');
  });

  // Small fix #1: the daily cap must be checked before the per-email rate limit is
  // touched — otherwise a request refused by the global cap still burns one of this
  // email's 3 hourly issuance slots for nothing.
  it('does not consume the per-email rate-limit slot when blocked by the daily cap', async () => {
    const today = new Date().toISOString().slice(0, 10);
    await env.CODES.put(`daily-email-count:${today}`, '50');
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 200 })));

    const email = 'cap-diario@mclair.com.br';
    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email, githubUsername: 'x' }),
      env
    );
    const data = await res.json<{ ok: boolean }>();
    expect(data.ok).toBe(false);

    // isRateLimited's key should be untouched — the daily-cap check must short-circuit
    // before it ever runs.
    expect(await env.CODES.get(`ratelimit:${email}`)).toBeNull();
  });

  // Small fix #2: the daily-cap counter must only increment after an email actually
  // sends — a Resend failure must not burn shared quota for a code nobody received.
  it('does not increment the daily cap counter when sendVerificationCode fails', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('erro', { status: 422 })));

    const res = await worker.fetch(
      postJson('/solicitar-codigo', { email: 'falha-resend@mclair.com.br', githubUsername: 'x' }),
      env
    );
    expect(res.status).toBe(500);

    const today = new Date().toISOString().slice(0, 10);
    expect(await env.CODES.get(`daily-email-count:${today}`)).toBeNull();
  });
});
