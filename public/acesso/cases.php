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
    // Galeria: reindex (removals leave gaps), drop all-empty items — same
    // repeater convention as pages.php.
    $gallery = [];
    foreach (array_values((array) ($_POST['gallery'] ?? [])) as $row) {
        if (!is_array($row)) continue;
        $src = trim((string) ($row['src'] ?? ''));
        $caption = trim((string) ($row['caption'] ?? ''));
        if ($src === '' && $caption === '') continue;
        $gallery[] = ['src' => $src, 'caption' => $caption];
    }
    $galleryJson = json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare('UPDATE cmstest_cases SET client = ?, sector = ?, challenge = ?, solution = ?, img = ?, gallery = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$_POST['client'], $_POST['sector'], $_POST['challenge'], $_POST['solution'], $_POST['img'], $galleryJson, $_SESSION['cms_user_id'], $slug]);
    queueRebuild();
    header('Location: casos?slug=' . urlencode($slug) . '&saved=1&t=' . time());
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
  <?php if (isset($_GET['saved'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/cases/<?= urlencode($slug) ?>"><?= cmsCheckIcon() ?><span class="msg-text">Case salvo.</span></div><?php endif; ?>
  <?php $gal = json_decode($item['gallery'] ?? '', true); if (!is_array($gal)) $gal = []; ?>
  <style>
    .rep { margin-top:6px; }
    .rep-item { border:1px solid var(--line); border-radius:10px; background:#FAFBFC; padding:2px 16px 16px; margin-bottom:12px; }
    .rep-item-head { display:flex; align-items:center; justify-content:space-between; padding-top:12px; }
    .rep-item-head .rep-n { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); }
    .rep-item-head button { background:none; border:none; color:var(--red); font-weight:700; font-size:.74rem; cursor:pointer; padding:0; font-family:inherit; }
    .rep-add { margin-top:4px; }
  </style>
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
    <label>Imagem de capa</label>
    <input type="text" name="img" class="img-url" value="<?= htmlspecialchars($item['img'] ?? '') ?>" placeholder="/uploads/exemplo.jpg" />
    <label>Desafio</label>
    <textarea name="challenge" style="min-height:100px"><?= htmlspecialchars($item['challenge']) ?></textarea>
    <label>Solução</label>
    <textarea name="solution" style="min-height:100px"><?= htmlspecialchars($item['solution']) ?></textarea>

    <label>Galeria de fotos</label>
    <div class="rep grid-cols" id="galRep" data-next="<?= count($gal) ?>">
      <?php foreach ($gal as $i => $g): ?>
      <div class="rep-item">
        <div class="rep-item-head">
          <span class="rep-n">Foto <?= $i + 1 ?></span>
          <button type="button" onclick="galDel(this)">Remover</button>
        </div>
        <label>Imagem</label>
        <input type="text" class="img-url" name="gallery[<?= $i ?>][src]" value="<?= htmlspecialchars($g['src'] ?? '') ?>" placeholder="/uploads/exemplo.jpg" />
        <label>Legenda</label>
        <input type="text" name="gallery[<?= $i ?>][caption]" value="<?= htmlspecialchars($g['caption'] ?? '') ?>" />
      </div>
      <?php endforeach; ?>
    </div>
    <template id="galTpl">
      <div class="rep-item">
        <div class="rep-item-head">
          <span class="rep-n">Nova foto</span>
          <button type="button" onclick="galDel(this)">Remover</button>
        </div>
        <label>Imagem</label>
        <input type="text" class="img-url" name="gallery[__i__][src]" value="" placeholder="/uploads/exemplo.jpg" />
        <label>Legenda</label>
        <input type="text" name="gallery[__i__][caption]" value="" />
      </div>
    </template>
    <button type="button" class="btn secondary rep-add" onclick="galAdd()">+ Adicionar foto</button>

    <div><button type="submit" class="btn" style="margin-top:16px">Salvar</button></div>
  </form>
  <script>
  // Same repeater pattern as pages.php.
  function galAdd() {
    var rep = document.getElementById('galRep');
    var i = parseInt(rep.dataset.next, 10);
    rep.dataset.next = i + 1;
    rep.insertAdjacentHTML('beforeend', document.getElementById('galTpl').innerHTML.replace(/__i__/g, i));
    imgDropInit(rep); // enhance the freshly cloned image-URL input
  }
  function galDel(btn) {
    btn.closest('.rep-item').remove();
  }
  </script>
  </div>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
