import { assertEquals, assertStringIncludes } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import { makeHandler } from '../src/main.ts';

function postJson(path: string, body: unknown, ip = '198.51.100.1'): Request {
  return new Request(`https://worker.test${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Forwarded-For': ip },
    body: JSON.stringify(body),
  });
}

async function freshHandler() {
  const kv = await openKv(':memory:');
  const handler = makeHandler(kv, { resendApiKey: 'fake-resend-key', githubAdminToken: 'fake-github-token' });
  return { kv, handler };
}

Deno.test('serves the signup page on GET /', async () => {
  const { kv, handler } = await freshHandler();
  const res = await handler(new Request('https://worker.test/'));
  assertEquals(res.status, 200);
  assertStringIncludes(await res.text(), 'mclair.com.br');
  kv.close();
});

Deno.test('responds 404 for unknown routes', async () => {
  const { kv, handler } = await freshHandler();
  const res = await handler(new Request('https://worker.test/nope'));
  assertEquals(res.status, 404);
  kv.close();
});

Deno.test('rejects /solicitar-codigo for a non-mclair email', async () => {
  const { kv, handler } = await freshHandler();
  const res = await handler(postJson('/solicitar-codigo', { email: 'kelly@gmail.com', githubUsername: 'kelly' }));
  const data = (await res.json()) as { ok: boolean };
  assertEquals(data.ok, false);
  kv.close();
});

Deno.test('completes the full signup flow: request code, confirm, add collaborator', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let sentCode = '';
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    const url = input.toString();
    if (url.includes('api.resend.com')) {
      const body = JSON.parse((init?.body as string) ?? '{}');
      const match = /(\d{6})/.exec(body.html);
      sentCode = match ? match[1] : '';
      return Promise.resolve(new Response('{}', { status: 200 }));
    }
    if (url.includes('/users/')) return Promise.resolve(new Response('{}', { status: 200 }));
    if (url.includes('/collaborators/')) {
      if (init?.method === 'PUT') return Promise.resolve(new Response('{}', { status: 201 }));
      return Promise.resolve(new Response('{}', { status: 404 }));
    }
    return Promise.resolve(new Response('unexpected url in test', { status: 500 }));
  };
  try {
    const solicitar = await handler(
      postJson('/solicitar-codigo', { email: 'kelly@mclair.com.br', githubUsername: 'kellypinheiro' })
    );
    assertEquals(((await solicitar.json()) as { ok: boolean }).ok, true);
    assertEquals(/^\d{6}$/.test(sentCode), true);

    const confirmar = await handler(
      postJson('/confirmar-codigo', { email: 'kelly@mclair.com.br', code: sentCode, githubUsername: 'kellypinheiro' })
    );
    const data = (await confirmar.json()) as { ok: boolean; message?: string };
    assertEquals(data.ok, true);
    assertStringIncludes(data.message ?? '', 'admin');
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('a wrong code never reaches the GitHub API', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let githubCalls = 0;
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    const url = input.toString();
    if (url.includes('api.resend.com')) return Promise.resolve(new Response('{}', { status: 200 }));
    if (url.includes('api.github.com')) {
      githubCalls++;
      return Promise.resolve(new Response('{}', { status: 200 }));
    }
    return Promise.resolve(new Response('unexpected url in test', { status: 500 }));
  };
  try {
    await handler(postJson('/solicitar-codigo', { email: 'ana@mclair.com.br', githubUsername: 'ana' }));
    const res = await handler(
      postJson('/confirmar-codigo', { email: 'ana@mclair.com.br', code: '000000', githubUsername: 'ana' })
    );
    const data = (await res.json()) as { ok: boolean };
    assertEquals(data.ok, false);
    assertEquals(githubCalls, 0);
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('does not consume the code on a nonexistent GitHub username — the same code still works afterward', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let sentCode = '';
  let userShouldExist = false;
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    const url = input.toString();
    if (url.includes('api.resend.com')) {
      const body = JSON.parse((init?.body as string) ?? '{}');
      const match = /(\d{6})/.exec(body.html);
      sentCode = match ? match[1] : '';
      return Promise.resolve(new Response('{}', { status: 200 }));
    }
    if (url.includes('/users/')) {
      return Promise.resolve(new Response('{}', { status: userShouldExist ? 200 : 404 }));
    }
    if (url.includes('/collaborators/')) {
      if (init?.method === 'PUT') return Promise.resolve(new Response('{}', { status: 201 }));
      return Promise.resolve(new Response('{}', { status: 404 }));
    }
    return Promise.resolve(new Response('unexpected url in test', { status: 500 }));
  };
  try {
    await handler(postJson('/solicitar-codigo', { email: 'bruno@mclair.com.br', githubUsername: 'usuario-inventado' }));
    const first = await handler(
      postJson('/confirmar-codigo', { email: 'bruno@mclair.com.br', code: sentCode, githubUsername: 'usuario-inventado' })
    );
    assertEquals(((await first.json()) as { ok: boolean }).ok, false);

    userShouldExist = true;
    const second = await handler(
      postJson('/confirmar-codigo', { email: 'bruno@mclair.com.br', code: sentCode, githubUsername: 'usuario-corrigido' })
    );
    assertEquals(((await second.json()) as { ok: boolean }).ok, true);
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('rejects a 4th /solicitar-codigo request in the same hour and never calls Resend for it', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let resendCalls = 0;
  globalThis.fetch = (input: string | URL | Request) => {
    if (input.toString().includes('api.resend.com')) resendCalls++;
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    for (let i = 0; i < 3; i++) {
      await handler(postJson('/solicitar-codigo', { email: 'quatro@mclair.com.br', githubUsername: 'quatro' }));
    }
    assertEquals(resendCalls, 3);
    const res = await handler(
      postJson('/solicitar-codigo', { email: 'quatro@mclair.com.br', githubUsername: 'quatro' })
    );
    assertEquals(((await res.json()) as { ok: boolean }).ok, false);
    assertEquals(resendCalls, 3);
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('blocks /confirmar-codigo after too many wrong-code guesses and force-deletes the code', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let sentCode = '';
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    const url = input.toString();
    if (url.includes('api.resend.com')) {
      const body = JSON.parse((init?.body as string) ?? '{}');
      const match = /(\d{6})/.exec(body.html);
      sentCode = match ? match[1] : '';
      return Promise.resolve(new Response('{}', { status: 200 }));
    }
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    await handler(postJson('/solicitar-codigo', { email: 'muitas@mclair.com.br', githubUsername: 'muitas' }));
    for (let i = 0; i < 5; i++) {
      await handler(
        postJson('/confirmar-codigo', { email: 'muitas@mclair.com.br', code: '000000', githubUsername: 'muitas' })
      );
    }
    const blocked = await handler(
      postJson('/confirmar-codigo', { email: 'muitas@mclair.com.br', code: '000000', githubUsername: 'muitas' })
    );
    assertStringIncludes(((await blocked.json()) as { error: string }).error, 'Muitas tentativas');

    const withRealCode = await handler(
      postJson('/confirmar-codigo', { email: 'muitas@mclair.com.br', code: sentCode, githubUsername: 'muitas' })
    );
    assertEquals(((await withRealCode.json()) as { ok: boolean }).ok, false);
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('returns a clean 500 JSON error for a malformed JSON body, not an unhandled exception', async () => {
  const { kv, handler } = await freshHandler();
  const badRequest = new Request('https://worker.test/solicitar-codigo', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Forwarded-For': '198.51.100.1' },
    body: '{not valid json',
  });
  const res = await handler(badRequest);
  assertEquals(res.status, 500);
  const data = (await res.json()) as { ok: boolean };
  assertEquals(data.ok, false);
  kv.close();
});

Deno.test('blocks a 21st request from the same IP, sharing one bucket across /solicitar-codigo and /confirmar-codigo', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 200 }));
  const ip = '198.51.100.99';
  try {
    for (let i = 0; i < 10; i++) {
      await handler(postJson('/solicitar-codigo', { email: `pessoa${i}@mclair.com.br`, githubUsername: 'x' }, ip));
    }
    for (let i = 0; i < 10; i++) {
      await handler(
        postJson('/confirmar-codigo', { email: `pessoa${i}@mclair.com.br`, code: '000000', githubUsername: 'x' }, ip)
      );
    }
    // 20 total across both routes so far — the 21st, on either route, must be blocked.
    const res = await handler(postJson('/solicitar-codigo', { email: 'mais-uma@mclair.com.br', githubUsername: 'x' }, ip));
    const data = (await res.json()) as { ok: boolean; error: string };
    assertEquals(data.ok, false);
    assertStringIncludes(data.error, 'Muitas requisições');
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('a code cannot be reused for a second /confirmar-codigo call after a successful grant', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let sentCode = '';
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    const url = input.toString();
    if (url.includes('api.resend.com')) {
      const body = JSON.parse((init?.body as string) ?? '{}');
      const match = /(\d{6})/.exec(body.html);
      sentCode = match ? match[1] : '';
      return Promise.resolve(new Response('{}', { status: 200 }));
    }
    if (url.includes('/users/')) return Promise.resolve(new Response('{}', { status: 200 }));
    if (url.includes('/collaborators/')) {
      if (init?.method === 'PUT') return Promise.resolve(new Response('{}', { status: 201 }));
      return Promise.resolve(new Response('{}', { status: 404 }));
    }
    return Promise.resolve(new Response('unexpected url in test', { status: 500 }));
  };
  try {
    await handler(postJson('/solicitar-codigo', { email: 'reuso@mclair.com.br', githubUsername: 'reuso' }));
    const first = await handler(
      postJson('/confirmar-codigo', { email: 'reuso@mclair.com.br', code: sentCode, githubUsername: 'reuso' })
    );
    assertEquals(((await first.json()) as { ok: boolean }).ok, true);

    const second = await handler(
      postJson('/confirmar-codigo', { email: 'reuso@mclair.com.br', code: sentCode, githubUsername: 'reuso' })
    );
    assertEquals(((await second.json()) as { ok: boolean }).ok, false);
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});

Deno.test('the global daily email cap is checked before the per-email rate limit is touched', async () => {
  const { kv, handler } = await freshHandler();
  const original = globalThis.fetch;
  let resendCalls = 0;
  globalThis.fetch = (input: string | URL | Request) => {
    if (input.toString().includes('api.resend.com')) resendCalls++;
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    // Exhaust the global daily cap (50) using distinct emails so the per-email
    // limiter never itself becomes the blocker.
    for (let i = 0; i < 50; i++) {
      await handler(
        postJson('/solicitar-codigo', { email: `pessoa${i}@mclair.com.br`, githubUsername: 'x' }, `10.0.0.${i % 250}`)
      );
    }
    assertEquals(resendCalls, 50);

    const res = await handler(
      postJson('/solicitar-codigo', { email: 'depois-do-limite@mclair.com.br', githubUsername: 'x' }, '10.0.1.1')
    );
    const data = (await res.json()) as { ok: boolean; error: string };
    assertEquals(data.ok, false);
    assertEquals(data.error, 'Tenta de novo mais tarde.');
    assertEquals(resendCalls, 50); // the 51st request must not have reached Resend
  } finally {
    globalThis.fetch = original;
    kv.close();
  }
});
