# Self-Service Admin Panel Access — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Task 7 is not a normal implementation task** — it is a manual runbook only the project owner (Sandru) can execute, since it requires his personal Cloudflare/Resend/GitHub credentials. Do not dispatch an implementer subagent for it; present it to the human instead once Tasks 1–6 are reviewed and merged.

**Goal:** Let any `@mclair.com.br` staff member self-provision GitHub write access to the `sandruhill/mclair` repo (and therefore the `/admin/` CMS panel) without Sandru manually adding each person as a collaborator.

**Architecture:** A standalone Cloudflare Worker (new directory `access-worker/` in this repo) serves a small signup page and two JSON endpoints. Step 1 emails a 6-digit verification code (via Resend) to prove the person controls the `@mclair.com.br` address they typed. Step 2 checks the code, confirms the GitHub username is real, and calls GitHub's REST API to add that username as a repository collaborator — using a scoped admin token held only in the Worker, never exposed to the browser. No user database: GitHub's own collaborator list is the source of truth for who has access.

**Tech Stack:** Cloudflare Workers, TypeScript, Cloudflare KV (verification codes + rate-limit counters), Resend (transactional email), GitHub REST API. Tests via `@cloudflare/vitest-pool-workers` (runs real Workers runtime + simulated KV, no live network calls — Resend/GitHub calls are mocked via `fetch` stubbing).

## Global Constraints

- No framework beyond the Workers runtime itself (no Hono/itty-router) — 3 routes doesn't justify a router dependency.
- No user database. GitHub's collaborator list is the only persisted "who has access" state.
- The GitHub token used by the Worker must be a fine-grained PAT scoped only to the `sandruhill/mclair` repository — never a broad classic token. This is Sandru's responsibility to generate correctly (Task 7); the code just reads it from `env.GITHUB_ADMIN_TOKEN`.
- Signup only for `@mclair.com.br` addresses (case-insensitive), enforced server-side before any email is sent.
- Verification codes: 6 digits, single-use, 15-minute expiry (900 seconds).
- Rate limit: max 3 code requests per email per rolling hour.
- All user-facing error/success messages are in Brazilian Portuguese (this is an internal tool for Brazilian staff, matching the rest of this project).
- Never log or return the GitHub admin token or the Resend API key in any response body.

---

## File Structure

```
access-worker/
  package.json
  tsconfig.json
  wrangler.toml
  vitest.config.ts
  src/
    index.ts          — Worker entry point: routes, request/response wiring
    codes.ts           — email validation, code generation, KV store/verify
    ratelimit.ts        — KV-based rate limiting
    email.ts             — Resend API call
    github.ts             — GitHub REST API calls (user exists, collaborator check/add)
    signup-page.ts          — exports the signup page HTML as a string constant
  test/
    codes.test.ts
    ratelimit.test.ts
    email.test.ts
    github.test.ts
    index.test.ts        — integration tests against the full fetch handler
```

Each module under `src/` has one responsibility and no dependency on `index.ts` (so each is testable in isolation before `index.ts` wires them together in Task 6).

---

### Task 1: Project scaffolding

**Files:**
- Create: `access-worker/package.json`
- Create: `access-worker/tsconfig.json`
- Create: `access-worker/wrangler.toml`
- Create: `access-worker/vitest.config.ts`
- Create: `access-worker/src/index.ts`
- Test: `access-worker/test/index.test.ts`

**Interfaces:**
- Produces: the `Env` interface (`CODES: KVNamespace`, `RESEND_API_KEY: string`, `GITHUB_ADMIN_TOKEN: string`) that every later task's tests reference via `cloudflare:test`'s `env` export, and the base Worker `fetch` handler shape (`export default { async fetch(request, env) }`) that Task 6 extends with real routes.

This task proves the whole toolchain works (wrangler, vitest-pool-workers, KV binding) before any real logic is written.

- [ ] **Step 1: Create `package.json`**

