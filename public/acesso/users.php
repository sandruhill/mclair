<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();
$isAdmin = cmsRole() === 'admin';
$myId = (int) $_SESSION['cms_user_id'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$isAdmin) { http_response_code(403); die('Você não tem permissão pra criar usuários.'); }
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

    // Edits both "admin editing anyone" and "anyone editing their own record"
    // (the same form drives both -- see the card below). A non-admin can only
    // ever target their own id, and can never touch the role column.
    if ($action === 'update') {
        $id = $isAdmin ? (int) ($_POST['id'] ?? 0) : $myId;
        $username = trim(strtolower($_POST['username'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $avatarUrl = trim($_POST['avatar_url'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '') {
            $error = 'E-mail obrigatório.';
        } elseif (!str_ends_with($username, '@mclair.com.br')) {
            $error = 'Usuários precisam de um e-mail @mclair.com.br (só funcionários da Mclair).';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Senha precisa ter pelo menos 8 caracteres (ou deixe em branco pra manter a atual).';
        } else {
            $fields = ['username = ?', 'display_name = ?', 'avatar_url = ?'];
            $params = [$username, $displayName !== '' ? $displayName : null, $avatarUrl !== '' ? $avatarUrl : null];

            if ($isAdmin) {
                $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';
                $fields[] = 'role = ?';
                $params[] = $role;
            }

            if ($password !== '') {
                $fields[] = 'password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
                if ($isAdmin && $id !== $myId) {
                    // Admin resetting someone else's password: force the popup again.
                    $fields[] = 'password_changed_at = NULL';
                    $fields[] = 'login_count_since_reset = 0';
                } else {
                    // Changing your own password: you obviously know it now.
                    $fields[] = 'password_changed_at = NOW()';
                }
            }

            $params[] = $id;
            try {
                $stmt = $pdo->prepare('UPDATE cmstest_users SET ' . implode(', ', $fields) . ' WHERE id = ?');
                $stmt->execute($params);
                $qs = $id === $myId ? ($password !== '' ? 'pw_changed=1' : 'saved=1') : 'updated=1';
                header('Location: usuarios?' . $qs);
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Esse usuário já existe.' : 'Erro ao atualizar usuário.';
            }
        }
    }

    // Lightweight password-only change: used by the forced/reminder popup that
    // can show up on any admin page, which doesn't carry the full edit form's
    // other fields. Always targets your own account.
    if ($action === 'password') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $redirectTo = $_POST['redirect_to'] ?? 'usuarios';
        if (strlen($new) < 8) {
            $error = 'A nova senha precisa ter pelo menos 8 caracteres.';
        } elseif ($new !== $confirm) {
            $error = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE cmstest_users SET password_hash = ?, password_changed_at = NOW(), login_count_since_reset = 0 WHERE id = ?');
            $stmt->execute([$hash, $myId]);
            $sep = str_contains($redirectTo, '?') ? '&' : '?';
            header('Location: ' . $redirectTo . $sep . 'pw_changed=1');
            exit;
        }
    }

    if ($action === 'delete') {
        if (!$isAdmin) { http_response_code(403); die('Você não tem permissão pra remover usuários.'); }
        $id = (int) $_POST['id'];
        if ($id !== $myId) {
            $stmt = $pdo->prepare('DELETE FROM cmstest_users WHERE id = ?');
            $stmt->execute([$id]);
        }
        header('Location: usuarios?deleted=1');
        exit;
    }
}

$users = $isAdmin ? $pdo->query('SELECT id, username, display_name, role, created_at, last_login_at FROM cmstest_users ORDER BY created_at')->fetchAll() : [];

// Non-admins only ever see/edit their own record here; admins can edit
// anyone via ?edit=<id>, or land on the blank "novo usuário" form.
$editId = $isAdmin ? (int) ($_GET['edit'] ?? 0) : $myId;
$editUser = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT id, username, display_name, avatar_url, role FROM cmstest_users WHERE id = ?');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
}

adminLayoutTop('users', $isAdmin ? 'Usuários' : 'Meu perfil');
?>

<?php if (isset($_GET['created'])): ?><div class="msg ok">Usuário criado.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="msg ok">Usuário atualizado.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="msg ok">Usuário removido.</div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($isAdmin): ?>
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
<?php endif; ?>

<div class="card" style="max-width:420px">
  <strong><?= !$isAdmin ? 'Meu perfil' : ($editUser ? 'Editar usuário' : 'Novo usuário') ?></strong>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>" />
    <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>" /><?php endif; ?>
    <label>E-mail (@mclair.com.br)</label>
    <input type="email" name="username" placeholder="nome@mclair.com.br" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required />
    <p class="hint">Só e-mails da Mclair podem ser cadastrados.</p>
    <label>Nome de exibição</label>
    <input type="text" name="display_name" value="<?= htmlspecialchars($editUser['display_name'] ?? '') ?>" placeholder="opcional" />
    <label>Foto de perfil</label>
    <input type="text" class="img-url" data-imgdrop-ratio="1:1" name="avatar_url" value="<?= htmlspecialchars($editUser['avatar_url'] ?? '') ?>" />
    <label>Senha<?= $editUser ? ' (deixe em branco pra manter a atual)' : ' (mín. 8 caracteres)' ?></label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" name="password" id="newPassword" minlength="8" <?= $editUser ? '' : 'required' ?> style="font-family:ui-monospace,monospace" />
      <button type="button" class="btn secondary" style="white-space:nowrap" onclick="generatePassword()">Gerar senha</button>
    </div>
    <p class="hint"><?= ($isAdmin && $editUser && (int)$editUser['id'] !== $myId) ? 'Trocar a senha aqui força o usuário a defini-la de novo no próximo login.' : ($editUser ? 'Deixe em branco pra manter a senha atual.' : 'Clique em "Gerar senha" pra criar uma senha forte automaticamente. Copie antes de salvar, ela não aparece de novo depois.') ?></p>
    <?php if ($isAdmin): ?>
    <label>Permissão</label>
    <select name="role">
      <option value="author" <?= ($editUser['role'] ?? '') === 'author' ? 'selected' : '' ?>>Autor (edita só os próprios posts do blog)</option>
      <option value="editor" <?= ($editUser['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor (edita todo o conteúdo)</option>
      <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador (edita tudo + gerencia usuários)</option>
    </select>
    <?php endif; ?>
    <div class="editor-actions">
      <button type="submit" class="btn" style="margin-top:16px"><?= $editUser ? 'Salvar alterações' : 'Criar usuário' ?></button>
      <?php if ($isAdmin && $editUser): ?><a href="usuarios" class="btn secondary" style="margin-top:16px;margin-left:8px">Cancelar</a><?php endif; ?>
    </div>
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
