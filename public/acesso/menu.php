<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();


$MENU_PAGES = [
    'homepage'  => 'Home',
    'sobre'     => 'Sobre',
    'clientes'  => 'Clientes',
    'contato'   => 'Contato',
    'mentorias' => 'Mentorias',
];
$MENU_TYPES = [
    'singleton'  => 'Página fixa',
    'service'    => 'Serviço',
    'blog_index' => 'Blog',
    'custom'     => 'Link personalizado',
];

$services = [];
foreach ($pdo->query('SELECT slug, title FROM cmstest_services ORDER BY num') as $s) {
    $services[$s['slug']] = $s['title'];
}

// Rewrites sort_order = position for every sibling of $parentId (self-healing
// against gaps/dupes), returns the ordered sibling id list.
function menuReorderSiblings(PDO $pdo, ?int $parentId): array {
    $stmt = $pdo->prepare('SELECT id FROM cmstest_menu WHERE parent_id <=> ? ORDER BY sort_order, id');
    $stmt->execute([$parentId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $upd = $pdo->prepare('UPDATE cmstest_menu SET sort_order = ? WHERE id = ?');
    foreach ($ids as $i => $mid) $upd->execute([$i, $mid]);
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        // FK is ON DELETE SET NULL: children become top-level automatically.
        $pdo->prepare('DELETE FROM cmstest_menu WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        queueRebuild();
        header('Location: menu?removed=1&t=' . time());
        exit;
    }

    if ($action === 'move') {
        $id  = (int) ($_POST['id'] ?? 0);
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $stmt = $pdo->prepare('SELECT parent_id FROM cmstest_menu WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $ids = menuReorderSiblings($pdo, $parentId);
            $pos = array_search($id, $ids, true);
            $swap = $pos + $dir;
            if ($pos !== false && isset($ids[$swap])) {
                $upd = $pdo->prepare('UPDATE cmstest_menu SET sort_order = ? WHERE id = ?');
                $upd->execute([$swap, $id]);
                $upd->execute([$pos, $ids[$swap]]);
            }
        }
        queueRebuild();
        header('Location: menu');
        exit;
    }

    if ($action === 'save') {
        $id    = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $type  = (string) ($_POST['link_type'] ?? '');
        if ($label === '' || !isset($MENU_TYPES[$type])) {
            header('Location: menu?err=1' . ($id ? '&edit=' . $id : ''));
            exit;
        }

        // link_value depends on the type; validate against the known lists.
        $value = '';
        if ($type === 'singleton') {
            $value = (string) ($_POST['lv_singleton'] ?? '');
            if (!isset($MENU_PAGES[$value])) { header('Location: menu?err=1'); exit; }
        } elseif ($type === 'service') {
            $value = (string) ($_POST['lv_service'] ?? '');
            if (!isset($services[$value])) { header('Location: menu?err=1'); exit; }
        } elseif ($type === 'custom') {
            $value = trim((string) ($_POST['lv_custom'] ?? ''));
            if ($value === '') { header('Location: menu?err=1' . ($id ? '&edit=' . $id : '')); exit; }
        } // blog_index: stays ''

        // Parent must be an existing TOP-LEVEL item and never the item itself.
        // Options are top-level only, so excluding self also rules out cycles:
        // no descendant of this item can be top-level.
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
        if ($parentId !== null) {
            if ($parentId === $id) $parentId = null;
            else {
                $stmt = $pdo->prepare('SELECT id FROM cmstest_menu WHERE id = ? AND parent_id IS NULL');
                $stmt->execute([$parentId]);
                if (!$stmt->fetch()) $parentId = null;
            }
        }

        if ($id) {
            $stmt = $pdo->prepare('SELECT parent_id FROM cmstest_menu WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if (!$old) { header('Location: menu'); exit; }
            $oldParent = $old['parent_id'] !== null ? (int) $old['parent_id'] : null;
            $pdo->prepare('UPDATE cmstest_menu SET label = ?, link_type = ?, link_value = ?, parent_id = ? WHERE id = ?')
                ->execute([$label, $type, $value, $parentId, $id]);
            if ($oldParent !== $parentId) {
                // moved to another parent: send to the end of the new siblings
                $pdo->prepare('UPDATE cmstest_menu SET sort_order = ? WHERE id = ?')
                    ->execute([count(menuReorderSiblings($pdo, $parentId)), $id]);
                menuReorderSiblings($pdo, $parentId);
                menuReorderSiblings($pdo, $oldParent);
            }
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM cmstest_menu WHERE parent_id <=> ?');
            $stmt->execute([$parentId]);
            $pdo->prepare('INSERT INTO cmstest_menu (parent_id, label, link_type, link_value, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$parentId, $label, $type, $value, (int) $stmt->fetchColumn()]);
        }
        queueRebuild();
        header('Location: menu?saved=1&t=' . time());
        exit;
    }
}

// ---- Load the tree ----
$items = $pdo->query('SELECT id, parent_id, label, link_type, link_value FROM cmstest_menu ORDER BY sort_order, id')->fetchAll();
$byParent = [];
foreach ($items as $it) $byParent[$it['parent_id'] ?? 0][] = $it;

$editId   = (int) ($_GET['edit'] ?? 0);
$editItem = null;
foreach ($items as $it) if ((int) $it['id'] === $editId) $editItem = $it;

function menuTargetBadge(array $it, array $pages, array $services): string {
    switch ($it['link_type']) {
        case 'singleton':  return 'Página: ' . ($pages[$it['link_value']] ?? $it['link_value']);
        case 'service':    return 'Serviço: ' . ($services[$it['link_value']] ?? $it['link_value']);
        case 'blog_index': return 'Blog';
        default:           return 'Link: ' . $it['link_value'];
    }
}

function menuRenderRows(array $byParent, int $parentKey, int $depth, array $pages, array $services): void {
    $sibs = $byParent[$parentKey] ?? [];
    $n = count($sibs);
    foreach ($sibs as $i => $it) {
        $id = (int) $it['id'];
        echo '<div class="mi" style="margin-left:' . ($depth * 34) . 'px">';
        echo '<div class="mi-info"><strong>' . htmlspecialchars($it['label']) . '</strong>';
        echo '<span class="badge">' . htmlspecialchars(menuTargetBadge($it, $pages, $services)) . '</span></div>';
        echo '<div class="mi-actions">';
        echo '<form method="post"><input type="hidden" name="action" value="move" /><input type="hidden" name="id" value="' . $id . '" /><input type="hidden" name="dir" value="up" />'
           . '<button type="submit" title="Mover para cima"' . ($i === 0 ? ' disabled' : '') . '>&uarr;</button></form>';
        echo '<form method="post"><input type="hidden" name="action" value="move" /><input type="hidden" name="id" value="' . $id . '" /><input type="hidden" name="dir" value="down" />'
           . '<button type="submit" title="Mover para baixo"' . ($i === $n - 1 ? ' disabled' : '') . '>&darr;</button></form>';
        echo '<a href="menu?edit=' . $id . '">editar</a>';
        echo '<form method="post">'
           . '<input type="hidden" name="action" value="delete" /><input type="hidden" name="id" value="' . $id . '" />'
           . '<button type="button" class="del" data-confirm="Remover? Os itens filhos passam a ficar no topo do menu." data-yes="sim, remover" onclick="askConfirm(this)">remover</button></form>';
        echo '</div></div>';
        menuRenderRows($byParent, $id, $depth + 1, $pages, $services);
    }
}

adminLayoutTop('menu', 'Menu', null, 'https://mclair.com.br/');
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/"><?= cmsCheckIcon() ?><span class="msg-text">Item salvo.</span></div><?php endif; ?>
<?php if (isset($_GET['removed'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/"><?= cmsCheckIcon() ?><span class="msg-text">Item removido. Os itens filhos, se havia, agora estão no topo do menu.</span></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="msg err">Preencha o rótulo e o destino do link antes de salvar.</div><?php endif; ?>

<style>
  .mi { display:flex; align-items:center; justify-content:space-between; gap:12px;
        background:var(--paper); border:1px solid var(--line); border-radius:10px;
        padding:10px 14px; margin-bottom:8px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .mi-info { display:flex; align-items:center; gap:10px; min-width:0; }
  .mi-info strong { font-size:.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .mi-actions { display:flex; align-items:center; gap:10px; flex-shrink:0; }
  .mi-actions form { display:inline; margin:0; }
  .mi-actions button, .mi-actions a { background:none; border:none; padding:0; cursor:pointer;
        font-size:.78rem; font-weight:700; font-family:inherit; color:var(--ink-3); }
  .mi-actions button:hover, .mi-actions a:hover { color:var(--ink); }
  .mi-actions button[disabled] { opacity:.25; cursor:default; }
  .mi-actions a { color:var(--red); }
  .mi-actions button.del { color:#9a1f1f; text-decoration:underline; }
</style>

<div class="editor-grid">
  <div>
    <?php if (!$items): ?>
      <div class="card"><p class="hint" style="font-size:.85rem">Nenhum item de menu ainda. Adicione o primeiro pelo formulário ao lado.</p></div>
    <?php else: ?>
      <?php menuRenderRows($byParent, 0, 0, $MENU_PAGES, $services); ?>
      <p class="hint">Use &uarr;/&darr; para reordenar entre irmãos. Itens indentados são sub-itens do item acima.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <strong style="font-size:.9rem"><?= $editItem ? 'Editando: ' . htmlspecialchars($editItem['label']) : 'Adicionar item' ?></strong>
    <form method="post">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= $editItem ? (int) $editItem['id'] : 0 ?>" />

      <label>Rótulo</label>
      <input type="text" name="label" value="<?= htmlspecialchars($editItem['label'] ?? '') ?>" placeholder="Ex.: Serviços" />

      <label>Tipo de link</label>
      <select name="link_type" id="linkType" onchange="setLinkType(this.value)">
        <?php foreach ($MENU_TYPES as $t => $tl): ?>
        <option value="<?= $t ?>"<?= ($editItem['link_type'] ?? 'singleton') === $t ? ' selected' : '' ?>><?= $tl ?></option>
        <?php endforeach; ?>
      </select>

      <div id="pane_singleton">
        <label>Página</label>
        <select name="lv_singleton">
          <?php foreach ($MENU_PAGES as $s => $name): ?>
          <option value="<?= $s ?>"<?= ($editItem['link_type'] ?? '') === 'singleton' && $editItem['link_value'] === $s ? ' selected' : '' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="pane_service">
        <label>Serviço</label>
        <select name="lv_service">
          <?php foreach ($services as $s => $title): ?>
          <option value="<?= htmlspecialchars($s) ?>"<?= ($editItem['link_type'] ?? '') === 'service' && $editItem['link_value'] === $s ? ' selected' : '' ?>><?= htmlspecialchars($title) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="pane_custom">
        <label>URL</label>
        <input type="text" name="lv_custom" value="<?= ($editItem['link_type'] ?? '') === 'custom' ? htmlspecialchars($editItem['link_value']) : '' ?>" placeholder="/contato ou https://..." />
        <p class="hint">Caminho do site (ex.: /servicos) ou endereço completo.</p>
      </div>

      <label>Item pai</label>
      <select name="parent_id">
        <option value="">— nenhum, fica no topo —</option>
        <?php foreach ($byParent[0] ?? [] as $top): if ((int) $top['id'] === $editId) continue; ?>
        <option value="<?= (int) $top['id'] ?>"<?= $editItem && (int) $editItem['parent_id'] === (int) $top['id'] ? ' selected' : '' ?>><?= htmlspecialchars($top['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Escolha um item do topo para criar um sub-item dele.</p>

      <div style="margin-top:20px">
        <button type="submit" class="btn"><?= $editItem ? 'Salvar' : 'Adicionar' ?></button>
        <?php if ($editItem): ?><a href="menu" class="btn secondary" style="margin-left:8px">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
function setLinkType(t) {
  document.getElementById('pane_singleton').style.display = t === 'singleton' ? '' : 'none';
  document.getElementById('pane_service').style.display   = t === 'service'   ? '' : 'none';
  document.getElementById('pane_custom').style.display    = t === 'custom'    ? '' : 'none';
}
setLinkType(document.getElementById('linkType').value);
</script>

<?php adminLayoutBottom(); ?>
