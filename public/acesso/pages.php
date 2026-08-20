<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();

$PAGES = [
    'homepage'  => 'Home',
    'sobre'     => 'Sobre',
    'clientes'  => 'Clientes',
    'contato'   => 'Contato',
    'mentorias' => 'Mentorias',
    'llms'      => 'LLMs.txt',
];

// Friendly labels for known keys; anything unknown falls back to the raw key.
$GLOBALS['PAGES_LABELS'] = [
    // top-level
    'heroVideoId' => 'ID do vídeo do hero (YouTube)',
    'stats' => 'Estatísticas', 'testimonials' => 'Depoimentos', 'seo' => 'SEO',
    'valores' => 'Valores', 'equipe' => 'Equipe', 'clients' => 'Clientes',
    'contactInfo' => 'Informações de contato', 'faqs' => 'Perguntas frequentes',
    'bolderLevels' => 'Níveis Bolder', 'bolderPressLevels' => 'Níveis Bolder Press',
    // item fields
    'n' => 'Número', 's' => 'Sufixo', 'label' => 'Legenda',
    'quote' => 'Depoimento', 'name' => 'Nome', 'role' => 'Cargo', 'photo' => 'Foto',
    'icon' => 'Ícone', 'title' => 'Título', 'desc' => 'Descrição',
    'nome' => 'Nome', 'cargo' => 'Cargo', 'initials' => 'Iniciais', 'bio' => 'Bio',
    'logo' => 'Logo', 'case' => 'Case',
    'q' => 'Pergunta', 'a' => 'Resposta',
    'phone' => 'Telefone', 'email' => 'E-mail',
    'description' => 'Descrição', 'keywords' => 'Palavras-chave', 'ogImage' => 'Imagem OG',
];

function pagesLabel(string $key): string {
    return $GLOBALS['PAGES_LABELS'][$key] ?? $key;
}

// Keys whose value is an image URL -> get the drag-and-drop upload widget.
function pagesIsImgKey(string $key): bool {
    return in_array($key, ['photo', 'logo', 'ogImage'], true);
}