```json
{
  "name": "mclair-access-worker",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "wrangler dev",
    "test": "vitest run",
    "deploy": "wrangler deploy"
  },
  "devDependencies": {
    "@cloudflare/vitest-pool-workers": "^0.5.0",
    "@cloudflare/workers-types": "^4.20250101.0",
    "typescript": "^5.5.0",
    "vitest": "^2.0.0",
    "wrangler": "^3.80.0"
  }
}
```

- [ ] **Step 2: Create `tsconfig.json`**

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "lib": ["ES2022"],
    "module": "ES2022",
    "moduleResolution": "Bundler",
    "types": ["@cloudflare/workers-types"],
    "strict": true,
    "skipLibCheck": true,
    "noEmit": true
  },
  "include": ["src", "test"]
}
```

- [ ] **Step 3: Create `wrangler.toml`**

```toml
name = "mclair-access-worker"
main = "src/index.ts"
compatibility_date = "2026-08-13"

[[kv_namespaces]]
binding = "CODES"
id = "REPLACE_WITH_REAL_KV_ID"
preview_id = "REPLACE_WITH_REAL_KV_ID"

# Secrets — set via `wrangler secret put`, never written to this file:
#   RESEND_API_KEY
#   GITHUB_ADMIN_TOKEN
```

The placeholder KV ids are intentional — Task 7 replaces them once a real KV namespace exists. `vitest-pool-workers` and `wrangler dev --local` both work fine against a placeholder id because they simulate KV locally; only `wrangler deploy` needs the real id.

- [ ] **Step 4: Create `vitest.config.ts`**

```ts
import { defineWorkersConfig } from '@cloudflare/vitest-pool-workers/config';

export default defineWorkersConfig({
  test: {
    poolOptions: {
      workers: {
        wrangler: { configPath: './wrangler.toml' },
      },
    },
  },
});
```

- [ ] **Step 5: Create a minimal `src/index.ts`**

```ts
export interface Env {
  CODES: KVNamespace;
  RESEND_API_KEY: string;
  GITHUB_ADMIN_TOKEN: string;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    if (request.method === 'GET' && url.pathname === '/health') {
      return new Response('ok', { status: 200 });
    }
    return new Response('Not found', { status: 404 });
  },
};
```

- [ ] **Step 6: Write the failing test**

```ts
// access-worker/test/index.test.ts
import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import worker from '../src/index';

describe('worker fetch handler', () => {
  it('responds 200 on GET /health', async () => {
    const res = await worker.fetch(new Request('https://worker.test/health'), env);
    expect(res.status).toBe(200);
    expect(await res.text()).toBe('ok');
  });

  it('responds 404 for unknown routes', async () => {
    const res = await worker.fetch(new Request('https://worker.test/nope'), env);
    expect(res.status).toBe(404);
  });
});
```

- [ ] **Step 7: Install dependencies and run the test**

Run (from `access-worker/`): `npm install && npm test`
Expected: both tests PASS.

- [ ] **Step 8: Commit**

```bash
cd access-worker
git add package.json tsconfig.json wrangler.toml vitest.config.ts src/index.ts test/index.test.ts package-lock.json
git commit -m "chore: scaffold access-worker Cloudflare Worker project"
```

---

### Task 2: Email validation + verification codes (`codes.ts`)

**Files:**
- Create: `access-worker/src/codes.ts`
- Test: `access-worker/test/codes.test.ts`

**Interfaces:**
- Produces: `isValidMclairEmail(email: string): boolean`, `generateCode(): string` (6-digit numeric string), `storeCode(kv: KVNamespace, email: string, code: string): Promise<void>`, `verifyAndConsumeCode(kv: KVNamespace, email: string, code: string): Promise<boolean>` — all consumed directly by `index.ts` in Task 6.
- Consumes: nothing from other tasks (only the ambient `KVNamespace` type from `@cloudflare/workers-types`, already available from Task 1's tsconfig).

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/codes.test.ts
import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import { isValidMclairEmail, generateCode, storeCode, verifyAndConsumeCode } from '../src/codes';

describe('isValidMclairEmail', () => {
  it('accepts a valid mclair.com.br address', () => {
    expect(isValidMclairEmail('kelly@mclair.com.br')).toBe(true);
  });

  it('is case-insensitive on the domain', () => {
    expect(isValidMclairEmail('kelly@MCLAIR.COM.BR')).toBe(true);
  });

  it('rejects other domains', () => {
    expect(isValidMclairEmail('kelly@gmail.com')).toBe(false);
  });

  it('rejects malformed input', () => {
    expect(isValidMclairEmail('not-an-email')).toBe(false);
  });
});

describe('generateCode', () => {
  it('returns a 6-digit numeric string', () => {
    expect(generateCode()).toMatch(/^\d{6}$/);
  });
});

describe('storeCode / verifyAndConsumeCode', () => {
  it('verifies a code that was just stored', async () => {
    await storeCode(env.CODES, 'kelly@mclair.com.br', '123456');
    expect(await verifyAndConsumeCode(env.CODES, 'kelly@mclair.com.br', '123456')).toBe(true);
  });

  it('rejects a wrong code', async () => {
    await storeCode(env.CODES, 'kelly2@mclair.com.br', '123456');
    expect(await verifyAndConsumeCode(env.CODES, 'kelly2@mclair.com.br', '999999')).toBe(false);
  });

  it('is single-use — the same code cannot be verified twice', async () => {
    await storeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    const first = await verifyAndConsumeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    const second = await verifyAndConsumeCode(env.CODES, 'kelly3@mclair.com.br', '123456');
    expect(first).toBe(true);
    expect(second).toBe(false);
  });

  it('rejects when no code was ever stored for that email', async () => {
    expect(await verifyAndConsumeCode(env.CODES, 'nunca-pediu@mclair.com.br', '123456')).toBe(false);
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm test -- codes.test.ts`
Expected: FAIL — `codes.ts` does not exist yet.

