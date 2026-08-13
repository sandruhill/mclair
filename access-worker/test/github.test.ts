import { describe, it, expect, vi, afterEach } from 'vitest';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from '../src/github';

describe('githubUserExists', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('returns true for a 200 response', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 200 })));
    expect(await githubUserExists('kellypinheiro')).toBe(true);
  });

  it('returns false for a 404 response', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 404 })));
    expect(await githubUserExists('usuario-que-nao-existe')).toBe(false);
  });
});

describe('isAlreadyCollaborator', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('returns true for a 204 response', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(null, { status: 204 })));
    expect(await isAlreadyCollaborator('fake-token', 'kellypinheiro')).toBe(true);
  });

  it('returns false for a 404 response', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 404 })));
    expect(await isAlreadyCollaborator('fake-token', 'kellypinheiro')).toBe(false);
  });
});

describe('addCollaborator', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('returns true when GitHub creates a new invite (201)', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 201 })));
    expect(await addCollaborator('fake-token', 'kellypinheiro')).toBe(true);
  });

  it('returns true when the user already had access (204)', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(null, { status: 204 })));
    expect(await addCollaborator('fake-token', 'kellypinheiro')).toBe(true);
  });

  it('returns false when GitHub rejects the request', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 403 })));
    expect(await addCollaborator('fake-token', 'kellypinheiro')).toBe(false);
  });

  it('sends push permission and bearer auth', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 201 }));
    vi.stubGlobal('fetch', fetchMock);
    await addCollaborator('minha-chave', 'kellypinheiro');
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain('/repos/sandruhill/mclair/collaborators/kellypinheiro');
    expect(init.method).toBe('PUT');
    const headers = init.headers as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer minha-chave');
    expect(JSON.parse(init.body as string)).toEqual({ permission: 'push' });
  });
});
