<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM cmstest_blog_posts WHERE slug = ?');
    $stmt->execute([$_POST['slug']]);
    header('Location: index.php?deleted=1');
    exit;
}

$perPage = 20;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int) $pdo->query('SELECT COUNT(*) FROM cmstest_blog_posts')->fetchColumn();
$lastPage = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare('SELECT slug, title, featured_image, image_url, post_date, category, author FROM cmstest_blog_posts ORDER BY post_date DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

adminLayoutTop('blog', 'Posts do blog');
?>

<?php if (isset($_GET['deleted'])): ?><div class="msg ok">Post removido do banco.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>

<table class="dt">
<tr><th>Capa</th><th>Título</th><th>Categoria</th><th>Data</th><th>Autor</th><th>Ações</th></tr>
<?php foreach ($posts as $p): $img = $p['featured_image'] ?: $p['image_url']; ?>
<tr>
  <td><?php if ($img): ?><img class="dt-thumb" src="<?= htmlspecialchars($img) ?>" alt="" /><?php else: ?><div class="dt-thumb-empty"></div><?php endif; ?></td>
  <td><?= htmlspecialchars($p['title']) ?></td>
  <td><span class="badge"><?= htmlspecialchars($p['category']) ?></span></td>
  <td><?= htmlspecialchars($p['post_date']) ?></td>
  <td><?= htmlspecialchars($p['author']) ?></td>
  <td class="dt-actions">
    <a href="edit.php?slug=<?= urlencode($p['slug']) ?>">editar</a>
    <form method="post" style="display:inline" onsubmit="return confirm('Apagar este post do banco?');">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>" />
      <button type="submit" class="del">apagar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($lastPage > 1): ?>
<div class="pagination">
  <?php for ($n = 1; $n <= $lastPage; $n++): ?>
    <a href="?p=<?= $n ?>" class="<?= $n === $page ? 'active' : '' ?>"><?= $n ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
