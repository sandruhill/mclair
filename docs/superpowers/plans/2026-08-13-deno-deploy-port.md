# Port access-worker to Deno Deploy — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Task 6 is not a normal implementation task** — it's a manual runbook only the human (Sandru) can execute (Deno Deploy account, connecting the GitHub repo, setting secrets). Do not dispatch an implementer subagent for it; present it to the human directly once Tasks 1–5 are reviewed.

**Goal:** Replace `access-worker/`'s Cloudflare Workers implementation with an equivalent Deno Deploy implementation — same routes, same security behavior, same exported module shapes — because the human is blocked on Cloudflare account verification by a credit-card issue, and Deno Deploy's individual free tier needs no card.

**Architecture:** Same 6-module structure as the Cloudflare version, in the same directory (this replaces it in place, not a parallel build). The only things that change are: KV storage (`Deno.Kv` instead of Cloudflare `KVNamespace`, with real atomic check-and-set instead of the Cloudflare version's accepted non-atomic read-then-write), the HTTP entry point (`Deno.serve` instead of a Workers `fetch` export), and how secrets are read (`Deno.env.get` instead of an `env` parameter). `email.ts`, `github.ts`, and `signup-page.ts` are pure `fetch`-based or static and port with zero code changes.

**Tech Stack:** Deno (built-in test runner, built-in KV, built-in TypeScript — no `npm install`, no bundler, no `tsconfig.json`). Tests use `jsr:@std/assert` for assertions and direct `globalThis.fetch` reassignment for mocking outbound calls (Deno's global `fetch` is a plain reassignable binding — no separate mocking library needed).

## Global Constraints

- Do not change any security-relevant constant during the port: 6-digit codes, 900-second (15-minute) code TTL, 5 verify-attempts per code before force-deletion, 3 code-requests per email per hour, 20 requests per IP per day (day-keyed), 50 emails per day global cap (checked before per-email limits, incremented only after a real Resend send succeeds), `@mclair.com.br`-only signup, GitHub username existence checked only after a code match (never before — this is what closes the token-drain issue the Cloudflare version's review caught).
- No new npm-style dependencies. Deno's standard library (`jsr:@std/*`) is fine; nothing else.
- `email.ts`, `github.ts`, `signup-page.ts` port with the exact same exported function signatures and behavior as the current Cloudflare version — copy their logic, only remove anything Cloudflare-specific (there isn't any; these three files don't import `KVNamespace` or reference `env`).
- All user-facing error/success messages stay in Brazilian Portuguese, verbatim.
- `--unstable-kv` is required on every `deno run`/`deno test`/`deno deploy` invocation — Deno KV is still an unstable API as of this Deno version.

---

## File Structure

```
access-worker/
  deno.json              — tasks (dev, test), replaces package.json/wrangler.toml/tsconfig.json
  src/
    kv.ts                 — Deno.Kv wrapper: openKv(), and shared atomic
                             counter helpers (checkAndIncrement, currentCount,
                             increment) used by both codes.ts and ratelimit.ts
    codes.ts                — ported: same 6 exports, Deno.Kv underneath
    ratelimit.ts              — ported: same 4 exports, Deno.Kv underneath
    email.ts                    — copied verbatim, zero changes
    github.ts                     — copied verbatim, zero changes
    signup-page.ts                  — copied verbatim, zero changes
    main.ts                          — replaces index.ts: exports a plain
                                       `handler(request: Request): Promise<Response>`
                                       function (so tests can call it directly),
                                       plus a `Deno.serve(handler)` call
  test/
    kv.test.ts
    codes.test.ts
    ratelimit.test.ts
    email.test.ts
    github.test.ts
    main.test.ts
```

The old Cloudflare files (`package.json`, `tsconfig.json`, `wrangler.toml`, `vitest.config.ts`, `index.ts`, `index.test.ts`, `node_modules/`, `package-lock.json`) are deleted as part of this port — this supersedes them, it doesn't sit alongside them.

---

### Task 1: Deno project setup + KV wrapper with atomic counter helpers

**Files:**
- Delete: `access-worker/package.json`, `access-worker/package-lock.json`, `access-worker/tsconfig.json`, `access-worker/wrangler.toml`, `access-worker/vitest.config.ts`, `access-worker/.gitignore` (replaced by a Deno-appropriate one in this task)
- Create: `access-worker/deno.json`
- Create: `access-worker/.gitignore`
- Create: `access-worker/src/kv.ts`
- Test: `access-worker/test/kv.test.ts`

**Interfaces:**
- Produces: `openKv(path？: string): Promise<Deno.Kv>`, `checkAndIncrement(kv: Deno.Kv, key: Deno.KvKey, max: number, ttlMs: number): Promise<boolean>` (returns true if allowed+incremented, false if at/over cap), `currentCount(kv: Deno.Kv, key: Deno.KvKey): Promise<number>`, `increment(kv: Deno.Kv, key: Deno.KvKey, ttlMs: number): Promise<void>` — all consumed by Tasks 2 and 3.

This task also deletes the old Cloudflare/npm scaffolding so the directory doesn't end up with both toolchains half-present.

- [ ] **Step 1: Remove the old Cloudflare/npm scaffolding**

```bash
cd /Users/macbook/mclair/access-worker
rm -f package.json package-lock.json tsconfig.json wrangler.toml vitest.config.ts .gitignore
rm -rf node_modules
```

- [ ] **Step 2: Create `deno.json`**

```json
{
  "tasks": {
    "dev": "deno run --allow-net --allow-env --unstable-kv --watch src/main.ts",
    "test": "deno test --allow-net --allow-env --unstable-kv"
  }
}
```

- [ ] **Step 3: Create `.gitignore`**

```
.env
```

(Deno has no `node_modules`/lockfile-as-build-artifact to ignore — dependencies are fetched and cached outside the project directory.)

- [ ] **Step 4: Write the failing tests**

```ts
// access-worker/test/kv.test.ts
import { assertEquals } from 'jsr:@std/assert';
import { openKv, checkAndIncrement, currentCount, increment } from '../src/kv.ts';

Deno.test('checkAndIncrement allows requests under the cap', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'a'];
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), true);
  kv.close();
});

Deno.test('checkAndIncrement blocks once the cap is reached', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'b'];
  await checkAndIncrement(kv, key, 3, 60_000);
  await checkAndIncrement(kv, key, 3, 60_000);
  await checkAndIncrement(kv, key, 3, 60_000);
  assertEquals(await checkAndIncrement(kv, key, 3, 60_000), false);
  kv.close();
});

Deno.test('checkAndIncrement tracks different keys independently', async () => {
  const kv = await openKv(':memory:');
  const keyA: Deno.KvKey = ['test', 'c'];
  const keyB: Deno.KvKey = ['test', 'd'];
  await checkAndIncrement(kv, keyA, 1, 60_000);
  assertEquals(await checkAndIncrement(kv, keyA, 1, 60_000), false);
  assertEquals(await checkAndIncrement(kv, keyB, 1, 60_000), true);
  kv.close();
});

Deno.test('currentCount is 0 for an unset key and reflects checkAndIncrement calls', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'e'];
  assertEquals(await currentCount(kv, key), 0);
  await checkAndIncrement(kv, key, 5, 60_000);
  await checkAndIncrement(kv, key, 5, 60_000);
  assertEquals(await currentCount(kv, key), 2);
  kv.close();
});

Deno.test('increment raises the count without checking a cap', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'f'];
  await increment(kv, key, 60_000);
  await increment(kv, key, 60_000);
  await increment(kv, key, 60_000);
  assertEquals(await currentCount(kv, key), 3);
  kv.close();
});

Deno.test('a value written with an expireIn eventually disappears from currentCount', async () => {
  const kv = await openKv(':memory:');
  const key: Deno.KvKey = ['test', 'g'];
  // 1ms TTL — long enough to write, short enough to have expired by the time we read.
  await increment(kv, key, 1);
  await new Promise((resolve) => setTimeout(resolve, 50));
  assertEquals(await currentCount(kv, key), 0);
  kv.close();
});
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `deno task test kv.test.ts` (from `access-worker/`)
Expected: FAIL — `../src/kv.ts` does not exist yet.

- [ ] **Step 6: Write `src/kv.ts`**

```ts
export function openKv(path?: string): Promise<Deno.Kv> {
  return Deno.openKv(path);
}

// Atomically writes `value` at `key`, but only if nobody else has written to
// `key` since `entry` was read (Deno KV's optimistic-concurrency check).
// Returns whether the write actually happened.
async function tryCommit(
  kv: Deno.Kv,
  entry: Deno.KvEntryMaybe<number>,
  key: Deno.KvKey,
  value: number,
  ttlMs: number
): Promise<boolean> {
  const res = await kv.atomic().check(entry).set(key, value, { expireIn: ttlMs }).commit();
  return res.ok;
}

// Checks a counter against `max` and increments it if under, atomically.
// Retries once on a lost race (two requests reading the same stale count at
// the same instant); if both attempts lose the race, fails closed — treats
// it as "blocked" rather than risk letting two racing requests both through.
// This is the shared building block behind every rate/attempt limiter in
// codes.ts and ratelimit.ts.
export async function checkAndIncrement(
  kv: Deno.Kv,
  key: Deno.KvKey,
  max: number,
  ttlMs: number
): Promise<boolean> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const entry = await kv.get<number>(key);
    const count = entry.value ?? 0;
    if (count >= max) return false;
    if (await tryCommit(kv, entry, key, count + 1, ttlMs)) return true;
  }
  return false;
}

export async function currentCount(kv: Deno.Kv, key: Deno.KvKey): Promise<number> {
  const entry = await kv.get<number>(key);
  return entry.value ?? 0;
}

// Increments a counter unconditionally (no cap check — the caller already
// checked one separately, e.g. the daily email cap is checked before sending
// and incremented only after a real send succeeds). Retried once on a lost
// race; if both attempts lose, under-reports by one rather than throwing —
// an acceptable soft-limit tradeoff for a counter that exists to bound abuse,
// not to bill anyone precisely.
export async function increment(kv: Deno.Kv, key: Deno.KvKey, ttlMs: number): Promise<void> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const entry = await kv.get<number>(key);
    const count = entry.value ?? 0;
    if (await tryCommit(kv, entry, key, count + 1, ttlMs)) return;
  }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `deno task test kv.test.ts`