- [ ] **Step 3: Write `src/codes.ts`**

```ts
export function isValidMclairEmail(email: string): boolean {
  return /^[^\s@]+@mclair\.com\.br$/i.test(email.trim());
}

export function generateCode(): string {
  const n = (crypto.getRandomValues(new Uint32Array(1))[0] % 900000) + 100000;
  return String(n);
}

const CODE_TTL_SECONDS = 15 * 60;

export async function storeCode(kv: KVNamespace, email: string, code: string): Promise<void> {
  await kv.put(`code:${email.toLowerCase()}`, code, { expirationTtl: CODE_TTL_SECONDS });
}

export async function verifyAndConsumeCode(
  kv: KVNamespace,
  email: string,
  code: string
): Promise<boolean> {
  const key = `code:${email.toLowerCase()}`;
  const stored = await kv.get(key);
  if (stored === null || stored !== code) return false;
  await kv.delete(key);
  return true;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm test -- codes.test.ts`
Expected: PASS, all 8 tests.

- [ ] **Step 5: Commit**

```bash
cd access-worker
git add src/codes.ts test/codes.test.ts
git commit -m "feat: add email validation and verification code storage"
```

---

### Task 3: Rate limiting (`ratelimit.ts`)

**Files:**
- Create: `access-worker/src/ratelimit.ts`
- Test: `access-worker/test/ratelimit.test.ts`

**Interfaces:**
- Produces: `isRateLimited(kv: KVNamespace, email: string): Promise<boolean>` — returns `true` when the caller should be blocked. Consumed directly by `index.ts` in Task 6.
- Consumes: nothing from other tasks.

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/ratelimit.test.ts
import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import { isRateLimited } from '../src/ratelimit';

