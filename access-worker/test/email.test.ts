import { assertEquals } from 'jsr:@std/assert';
import { sendVerificationCode } from '../src/email.ts';

Deno.test('sendVerificationCode returns true when the relay responds ok', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () =>
    Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
  try {
    const ok = await sendVerificationCode('fake-secret', 'kelly@mclair.com.br', '123456');
    assertEquals(ok, true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode returns false when the relay responds with an error status', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () =>
    Promise.resolve(new Response(JSON.stringify({ ok: false, error: 'unauthorized' }), { status: 400 }));
  try {
    const ok = await sendVerificationCode('fake-secret', 'kelly@mclair.com.br', '123456');
    assertEquals(ok, false);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode returns false when the relay responds ok=false with 200 status', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () =>
    Promise.resolve(new Response(JSON.stringify({ ok: false, error: 'mail() failed' }), { status: 200 }));
  try {
    const ok = await sendVerificationCode('fake-secret', 'kelly@mclair.com.br', '123456');
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
    return Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
  };
  try {
    await sendVerificationCode('fake-secret', 'kelly@mclair.com.br', '654321');
    const body = JSON.parse(capturedBody);
    assertEquals(body.to, 'kelly@mclair.com.br');
    assertEquals(body.code, '654321');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode authenticates with the given relay secret', async () => {
  const original = globalThis.fetch;
  let capturedBody = '';
  globalThis.fetch = (_input: string | URL | Request, init?: RequestInit) => {
    capturedBody = (init?.body as string) ?? '';
    return Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
  };
  try {
    await sendVerificationCode('minha-chave-secreta', 'kelly@mclair.com.br', '123456');
    const body = JSON.parse(capturedBody);
    assertEquals(body.secret, 'minha-chave-secreta');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('sendVerificationCode posts to the mail relay URL', async () => {
  const original = globalThis.fetch;
  let capturedUrl = '';
  globalThis.fetch = (input: string | URL | Request) => {
    capturedUrl = String(input);
    return Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
  };
  try {
    await sendVerificationCode('fake-secret', 'kelly@mclair.com.br', '123456');
    assertEquals(capturedUrl, 'https://olive-gnat-658393.hostingersite.com/mail-relay/send.php');
  } finally {
    globalThis.fetch = original;
  }
});