Expected: PASS, all 6 tests.

- [ ] **Step 8: Commit**

```bash
cd /Users/macbook/mclair
git add access-worker/deno.json access-worker/.gitignore access-worker/src/kv.ts access-worker/test/kv.test.ts \
        access-worker/package.json access-worker/package-lock.json access-worker/tsconfig.json \
        access-worker/wrangler.toml access-worker/vitest.config.ts
git commit -m "chore: replace Cloudflare/npm scaffolding with Deno project + KV wrapper"
```

(The `git add` includes the deleted files so their removal is captured in this commit — `git add` stages deletions too.)

---

### Task 2: Port `codes.ts` to Deno KV

**Files:**
- Delete: `access-worker/test/codes.test.ts` (old, vitest-based — replaced in this task)
- Modify: `access-worker/src/codes.ts` (full rewrite in place)
- Create: `access-worker/test/codes.test.ts`

**Interfaces:**
- Consumes: `checkAndIncrement` from `src/kv.ts` (Task 1).
- Produces: same 6 exports as the Cloudflare version, now typed against `Deno.Kv` instead of `KVNamespace` — `isValidMclairEmail(email: string): boolean`, `generateCode(): string`, `storeCode(kv: Deno.Kv, email: string, code: string): Promise<void>`, `codeMatches(kv: Deno.Kv, email: string, code: string): Promise<boolean>`, `consumeCode(kv: Deno.Kv, email: string): Promise<void>`, `isVerifyAttemptLimited(kv: Deno.Kv, email: string): Promise<boolean>`. Consumed by Task 5.

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/codes.test.ts
import { assertEquals } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  codeMatches,
  consumeCode,
  isVerifyAttemptLimited,
} from '../src/codes.ts';

