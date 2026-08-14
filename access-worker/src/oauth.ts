// Implements the GitHub OAuth popup handshake that Sveltia CMS (and its
// Decap/Netlify CMS predecessors) expect from a "backend" auth provider:
// the CMS opens a popup at /auth, the popup redirects to GitHub, GitHub
// redirects back to /callback, and /callback exchanges the code for a
// token and hands it to the opener window via postMessage. This replaces
// the old Vercel-hosted api/auth.ts + api/callback.ts pair that got
// deleted when the site went fully static.

const STATE_TTL_MS = 10 * 60 * 1000;

function stateKey(state: string): Deno.KvKey {
  return ['oauth-state', state];
}

export function generateState(): string {
  return crypto.randomUUID();
}

export async function storeState(kv: Deno.Kv, state: string): Promise<void> {
  await kv.set(stateKey(state), true, { expireIn: STATE_TTL_MS });
}

// Single-use: a state that was already consumed (or never existed) fails,
// which is what stops an attacker from replaying a captured callback URL.
export async function consumeState(kv: Deno.Kv, state: string): Promise<boolean> {
  const entry = await kv.get(stateKey(state));
  if (!entry.value) return false;
  await kv.delete(stateKey(state));
  return true;
}

export function buildAuthorizeUrl(clientId: string, redirectUri: string, state: string): string {
  const url = new URL('https://github.com/login/oauth/authorize');
  url.searchParams.set('client_id', clientId);
  url.searchParams.set('redirect_uri', redirectUri);
  url.searchParams.set('scope', 'repo,user');
  url.searchParams.set('state', state);
  return url.toString();
}

export async function exchangeCodeForToken(
  clientId: string,
  clientSecret: string,
  code: string
): Promise<string | null> {
  const res = await fetch('https://github.com/login/oauth/access_token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ client_id: clientId, client_secret: clientSecret, code }),
    signal: AbortSignal.timeout(10_000),
  });
  if (!res.ok) return null;
  const data = (await res.json().catch(() => null)) as { access_token?: string } | null;
  return data?.access_token ?? null;
}

// Both pages follow the same handshake: announce readiness to the opener,
// wait for the opener's reply (any message — Sveltia sends one to confirm
// it's listening), then deliver the payload targeted at that reply's exact
// origin rather than "*", so a captured/mis-set opener can't be handed a
// live token.
function handshakePage(payload: string): string {
  return `<!doctype html>
<html><body>
<script>
(function () {
  function receiveMessage(e) {
    window.opener.postMessage(${JSON.stringify(payload)}, e.origin);
    window.removeEventListener('message', receiveMessage, false);
  }
  window.addEventListener('message', receiveMessage, false);
  window.opener.postMessage('authorizing:github', '*');
})();
</script>
</body></html>`;
}

export function successPage(token: string): string {
  const message = 'authorization:github:success:' + JSON.stringify({ token, provider: 'github' });
  return handshakePage(message);
}

export function errorPage(errorMessage: string): string {
  const message = 'authorization:github:error:' + JSON.stringify({ message: errorMessage });
  return handshakePage(message);
}
