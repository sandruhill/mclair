# Port access-worker from Cloudflare Workers to Deno Deploy

## Context

`access-worker/` (the self-service GitHub access flow, spec'd and built earlier this session) targets Cloudflare Workers. Sandru hit a billing/card verification issue on Cloudflare and can't complete account setup. Deno Deploy's individual free tier (1M requests/month, 100GB bandwidth, 1GiB KV) requires no credit card — only organization-level accounts do, which this single-person use case doesn't need.

This is a runtime/storage port, not a redesign. Every business rule from the original spec (`2026-08-13-self-service-admin-access-design.md`) and every security fix from its review cycle (attempt-limit brute-force protection, per-IP and daily-global rate limits, code-match-before-GitHub-call ordering, email normalization) carries over unchanged. Only the platform-specific plumbing changes.

## What changes

- **Storage:** Cloudflare `KVNamespace` → `Deno.Kv`. Keys become structured arrays (`["code", email]` instead of the string `code:${email}`). TTLs are `expireIn` in milliseconds instead of `expirationTtl` in seconds. Where the previous implementation used a manual get-then-put counter (flagged in review as non-atomic, accepted as a soft-limit tradeoff), this port uses Deno KV's native atomic `sum`/`check` operations where it's a direct swap-in — closing that previously-accepted gap is a welcome side effect of the platform change, not new scope being added on purpose.
- **HTTP handler:** the Workers `export default { async fetch(request, env) }` shape → `Deno.serve((request) => { ... })`. Secrets read via `Deno.env.get('RESEND_API_KEY')` instead of an `env` parameter.
- **Tests:** `@cloudflare/vitest-pool-workers` + Miniflare → Deno's built-in test runner (`deno test`) with `Deno.openKv(":memory:")` per test for isolated, ephemeral storage (direct equivalent of the simulated KV the previous suite used).
- **Deployment:** no `wrangler.toml`/CLI secret dance. Deno Deploy's GitHub integration deploys on every push to `main` once the repo is connected via its dashboard; secrets are set in that same dashboard. No new GitHub Actions workflow needed for this piece (unlike the Astro site's FTP deploy, this project is TypeScript Deno runs natively — no build step).

## What doesn't change

Every rule from the original spec's "Endpoints" and "Error handling" sections, and every fix from the review cycle: 6-digit single-use codes with 15-minute expiry, 5 attempts per code before force-deletion, 3 code-requests/email/hour, 20 requests/IP/day (day-keyed, not rolling), 50 emails/day global cap checked before per-email limits, `@mclair.com.br`-only signup, GitHub username existence checked only after a code match (never before, to avoid the token-drain the review caught), collaborator invite via a fine-grained GitHub PAT never exposed to the browser, Portuguese-language user-facing messages, no user database (GitHub's own collaborator list remains the only "who has access" record).

## File structure

Replaces `access-worker/` in place (same directory, same repo) — this isn't a parallel implementation, it supersedes the Cloudflare version, which never got deployed.

```
access-worker/
  deno.json              — Deno config: tasks (test/dev/deploy hints), no build step needed
  src/
    kv.ts                 — thin wrapper: open the KV store, expose the same
                            get/set/delete-with-expiry shape the rest of the
                            code already expects, so codes.ts/ratelimit.ts
                            need minimal changes beyond the key-array format
    codes.ts               — ported: same exports, Deno.Kv underneath
    ratelimit.ts             — ported: same exports, Deno.Kv underneath,
                                atomic counters where it's a direct swap-in
    email.ts                 — unchanged logic (pure fetch call), only the
                                secret-reading call site changes
    github.ts                  — unchanged logic (pure fetch calls)
    signup-page.ts               — unchanged (static HTML/JS string)
    main.ts                       — replaces index.ts: Deno.serve handler,
                                     same route logic
  test/
    kv.test.ts
    codes.test.ts
    ratelimit.test.ts
    email.test.ts
    github.test.ts
    main.test.ts                   — replaces index.test.ts
```

## Out of scope

- No behavior change beyond what the platform forces. If the port surfaces a reason to reconsider a security control's exact numbers (rate limit thresholds, TTLs), that's a separate conversation — not bundled into this port.
- No parallel Cloudflare + Deno dual-deploy. Cloudflare is abandoned for this component; Deno Deploy is the only target.