Deno.test('isValidMclairEmail accepts a valid mclair.com.br address', () => {
  assertEquals(isValidMclairEmail('kelly@mclair.com.br'), true);
});

Deno.test('isValidMclairEmail is case-insensitive on the domain', () => {
  assertEquals(isValidMclairEmail('kelly@MCLAIR.COM.BR'), true);
});

Deno.test('isValidMclairEmail rejects other domains', () => {
  assertEquals(isValidMclairEmail('kelly@gmail.com'), false);
});

Deno.test('isValidMclairEmail rejects malformed input', () => {
  assertEquals(isValidMclairEmail('not-an-email'), false);
});

Deno.test('generateCode returns a 6-digit numeric string', () => {
  assertEquals(/^\d{6}$/.test(generateCode()), true);
});

Deno.test('codeMatches confirms a code that was just stored', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly@mclair.com.br', '123456'), true);
  kv.close();
});

Deno.test('codeMatches rejects a wrong code', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly2@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly2@mclair.com.br', '999999'), false);
  kv.close();
});

Deno.test('codeMatches does not consume the code — it stays valid after checking', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly3@mclair.com.br', '123456');
  await codeMatches(kv, 'kelly3@mclair.com.br', '123456');
  assertEquals(await codeMatches(kv, 'kelly3@mclair.com.br', '123456'), true);
  kv.close();
});

Deno.test('consumeCode makes a subsequent codeMatches call fail', async () => {
  const kv = await openKv(':memory:');
  await storeCode(kv, 'kelly4@mclair.com.br', '123456');
  await consumeCode(kv, 'kelly4@mclair.com.br');
  assertEquals(await codeMatches(kv, 'kelly4@mclair.com.br', '123456'), false);
  kv.close();
});

Deno.test('codeMatches rejects when no code was ever stored for that email', async () => {
  const kv = await openKv(':memory:');
  assertEquals(await codeMatches(kv, 'nunca-pediu@mclair.com.br', '123456'), false);
  kv.close();
});

Deno.test('isVerifyAttemptLimited allows the first 5 attempts', async () => {
  const kv = await openKv(':memory:');
  const email = 'tentativas@mclair.com.br';
  for (let i = 0; i < 5; i++) {
    assertEquals(await isVerifyAttemptLimited(kv, email), false);
  }
  kv.close();
});

Deno.test('isVerifyAttemptLimited blocks the 6th attempt and force-deletes the code', async () => {
  const kv = await openKv(':memory:');
  const email = 'travado@mclair.com.br';
  await storeCode(kv, email, '123456');
  for (let i = 0; i < 5; i++) {
    await isVerifyAttemptLimited(kv, email);
  }
  assertEquals(await isVerifyAttemptLimited(kv, email), true);
  assertEquals(await codeMatches(kv, email, '123456'), false);
  kv.close();
});