describe('isRateLimited', () => {
  it('allows the first 3 requests for an email', async () => {
    const email = 'novato@mclair.com.br';
    expect(await isRateLimited(env.CODES, email)).toBe(false);
    expect(await isRateLimited(env.CODES, email)).toBe(false);
    expect(await isRateLimited(env.CODES, email)).toBe(false);
  });

  it('blocks the 4th request within the window', async () => {
    const email = 'insistente@mclair.com.br';
    await isRateLimited(env.CODES, email);
    await isRateLimited(env.CODES, email);
    await isRateLimited(env.CODES, email);
    expect(await isRateLimited(env.CODES, email)).toBe(true);
  });

  it('tracks different emails independently', async () => {
    const emailA = 'pessoa-a@mclair.com.br';
    const emailB = 'pessoa-b@mclair.com.br';
    await isRateLimited(env.CODES, emailA);
    await isRateLimited(env.CODES, emailA);
    await isRateLimited(env.CODES, emailA);
    expect(await isRateLimited(env.CODES, emailB)).toBe(false);
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm test -- ratelimit.test.ts`
Expected: FAIL — `ratelimit.ts` does not exist yet.

- [ ] **Step 3: Write `src/ratelimit.ts`**

```ts
const WINDOW_SECONDS = 60 * 60;
const MAX_REQUESTS = 3;

export async function isRateLimited(kv: KVNamespace, email: string): Promise<boolean> {
  const key = `ratelimit:${email.toLowerCase()}`;
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= MAX_REQUESTS) return true;
  await kv.put(key, String(count + 1), { expirationTtl: WINDOW_SECONDS });
  return false;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm test -- ratelimit.test.ts`
Expected: PASS, all 3 tests.

- [ ] **Step 5: Commit**

```bash
cd access-worker
git add src/ratelimit.ts test/ratelimit.test.ts
git commit -m "feat: add per-email rate limiting for code requests"
```

---

### Task 4: Resend email sending (`email.ts`)

**Files:**
- Create: `access-worker/src/email.ts`
- Test: `access-worker/test/email.test.ts`

**Interfaces:**
- Produces: `sendVerificationCode(apiKey: string, toEmail: string, code: string): Promise<boolean>` — consumed directly by `index.ts` in Task 6.
- Consumes: nothing from other tasks. Calls the real Resend REST API (`https://api.resend.com/emails`) via global `fetch`, which tests stub — no live network call happens in tests.

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/email.test.ts
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm test -- email.test.ts`
Expected: FAIL — `email.ts` does not exist yet.

- [ ] **Step 3: Write `src/email.ts`**

```ts
export async function sendVerificationCode(
  apiKey: string,
  toEmail: string,
  code: string
): Promise<boolean> {
  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: 'Painel Mclair <acesso@mclair.com.br>',
      to: toEmail,
      subject: `Seu código de acesso: ${code}`,
      html: `<p>Seu código de verificação é <strong>${code}</strong>. Ele expira em 15 minutos.</p>`,
    }),
  });
  return res.ok;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm test -- email.test.ts`
Expected: PASS, all 4 tests.

- [ ] **Step 5: Commit**

```bash
cd access-worker
git add src/email.ts test/email.test.ts
git commit -m "feat: add Resend verification code email sending"
```

---

### Task 5: GitHub collaborator API calls (`github.ts`)

**Files:**
- Create: `access-worker/src/github.ts`
- Test: `access-worker/test/github.test.ts`

**Interfaces:**
- Produces: `githubUserExists(username: string): Promise<boolean>`, `isAlreadyCollaborator(token: string, username: string): Promise<boolean>`, `addCollaborator(token: string, username: string): Promise<boolean>` — all consumed directly by `index.ts` in Task 6.
- Consumes: nothing from other tasks. Calls the real GitHub REST API via global `fetch`, which tests stub.

The repo is hardcoded as `sandruhill/mclair` (matches the spec — this Worker exists to manage access to exactly one repository, not a generic multi-repo tool).

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/github.test.ts
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm test -- github.test.ts`
Expected: FAIL — `github.ts` does not exist yet.

- [ ] **Step 3: Write `src/github.ts`**

```ts
const REPO_OWNER = 'sandruhill';
const REPO_NAME = 'mclair';

function githubHeaders(token: string): HeadersInit {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/vnd.github+json',
    'X-GitHub-Api-Version': '2022-11-28',
    'User-Agent': 'mclair-access-worker',
  };
}

export async function githubUserExists(username: string): Promise<boolean> {
  const res = await fetch(`https://api.github.com/users/${encodeURIComponent(username)}`, {
    headers: { 'User-Agent': 'mclair-access-worker' },
  });
  return res.status === 200;
}

