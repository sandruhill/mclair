<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM cmstest_cases WHERE slug = ?');
    $stmt->execute([$_POST['slug']]);
    queueRebuild();
    header('Location: casos?deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slug) {
    $stmt = $pdo->prepare('UPDATE cmstest_cases SET client = ?, sector = ?, challenge = ?, solution = ?, img = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$_POST['client'], $_POST['sector'], $_POST['challenge'], $_POST['solution'], $_POST['img'], $_SESSION['cms_user_id'], $slug]);
    queueRebuild();
    header('Location: casos?slug=' . urlencode($slug) . '&saved=1');
    exit;
}

if ($slug) {
    $stmt = $pdo->prepare('
      SELECT c.*, u.username AS updated_by_name
      FROM cmstest_cases c LEFT JOIN cmstest_users u ON u.id = c.updated_by
      WHERE c.slug = ?
    ');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); die('Case não encontrado'); }
}
$list = $pdo->query('SELECT slug, client, sector, num, img FROM cmstest_cases ORDER BY num')->fetchAll();

adminLayoutTop('cases', $slug ? "Editando: {$item['client']}" : 'Cases', $slug ? ['label' => 'Cases', 'href' => '/acesso/casos'] : null);
?>

<?php if (!$slug): ?>
  <?php if (isset($_GET['deleted'])): ?><div class="msg ok">Case removido.</div><?php endif; ?>
  <?php
  $sectors = count(array_unique(array_filter(array_column($list, 'sector'))));
  adminStatCards([
      ['num' => count($list), 'lbl' => 'Cases no total'],
      ['num' => $sectors, 'lbl' => 'Setores atendidos'],
  ]);
  ?>
  <div class="tablecard">
  <div class="tablecard-head"><div><strong>Cases</strong><span class="count"><?= count($list) ?></span></div></div>
  <table class="dt">
  <tr><th>Capa</th><th class="s">#</th><th>Cliente</th><th>Setor</th><th>Ações</th></tr>
  <?php foreach ($list as $c): ?>
  <tr>
    <td><?php if ($c['img']): ?><img class="dt-thumb" src="<?= htmlspecialchars($c['img']) ?>" alt="" /><?php else: ?><div class="dt-thumb-empty"></div><?php endif; ?></td>
    <td><?= htmlspecialchars($c['num']) ?></td>
    <td><?= htmlspecialchars($c['client']) ?></td>
    <td><span class="badge"><?= htmlspecialchars($c['sector']) ?></span></td>
    <td class="dt-actions">
      <a href="casos?slug=<?= urlencode($c['slug']) ?>">editar</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="slug" value="<?= htmlspecialchars($c['slug']) ?>" />
        <button type="button" class="del" data-confirm="Apagar este case?" onclick="askConfirm(this)">apagar</button>
      </form>
    </td>
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
    <label>Cliente</label>
    <input type="text" name="client" value="<?= htmlspecialchars($item['client']) ?>" />
    <label>Setor</label>
    <input type="text" name="sector" value="<?= htmlspecialchars($item['sector']) ?>" />
    <label>Imagem de capa (URL)</label>
    <input type="text" name="img" class="img-url" value="<?= htmlspecialchars($item['img'] ?? '') ?>" placeholder="/uploads/exemplo.jpg" />
    <label>Desafio</label>
    <textarea name="challenge" style="min-height:100px"><?= htmlspecialchars($item['challenge']) ?></textarea>
    <label>Solução</label>
    <textarea name="solution" style="min-height:100px"><?= htmlspecialchars($item['solution']) ?></textarea>
    <button type="submit" class="btn" style="margin-top:16px">Salvar</button>
  </form>
  </div>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