Deno.test('storeCode resets the attempt counter so a fresh code gives a fresh budget', async () => {
  const kv = await openKv(':memory:');
  const email = 'segunda-chance@mclair.com.br';
  await storeCode(kv, email, '111111');
  for (let i = 0; i < 5; i++) {
    await isVerifyAttemptLimited(kv, email);
  }
  assertEquals(await isVerifyAttemptLimited(kv, email), true); // capped
  await storeCode(kv, email, '222222'); // fresh code issued
  assertEquals(await isVerifyAttemptLimited(kv, email), false); // budget reset
  kv.close();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno task test codes.test.ts`
Expected: FAIL — `src/codes.ts` still has the old `KVNamespace`-typed, `verifyAndConsumeCode`-shaped implementation, so the new imports (`codeMatches`, `consumeCode`, `isVerifyAttemptLimited` with a `Deno.Kv` first argument) won't match.

- [ ] **Step 3: Rewrite `src/codes.ts`**

```ts
import { checkAndIncrement } from './kv.ts';

export function isValidMclairEmail(email: string): boolean {
  return /^[^\s@]+@mclair\.com\.br$/i.test(email.trim());
}

export function generateCode(): string {
  const n = (crypto.getRandomValues(new Uint32Array(1))[0] % 900000) + 100000;
  return String(n);
}

const CODE_TTL_MS = 15 * 60 * 1000;
const MAX_VERIFY_ATTEMPTS = 5;

function codeKey(email: string): Deno.KvKey {
  return ['code', email.toLowerCase()];
}

function verifyAttemptsKey(email: string): Deno.KvKey {
  return ['verify-attempts', email.toLowerCase()];
}

export async function storeCode(kv: Deno.Kv, email: string, code: string): Promise<void> {
  await kv.set(codeKey(email), code, { expireIn: CODE_TTL_MS });
  // A fresh code means a fresh set of guesses — otherwise someone who legitimately
  // burns through the verify-attempt cap and requests a new code (as instructed)
  // would stay locked out until the old attempt counter's TTL expires on its own.
  await kv.delete(verifyAttemptsKey(email));
}

// Checking the code and consuming it are separate steps, so a caller can
// validate the code without burning it until every other step of the flow
// (e.g. the GitHub username lookup) has also succeeded.
export async function codeMatches(kv: Deno.Kv, email: string, code: string): Promise<boolean> {
  const entry = await kv.get<string>(codeKey(email));
  return entry.value !== null && entry.value === code;
}

export async function consumeCode(kv: Deno.Kv, email: string): Promise<void> {
  await kv.delete(codeKey(email));
}

// Called once per confirm request, before the submitted code is even looked
// at: if this email already has MAX_VERIFY_ATTEMPTS on the books, block
// immediately and force-delete the stored code so a fresh one is required.
export async function isVerifyAttemptLimited(kv: Deno.Kv, email: string): Promise<boolean> {
  const allowed = await checkAndIncrement(kv, verifyAttemptsKey(email), MAX_VERIFY_ATTEMPTS, CODE_TTL_MS);
  if (!allowed) {
    await kv.delete(codeKey(email));
    return true;
  }
  return false;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno task test codes.test.ts`
Expected: PASS, all 13 tests.

- [ ] **Step 5: Commit**

```bash
cd /Users/macbook/mclair
git add access-worker/src/codes.ts access-worker/test/codes.test.ts
git commit -m "feat: port codes.ts to Deno KV"
```

---

### Task 3: Port `ratelimit.ts` to Deno KV

**Files:**
- Delete: `access-worker/test/ratelimit.test.ts` (old, vitest-based)
- Modify: `access-worker/src/ratelimit.ts` (full rewrite in place)
- Create: `access-worker/test/ratelimit.test.ts`

**Interfaces:**
- Consumes: `checkAndIncrement`, `currentCount`, `increment` from `src/kv.ts` (Task 1).
- Produces: `isRateLimited(kv: Deno.Kv, email: string): Promise<boolean>`, `isDailyEmailCapReached(kv: Deno.Kv): Promise<boolean>`, `incrementDailyEmailCount(kv: Deno.Kv): Promise<void>`, `isIpRateLimited(kv: Deno.Kv, ip: string): Promise<boolean>` — consumed by Task 5.

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/ratelimit.test.ts
import { assertEquals } from 'jsr:@std/assert';
import { openKv } from '../src/kv.ts';
import {
  isRateLimited,
  isDailyEmailCapReached,
  incrementDailyEmailCount,
  isIpRateLimited,
} from '../src/ratelimit.ts';

Deno.test('isRateLimited allows the first 3 requests for an email', async () => {
  const kv = await openKv(':memory:');
  const email = 'novato@mclair.com.br';
  assertEquals(await isRateLimited(kv, email), false);
  assertEquals(await isRateLimited(kv, email), false);
  assertEquals(await isRateLimited(kv, email), false);
  kv.close();
});

Deno.test('isRateLimited blocks the 4th request within the window', async () => {
  const kv = await openKv(':memory:');
  const email = 'insistente@mclair.com.br';
  await isRateLimited(kv, email);
  await isRateLimited(kv, email);
  await isRateLimited(kv, email);
  assertEquals(await isRateLimited(kv, email), true);
  kv.close();
});

Deno.test('isRateLimited tracks different emails independently', async () => {
  const kv = await openKv(':memory:');
  const emailA = 'pessoa-a@mclair.com.br';
  const emailB = 'pessoa-b@mclair.com.br';
  await isRateLimited(kv, emailA);
  await isRateLimited(kv, emailA);
  await isRateLimited(kv, emailA);
  assertEquals(await isRateLimited(kv, emailB), false);
  kv.close();
});

Deno.test('isDailyEmailCapReached is false with no sends and does not itself increment', async () => {
  const kv = await openKv(':memory:');
  assertEquals(await isDailyEmailCapReached(kv), false);
  assertEquals(await isDailyEmailCapReached(kv), false); // calling it twice doesn't count as 2 sends
  kv.close();
});

Deno.test('incrementDailyEmailCount raises the count; isDailyEmailCapReached trips at 50', async () => {
  const kv = await openKv(':memory:');
  for (let i = 0; i < 50; i++) {
    await incrementDailyEmailCount(kv);
  }
  assertEquals(await isDailyEmailCapReached(kv), true);
  kv.close();
});

Deno.test('isIpRateLimited allows the first 20 requests for an IP', async () => {
  const kv = await openKv(':memory:');
  const ip = '203.0.113.10';
  for (let i = 0; i < 20; i++) {
    assertEquals(await isIpRateLimited(kv, ip), false);
  }
  kv.close();
});

Deno.test('isIpRateLimited blocks the 21st request', async () => {
  const kv = await openKv(':memory:');
  const ip = '203.0.113.11';
  for (let i = 0; i < 20; i++) {
    await isIpRateLimited(kv, ip);
  }
  assertEquals(await isIpRateLimited(kv, ip), true);
  kv.close();
});

Deno.test('isIpRateLimited tracks different IPs independently', async () => {
  const kv = await openKv(':memory:');
  const ipA = '203.0.113.20';
  const ipB = '203.0.113.21';
  for (let i = 0; i < 20; i++) {
    await isIpRateLimited(kv, ipA);
  }
  assertEquals(await isIpRateLimited(kv, ipA), true);
  assertEquals(await isIpRateLimited(kv, ipB), false);
  kv.close();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno task test ratelimit.test.ts`
Expected: FAIL — `src/ratelimit.ts` still has the old `KVNamespace`-typed implementation.

- [ ] **Step 3: Rewrite `src/ratelimit.ts`**

```ts
import { checkAndIncrement, currentCount, increment } from './kv.ts';

const WINDOW_MS = 60 * 60 * 1000;
const MAX_REQUESTS = 3;

export function isRateLimited(kv: Deno.Kv, email: string): Promise<boolean> {
  return checkAndIncrement(kv, ['ratelimit', email.toLowerCase()], MAX_REQUESTS, WINDOW_MS).then(
    (allowed) => !allowed
  );
}

const DAY_MS = 24 * 60 * 60 * 1000;
const MAX_DAILY_EMAILS = 50; // Resend free tier is 100/day — leave headroom

function dailyEmailCountKey(): Deno.KvKey {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  return ['daily-email-count', today];
}

// Global cap across all users, not per-email — protects the account's shared
// daily Resend quota from being exhausted by (accidental or malicious) volume.
// Check-only: does NOT increment. Call incrementDailyEmailCount separately, and
// only after an email actually sends — otherwise a Resend failure/timeout still
// burns quota for an email nobody received.
export async function isDailyEmailCapReached(kv: Deno.Kv): Promise<boolean> {
  return (await currentCount(kv, dailyEmailCountKey())) >= MAX_DAILY_EMAILS;
}

export function incrementDailyEmailCount(kv: Deno.Kv): Promise<void> {
  return increment(kv, dailyEmailCountKey(), DAY_MS);
}

const IP_MAX_REQUESTS = 20;

function ipRateLimitKey(ip: string): Deno.KvKey {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  return ['ip-ratelimit', ip, today];
}

// Per-IP counter on top of the per-email and global-daily limits above — an anonymous
// caller only needs the @mclair.com.br domain suffix (not a real mailbox) to hit
// /solicitar-codigo or /confirmar-codigo, so without this a single source can rotate
// the email local-part to drain the shared daily cap alone. Day-keyed (not a rolling
// hourly window) so a patient single IP can't just wait out repeated TTL resets to
// eventually exhaust the 50/day global cap alone — exhausting it now genuinely needs
// at least 3 distinct source IPs cooperating within the same UTC day.
export function isIpRateLimited(kv: Deno.Kv, ip: string): Promise<boolean> {
  return checkAndIncrement(kv, ipRateLimitKey(ip), IP_MAX_REQUESTS, DAY_MS).then((allowed) => !allowed);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno task test ratelimit.test.ts`
Expected: PASS, all 8 tests.

- [ ] **Step 5: Commit**

```bash
cd /Users/macbook/mclair
git add access-worker/src/ratelimit.ts access-worker/test/ratelimit.test.ts
git commit -m "feat: port ratelimit.ts to Deno KV, with atomic check-and-increment"
```

---

### Task 4: Copy `email.ts`, `github.ts`, `signup-page.ts` verbatim + port their tests to Deno

**Files:**
- Modify: `access-worker/src/email.ts` (no logic changes — only re-verify it has zero Cloudflare-specific code, which it already does)
- Modify: `access-worker/src/github.ts` (same)
- Modify: `access-worker/src/signup-page.ts` (same)
- Delete: `access-worker/test/email.test.ts`, `access-worker/test/github.test.ts` (old, vitest-based)
- Create: `access-worker/test/email.test.ts`, `access-worker/test/github.test.ts`

**Interfaces:**
- Consumes: nothing from Tasks 1–3.
- Produces: `sendVerificationCode(apiKey: string, toEmail: string, code: string): Promise<boolean>`, `githubUserExists(token: string, username: string): Promise<boolean>`, `isAlreadyCollaborator(token: string, username: string): Promise<boolean>`, `addCollaborator(token: string, username: string): Promise<boolean>`, `SIGNUP_PAGE_HTML: string` — all consumed by Task 5.

These three source files need no logic changes — they're pure `fetch`-based (or, for `signup-page.ts`, a static string), and `fetch`/`AbortSignal`/`encodeURIComponent` are standard Web APIs available in Deno exactly as in Workers. Confirm `src/email.ts`, `src/github.ts`, and `src/signup-page.ts` still contain exactly this content (they should already, untouched since Task 4/5 of the original Cloudflare plan) — if anything differs, that's a sign something else touched them and is worth flagging, not silently overwriting.

`src/email.ts` (confirm unchanged):
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
    signal: AbortSignal.timeout(10_000),
  });
  return res.ok;
}
```

`src/github.ts` (confirm unchanged):
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

export async function githubUserExists(token: string, username: string): Promise<boolean> {
  const res = await fetch(`https://api.github.com/users/${encodeURIComponent(username)}`, {
    headers: githubHeaders(token),
    signal: AbortSignal.timeout(10_000),
  });
  return res.status === 200;
}

