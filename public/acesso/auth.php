<?php
require_once __DIR__ . '/db.php';
session_start();

if (isset($_POST['username'], $_POST['password'])) {
    $stmt = cmsDb()->prepare('SELECT id, username, password_hash FROM cmstest_users WHERE username = ?');
    $stmt->execute([trim($_POST['username'])]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password_hash'])) {
        $_SESSION['cms_user_id'] = $user['id'];
        $_SESSION['cms_username'] = $user['username'];
        $upd = cmsDb()->prepare('UPDATE cmstest_users SET last_login_at = NOW() WHERE id = ?');
        $upd->execute([$user['id']]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $authError = 'Usuário ou senha incorretos.';
}

if (empty($_SESSION['cms_user_id'])) {
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>Painel Mclair — login</title>
    <style>
      :root { --red:#C8102E; --ink:#1A1B1E; --ink-3:#6B7280; --line:#E5E7EB; --bg:#F6F7F9; }
      * { box-sizing: border-box; }
      body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--ink); }
      .split { display:flex; min-height:100vh; }
      .form-side { flex:1; display:flex; align-items:center; justify-content:center; padding:32px; background:#fff; }
      .form-card { width:100%; max-width:360px; }
      .brand { display:flex; align-items:center; gap:9px; margin-bottom:34px; }
      .brand .dot { width:24px; height:24px; border-radius:7px; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.8rem; }
      .brand strong { font-size:.95rem; letter-spacing:.01em; }
      h1 { font-size:1.5rem; margin:0 0 6px; letter-spacing:-.01em; }
      .sub { font-size:.85rem; color:var(--ink-3); margin:0 0 28px; }
      label { display:block; font-weight:700; margin:16px 0 5px; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-3); }
      label:first-of-type { margin-top:0; }
      input { width:100%; padding:11px 13px; border:1px solid var(--line); border-radius:8px; font-family:inherit; font-size:.92rem; background:#fff; }
      input:focus { outline:2px solid rgba(200,16,46,.22); outline-offset:0; border-color:var(--red); }
      button { width:100%; margin-top:22px; background:var(--red); color:#fff; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:700; font-size:.92rem; }
      button:hover { opacity:.92; }
      .err { background:var(--red); color:#fff; padding:9px 13px; border-radius:8px; font-size:.82rem; margin-bottom:16px; }
      .image-side {
        flex:1; position:relative; overflow:hidden; display:none;
        background:#211B14 url('/brand/mockup-completo.jpg') center/cover no-repeat;
      }
      .image-side::after {
        content:''; position:absolute; inset:0;
        background:linear-gradient(180deg, rgba(33,27,20,.15) 0%, rgba(20,16,12,.15) 55%, rgba(15,12,9,.88) 100%);
      }
      .image-overlay { position:absolute; left:36px; right:36px; bottom:36px; z-index:1; color:#fff; }
      .image-overlay .quote { font-size:1.05rem; font-weight:300; line-height:1.55; margin:0 0 16px; max-width:420px; }
      .image-overlay .who strong { display:block; font-size:.88rem; }
      .image-overlay .who span { font-size:.76rem; color:rgba(255,255,255,.65); }
      @media (min-width: 880px) { .image-side { display:block; } }
    </style>
    </head>
    <body>
    <div class="split">
      <div class="form-side">
        <div class="form-card">
          <div class="brand"><span class="dot">M</span><strong>Painel Mclair</strong></div>
          <h1>Bem-vindo de volta</h1>
          <p class="sub">Entre com sua conta para gerenciar o site.</p>
          <?php if (isset($authError)): ?><div class="err"><?= htmlspecialchars($authError) ?></div><?php endif; ?>
          <form method="post">
            <label>Usuário ou e-mail</label>
            <input type="text" name="username" autofocus />
            <label>Senha</label>
            <input type="password" name="password" />
            <button type="submit">Entrar</button>
          </form>
        </div>
      </div>
      <div class="image-side">
        <div class="image-overlay">
          <p class="quote">"Construímos autoridade de marca com Comunicação Estratégica desde 2017."</p>
          <div class="who"><strong>Mclair Comunicação</strong><span>200+ marcas atendidas · São Paulo</span></div>
        </div>
      </div>
    </div>
    </body></html>
    <?php
    exit;
}

// Re-read the role on every request (not just at login) so a demotion/promotion
// takes effect immediately instead of waiting for the session to expire.
$stmt = cmsDb()->prepare('SELECT role FROM cmstest_users WHERE id = ?');
$stmt->execute([$_SESSION['cms_user_id']]);
$_SESSION['cms_role'] = $stmt->fetchColumn() ?: 'author';

function cmsRole(): string {
    return $_SESSION['cms_role'] ?? 'author';
}

function cmsRequireRole(array $allowed): void {
    if (!in_array(cmsRole(), $allowed, true)) {
        http_response_code(403);
        die('Você não tem permissão para acessar esta página.');
    }
}
