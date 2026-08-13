# Self-Service Admin Panel Access

## Context

The `/admin/` panel (Sveltia CMS) currently only has one working login method:
"Sign In Using Access Token," which requires a staff member to (1) have their
own GitHub account and (2) be added as a collaborator on the
`sandruhill/mclair` repository by Sandru, manually, one person at a time.
Sandru wants step 2 removed — any Mclair staff member should be able to gain
edit access without waiting on him to act.

## Why not a fully custom login system

The obvious-looking alternative — email + password against our own user
database, with a shared service credential doing the actual GitHub writes
underneath — was researched and rejected. Sveltia CMS only supports three git
backends (GitHub, GitLab, Gitea/Forgejo) plus a local test backend; it has no
git-gateway or custom-proxy backend (confirmed against Sveltia's own docs and
GitHub discussions). Building a fully custom login would mean either forking
Sveltia CMS itself, or handing every logged-in browser a real, shared,
write-capable GitHub token — which would also make every commit show up
under one generic identity instead of the actual person who made the change.
Sandru confirmed per-person attribution in git history matters, which rules
this path out on its own.

## Approach: auto-provisioned GitHub collaborator access

Staff keep creating their own free GitHub account (already covered in the
existing admin manual) and keep logging in with their own Personal Access
Token, exactly as documented today. What disappears is Sandru manually
running `Settings → Collaborators → Add people` for each person. Instead:

1. A small signup page (plain HTML/JS, no framework) asks for name, a
   `@mclair.com.br` email address, and a GitHub username.
2. A verification code is emailed to that address. The person enters it back
   on the page. This proves they actually control that inbox — a bare
   domain-suffix check on a text field proves nothing on its own, since
   anyone can type `qualquer-coisa@mclair.com.br` into a form.
3. Once the code checks out, the backend confirms the GitHub username is
   real and not already a collaborator, then calls GitHub's API to add them
   as a collaborator with write access — automatically, no click from
   Sandru.
4. GitHub itself still requires the invited person to accept the invite
   (one click, via GitHub's own notification/email) before they can push.
   This is GitHub's own collaboration model and can't be skipped — it isn't
   a bottleneck on Sandru's side, so it doesn't reintroduce the problem
   being solved here.
5. From there, login proceeds exactly as the existing manual already
   describes (Sign In Using Access Token).

## Components

**Cloudflare Workers** hosts the signup page and its two API endpoints.
Picked because Hostinger's shared hosting has no Node.js support (confirmed
via SSH during this session) and Sveltia CMS's own official OAuth helper
(`sveltia-cms-auth`) already uses this exact pattern — there's a working
precedent for "small auth-adjacent script lives on Cloudflare Workers, main
site stays static on Hostinger."

**Cloudflare KV** stores the short-lived verification codes (email → code,
~10–15 min TTL, single use). No real user database is needed — GitHub's own
collaborator list is the source of truth for who has access, so there's
nothing else to persist.

**Resend** sends the verification email. Free tier (100 emails/day) is far
more than a small agency's onboarding volume needs. Requires verifying a
sending domain via DNS — Sandru (or whoever holds DNS for mclair.com.br)
needs to add the records Resend provides before this works.

**GitHub API, called from the Worker**, using a fine-grained Personal Access
Token scoped only to the `sandruhill/mclair` repository's collaborator
management — not a broad classic token. Stored as a Cloudflare Worker
secret, never exposed to the browser.

## Endpoints

- `POST /solicitar-codigo` — body: `{ name, email, githubUsername }`.
  Validates the email ends in `@mclair.com.br`, rate-limits repeat requests
  for the same email (KV counter), generates a 6-digit code, stores it in KV
  keyed by email with a short TTL, sends it via Resend. Returns a generic
  success response either way (doesn't reveal whether an email is already
  registered/valid, to avoid turning this into an enumeration oracle).

- `POST /confirmar-codigo` — body: `{ email, code, githubUsername }`.
  Looks up the code in KV, checks it matches and hasn't expired, deletes it
  (single use). On success: calls `GET /users/{githubUsername}` to confirm
  the account exists, `GET /repos/sandruhill/mclair/collaborators/{username}`
  to check for an existing invite/membership (skip with a friendly "you
  already have access" message if so), then
  `PUT /repos/sandruhline/mclair/collaborators/{username}` with
  `permission: push` to send the invite. Returns a message telling the
  person to check GitHub for the invite and accept it, then go to `/admin/`.

## Error handling

- Wrong domain email → rejected before any code is sent, with a clear
  message ("precisa ser um e-mail @mclair.com.br").
- Nonexistent GitHub username → caught at confirm time via the GitHub API
  check, clear message asking them to double-check the username.
- Expired or wrong code → clear message, option to request a new one.
- Repeated code requests for the same email → rate-limited (a small fixed
  cap per time window) to avoid the email-sending step being abused as a
  spam vector.
- GitHub API failure (rate limit, token issue) → surfaced as a generic
  "algo deu errado, tenta de novo em alguns minutos" rather than leaking
  internal error detail to the signup page.

## Out of scope for this version

- No password of any kind — GitHub's own PAT/OAuth remains the only ongoing
  authentication method after this one-time signup gate.
- No admin UI to revoke access — removing someone is still a manual GitHub
  action (`Settings → Collaborators → Remove`) for now; this spec only
  covers granting access, not lifecycle management.
- No self-service for people outside `@mclair.com.br` (external Mclair
  clients) — confirmed out of scope; this is for Mclair's own staff only.

## Follow-up (tracked, not part of this build)

Once this ships, Part 1 of the existing admin manual (published as a Claude
Artifact, not a file in this repo) needs its "peça pro Sandru te adicionar
como colaborador" step replaced with instructions for the new signup form.
This happens after the feature exists, not before.