export async function isAlreadyCollaborator(token: string, username: string): Promise<boolean> {
  const res = await fetch(
    `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/collaborators/${encodeURIComponent(username)}`,
    { headers: githubHeaders(token), signal: AbortSignal.timeout(10_000) }
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
      signal: AbortSignal.timeout(10_000),
    }
  );
  return res.status === 201 || res.status === 204;
}
```

`src/signup-page.ts`: leave completely untouched (it's already platform-agnostic static content — do not even re-save the file if it's already correct, to avoid any accidental whitespace/encoding diff).

- [ ] **Step 1: Write the failing tests for `email.ts`**

```ts
// access-worker/test/email.test.ts
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
```

- [ ] **Step 2: Write the failing tests for `github.ts`**

```ts
// access-worker/test/github.test.ts
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
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `deno task test email.test.ts github.test.ts`
Expected: FAIL — imports reference `../src/email.ts`/`../src/github.ts` with a `.ts` extension and Deno-native test syntax, which the still-vitest-shaped files/imports don't satisfy until you confirm the source files (already correct) are in place; more concretely this will fail simply because `test/email.test.ts`/`test/github.test.ts` don't exist yet under these new contents.

- [ ] **Step 4: Confirm/write `src/email.ts` and `src/github.ts` exactly as shown above, leave `src/signup-page.ts` untouched**

