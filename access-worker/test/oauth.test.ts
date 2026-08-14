import { assertEquals, assertStringIncludes } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import {
  generateState,
  storeState,
  consumeState,
  buildAuthorizeUrl,
  exchangeCodeForToken,
  successPage,
  errorPage,
} from '../src/oauth.ts';

Deno.test('generateState returns a non-empty, unpredictable-looking string', () => {
  const a = generateState();
  const b = generateState();
  assertEquals(typeof a, 'string');
  assertEquals(a.length > 10, true);
  assertEquals(a === b, false);
});

Deno.test('consumeState accepts a state that was just stored', async () => {
  const kv = await openKv(':memory:');
  await storeState(kv, 'abc123');
  assertEquals(await consumeState(kv, 'abc123'), true);
  kv.close();
});

Deno.test('consumeState is single-use — a second call for the same state fails', async () => {
  const kv = await openKv(':memory:');
  await storeState(kv, 'abc123');
  assertEquals(await consumeState(kv, 'abc123'), true);
  assertEquals(await consumeState(kv, 'abc123'), false);
  kv.close();
});

Deno.test('consumeState rejects a state that was never stored', async () => {
  const kv = await openKv(':memory:');
  assertEquals(await consumeState(kv, 'never-stored'), false);
  kv.close();
});

Deno.test('buildAuthorizeUrl includes client id, redirect uri, scope and state', () => {
  const url = buildAuthorizeUrl('client-abc', 'https://worker.test/callback', 'state-xyz');
  const parsed = new URL(url);
  assertEquals(parsed.origin + parsed.pathname, 'https://github.com/login/oauth/authorize');
  assertEquals(parsed.searchParams.get('client_id'), 'client-abc');
  assertEquals(parsed.searchParams.get('redirect_uri'), 'https://worker.test/callback');
  assertEquals(parsed.searchParams.get('scope'), 'repo,user');
  assertEquals(parsed.searchParams.get('state'), 'state-xyz');
});

Deno.test('exchangeCodeForToken returns the access token on success', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () =>
    Promise.resolve(new Response(JSON.stringify({ access_token: 'gho_abc123' }), { status: 200 }));
  try {
    const token = await exchangeCodeForToken('id', 'secret', 'code');
    assertEquals(token, 'gho_abc123');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('exchangeCodeForToken returns null on an error status', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('nope', { status: 401 }));
  try {
    const token = await exchangeCodeForToken('id', 'secret', 'code');
    assertEquals(token, null);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('exchangeCodeForToken sends client id, secret and code in the request body', async () => {
  const original = globalThis.fetch;
  let capturedBody = '';
  globalThis.fetch = (_input: string | URL | Request, init?: RequestInit) => {
    capturedBody = (init?.body as string) ?? '';
    return Promise.resolve(new Response(JSON.stringify({ access_token: 'tok' }), { status: 200 }));
  };
  try {
    await exchangeCodeForToken('my-client', 'my-secret', 'my-code');
    const body = JSON.parse(capturedBody);
    assertEquals(body.client_id, 'my-client');
    assertEquals(body.client_secret, 'my-secret');
    assertEquals(body.code, 'my-code');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('successPage embeds the token in a github:success postMessage', () => {
  const html = successPage('gho_secrettoken');
  assertStringIncludes(html, 'authorization:github:success:');
  assertStringIncludes(html, 'gho_secrettoken');
});

Deno.test('errorPage embeds the message in a github:error postMessage, not a success one', () => {
  const html = errorPage('algo deu errado');
  assertStringIncludes(html, 'authorization:github:error:');
  assertStringIncludes(html, 'algo deu errado');
  assertEquals(html.includes('authorization:github:success:'), false);
});
