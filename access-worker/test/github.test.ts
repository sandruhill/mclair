import { assertEquals, assertStringIncludes } from 'jsr:@std/assert';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from '../src/github.ts';

Deno.test('githubUserExists returns true for a 200 response', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 200 }));
  try {
    assertEquals(await githubUserExists('fake-token', 'kellypinheiro'), true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('githubUserExists returns false for a 404 response', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 404 }));
  try {
    assertEquals(await githubUserExists('fake-token', 'usuario-que-nao-existe'), false);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('githubUserExists authenticates with the given token', async () => {
  const original = globalThis.fetch;
  let capturedAuth = '';
  globalThis.fetch = (_input: string | URL | Request, init?: RequestInit) => {
    const headers = init?.headers as Record<string, string>;
    capturedAuth = headers.Authorization ?? '';
    return Promise.resolve(new Response('{}', { status: 200 }));
  };
  try {
    await githubUserExists('minha-chave', 'kellypinheiro');
    assertEquals(capturedAuth, 'Bearer minha-chave');
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('isAlreadyCollaborator returns true for a 204 response', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response(null, { status: 204 }));
  try {
    assertEquals(await isAlreadyCollaborator('fake-token', 'kellypinheiro'), true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('isAlreadyCollaborator returns false for a 404 response', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 404 }));
  try {
    assertEquals(await isAlreadyCollaborator('fake-token', 'kellypinheiro'), false);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('addCollaborator returns true when GitHub creates a new invite (201)', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 201 }));
  try {
    assertEquals(await addCollaborator('fake-token', 'kellypinheiro'), true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('addCollaborator returns true when the user already had access (204)', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response(null, { status: 204 }));
  try {
    assertEquals(await addCollaborator('fake-token', 'kellypinheiro'), true);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('addCollaborator returns false when GitHub rejects the request', async () => {
  const original = globalThis.fetch;
  globalThis.fetch = () => Promise.resolve(new Response('{}', { status: 403 }));
  try {
    assertEquals(await addCollaborator('fake-token', 'kellypinheiro'), false);
  } finally {
    globalThis.fetch = original;
  }
});

Deno.test('addCollaborator sends push permission and bearer auth to the right URL', async () => {
  const original = globalThis.fetch;
  let capturedUrl = '';
  let capturedMethod = '';
  let capturedAuth = '';
  let capturedBody = '';
  globalThis.fetch = (input: string | URL | Request, init?: RequestInit) => {
    capturedUrl = input.toString();
    capturedMethod = init?.method ?? '';
    const headers = init?.headers as Record<string, string>;
    capturedAuth = headers.Authorization ?? '';
    capturedBody = (init?.body as string) ?? '';
    return Promise.resolve(new Response('{}', { status: 201 }));
  };
  try {
    await addCollaborator('minha-chave', 'kellypinheiro');
    assertStringIncludes(capturedUrl, '/repos/sandruhill/mclair/collaborators/kellypinheiro');
    assertEquals(capturedMethod, 'PUT');
    assertEquals(capturedAuth, 'Bearer minha-chave');
    assertEquals(JSON.parse(capturedBody), { permission: 'push' });
  } finally {
    globalThis.fetch = original;
  }
});
