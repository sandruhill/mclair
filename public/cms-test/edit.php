<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE cmstest_blog_posts SET title = ?, subtitle = ?, content_md = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$_POST['title'], $_POST['subtitle'], $_POST['content_md'], $_SESSION['cms_user_id'], $slug]);
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
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex" />
<title>Editar — <?= htmlspecialchars($post['title']) ?></title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; background: #F1EBDD; color: #211B14; }
  h1 { color: #C8102E; font-size: 1.3rem; }
  label { display: block; font-weight: 700; margin: 16px 0 6px; font-size: 0.85rem; }
  input, textarea { width: 100%; padding: 10px; border: 1px solid #D6C9A8; border-radius: 6px; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; }
  textarea { min-height: 300px; }
  button { margin-top: 16px; background: #C8102E; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; }
  .saved { background: #2F7D4F; color: #fff; padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
  a.back { display: inline-block; margin-bottom: 16px; }
</style>
</head>
<body>
<a class="back" href="index.php">&larr; voltar</a>
<?php if (isset($_GET['saved'])): ?><div class="saved">Salvo no banco de dados de teste.</div><?php endif; ?>
<h1>Editando (banco de teste): <?= htmlspecialchars($post['title']) ?></h1>
<p style="font-size:.85rem;color:#665D4D">
  Logado como <strong><?= htmlspecialchars($_SESSION['cms_username']) ?></strong>
  <?php if ($post['updated_by_name']): ?> · última edição por <strong><?= htmlspecialchars($post['updated_by_name']) ?></strong><?php endif; ?>
</p>
<form method="post">
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />
  <label>Título</label>
  <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" />
  <label>Subtítulo</label>
  <input type="text" name="subtitle" value="<?= htmlspecialchars($post['subtitle']) ?>" />
  <label>Conteúdo (markdown)</label>
  <textarea name="content_md"><?= htmlspecialchars($post['content_md']) ?></textarea>
  <button type="submit">Salvar no banco</button>
</form>
</body>
</html>
