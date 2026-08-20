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
    <html lang="pt-BR"><head><meta charset="utf-8" /><meta name="robots" content="noindex" />
    <title>CMS Teste — login</title>
    <style>body{font-family:-apple-system,sans-serif;max-width:360px;margin:100px auto;padding:0 20px;}
    input{width:100%;padding:10px;border:1px solid #D6C9A8;border-radius:6px;margin-top:8px;box-sizing:border-box}
    button{margin-top:12px;background:#C8102E;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer}
    .err{color:#C8102E}</style></head>
    <body>
    <h2>CMS Teste — Mclair</h2>
    <?php if (isset($authError)): ?><p class="err"><?= htmlspecialchars($authError) ?></p><?php endif; ?>
    <form method="post">
      <input type="text" name="username" placeholder="Usuário" autofocus />
      <input type="password" name="password" placeholder="Senha" />
      <button type="submit">Entrar</button>
    </form>
    </body></html>
    <?php
    exit;
}
