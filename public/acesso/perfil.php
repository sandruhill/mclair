<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();
$userId = (int) $_SESSION['cms_user_id'];

$error = '';
$saved = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectTo = $_POST['redirect_to'] ?? 'perfil.php?saved=1';

    if ($action === 'profile') {
        $displayName = trim($_POST['display_name'] ?? '');
        $avatarUrl = trim($_POST['avatar_url'] ?? '');
        $stmt = $pdo->prepare('UPDATE cmstest_users SET display_name = ?, avatar_url = ? WHERE id = ?');
        $stmt->execute([$displayName !== '' ? $displayName : null, $avatarUrl !== '' ? $avatarUrl : null, $userId]);
        header('Location: perfil.php?saved=1');
        exit;
    }

    if ($action === 'password') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 8) {
            $error = 'A nova senha precisa ter pelo menos 8 caracteres.';
        } elseif ($new !== $confirm) {
            $error = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE cmstest_users SET password_hash = ?, password_changed_at = NOW(), login_count_since_reset = 0 WHERE id = ?');
            $stmt->execute([$hash, $userId]);
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT username, display_name, avatar_url FROM cmstest_users WHERE id = ?');
$stmt->execute([$userId]);
$me = $stmt->fetch();

adminLayoutTop('profile', 'Meu perfil');
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok">Perfil atualizado.</div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="editor-grid">
  <div>
    <div class="card">
      <strong>Dados do perfil</strong>
      <form method="post">
        <input type="hidden" name="action" value="profile" />
        <label>Nome de exibição</label>
        <input type="text" name="display_name" value="<?= htmlspecialchars($me['display_name'] ?? '') ?>" placeholder="<?= htmlspecialchars($me['username']) ?>" />
        <p class="hint">Aparece na barra lateral do painel no lugar do e-mail. Deixe em branco pra usar o e-mail.</p>
        <label>Foto de perfil</label>
        <input type="text" class="img-url" data-imgdrop-ratio="1:1" name="avatar_url" value="<?= htmlspecialchars($me['avatar_url'] ?? '') ?>" />
        <button type="submit" class="btn" style="margin-top:16px">Salvar perfil</button>
      </form>
    </div>

    <div class="card" style="margin-top:20px">
      <strong>Trocar senha</strong>
      <form method="post">
        <input type="hidden" name="action" value="password" />
        <label>Nova senha (mín. 8 caracteres)</label>
        <input type="password" name="new_password" minlength="8" required />
        <label>Confirmar nova senha</label>
        <input type="password" name="confirm_password" minlength="8" required />
        <button type="submit" class="btn" style="margin-top:16px">Trocar senha</button>
      </form>
    </div>
  </div>
</div>

<?php adminLayoutBottom(); ?>