If `src/email.ts` and `src/github.ts` already match the code blocks in this task's Interfaces section exactly, no edit is needed — just proceed. If they don't match (unexpected), write them to match exactly before continuing.

- [ ] **Step 5: Run tests to verify they pass**

Run: `deno task test email.test.ts github.test.ts`
Expected: PASS — 4 tests in `email.test.ts`, 8 tests in `github.test.ts`.

- [ ] **Step 6: Commit**

```bash
cd /Users/macbook/mclair
git add access-worker/src/email.ts access-worker/src/github.ts access-worker/src/signup-page.ts \
        access-worker/test/email.test.ts access-worker/test/github.test.ts
git commit -m "feat: port email.ts/github.ts tests to Deno; signup-page.ts unchanged"
```

---

### Task 5: `main.ts` — the Deno.serve entry point

**Files:**
- Delete: `access-worker/src/index.ts`, `access-worker/test/index.test.ts` (Cloudflare-shaped, replaced by this task)
- Create: `access-worker/src/main.ts`
- Create: `access-worker/test/main.test.ts`

**Interfaces:**
- Consumes: everything from Tasks 2–4 (`codes.ts`, `ratelimit.ts`, `email.ts`, `github.ts`, `signup-page.ts`'s exports, as listed in their own Interfaces sections).
- Produces: `handler(request: Request): Promise<Response>` (exported for tests to call directly, without needing a running server) plus a `Deno.serve(handler)` call for the real entry point. Nothing downstream consumes this — it's the last task before the manual deployment runbook.

The route logic mirrors the Cloudflare version's `index.ts` exactly (same checks, same order, same messages) — only the plumbing around it changes: `Deno.Kv` opened once at module scope instead of an `env.CODES` binding, secrets read via `Deno.env.get(...)`, and the client IP read from `X-Forwarded-For` (falling back to `X-Real-IP`, then `'unknown'`) instead of Cloudflare's `CF-Connecting-IP` header — Deno Deploy sits behind its own edge proxy and uses the `X-Forwarded-For` convention.

- [ ] **Step 1: Write the failing tests**

```ts
// access-worker/test/main.test.ts
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

Deno.test('blocks a 21st request from the same IP across both routes', async () => {
  const { kv, handler } = await freshHandler();
  const ip = '198.51.100.99';
  for (let i = 0; i < 20; i++) {
    await handler(postJson('/solicitar-codigo', { email: `pessoa${i}@mclair.com.br`, githubUsername: 'x' }, ip));
  }
  const res = await handler(postJson('/solicitar-codigo', { email: 'mais-uma@mclair.com.br', githubUsername: 'x' }, ip));
  const data = (await res.json()) as { ok: boolean; error: string };
  assertEquals(data.ok, false);
  assertStringIncludes(data.error, 'Muitas requisições');
  kv.close();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno task test main.test.ts`
Expected: FAIL — `../src/main.ts` does not exist yet.

- [ ] **Step 3: Write `src/main.ts`**

```ts
import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  codeMatches,
  consumeCode,
  isVerifyAttemptLimited,
} from './codes.ts';
import {
  isRateLimited,
  isDailyEmailCapReached,
  incrementDailyEmailCount,
  isIpRateLimited,
} from './ratelimit.ts';
import { sendVerificationCode } from './email.ts';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from './github.ts';
import { SIGNUP_PAGE_HTML } from './signup-page.ts';
import { openKv } from './kv.ts';

export interface Secrets {
  resendApiKey: string;
  githubAdminToken: string;
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const GENERIC_ERROR = { ok: false, error: 'Algo deu errado. Tenta de novo.' };
const IP_RATE_LIMITED_ERROR = {
  ok: false,
  error: 'Muitas requisições desse endereço. Tenta de novo mais tarde.',
};

function clientIp(request: Request): string {
  const forwarded = request.headers.get('x-forwarded-for');
  if (forwarded) {
    const first = forwarded.split(',')[0]?.trim();
    if (first) return first;
  }
  return request.headers.get('x-real-ip') ?? 'unknown';
}

async function handleSolicitarCodigo(
  request: Request,
  kv: Deno.Kv,
  secrets: Secrets,
  ip: string
): Promise<Response> {
  if (await isIpRateLimited(kv, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const { email: rawEmail, githubUsername: rawGithubUsername } = (await request.json()) as {
    email?: string;
    githubUsername?: string;
  };

  const email = (rawEmail ?? '').trim().toLowerCase();
  const githubUsername = (rawGithubUsername ?? '').trim();

  if (!email || !isValidMclairEmail(email)) {
    return json({ ok: false, error: 'Precisa ser um e-mail @mclair.com.br.' });
  }
  if (!githubUsername) {
    return json({ ok: false, error: 'Informe seu usuário do GitHub.' });
  }

  // Cheapest / most-global check first, so a request that's going to be refused
  // by the shared daily cap never burns one of this email's hourly issuance slots.
  if (await isDailyEmailCapReached(kv)) {
    return json({ ok: false, error: 'Tenta de novo mais tarde.' });
  }

  if (await isRateLimited(kv, email)) {
    return json({
      ok: false,
      error: 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.',
    });
  }

  const code = generateCode();
  await storeCode(kv, email, code);
  const sent = await sendVerificationCode(secrets.resendApiKey, email, code);
  if (!sent) {
    return json(
      { ok: false, error: 'Não consegui mandar o e-mail agora. Tenta de novo em alguns minutos.' },
      500
    );
  }
  // Only count against the shared daily quota once an email was actually sent —
  // a Resend failure above must not burn quota for a code nobody received.
  await incrementDailyEmailCount(kv);

  return json({ ok: true });
}

async function handleConfirmarCodigo(
  request: Request,
  kv: Deno.Kv,
  secrets: Secrets,
  ip: string
): Promise<Response> {
  if (await isIpRateLimited(kv, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const {
    email: rawEmail,
    code,
    githubUsername: rawGithubUsername,
  } = (await request.json()) as {
    email?: string;
    code?: string;
    githubUsername?: string;
  };

  const email = (rawEmail ?? '').trim().toLowerCase();
  const githubUsername = (rawGithubUsername ?? '').trim();

  if (!email || !code || !githubUsername) {
    return json({ ok: false, error: 'Preencha todos os campos.' });
  }

  if (await isVerifyAttemptLimited(kv, email)) {
    return json({ ok: false, error: 'Muitas tentativas. Pede um novo código.' });
  }

  // Check the code BEFORE touching the GitHub API — without this, anyone who knows
  // (or guesses) an @mclair.com.br local-part can drive githubUserExists with no
  // code at all, spending the admin token's shared rate-limit budget. The code is
  // not consumed yet, though: a subsequent username typo or a transient GitHub API
  // failure must not burn it, so the person can retry with the same code.
  const matches = await codeMatches(kv, email, code);
  if (!matches) {
    return json({ ok: false, error: 'Código inválido ou expirado. Pede um novo.' });
  }

  const userExists = await githubUserExists(secrets.githubAdminToken, githubUsername);
  if (!userExists) {
    return json({
      ok: false,
      error: 'Não encontrei esse usuário no GitHub. Confere se digitou certo.',
    });
  }

  const already = await isAlreadyCollaborator(secrets.githubAdminToken, githubUsername);
  if (already) {
    await consumeCode(kv, email);
    return json({ ok: true, message: 'Você já tem acesso! Pode ir direto pro /admin/.' });
  }

  const added = await addCollaborator(secrets.githubAdminToken, githubUsername);
  if (!added) {
    return json(
      { ok: false, error: 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.' },
      500
    );
  }

  await consumeCode(kv, email);
  return json({
    ok: true,
    message:
      'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
  });
}

export function makeHandler(kv: Deno.Kv, secrets: Secrets): (request: Request) => Promise<Response> {
  return async (request: Request): Promise<Response> => {
    const url = new URL(request.url);
    const ip = clientIp(request);

    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(SIGNUP_PAGE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    if (request.method === 'POST' && url.pathname === '/solicitar-codigo') {
      try {
        return await handleSolicitarCodigo(request, kv, secrets, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    if (request.method === 'POST' && url.pathname === '/confirmar-codigo') {
      try {
        return await handleConfirmarCodigo(request, kv, secrets, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    return new Response('Not found', { status: 404 });
  };
}

// Real entry point — not exercised by tests, which call makeHandler(...) directly
// against an in-memory KV instead of hitting this module-scope side effect.
if (import.meta.main) {
  const kv = await openKv();
  const secrets: Secrets = {
    resendApiKey: Deno.env.get('RESEND_API_KEY') ?? '',
    githubAdminToken: Deno.env.get('GITHUB_ADMIN_TOKEN') ?? '',
  };
  Deno.serve(makeHandler(kv, secrets));
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno task test main.test.ts`
Expected: PASS, all 10 tests.

- [ ] **Step 5: Run the full test suite**

Run: `deno task test` (from `access-worker/`, no filename filter — runs every `test/*.test.ts` file)
Expected: PASS, all tests across all 5 files (6 in `kv.test.ts` + 13 in `codes.test.ts` + 8 in `ratelimit.test.ts` + 4 in `email.test.ts` + 8 in `github.test.ts` + 10 in `main.test.ts` = 49 total).

- [ ] **Step 6: Manual smoke test with `deno task dev`**

Run: `deno task dev` (from `access-worker/`), then in a browser open `http://localhost:8000/`. Confirm the signup page renders with the two-step form (same page as the Cloudflare version — `signup-page.ts` is untouched). Submitting it will fail against the fake/empty secrets configured locally — that's expected; the goal is confirming the server starts and serves the page without a runtime error, not a live end-to-end run (that happens in Task 6, once real secrets are set in the Deno Deploy dashboard).

- [ ] **Step 7: Delete the old Cloudflare files this task supersedes**

```bash
cd /Users/macbook/mclair/access-worker
rm -f src/index.ts test/index.test.ts
```

- [ ] **Step 8: Commit**

```bash
cd /Users/macbook/mclair
git add access-worker/src/main.ts access-worker/test/main.test.ts
git add access-worker/src/index.ts access-worker/test/index.test.ts  # stages the deletions
git commit -m "feat: add Deno.serve entry point (main.ts), remove Cloudflare index.ts"
```

---

### Task 6: Deployment runbook (Sandru — manual, not code)

**This task has no implementer subagent.** Every step requires Sandru's own Deno Deploy account and GitHub access — no agent can act on it. Once Tasks 1–5 are built and reviewed, present this checklist to Sandru directly.

- [ ] **Step 1: Create a Deno Deploy account**

At `dash.deno.com`, sign up (GitHub login is the easiest option, given the repo is already on GitHub). No credit card is needed for an individual account — only organization accounts require one.

- [ ] **Step 2: Connect the repository**

From the Deno Deploy dashboard, create a new project and connect it to the `sandruhill/mclair` GitHub repository. Point it at the `access-worker/` subdirectory and `src/main.ts` as the entry point (Deno Deploy supports deploying from a subdirectory of a monorepo — look for that option during project setup; if the dashboard doesn't offer a subdirectory field directly, check its docs for the monorepo/"root directory" setting).

- [ ] **Step 3: Enable Deno KV for the project**

Deno KV may need to be explicitly enabled per-project in the dashboard (separate from the `--unstable-kv` CLI flag used locally) — look for a "KV" or "Database" tab in the project settings and enable it if there's a toggle.

- [ ] **Step 4: Set the two secrets**

In the project's environment variables/secrets settings, add:
- `RESEND_API_KEY` — from a Resend account (`resend.com`, free tier, requires verifying `mclair.com.br` or a subdomain as a sending domain via DNS records Resend provides).
- `GITHUB_ADMIN_TOKEN` — a GitHub fine-grained Personal Access Token scoped only to the `mclair` repository, with **Administration: Read and write** permission (covers adding collaborators) and nothing else. Generate at `github.com/settings/personal-access-tokens/new`, resource owner `sandruhill`, repository access limited to `mclair`.

- [ ] **Step 5: Deploy**

Push to `main` (or trigger a deploy from the dashboard) — Deno Deploy builds and deploys automatically once the repo is connected. It prints a live URL (something like `https://mclair-access-worker.deno.dev` or a project-specific subdomain).

- [ ] **Step 6: End-to-end smoke test**

Open the deployed URL, fill in your own `@mclair.com.br` email and GitHub username, submit, confirm the code arrives by email within a minute or two, enter it, confirm the success message. Check `github.com/sandruhill/mclair/settings/access` to confirm the invite appears. Accept the invite from the test account and confirm `/admin/` login still works via "Sign In Using Access Token."

- [ ] **Step 7: Point staff at the new URL**

Once confirmed working, this URL replaces "peça pro Sandru te adicionar como colaborador" in Part 1 of the published admin manual — flag this back to whoever's continuing the work so the manual gets updated (tracked as a follow-up, not part of this plan's scope, same as it was for the original Cloudflare version).
