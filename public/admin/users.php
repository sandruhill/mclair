<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();

$me = $pdo->prepare('SELECT role FROM cmstest_users WHERE id = ?');
$me->execute([$_SESSION['cms_user_id']]);
$myRole = $me->fetchColumn();
if ($myRole !== 'admin') { http_response_code(403); die('Só administradores podem gerenciar usuários.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim(strtolower($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] === 'admin' ? 'admin' : 'editor';

        if ($username === '' || strlen($password) < 8) {
            $error = 'E-mail obrigatório e senha com pelo menos 8 caracteres.';
        } elseif (!str_ends_with($username, '@mclair.com.br')) {
            $error = 'Novos usuários precisam de um e-mail @mclair.com.br (só funcionários da Mclair).';
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

adminLayoutTop('users', 'Usuários');
?>

<?php if (isset($_GET['created'])): ?><div class="msg ok">Usuário criado.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="msg ok">Usuário removido.</div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="tablecard" style="margin-bottom:24px">
<div class="tablecard-head"><div><strong>Usuários</strong><span class="count"><?= count($users) ?></span></div></div>
<table class="dt">
<tr><th>Usuário / e-mail</th><th>Permissão</th><th class="s">Criado em</th><th>Último login</th><th>Ações</th></tr>
<?php foreach ($users as $u): ?>
<tr>
  <td><?= htmlspecialchars($u['username']) ?></td>
  <td><span class="badge" style="<?= $u['role']==='admin' ? 'background:var(--red);color:#fff' : '' ?>"><?= $u['role'] === 'admin' ? 'Administrador' : 'Editor' ?></span></td>
  <td><?= htmlspecialchars($u['created_at']) ?></td>
  <td><?= htmlspecialchars($u['last_login_at'] ?? 'nunca') ?></td>
  <td class="dt-actions">
    <?php if ((int)$u['id'] !== (int)$_SESSION['cms_user_id']): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Remover este usuário?');">
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
</div>

<div class="card" style="max-width:420px">
  <strong>Novo usuário</strong>
  <form method="post">
    <input type="hidden" name="action" value="create" />
    <label>E-mail (@mclair.com.br)</label>
    <input type="email" name="username" placeholder="nome@mclair.com.br" required />
    <p class="hint">Só e-mails da Mclair podem ser cadastrados.</p>
    <label>Senha (mín. 8 caracteres)</label>
    <input type="password" name="password" minlength="8" required />
    <label>Permissão</label>
    <select name="role">
      <option value="editor">Editor (edita conteúdo)</option>
      <option value="admin">Administrador (edita + gerencia usuários)</option>
    </select>
    <button type="submit" class="btn" style="margin-top:16px">Criar usuário</button>
  </form>
</div>

<?php adminLayoutBottom(); ?>