export async function isAlreadyCollaborator(token: string, username: string): Promise<boolean> {
  const res = await fetch(
    `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/collaborators/${encodeURIComponent(username)}`,
    { headers: githubHeaders(token) }
  );
  return res.status === 204;
}

export async function addCollaborator(token: string, username: string): Promise<boolean> {
  const res = await fetch(
    `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/collaborators/${encodeURIComponent(username)}`,
    {
      method: 'PUT',
      headers: { ...githubHeaders(token), 'Content-Type': 'application/json' },
      body: JSON.stringify({ permission: 'push' }),
    }
  );
  return res.status === 201 || res.status === 204;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm test -- github.test.ts`
Expected: PASS, all 7 tests.

- [ ] **Step 5: Commit**

```bash
cd access-worker
git add src/github.ts test/github.test.ts
git commit -m "feat: add GitHub collaborator check/add API calls"
```

---

### Task 6: Signup page + wire up the full Worker

**Files:**
- Create: `access-worker/src/signup-page.ts`
- Modify: `access-worker/src/index.ts` (replace the Task 1 placeholder with the real routes)
- Modify: `access-worker/test/index.test.ts` (replace the Task 1 placeholder tests with real coverage)

**Interfaces:**
- Consumes: everything produced by Tasks 2–5 (`isValidMclairEmail`, `generateCode`, `storeCode`, `verifyAndConsumeCode` from `codes.ts`; `isRateLimited` from `ratelimit.ts`; `sendVerificationCode` from `email.ts`; `githubUserExists`, `isAlreadyCollaborator`, `addCollaborator` from `github.ts`).
- Produces: the final `fetch` handler — `GET /` (signup page), `POST /solicitar-codigo`, `POST /confirmar-codigo`. Nothing downstream consumes this; it's the last task before the manual deployment runbook.

- [ ] **Step 1: Write `src/signup-page.ts`**

```ts
export const SIGNUP_PAGE_HTML = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Acesso ao Painel Mclair</title>
<style>
  :root { --red: #C8102E; --ink: #1C1A17; --ink-3: #6B6560; --line: #E5E2DD; --cream: #FAFAF8; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--cream); color: var(--ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
  .wrap { max-width: 420px; margin: 0 auto; padding: 48px 20px; }
  h1 { font-size: 1.4rem; margin: 0 0 8px; }
  p.lead { color: var(--ink-3); font-size: 0.95rem; margin: 0 0 32px; }
  label { display: block; font-size: 0.85rem; font-weight: 600; margin: 16px 0 6px; }
  input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 1rem; }
  button { width: 100%; margin-top: 24px; padding: 14px; background: var(--red); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; }
  button:disabled { opacity: 0.5; cursor: default; }
  #msg { margin-top: 16px; font-size: 0.9rem; }
  #msg.error { color: var(--red); }
  #msg.ok { color: #2F7D4F; }
  #step2 { display: none; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Acesso ao Painel Mclair</h1>
  <p class="lead">Preenche com seu e-mail @mclair.com.br e seu usuário do GitHub pra liberar seu acesso ao painel de edição do site.</p>

  <form id="step1">
    <label for="email">E-mail</label>
    <input id="email" type="email" placeholder="seunome@mclair.com.br" required />
    <label for="github">Usuário do GitHub</label>
    <input id="github" type="text" placeholder="seu-usuario" required />
    <button type="submit">Enviar código</button>
  </form>

  <form id="step2">
    <label for="code">Código recebido por e-mail</label>
    <input id="code" type="text" inputmode="numeric" pattern="\\d{6}" maxlength="6" placeholder="000000" required />
    <button type="submit">Confirmar</button>
  </form>

  <div id="msg"></div>
</div>

<script>
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const msg = document.getElementById('msg');
  let email = '';
  let github = '';

  function showMsg(text, kind) {
    msg.textContent = text;
    msg.className = kind;
  }

  step1.addEventListener('submit', async (e) => {
    e.preventDefault();
    email = document.getElementById('email').value.trim();
    github = document.getElementById('github').value.trim();
    showMsg('Enviando...', '');
    const res = await fetch('/solicitar-codigo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, githubUsername: github }),
    });
    const data = await res.json();
    if (data.ok) {
      showMsg('Código enviado! Confere seu e-mail.', 'ok');
      step1.style.display = 'none';
      step2.style.display = 'block';
    } else {
      showMsg(data.error || 'Algo deu errado.', 'error');
    }
  });

  step2.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = document.getElementById('code').value.trim();
    showMsg('Confirmando...', '');
    const res = await fetch('/confirmar-codigo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, code, githubUsername: github }),
    });
    const data = await res.json();
    if (data.ok) {
      showMsg(data.message || 'Acesso liberado!', 'ok');
      step2.style.display = 'none';
    } else {
      showMsg(data.error || 'Algo deu errado.', 'error');
    }
  });
