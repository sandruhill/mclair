<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$pdo = cmsDb();

// Only admins manage users.
$me = $pdo->prepare('SELECT role FROM cmstest_users WHERE id = ?');
$me->execute([$_SESSION['cms_user_id']]);
$myRole = $me->fetchColumn();
if ($myRole !== 'admin') { http_response_code(403); die('Só administradores podem gerenciar usuários.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] === 'admin' ? 'admin' : 'editor';

        if ($username === '' || strlen($password) < 8) {
            $error = 'Usuário obrigatório e senha com pelo menos 8 caracteres.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO cmstest_users (username, password_hash, role) VALUES (?, ?, ?)');
                $stmt->execute([$username, $hash, $role]);
                header('Location: users.php?created=1');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Esse usuário já existe.' : 'Erro ao criar usuário.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        if ($id !== (int) $_SESSION['cms_user_id']) {
            $stmt = $pdo->prepare('DELETE FROM cmstest_users WHERE id = ?');
            $stmt->execute([$id]);
        }
        header('Location: users.php?deleted=1');
        exit;
    }
}

$users = $pdo->query('SELECT id, username, role, created_at, last_login_at FROM cmstest_users ORDER BY created_at')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex" />
<title>Usuários — CMS Teste</title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; background: #F1EBDD; color: #211B14; }
  h1 { color: #C8102E; font-size: 1.4rem; }
  a.back { display: inline-block; margin-bottom: 16px; color: #C8102E; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; margin-bottom: 30px; }
  th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
  th { background: #211B14; color: #fff; }
  .role-admin { color: #C8102E; font-weight: 700; }
  .role-editor { color: #665D4D; }
  form.inline { display: inline; }
  button.del { background: none; border: none; color: #C8102E; cursor: pointer; font-size: 0.8rem; text-decoration: underline; }
  .new-user { background: #fff; border: 1px solid #D6C9A8; border-radius: 8px; padding: 20px; }
  label { display: block; font-weight: 700; margin: 12px 0 4px; font-size: 0.8rem; }
  input, select { width: 100%; padding: 8px 10px; border: 1px solid #D6C9A8; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
  button[type=submit] { margin-top: 14px; background: #C8102E; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; }
  .msg { padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
  .ok { background: #2F7D4F; color: #fff; }
  .err { background: #C8102E; color: #fff; }
</style>
</head>
<body>
<a class="back" href="index.php">&larr; voltar</a>
<h1>Usuários do CMS</h1>

<?php if (isset($_GET['created'])): ?><div class="msg ok">Usuário criado.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="msg ok">Usuário removido.</div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<table>
<tr><th>Usuário</th><th>Permissão</th><th>Criado em</th><th>Último login</th><th></th></tr>
<?php foreach ($users as $u): ?>
<tr>
  <td><?= htmlspecialchars($u['username']) ?></td>
  <td class="role-<?= htmlspecialchars($u['role']) ?>"><?= $u['role'] === 'admin' ? 'Administrador' : 'Editor' ?></td>
  <td><?= htmlspecialchars($u['created_at']) ?></td>
  <td><?= htmlspecialchars($u['last_login_at'] ?? 'nunca') ?></td>
  <td>
    <?php if ((int)$u['id'] !== (int)$_SESSION['cms_user_id']): ?>
    <form class="inline" method="post" onsubmit="return confirm('Remover este usuário?');">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
      <button type="submit" class="del">remover</button>
    </form>
    <?php else: ?>
    <em style="color:#999">você</em>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>

<div class="new-user">
  <strong>Novo usuário</strong>
  <form method="post">
    <input type="hidden" name="action" value="create" />
    <label>Usuário</label>
    <input type="text" name="username" required />
    <label>Senha (mín. 8 caracteres)</label>
    <input type="password" name="password" minlength="8" required />
    <label>Permissão</label>
    <select name="role">
      <option value="editor">Editor (edita conteúdo)</option>
      <option value="admin">Administrador (edita conteúdo + gerencia usuários)</option>
    </select>
    <button type="submit">Criar usuário</button>
  </form>
</div>
</body>
</html>
