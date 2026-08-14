import { assertEquals, assertStringIncludes } from 'jsr:@std/assert';
import { sendVerificationCode } from '../src/email.ts';

Deno.test('sendVerificationCode returns true when Resend responds ok', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response(JSON.stringify({ id: 'abc' }), { status: 200 }));
  try {
    const ok = await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '123456');
    assertEquals(ok, true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode returns false when Resend responds with an error status', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('erro', { status: 422 }));
  try {
    const ok = await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '123456');
    assertEquals(ok, false);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode sends the recipient and code in the request body', async () => {
  const original = globalThis.fetch;
  let capturedBody = '';
  globalThis.fetch = (_input: string | URL | Request, init?: RequestInit) => {
    capturedBody = (init?.body as string) ?? '';
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    await sendVerificationCode('fake-key', 'kelly@mclair.com.br', '654321');
    const body = JSON.parse(capturedBody);
    assertEquals(body.to, 'kelly@mclair.com.br');
    assertStringIncludes(body.html, '654321');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode authenticates with the given API key', async () => {
  const original = globalThis.fetch;
  let capturedAuth = '';
  globalThis.fetch = (_input: string | URL | Request, init?: RequestInit) => {
    const headers = init?.headers as Record<string, string>;
    capturedAuth = headers.Authorization ?? '';
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    await sendVerificationCode('minha-chave-secreta', 'kelly@mclair.com.br', '123456');
    assertEquals(capturedAuth, 'Bearer minha-chave-secreta');
  } finally {
    globalThis.fetch = original;
  }
});
