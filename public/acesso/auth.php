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
      :root { --red:#C8102E; --ink:#1A1B1E; --ink-3:#6B7280; --line:#E5E7EB; --bg:#F1F2F4; }
      * { box-sizing: border-box; }
      body {
        margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
        font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--ink);
        background:var(--bg); padding:24px;
      }
      .glow {
        position:fixed; top:50%; left:50%; width:640px; height:640px; transform:translate(-50%,-50%);
        background:radial-gradient(circle, rgba(200,16,46,.07) 0%, rgba(200,16,46,0) 70%);
        pointer-events:none; z-index:0;
      }
      .form-card {
        position:relative; z-index:1; width:100%; max-width:380px; background:#fff;
        border-radius:16px; padding:40px 36px; box-shadow:0 2px 6px rgba(20,16,12,.04), 0 24px 64px -20px rgba(20,16,12,.14);
      }
      .brand { display:flex; align-items:center; justify-content:center; gap:9px; margin-bottom:30px; }
      .brand .dot { width:24px; height:24px; border-radius:7px; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.8rem; }
      .brand strong { font-size:.95rem; letter-spacing:.01em; }
      h1 { font-size:1.4rem; margin:0 0 6px; letter-spacing:-.01em; text-align:center; }
      .sub { font-size:.85rem; color:var(--ink-3); margin:0 0 28px; text-align:center; }
      label { display:block; font-weight:700; margin:16px 0 5px; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-3); }
      label:first-of-type { margin-top:0; }
      input { width:100%; padding:11px 13px; border:1px solid var(--line); border-radius:8px; font-family:inherit; font-size:.92rem; background:#fff; }
      input:focus { outline:2px solid rgba(200,16,46,.22); outline-offset:0; border-color:var(--red); }
      button { width:100%; margin-top:22px; background:var(--red); color:#fff; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:700; font-size:.92rem; }
      button:hover { opacity:.92; }
      .err { background:var(--red); color:#fff; padding:9px 13px; border-radius:8px; font-size:.82rem; margin-bottom:16px; }
    </style>
    </head>
    <body>
    <div class="glow"></div>
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