function pagesIsList(array $a): bool {
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

// Keys whose sample values read as prose -> textarea instead of input.
function pagesLongKeys(array $rows): array {
    $long = [];
    foreach ($rows as $row) {
        foreach ((array) $row as $sk => $v) {
            if (mb_strlen((string) $v) > 60 || strpos((string) $v, "\n") !== false) $long[$sk] = true;
        }
    }
    return $long;
}

function pagesMdbar(): void {
?>
<div class="mdbar">
  <button type="button" title="Negrito" onclick="mdWrap(this,'**','**','texto em negrito')"><b>B</b></button>
  <button type="button" title="Itálico" onclick="mdWrap(this,'*','*','texto em itálico')"><i>I</i></button>
  <span class="div"></span>
  <button type="button" title="Título (H2)" onclick="mdLine(this,'## ')">H2</button>
  <button type="button" title="Subtítulo (H3)" onclick="mdLine(this,'### ')">H3</button>
  <span class="div"></span>
  <button type="button" title="Link" onclick="mdLink(this)">🔗</button>
  <span class="div"></span>
  <button type="button" title="Lista" onclick="mdLine(this,'- ')">•&nbsp;Lista</button>
  <button type="button" title="Lista numerada" onclick="mdLine(this,'1. ', true)">1.&nbsp;Lista</button>
  <button type="button" title="Citação" onclick="mdLine(this,'> ')">&ldquo;&nbsp;Citação</button>
</div>
<?php
}

// One label + input/textarea per key, laid out on a responsive grid.
// $withBar adds the Markdown toolbar to prose fields (repeater items).
function pagesFieldsGrid(string $prefix, array $keys, array $values, array $longKeys, bool $withBar): void {
    echo '<div class="rep-fields">';
    foreach ($keys as $sk) {
        $val  = (string) ($values[$sk] ?? '');
        $long = isset($longKeys[$sk]);
        $name = htmlspecialchars($prefix . '[' . $sk . ']');
        echo '<div' . ($long ? ' class="fw"' : '') . '>';
        echo '<label>' . htmlspecialchars(pagesLabel((string) $sk)) . '</label>';
        if ($long && $withBar) {
            pagesMdbar();
            echo '<textarea name="' . $name . '" style="min-height:110px;font-size:.88rem">' . htmlspecialchars($val) . '</textarea>';
        } elseif ($long) {
            echo '<textarea name="' . $name . '" style="min-height:90px;font-size:.88rem">' . htmlspecialchars($val) . '</textarea>';
        } else {
            $imgClass = pagesIsImgKey((string) $sk) ? ' class="img-url"' : '';
            echo '<input type="text"' . $imgClass . ' name="' . $name . '" value="' . htmlspecialchars($val) . '" />';
        }
        echo '</div>';
    }
    echo '</div>';
}

$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
if ($slug !== '' && !isset($PAGES[$slug])) { http_response_code(404); die('Página não encontrada'); }

$item = null;
$decoded = null;
if ($slug) {
    $stmt = $pdo->prepare('SELECT slug, data FROM cmstest_singletons WHERE slug = ?');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); die('Página não encontrada'); }
    if ($slug !== 'llms') $decoded = json_decode($item['data'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slug) {
    if ($slug === 'llms') {
        $data = json_encode((string) ($_POST['content'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')->execute([$data, $slug]);
        queueRebuild();
        header('Location: paginas?slug=' . urlencode($slug) . '&saved=1&t=' . time());
        exit;
    }

    // Rebuild the object following the ORIGINAL key order/shape from the DB row.
    $new = [];
    foreach ($decoded as $key => $orig) {
        $posted = $_POST['f'][$key] ?? null;
        if (!is_array($orig)) {
            $new[$key] = (string) (is_array($posted) ? '' : ($posted ?? ''));
        } elseif (pagesIsList($orig)) {
            // Repeater: reindex (client-side removals leave gaps), drop all-empty items.
            $tplKeys = array_keys($orig[0] ?? []);
            $items = [];
            foreach (array_values(is_array($posted) ? $posted : []) as $row) {
                if (!is_array($row)) continue;
                if (!$tplKeys) $tplKeys = array_keys($row);
                $it = [];
                $empty = true;
                foreach ($tplKeys as $sk) {
                    $v = (string) ($row[$sk] ?? '');
                    if (trim($v) !== '') $empty = false;
                    $it[$sk] = $v;
                }
                if (!$empty) $items[] = $it;
            }
            $new[$key] = $items;
        } else {
            // Single object: every original key survives, blank input -> empty string.
            $obj = [];
            foreach (array_keys($orig) as $sk) {
                $obj[$sk] = (string) (is_array($posted) ? ($posted[$sk] ?? '') : '');
            }
            $new[$key] = $obj;
        }
    }

    $data = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')->execute([$data, $slug]);
    queueRebuild();
    header('Location: paginas?slug=' . urlencode($slug) . '&saved=1&t=' . time());
    exit;
}

$pagesLiveUrl = !$slug ? null : ($slug === 'homepage' ? 'https://mclair.com.br/' : ($slug === 'llms' ? null : 'https://mclair.com.br/' . urlencode($slug)));
adminLayoutTop('pages', $slug ? "Editando: {$PAGES[$slug]}" : 'Páginas', $slug ? ['label' => 'Páginas', 'href' => '/acesso/paginas'] : null, $pagesLiveUrl);
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
  <?php if (isset($_GET['saved'])): ?><div class="msg ok"<?= $pagesLiveUrl ? ' id="savedMsg" data-live-url="' . htmlspecialchars($pagesLiveUrl) . '"' : '' ?>><?= cmsCheckIcon() ?><span class="msg-text">Página salva.</span></div><?php endif; ?>

  <style>
    .objbox, .rep-item { border:1px solid var(--line); border-radius:10px; background:#FAFBFC; padding:2px 16px 16px; }
    .objbox { margin-top:6px; }
    .rep { margin-top:6px; }
    .rep-item { margin-bottom:12px; }
    .rep-item-head { display:flex; align-items:center; justify-content:space-between; padding-top:12px; }
    .rep-item-head .rep-n { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); }
    .rep-item-head button { background:none; border:none; color:var(--red); font-weight:700; font-size:.74rem; cursor:pointer; padding:0; font-family:inherit; }
    .rep-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap:0 14px; }
    .rep-fields .fw { grid-column:1 / -1; }
    .rep-add { margin-top:4px; }
    label.sec { font-size:.82rem; color:var(--ink); margin-top:22px; }
  </style>

  <div class="card">
  <form method="post">
    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

    <?php if ($slug === 'llms'): ?>
      <label style="margin-top:0">Conteúdo do /llms.txt</label>
      <textarea name="content" style="min-height:480px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars((string) json_decode($item['data'], true)) ?></textarea>
    <?php else: ?>
      <?php $first = true; foreach ($decoded as $key => $orig): ?>
        <label class="sec"<?= $first ? ' style="margin-top:0"' : '' ?>><?= htmlspecialchars(pagesLabel($key)) ?></label>

        <?php if (!is_array($orig)): ?>
          <?php $v = (string) $orig; ?>
          <?php if (mb_strlen($v) > 120 || strpos($v, "\n") !== false): ?>
            <textarea name="f[<?= htmlspecialchars($key) ?>]" style="min-height:100px"><?= htmlspecialchars($v) ?></textarea>
          <?php else: ?>
            <input type="text"<?= pagesIsImgKey($key) ? ' class="img-url"' : '' ?> name="f[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($v) ?>" />
          <?php endif; ?>

        <?php elseif (!pagesIsList($orig)): ?>
          <div class="objbox">
            <?php pagesFieldsGrid("f[$key]", array_keys($orig), $orig, pagesLongKeys([$orig]), false); ?>
          </div>

        <?php else: ?>
          <?php
            $tplKeys  = array_keys($orig[0] ?? []);
            $longKeys = pagesLongKeys($orig);
            $big      = count($orig) > 20;
            // No prose field in this repeater (stats, logos...) -> items are
            // short enough to sit side by side instead of one per row.
            $compact  = empty($longKeys);
            // Once the item itself is narrow, its own fields need pairing up
            // too (a lone "+" in a full-width input is the same waste one
            // level down) -- except when a field is an image drop-zone,
            // which needs real width and shouldn't get squeezed.
            $hasImgField = (bool) array_intersect($tplKeys, ['photo', 'logo', 'ogImage']);
            $tight = $compact && !$hasImgField && count($tplKeys) > 1;
          ?>
          <?php if ($big): ?>
            <button type="button" class="btn secondary rep-add" onclick="repAdd('<?= htmlspecialchars($key) ?>')">+ Adicionar item</button>
          <?php endif; ?>
          <div class="rep<?= $compact ? ' grid-cols' : '' ?><?= $tight ? ' tight-fields' : '' ?>" id="rep-<?= htmlspecialchars($key) ?>" data-next="<?= count($orig) ?>">
            <?php foreach ($orig as $i => $row): ?>
              <div class="rep-item">
                <div class="rep-item-head">
                  <span class="rep-n">Item <?= $i + 1 ?></span>
                  <button type="button" onclick="repDel(this)">Remover</button>
                </div>
                <?php pagesFieldsGrid("f[$key][$i]", $tplKeys, (array) $row, $longKeys, true); ?>
              </div>
            <?php endforeach; ?>
          </div>
          <template id="tpl-<?= htmlspecialchars($key) ?>">
            <div class="rep-item">
              <div class="rep-item-head">
                <span class="rep-n">Novo item</span>
                <button type="button" onclick="repDel(this)">Remover</button>
              </div>
              <?php pagesFieldsGrid("f[$key][__i__]", $tplKeys, [], $longKeys, true); ?>
            </div>
          </template>
          <button type="button" class="btn secondary rep-add" onclick="repAdd('<?= htmlspecialchars($key) ?>')">+ Adicionar item</button>
        <?php endif; ?>
      <?php $first = false; endforeach; ?>
    <?php endif; ?>

    <div>
      <button type="submit" class="btn" style="margin-top:20px">Salvar</button>
      <a href="paginas" class="btn secondary" style="margin-top:20px;margin-left:8px">Cancelar</a>
    </div>
  </form>
  </div>

  <script>
  // ---- Repeaters ----
  function repAdd(key) {
    var rep = document.getElementById('rep-' + key);
    var tpl = document.getElementById('tpl-' + key);
    var i = parseInt(rep.dataset.next, 10);
    rep.dataset.next = i + 1;
    rep.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, i));
    imgDropInit(rep); // enhance image-URL inputs in the freshly cloned item
  }
  function repDel(btn) {
    btn.closest('.rep-item').remove();
  }

  // ---- Markdown toolbar (same behavior as the post editor; the target
  // textarea is the toolbar's next sibling, so many instances coexist) ----
  function mdTa(btn) { return btn.closest('.mdbar').nextElementSibling; }

  function mdWrap(btn, before, after, placeholder) {
    var ta = mdTa(btn);
    ta.focus();
    var s = ta.selectionStart, e = ta.selectionEnd;
    var sel = ta.value.substring(s, e) || placeholder;
    ta.setRangeText(before + sel + after, s, e, 'select');
    ta.setSelectionRange(s + before.length, s + before.length + sel.length);
  }

  function mdLine(btn, prefix, numbered) {
    var ta = mdTa(btn);
    ta.focus();
    var s = ta.selectionStart, e = ta.selectionEnd;
    // expand to whole lines
    var ls = ta.value.lastIndexOf('\n', s - 1) + 1;
    var le = ta.value.indexOf('\n', e);
    if (le === -1) le = ta.value.length;
    var lines = ta.value.substring(ls, le).split('\n');
    var out = lines.map(function (line, i) {
      return (numbered ? (i + 1) + '. ' : prefix) + line;
    }).join('\n');
    ta.setRangeText(out, ls, le, 'select');
  }

  function mdLink(btn) {
    var ta = mdTa(btn);
    var s = ta.selectionStart, e = ta.selectionEnd;
    askUrl(btn, function (url) {
      var sel = ta.value.substring(s, e) || 'texto do link';
      ta.setRangeText('[' + sel + '](' + url + ')', s, e, 'select');
      ta.focus();
    });
  }
  </script>
<?php endif; ?>

<?php adminLayoutBottom(); ?>
