<?php
// Implements the GitHub OAuth popup handshake Sveltia CMS (and its
// Decap/Netlify CMS predecessors) expect from a "backend" auth provider:
// the CMS opens a popup at /auth, the popup redirects to GitHub, GitHub
// redirects back to /callback, and /callback exchanges the code for a
// token and hands it to the opener window via postMessage.
require_once __DIR__ . '/kv.php';

const OAUTH_STATE_TTL_SECONDS = 10 * 60;

function oauthStateKey(string $state): string {
    return 'oauth-state:' . $state;
}

function generateOauthState(): string {
    return bin2hex(random_bytes(16));
}

function storeOauthState(PDO $pdo, string $state): void {
    kvSet($pdo, oauthStateKey($state), '1', OAUTH_STATE_TTL_SECONDS);
}

// Single-use: a state that was already consumed (or never existed) fails,
// which is what stops an attacker from replaying a captured callback URL.
function consumeOauthState(PDO $pdo, string $state): bool {
    $value = kvGet($pdo, oauthStateKey($state));
    if ($value === null) return false;
    kvDelete($pdo, oauthStateKey($state));
    return true;
}

function buildAuthorizeUrl(string $clientId, string $redirectUri, string $state): string {
    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'scope' => 'repo,user',
        'state' => $state,
    ]);
    return 'https://github.com/login/oauth/authorize?' . $params;
}

function exchangeCodeForToken(string $clientId, string $clientSecret, string $code): ?string {
    $ch = curl_init('https://github.com/login/oauth/access_token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300 || $res === false) return null;
    $data = json_decode($res, true);
    return $data['access_token'] ?? null;
}

// Both pages follow the same handshake: announce readiness to the opener,
// wait for the opener's reply (any message — Sveltia sends one to confirm
// it's listening), then deliver the payload targeted at that reply's exact
// origin rather than "*", so a captured/mis-set opener can't be handed a
// live token.
function oauthHandshakePage(string $payload): string {
    $json = json_encode($payload);
    return <<<HTML
<!doctype html>
<html><body>
<script>
(function () {
  function receiveMessage(e) {
    window.opener.postMessage({$json}, e.origin);
    window.removeEventListener('message', receiveMessage, false);
  }
  window.addEventListener('message', receiveMessage, false);
  window.opener.postMessage('authorizing:github', '*');
})();
</script>
</body></html>
HTML;
}

function oauthSuccessPage(string $token): string {
    $message = 'authorization:github:success:' . json_encode(['token' => $token, 'provider' => 'github']);
    return oauthHandshakePage($message);
}

function oauthErrorPage(string $errorMessage): string {
    $message = 'authorization:github:error:' . json_encode(['message' => $errorMessage]);
    return oauthHandshakePage($message);
}
