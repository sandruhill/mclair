<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin']);
$pdo = cmsDb();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim(strtolower($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';

        if ($username === '' || strlen($password) < 8) {
            $error = 'E-mail obrigatório e senha com pelo menos 8 caracteres.';
        } elseif (!str_ends_with($username, '@mclair.com.br')) {
            $error = 'Novos usuários precisam de um e-mail @mclair.com.br (só funcionários da Mclair).';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO cmstest_users (username, password_hash, role) VALUES (?, ?, ?)');
                $stmt->execute([$username, $hash, $role]);
                header('Location: usuarios?created=1');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Esse usuário já existe.' : 'Erro ao criar usuário.';
            }
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim(strtolower($_POST['username'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';
        $password = $_POST['password'] ?? '';

        if ($username === '') {
            $error = 'E-mail obrigatório.';
        } elseif (!str_ends_with($username, '@mclair.com.br')) {
            $error = 'Usuários precisam de um e-mail @mclair.com.br (só funcionários da Mclair).';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Senha precisa ter pelo menos 8 caracteres (ou deixe em branco pra manter a atual).';
        } else {
            try {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE cmstest_users SET username = ?, display_name = ?, role = ?, password_hash = ?, password_changed_at = NULL, login_count_since_reset = 0 WHERE id = ?');
                    $stmt->execute([$username, $displayName !== '' ? $displayName : null, $role, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE cmstest_users SET username = ?, display_name = ?, role = ? WHERE id = ?');
                    $stmt->execute([$username, $displayName !== '' ? $displayName : null, $role, $id]);
                }
                header('Location: usuarios?updated=1');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Esse usuário já existe.' : 'Erro ao atualizar usuário.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        if ($id !== (int) $_SESSION['cms_user_id']) {
            $stmt = $pdo->prepare('DELETE FROM cmstest_users WHERE id = ?');
            $stmt->execute([$id]);
        }
        header('Location: usuarios?deleted=1');
        exit;
    }
}

$users = $pdo->query('SELECT id, username, display_name, role, created_at, last_login_at FROM cmstest_users ORDER BY created_at')->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, username, display_name, role FROM cmstest_users WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch() ?: null;
}

adminLayoutTop('users', 'Usuários');
?>

<?php if (isset($_GET['created'])): ?><div class="msg ok">Usuário criado.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="msg ok">Usuário atualizado.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="msg ok">Usuário removido.</div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="tablecard" style="margin-bottom:24px">
<div class="tablecard-head"><div><strong>Usuários</strong><span class="count"><?= count($users) ?></span></div></div>
<table class="dt">
<tr><th>Usuário / e-mail</th><th>Permissão</th><th class="s">Criado em</th><th>Último login</th><th>Ações</th></tr>
<?php foreach ($users as $u): ?>
<tr>
  <td><?php if (!empty($u['display_name'])): ?><strong><?= htmlspecialchars($u['display_name']) ?></strong><br><span style="color:var(--ink-3);font-size:.78rem"><?= htmlspecialchars($u['username']) ?></span><?php else: ?><?= htmlspecialchars($u['username']) ?><?php endif; ?></td>
  <?php $roleLabels = ['admin' => 'Administrador', 'editor' => 'Editor', 'author' => 'Autor']; ?>
  <td><span class="badge" style="<?= $u['role']==='admin' ? 'background:var(--red);color:#fff' : '' ?>"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
  <td><?= htmlspecialchars($u['created_at']) ?></td>
  <td><?= htmlspecialchars($u['last_login_at'] ?? 'nunca') ?></td>
  <td class="dt-actions">
    <a href="usuarios?edit=<?= (int)$u['id'] ?>">editar</a>
    <?php if ((int)$u['id'] !== (int)$_SESSION['cms_user_id']): ?>
    <form method="post" style="display:inline">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
      <button type="button" class="del" data-confirm="Remover este usuário?" data-yes="sim, remover" onclick="askConfirm(this)">remover</button>
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
  <strong><?= $editUser ? 'Editar usuário' : 'Novo usuário' ?></strong>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>" />
    <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>" /><?php endif; ?>
    <label>E-mail (@mclair.com.br)</label>
    <input type="email" name="username" placeholder="nome@mclair.com.br" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required />
    <p class="hint">Só e-mails da Mclair podem ser cadastrados.</p>
    <label>Nome de exibição</label>
    <input type="text" name="display_name" value="<?= htmlspecialchars($editUser['display_name'] ?? '') ?>" placeholder="opcional" />
    <label>Senha<?= $editUser ? ' (deixe em branco pra manter a atual)' : ' (mín. 8 caracteres)' ?></label>
    <div style="display:flex;gap:8px">
      <input type="text" name="password" id="newPassword" minlength="8" <?= $editUser ? '' : 'required' ?> style="font-family:ui-monospace,monospace" />
      <button type="button" class="btn secondary" style="white-space:nowrap" onclick="generatePassword()">Gerar senha</button>
    </div>
    <p class="hint"><?= $editUser ? 'Trocar a senha aqui força o usuário a defini-la de novo no próximo login.' : 'Clique em "Gerar senha" pra criar uma senha forte automaticamente. Copie antes de salvar, ela não aparece de novo depois.' ?></p>
    <label>Permissão</label>
    <select name="role">
      <option value="author" <?= ($editUser['role'] ?? '') === 'author' ? 'selected' : '' ?>>Autor (edita só os próprios posts do blog)</option>
      <option value="editor" <?= ($editUser['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor (edita todo o conteúdo)</option>
      <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador (edita tudo + gerencia usuários)</option>
    </select>
    <button type="submit" class="btn" style="margin-top:16px"><?= $editUser ? 'Salvar alterações' : 'Criar usuário' ?></button>
    <?php if ($editUser): ?><a href="usuarios" class="btn secondary" style="margin-top:16px;margin-left:8px">Cancelar</a><?php endif; ?>
  </form>
</div>

<script>
function generatePassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  const bytes = new Uint32Array(16);
  crypto.getRandomValues(bytes);
  let pw = '';
  for (let i = 0; i < 16; i++) pw += chars[bytes[i] % chars.length];
  const field = document.getElementById('newPassword');
  field.value = pw;
  field.focus();
  field.select();
}
</script>

<?php adminLayoutBottom(); ?>
