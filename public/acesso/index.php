<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // deploy-time generated, defines DB_*/GITHUB_*/OAUTH_* constants
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/kv.php';
require_once __DIR__ . '/codes.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/github.php';
require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/email.php';

function jsonResponse($body, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

function htmlResponse(string $body, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $body;
    exit;
}

const GENERIC_ERROR = ['ok' => false, 'error' => 'Algo deu errado. Tenta de novo.'];
const IP_RATE_LIMITED_ERROR = ['ok' => false, 'error' => 'Muitas requisições desse endereço. Tenta de novo mais tarde.'];

// X-Forwarded-For is a header proxies APPEND to as a request passes through
// them. Hostinger's own proxy sits directly in front of PHP here, so the
// RIGHTMOST entry is the one IT appended — the only entry an attacker can't
// forge themselves by sending their own X-Forwarded-For header.
function clientIp(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        $last = end($parts);
        if ($last !== '') return $last;
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function handleSolicitarCodigo(PDO $pdo, string $ip): void {
    if (isIpRateLimited($pdo, $ip)) {
        jsonResponse(IP_RATE_LIMITED_ERROR);
    }

    $input = readJsonBody();
    $email = strtolower(trim($input['email'] ?? ''));
    $githubUsername = trim($input['githubUsername'] ?? '');

    if ($email === '' || !isValidMclairEmail($email)) {
        jsonResponse(['ok' => false, 'error' => 'Precisa ser um e-mail @mclair.com.br.']);
    }
    if ($githubUsername === '') {
        jsonResponse(['ok' => false, 'error' => 'Informe seu usuário do GitHub.']);
    }

    // Cheapest / most-global check first, so a request that's going to be
    // refused by the shared daily cap never burns one of this email's
    // hourly issuance slots.
    if (isDailyEmailCapReached($pdo)) {
        jsonResponse(['ok' => false, 'error' => 'Tenta de novo mais tarde.']);
    }

    if (isRateLimited($pdo, $email)) {
        jsonResponse(['ok' => false, 'error' => 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.']);
    }

    $code = generateVerificationCode();
    storeCode($pdo, $email, $code);
    $sent = sendVerificationCode($email, $code);
    if (!$sent) {
        jsonResponse(['ok' => false, 'error' => 'Não consegui mandar o e-mail agora. Tenta de novo em alguns minutos.'], 500);
    }
    // Only count against the shared daily quota once an email was actually
    // sent — a send failure above must not burn quota for a code nobody
    // received.
    incrementDailyEmailCount($pdo);

    jsonResponse(['ok' => true]);
}

function handleConfirmarCodigo(PDO $pdo, string $ip): void {
    if (isIpRateLimited($pdo, $ip)) {
        jsonResponse(IP_RATE_LIMITED_ERROR);
    }

    $input = readJsonBody();
    $email = strtolower(trim($input['email'] ?? ''));
    $code = trim($input['code'] ?? '');
    $githubUsername = trim($input['githubUsername'] ?? '');

    if ($email === '' || $code === '' || $githubUsername === '') {
        jsonResponse(['ok' => false, 'error' => 'Preencha todos os campos.']);
    }

    if (isVerifyAttemptLimited($pdo, $email)) {
        jsonResponse(['ok' => false, 'error' => 'Muitas tentativas. Pede um novo código.']);
    }

    // Check the code BEFORE touching the GitHub API — without this, anyone
    // who knows (or guesses) an @mclair.com.br local-part can drive
    // githubUserExists with no code at all, spending the admin token's
    // shared rate limit. The code is not consumed yet, though: a
    // subsequent username typo or a transient GitHub API failure must not
    // burn it, so the person can retry with the same code.
    if (!codeMatches($pdo, $email, $code)) {
        jsonResponse(['ok' => false, 'error' => 'Código inválido ou expirado. Pede um novo.']);
    }

    if (!githubUserExists(GITHUB_ADMIN_TOKEN, $githubUsername)) {
        jsonResponse(['ok' => false, 'error' => 'Não encontrei esse usuário no GitHub. Confere se digitou certo.']);
    }

    if (isAlreadyCollaborator(GITHUB_ADMIN_TOKEN, $githubUsername)) {
        consumeCode($pdo, $email);
        error_log("acesso: granted (already had access): {$email} -> {$githubUsername}");
        jsonResponse(['ok' => true, 'message' => 'Você já tem acesso! Pode ir direto pro /admin/.']);
    }

    if (!addCollaborator(GITHUB_ADMIN_TOKEN, $githubUsername)) {
        jsonResponse(['ok' => false, 'error' => 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.'], 500);
    }

    consumeCode($pdo, $email);
    error_log("acesso: granted: {$email} -> {$githubUsername}");
    jsonResponse([
        'ok' => true,
        'message' => 'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
    ]);
}

// Sveltia CMS's "Sign In with GitHub" button opens a popup at /auth. This
// redirects it straight to GitHub's own authorize screen — the state param
// is what /callback later checks to make sure the code it receives came
// from a redirect this server actually issued, not a replayed/forged URL.
function handleAuth(PDO $pdo): void {
    $state = generateOauthState();
    storeOauthState($pdo, $state);
    $redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/callback';
    $authorizeUrl = buildAuthorizeUrl(GITHUB_OAUTH_CLIENT_ID, $redirectUri, $state);
    header('Location: ' . $authorizeUrl, true, 302);
    exit;
}

// GitHub redirects the popup here after the user authorizes (or denies)
// the app. Either way this returns an HTML page that hands the result back
// to the CMS admin page (the popup's opener) via the postMessage handshake
// Sveltia expects — the popup never navigates the admin tab itself.
function handleCallback(PDO $pdo): void {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if (!$state || !consumeOauthState($pdo, $state)) {
        htmlResponse(oauthErrorPage('Sessão de login expirada ou inválida. Tenta de novo.'));
    }
    if (!$code) {
        htmlResponse(oauthErrorPage('O GitHub não retornou um código de autorização.'));
    }

    $token = exchangeCodeForToken(GITHUB_OAUTH_CLIENT_ID, GITHUB_OAUTH_CLIENT_SECRET, $code);
    if (!$token) {
        htmlResponse(oauthErrorPage('Não consegui confirmar o login com o GitHub. Tenta de novo.'));
    }

    htmlResponse(oauthSuccessPage($token));
}

// ---- router ----
$pdo = acessoDb();
$ip = clientIp();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip a leading /acesso so routes match both during interim testing
// (mclair.com.br/acesso/...) and once this moves to its own subdomain
// root (acesso.mclair.com.br/...), where the prefix won't be there at all.
if ($path !== null && str_starts_with($path, '/acesso')) {
    $path = substr($path, strlen('/acesso'));
    if ($path === '') $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET' && ($path === '/' || $path === '/index.php')) {
        htmlResponse(file_get_contents(__DIR__ . '/signup-page.html'));
    }

    if ($method === 'POST' && $path === '/solicitar-codigo') {
        handleSolicitarCodigo($pdo, $ip);
    }

    if ($method === 'POST' && $path === '/confirmar-codigo') {
        handleConfirmarCodigo($pdo, $ip);
    }

    if ($method === 'GET' && $path === '/auth') {
        handleAuth($pdo);
    }

    if ($method === 'GET' && $path === '/callback') {
        handleCallback($pdo);
    }
} catch (Throwable $e) {
    error_log('acesso error: ' . $e->getMessage());
    if (str_starts_with($path, '/auth') || str_starts_with($path, '/callback')) {
        htmlResponse(oauthErrorPage('Algo deu errado. Tenta de novo.'), 500);
    }
    jsonResponse(GENERIC_ERROR, 500);
}

http_response_code(404);
echo 'Not found';