</script>
</body>
</html>`;
```

- [ ] **Step 2: Replace `test/index.test.ts` with full coverage (write the failing tests first)**

```ts
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
          const match = /(\\d{6})/.exec(body.html);
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
    expect(sentCode).toMatch(/^\\d{6}$/);

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
          const match = /(\\d{6})/.exec(body.html);
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
          const match = /(\\d{6})/.exec(body.html);
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
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `npm test -- index.test.ts`
Expected: FAIL — `index.ts` still only has the Task 1 placeholder, no `/solicitar-codigo` or `/confirmar-codigo` routes yet.

- [ ] **Step 4: Replace `src/index.ts` with the full router**

```ts
import { isValidMclairEmail, generateCode, storeCode, verifyAndConsumeCode } from './codes';
import { isRateLimited } from './ratelimit';
import { sendVerificationCode } from './email';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from './github';
import { SIGNUP_PAGE_HTML } from './signup-page';

export interface Env {
  CODES: KVNamespace;
  RESEND_API_KEY: string;
  GITHUB_ADMIN_TOKEN: string;
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(SIGNUP_PAGE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    if (request.method === 'POST' && url.pathname === '/solicitar-codigo') {
      const { email, githubUsername } = await request.json<{
        email?: string;
        githubUsername?: string;
      }>();

      if (!email || !isValidMclairEmail(email)) {
        return json({ ok: false, error: 'Precisa ser um e-mail @mclair.com.br.' });
      }
      if (!githubUsername || !githubUsername.trim()) {
        return json({ ok: false, error: 'Informe seu usuário do GitHub.' });
      }

      if (await isRateLimited(env.CODES, email)) {
        return json({
          ok: false,
          error: 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.',
        });
      }

      const code = generateCode();
      await storeCode(env.CODES, email, code);
      const sent = await sendVerificationCode(env.RESEND_API_KEY, email, code);
      if (!sent) {
        return json(
          { ok: false, error: 'Não consegui mandar o e-mail agora. Tenta de novo em alguns minutos.' },
          500
        );
      }

      return json({ ok: true });
    }

    if (request.method === 'POST' && url.pathname === '/confirmar-codigo') {
      const { email, code, githubUsername } = await request.json<{
        email?: string;
        code?: string;
        githubUsername?: string;
      }>();

      if (!email || !code || !githubUsername) {
        return json({ ok: false, error: 'Preencha todos os campos.' });
      }

      const valid = await verifyAndConsumeCode(env.CODES, email, code);
      if (!valid) {
        return json({ ok: false, error: 'Código inválido ou expirado. Pede um novo.' });
      }

      const userExists = await githubUserExists(githubUsername);
      if (!userExists) {
        return json({
          ok: false,
          error: 'Não encontrei esse usuário no GitHub. Confere se digitou certo.',
        });
      }

      const already = await isAlreadyCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
      if (already) {
        return json({ ok: true, message: 'Você já tem acesso! Pode ir direto pro /admin/.' });
      }

      const added = await addCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
      if (!added) {
        return json(
          { ok: false, error: 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.' },
          500
        );
      }

      return json({
        ok: true,
        message:
          'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
      });
    }

    return new Response('Not found', { status: 404 });
  },
};
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npm test`
Expected: PASS, all tests across every file (Tasks 1–6 combined).

- [ ] **Step 6: Manual smoke test with `wrangler dev`**

Run: `npm run dev` (from `access-worker/`), then in a browser open the printed local URL (e.g. `http://localhost:8787/`). Confirm the signup page renders with the two-step form. Submitting it will fail against real Resend/GitHub (no real secrets configured yet locally) — that's expected at this stage; the goal here is confirming the page renders and the JS wiring doesn't throw, not a live end-to-end run (that happens in Task 7, once Sandru has real secrets in place).

- [ ] **Step 7: Commit**

```bash
cd access-worker
git add src/signup-page.ts src/index.ts test/index.test.ts
git commit -m "feat: wire up signup page and full request/confirm flow"
```

---

### Task 7: Deployment runbook (Sandru — manual, not code)

**This task has no implementer subagent.** Every step here requires Sandru's own Cloudflare account, Resend account, DNS access for `mclair.com.br`, and GitHub account — none of which an agent can act on. Once Tasks 1–6 are built and reviewed, present this checklist to Sandru directly and let him work through it (or execute individual `wrangler`/`gh` commands together with him if he wants help driving them, but every account-creation and credential-generation click is his).

- [ ] **Step 1: Cloudflare account + Wrangler login**

If Sandru doesn't already have a Cloudflare account, create one free at `dash.cloudflare.com/sign-up`. Then, from `access-worker/`:

```bash
npx wrangler login
```

This opens a browser to authorize the Wrangler CLI against his Cloudflare account.

- [ ] **Step 2: Create the real KV namespace**

```bash
npx wrangler kv namespace create CODES
```

This prints an `id`. Replace both `REPLACE_WITH_REAL_KV_ID` placeholders in `access-worker/wrangler.toml` with that id, then commit:

```bash
git add access-worker/wrangler.toml
git commit -m "chore: set real Cloudflare KV namespace id"
```

- [ ] **Step 3: Resend account + domain verification**

Create a free account at `resend.com`. Add `mclair.com.br` (or a subdomain like `mail.mclair.com.br`, if preferred, to keep it separate from any existing mail setup) as a sending domain. Resend shows the exact DNS records (SPF, DKIM) to add — add them wherever `mclair.com.br`'s DNS is managed. Domain verification can take a few minutes to a few hours to propagate. Once verified, generate a Resend API key from the dashboard.

- [ ] **Step 4: GitHub fine-grained Personal Access Token**

At `github.com/settings/personal-access-tokens/new`:
- Token name: something identifiable, e.g. `mclair-access-worker`.
- Resource owner: `sandruhill`.
- Repository access: "Only select repositories" → `mclair`.
- Permissions: under "Repository permissions," set **Administration** to **Read and write** (this is the permission that covers adding collaborators). Leave everything else at its default (No access), following the global constraint of least-privilege scoping.
- Generate the token and copy it immediately (GitHub only shows it once).

- [ ] **Step 5: Set the Worker secrets**

From `access-worker/`:

```bash
npx wrangler secret put RESEND_API_KEY
# paste the Resend API key from Step 3 when prompted

npx wrangler secret put GITHUB_ADMIN_TOKEN
# paste the GitHub fine-grained PAT from Step 4 when prompted
```

- [ ] **Step 6: Deploy**

```bash
npm run deploy
```

Wrangler prints the live URL (something like `https://mclair-access-worker.<subdomain>.workers.dev`).

- [ ] **Step 7: End-to-end smoke test**

Open the deployed URL, fill in a real `@mclair.com.br` email (Sandru's own, for this first test) and a real GitHub username, submit, confirm the code arrives by email within a minute or two, enter it, and confirm the success message appears. Check `github.com/sandruhill/mclair/settings/access` to confirm the invite was actually created. Accept the invite from the test GitHub account and confirm `/admin/` login still works via "Sign In Using Access Token" as before.

- [ ] **Step 8: Point staff at the new URL**

Once confirmed working, this URL replaces "peça pro Sandru te adicionar como colaborador" in Part 1 of the published admin manual — flag this back to whoever's continuing the work so the manual gets updated (tracked as a follow-up in the design spec, not part of this plan's scope).
