<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE cmstest_blog_posts SET title = ?, subtitle = ?, category = ?, post_date = ?, featured_image = ?, content_md = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([
        $_POST['title'], $_POST['subtitle'], $_POST['category'], $_POST['post_date'],
        $_POST['featured_image'], $_POST['content_md'], $_SESSION['cms_user_id'], $slug,
    ]);
    header('Location: edit.php?slug=' . urlencode($slug) . '&saved=1');
    exit;
}

$stmt = $pdo->prepare('
  SELECT p.*, u.username AS updated_by_name
  FROM cmstest_blog_posts p
  LEFT JOIN cmstest_users u ON u.id = p.updated_by
  WHERE p.slug = ?
');
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) { http_response_code(404); die('Post não encontrado'); }

$categories = ['assessoria-de-imprensa','marketing-de-autoridade','branding-estrategico','marketing-digital','comunicacao','marca-pessoal','negocios','geral'];

adminLayoutTop('blog', 'Editando post');
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>

<div class="card">
<p style="font-size:.82rem;color:var(--ink-3);margin-top:0">
  <?php if ($post['updated_by_name']): ?>Última edição por <strong><?= htmlspecialchars($post['updated_by_name']) ?></strong><?php else: ?>Ainda sem edições registradas<?php endif; ?>
</p>
<form method="post">
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label>Título</label>
      <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" />
    </div>
    <div>
      <label>Categoria</label>
      <select name="category">
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat ?>" <?= $post['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Data de postagem</label>
      <input type="date" name="post_date" value="<?= htmlspecialchars($post['post_date']) ?>" />
    </div>
    <div>
      <label>Imagem de capa (URL)</label>
      <input type="text" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="/blog-images/exemplo.jpg" />
    </div>
  </div>

  <label>Subtítulo</label>
  <input type="text" name="subtitle" value="<?= htmlspecialchars($post['subtitle']) ?>" />

  <?php if ($post['featured_image']): ?>
    <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="" style="margin-top:12px;max-width:240px;border-radius:8px;display:block" />
  <?php endif; ?>

  <label>Conteúdo (markdown — links [texto](url), negrito **texto**)</label>
  <textarea name="content_md" style="min-height:420px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars($post['content_md']) ?></textarea>

  <button type="submit" class="btn" style="margin-top:16px">Salvar</button>
  <a href="index.php" class="btn secondary" style="margin-top:16px;margin-left:8px">Cancelar</a>
</form>
</div>

<?php adminLayoutBottom(); ?>
