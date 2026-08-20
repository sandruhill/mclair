<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slug) {
    $stmt = $pdo->prepare('UPDATE cmstest_services SET title = ?, headline = ?, intro = ?, full_desc = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$_POST['title'], $_POST['headline'], $_POST['intro'], $_POST['full_desc'], $_SESSION['cms_user_id'], $slug]);
    queueRebuild();
    header('Location: servicos?slug=' . urlencode($slug) . '&saved=1');
    exit;
}

if ($slug) {
    $stmt = $pdo->prepare('
      SELECT s.*, u.username AS updated_by_name
      FROM cmstest_services s LEFT JOIN cmstest_users u ON u.id = s.updated_by
      WHERE s.slug = ?
    ');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); die('Serviço não encontrado'); }
}
$list = $pdo->query('SELECT slug, title, num, image FROM cmstest_services ORDER BY num')->fetchAll();

adminLayoutTop('services', $slug ? "Editando: {$item['title']}" : 'Serviços', $slug ? ['label' => 'Serviços', 'href' => '/admin/servicos'] : null);
?>

<?php if (!$slug): ?>
  <?php
  $withImg = count(array_filter(array_column($list, 'image')));
  adminStatCards([
      ['num' => count($list), 'lbl' => 'Serviços no total'],
      ['num' => $withImg, 'lbl' => 'Com imagem de capa'],
  ]);
  ?>
  <div class="tablecard">
  <div class="tablecard-head"><div><strong>Serviços</strong><span class="count"><?= count($list) ?></span></div></div>
  <table class="dt">
  <tr><th>Capa</th><th class="s">#</th><th>Título</th><th>Ações</th></tr>
  <?php foreach ($list as $s): ?>
  <tr>
    <td><?php if ($s['image']): ?><img class="dt-thumb" src="<?= htmlspecialchars($s['image']) ?>" alt="" /><?php else: ?><div class="dt-thumb-empty"></div><?php endif; ?></td>
    <td><?= htmlspecialchars($s['num']) ?></td>
    <td><?= htmlspecialchars($s['title']) ?></td>
    <td class="dt-actions"><a href="servicos?slug=<?= urlencode($s['slug']) ?>">editar</a></td>
  </tr>
  <?php endforeach; ?>
  </table>
  </div>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>
  <div class="card">
  <p style="font-size:.82rem;color:var(--ink-3);margin-top:0">
    <?php if ($item['updated_by_name']): ?>Última edição por <strong><?= htmlspecialchars($item['updated_by_name']) ?></strong><?php else: ?>Ainda sem edições registradas<?php endif; ?>
  </p>
  <form method="post">
    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />
    <label>Título</label>
    <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" />
    <label>Headline</label>
    <input type="text" name="headline" value="<?= htmlspecialchars($item['headline']) ?>" />
    <label>Introdução</label>
    <textarea name="intro" style="min-height:100px"><?= htmlspecialchars($item['intro']) ?></textarea>
    <label>Descrição completa</label>
    <textarea name="full_desc" style="min-height:140px"><?= htmlspecialchars($item['full_desc']) ?></textarea>
    <button type="submit" class="btn" style="margin-top:16px">Salvar</button>
  </form>
  </div>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
