<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();

$PAGES = [
    'homepage'  => 'Home',
    'sobre'     => 'Sobre',
    'clientes'  => 'Clientes',
    'contato'   => 'Contato',
    'mentorias' => 'Mentorias',
    'llms'      => 'LLMs.txt',
];

$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
if ($slug !== '' && !isset($PAGES[$slug])) { http_response_code(404); die('Página não encontrada'); }

$item = null;
if ($slug) {
    $stmt = $pdo->prepare('SELECT slug, data FROM cmstest_singletons WHERE slug = ?');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); die('Página não encontrada'); }
}

$errors = [];      // keys with invalid JSON on POST
$fields = [];      // key => ['json' => bool, 'value' => string to show in the form]

if ($slug && $slug !== 'llms') {
    $decoded = json_decode($item['data'], true);
    foreach ($decoded as $key => $orig) {
        $fields[$key] = [
            'json'  => is_array($orig),
            'value' => is_array($orig)
                ? json_encode($orig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) $orig,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slug) {
    if ($slug === 'llms') {
        $data = json_encode((string) ($_POST['content'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')->execute([$data, $slug]);
        queueRebuild();
        header('Location: paginas?slug=' . urlencode($slug) . '&saved=1');
        exit;
    }

    $new = [];
    foreach ($fields as $key => $f) {
        $posted = (string) ($_POST['f'][$key] ?? '');
        $fields[$key]['value'] = $posted; // keep the user's text on redisplay
        if ($f['json']) {
            $val = json_decode($posted, true);
            if ($val === null && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = $key;
            } else {
                $new[$key] = $val;
            }
        } else {
            $new[$key] = $posted;
        }
    }

    if (!$errors) {
        $data = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')->execute([$data, $slug]);
        queueRebuild();
        header('Location: paginas?slug=' . urlencode($slug) . '&saved=1');
        exit;
    }
}

adminLayoutTop('pages', $slug ? "Editando: {$PAGES[$slug]}" : 'Páginas', $slug ? ['label' => 'Páginas', 'href' => '/admin/paginas'] : null);
?>

<?php if (!$slug): ?>
  <div class="tablecard">
  <div class="tablecard-head"><div><strong>Páginas do site</strong><span class="count"><?= count($PAGES) ?></span></div></div>
  <table class="dt">
  <tr><th>Página</th><th>Ações</th></tr>
  <?php foreach ($PAGES as $s => $name): ?>
  <tr>
    <td><?= htmlspecialchars($name) ?></td>
    <td class="dt-actions"><a href="paginas?slug=<?= urlencode($s) ?>">editar</a></td>
  </tr>
  <?php endforeach; ?>
  </table>
  </div>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="msg err">JSON inválido no campo: <?= htmlspecialchars(implode(', ', $errors)) ?>. Nada foi salvo — corrija e tente de novo.</div>
  <?php endif; ?>
  <div class="card">
  <form method="post">
    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

    <?php if ($slug === 'llms'): ?>
      <label style="margin-top:0">Conteúdo do /llms.txt</label>
      <textarea name="content" style="min-height:480px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars((string) json_decode($item['data'], true)) ?></textarea>
    <?php else: ?>
      <?php $first = true; foreach ($fields as $key => $f): ?>
        <label<?= $first ? ' style="margin-top:0"' : '' ?>><?= htmlspecialchars($key) ?></label>
        <?php if ($f['json']): ?>
          <textarea name="f[<?= htmlspecialchars($key) ?>]" style="min-height:220px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars($f['value']) ?></textarea>
          <p class="hint">Formato JSON — não altere a estrutura de chaves, só os valores.</p>
        <?php elseif (mb_strlen($f['value']) > 120 || strpos($f['value'], "\n") !== false): ?>
          <textarea name="f[<?= htmlspecialchars($key) ?>]" style="min-height:100px"><?= htmlspecialchars($f['value']) ?></textarea>
        <?php else: ?>
          <input type="text" name="f[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($f['value']) ?>" />
        <?php endif; ?>
      <?php $first = false; endforeach; ?>
    <?php endif; ?>

    <button type="submit" class="btn" style="margin-top:16px">Salvar</button>
    <a href="paginas" class="btn secondary" style="margin-top:16px;margin-left:8px">Cancelar</a>
  </form>
  </div>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
