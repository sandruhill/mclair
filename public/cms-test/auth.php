<?php
// config.php (deploy-time generated) defines CMS_TEST_PASSWORD.
require_once __DIR__ . '/config.php';
session_start();

if (isset($_POST['password'])) {
    if (hash_equals(CMS_TEST_PASSWORD, $_POST['password'])) {
        $_SESSION['cms_test_ok'] = true;
        header('Location: ' . ($_SERVER['REQUEST_URI']));
        exit;
    }
    $authError = 'Senha incorreta.';
}

if (empty($_SESSION['cms_test_ok'])) {
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
      <input type="password" name="password" placeholder="Senha" autofocus />
      <button type="submit">Entrar</button>
    </form>
    </body></html>
    <?php
    exit;
}
