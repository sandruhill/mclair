<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();

// Real case list, used to turn the "case" field (client -> case link) into a
// picker instead of a free-text path -- avoids typos in a hand-typed slug.
$GLOBALS['PAGES_CASE_OPTIONS'] = $pdo->query('SELECT slug, client FROM cmstest_cases ORDER BY num')->fetchAll(PDO::FETCH_KEY_PAIR);

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

// Expected aspect ratio for fields whose existing images are consistently
// one shape (logos, headshots are square) -- gets a soft warning on upload
// if a new image doesn't match. Fields with no consistent existing shape
// (case covers, galleries) are intentionally left unset.
function pagesImgRatio(string $key): ?string {
    return in_array($key, ['photo', 'logo'], true) ? '1:1' : null;
}

function pagesCaseOptionsHtml(string $selected = ''): string {
    $html = '<option value="">Nenhum</option>';
    foreach ($GLOBALS['PAGES_CASE_OPTIONS'] as $caseSlug => $caseClient) {
        $href = '/cases/' . $caseSlug;
        $html .= '<option value="' . htmlspecialchars($href) . '"' . ($selected === $href ? ' selected' : '') . '>' . htmlspecialchars($caseClient) . '</option>';
    }
    return $html;
}

// Clients (68+ logos) get their own manager instead of the generic repeater:
// bulk logo upload, grid/list view, and inline edit/delete over fetch() so
// touching one client doesn't reload/resubmit the other 67.
function clientsManagerRender(array $clients): void {
?>
<div id="cm" data-case-options="<?= htmlspecialchars(pagesCaseOptionsHtml(), ENT_QUOTES) ?>">
  <div class="cm-toolbar">
    <div class="cm-bulk-actions" id="cmBulkActions" style="display:none">
      <span id="cmSelCount"></span>
      <button type="button" class="del" onclick="cmDeleteSelected()">Apagar selecionados</button>
    </div>
    <div class="cm-view-group">
      <span class="cm-view-label">Modo de visualização</span>
      <div class="cm-view-toggle">
        <button type="button" class="on" data-view="grid" onclick="cmSetView('grid')">Quadrado</button>
        <button type="button" data-view="list" onclick="cmSetView('list')">Lista</button>
      </div>
    </div>
  </div>

  <div class="cm-bulk-drop" id="cmBulkDrop">
    <input type="file" id="cmBulkFile" accept="image/*" multiple style="display:none" />
    <svg class="cm-bulk-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 16V4M12 4l-4 4M12 4l4 4"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
    <div class="cm-bulk-msg">Arraste vários logos aqui ou clique para escolher. Cada imagem enviada vira um cliente novo.</div>
  </div>
  <p class="imgdrop-err" id="cmErr" style="display:none"></p>

  <div class="cm-list" id="cmList"></div>
  <button type="button" class="btn secondary" style="margin-top:14px" onclick="cmAddBlank()">+ Adicionar cliente</button>
</div>

<script>
(function () {
  var clients = <?= json_encode(array_values($clients), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var caseOptionsHtml = document.getElementById('cm').dataset.caseOptions;
  var view = 'grid';
  var editingIndex = null;
  var selected = new Set();

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function showErr(msg) {
    var el = document.getElementById('cmErr');
    el.textContent = msg;
    el.style.display = msg ? '' : 'none';
  }

  function caseLabel(href) {
    if (!href) return '';
    var opt = document.createElement('div');
    opt.innerHTML = caseOptionsHtml;
    var match = Array.from(opt.querySelectorAll('option')).find(function (o) { return o.value === href; });
    return match ? match.textContent : href;
  }

  function render() {
    var list = document.getElementById('cmList');
    list.className = 'cm-list cm-view-' + view;
    list.innerHTML = '';
    clients.forEach(function (c, i) {
      var card = document.createElement('div');
      card.className = 'cm-item';
      if (selected.has(i)) card.classList.add('cm-selected');
      if (editingIndex === i) {
        card.classList.add('cm-editing');
        card.innerHTML = editForm(c, i);
      } else {
        card.innerHTML =
          '<div class="cm-top"><label class="cm-check"><input type="checkbox" ' + (selected.has(i) ? 'checked' : '') + ' onchange="cmToggleSelect(' + i + ')" /></label></div>' +
          '<img class="cm-logo" src="' + esc(c.logo) + '" alt="" />' +
          '<div class="cm-info"><strong>' + esc(c.name) + '</strong>' +
          (c.case ? '<span class="cm-case-badge">' + esc(caseLabel(c.case)) + '</span>' : '<span class="cm-case-badge cm-empty">sem case</span>') +
          '</div>' +
          '<div class="cm-actions"><a href="#" onclick="cmEdit(' + i + ');return false">editar</a><a href="#" class="del" onclick="cmDelete(' + i + ');return false">apagar</a></div>';
      }
      list.appendChild(card);
    });
    if (!clients.length) list.innerHTML = '<p class="hint">Nenhum cliente ainda. Arraste logos acima para adicionar.</p>';
    imgDropInit(list);
    updateBulkBar();
  }

  function editForm(c, i) {
    return '' +
      '<div class="cm-edit">' +
      '<div class="cm-edit-logo"><label>Logo</label><input type="text" class="img-url" value="' + esc(c.logo) + '" data-f="logo" /></div>' +
      '<div class="cm-edit-fields">' +
      '<label>Nome</label><input type="text" value="' + esc(c.name) + '" data-f="name" />' +
      '<label>Case</label><select data-f="case">' + caseOptionsWithSelected(c.case) + '</select>' +
      '<div class="cm-edit-actions">' +
      '<button type="button" class="btn" onclick="cmSave(' + i + ')">Salvar</button>' +
      '<button type="button" class="btn secondary" onclick="cmCancelEdit()">Cancelar</button>' +
      '</div></div></div>';
  }

  function caseOptionsWithSelected(val) {
    var d = document.createElement('div');
    d.innerHTML = caseOptionsHtml;
    d.querySelectorAll('option').forEach(function (o) { o.selected = (o.value === (val || '')); });
    return d.innerHTML;
  }

  function updateBulkBar() {
    var bar = document.getElementById('cmBulkActions');
    bar.style.display = selected.size ? '' : 'none';
    document.getElementById('cmSelCount').textContent = selected.size + ' selecionado(s)';
  }

  window.cmSetView = function (v) {
    view = v;
    document.querySelectorAll('.cm-view-toggle button').forEach(function (b) {
      b.classList.toggle('on', b.dataset.view === v);
    });
    render();
  };

  window.cmEdit = function (i) { editingIndex = i; render(); };
  window.cmCancelEdit = function () { editingIndex = null; render(); };

  window.cmToggleSelect = function (i) {
    if (selected.has(i)) selected.delete(i); else selected.add(i);
    updateBulkBar();
  };

  window.cmAddBlank = function () {
    clients.push({ name: '', logo: '', case: '' });
    editingIndex = clients.length - 1;
    render();
  };

  window.cmSave = function (i) {
    var card = document.querySelectorAll('.cm-item')[i];
    var fields = {};
    card.querySelectorAll('[data-f]').forEach(function (el) { fields[el.dataset.f] = el.value.trim(); });
    if (!fields.name) { showErr('Nome é obrigatório.'); return; }
    showErr('');
    var fd = new FormData();
    fd.append('action', 'save');
    fd.append('index', i);
    fd.append('name', fields.name);
    fd.append('logo', fields.logo || '');
    fd.append('case', fields.case || '');
    fetch('/acesso/clients-api.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { showErr(res.error || 'Falha ao salvar.'); return; }
        clients = res.clients;
        editingIndex = null;
        render();
      })
      .catch(function () { showErr('Falha de rede ao salvar.'); });
  };

  window.cmDelete = function (i) {
    var card = document.querySelectorAll('.cm-item')[i];
    if (!card.classList.contains('cm-confirm-del')) {
      card.classList.add('cm-confirm-del');
      var link = card.querySelector('a.del');
      link.textContent = 'confirmar?';
      return;
    }
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('index', i);
    fetch('/acesso/clients-api.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { showErr(res.error || 'Falha ao apagar.'); return; }
        clients = res.clients;
        selected.clear();
        render();
      })
      .catch(function () { showErr('Falha de rede ao apagar.'); });
  };

  window.cmDeleteSelected = function () {
    if (!selected.size) return;
    var fd = new FormData();
    fd.append('action', 'delete_many');
    fd.append('indexes', JSON.stringify(Array.from(selected)));
    fetch('/acesso/clients-api.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { showErr(res.error || 'Falha ao apagar.'); return; }
        clients = res.clients;
        selected.clear();
        render();
      })
      .catch(function () { showErr('Falha de rede ao apagar.'); });
  };

  function nameFromFilename(fn) {
    return fn.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function bulkUpload(files) {
    if (!files.length) return;
    showErr('');
    var drop = document.getElementById('cmBulkDrop');
    drop.querySelector('.cm-bulk-msg').textContent = 'Enviando ' + files.length + ' imagem(ns)...';
    var uploads = Array.from(files).map(function (f) {
      var fd = new FormData();
      fd.append('file', f);
      return fetch('/acesso/upload.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j, name: f.name }; }); });
    });
    Promise.all(uploads).then(function (results) {
      var items = [];
      var errors = [];
      results.forEach(function (r) {
        if (r.ok && r.j.url) items.push({ name: nameFromFilename(r.name), logo: r.j.url });
        else errors.push(r.name);
      });
      if (errors.length) showErr('Falha ao enviar: ' + errors.join(', '));
      if (!items.length) { drop.querySelector('.cm-bulk-msg').textContent = 'Arraste vários logos aqui ou clique para escolher. Cada imagem enviada vira um cliente novo.'; return; }
      var fd = new FormData();
      fd.append('action', 'add_many');
      fd.append('items', JSON.stringify(items));
      return fetch('/acesso/clients-api.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          drop.querySelector('.cm-bulk-msg').textContent = 'Arraste vários logos aqui ou clique para escolher. Cada imagem enviada vira um cliente novo.';
          if (!res.ok) { showErr(res.error || 'Falha ao salvar.'); return; }
          clients = res.clients;
          render();
        });
    }).catch(function () { showErr('Falha de rede no envio em lote.'); });
  }

  var bulkDrop = document.getElementById('cmBulkDrop');
  var bulkFile = document.getElementById('cmBulkFile');
  bulkDrop.addEventListener('click', function () { bulkFile.click(); });
  bulkFile.addEventListener('change', function () { bulkUpload(bulkFile.files); bulkFile.value = ''; });
  bulkDrop.addEventListener('dragover', function (e) { e.preventDefault(); bulkDrop.classList.add('over'); });
  bulkDrop.addEventListener('dragleave', function () { bulkDrop.classList.remove('over'); });
  bulkDrop.addEventListener('drop', function (e) {
    e.preventDefault();
    bulkDrop.classList.remove('over');
    bulkUpload(e.dataTransfer.files);
  });

  render();
})();
</script>
<?php
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
        // Image URLs are single-line by nature -- a long descriptive filename
        // shouldn't ever downgrade the field to a plain textarea and lose
        // the drag-and-drop/preview widget.
        $long = isset($longKeys[$sk]) && !pagesIsImgKey((string) $sk);
        $name = htmlspecialchars($prefix . '[' . $sk . ']');
        echo '<div' . ($long ? ' class="fw"' : '') . '>';
        echo '<label>' . htmlspecialchars(pagesLabel((string) $sk)) . '</label>';
        if ($long && $withBar) {
            pagesMdbar();
            echo '<textarea name="' . $name . '" style="min-height:110px;font-size:.88rem">' . htmlspecialchars($val) . '</textarea>';
        } elseif ($long) {
            echo '<textarea name="' . $name . '" style="min-height:90px;font-size:.88rem">' . htmlspecialchars($val) . '</textarea>';
        } elseif ($sk === 'case') {
            echo '<select name="' . $name . '">' . pagesCaseOptionsHtml($val) . '</select>';
        } else {
            $imgClass = pagesIsImgKey((string) $sk) ? ' class="img-url"' : '';
            $ratio = pagesImgRatio((string) $sk);
            $ratioAttr = $ratio ? ' data-imgdrop-ratio="' . htmlspecialchars($ratio) . '"' : '';
            echo '<input type="text"' . $imgClass . $ratioAttr . ' name="' . $name . '" value="' . htmlspecialchars($val) . '" />';
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
        // clients is managed live via clients-api.php (fetch, no page reload)
        // and never gets <input name="f[clients]..."> fields -- keep it as is
        // instead of reconstructing from an empty POST and wiping all 68.
        if ($slug === 'clientes' && $key === 'clients') { $new[$key] = $orig; continue; }
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

    /* Clients manager */
    .cm-toolbar { display:flex; align-items:center; justify-content:flex-end; gap:20px; margin-bottom:14px; }
    .cm-view-group { display:flex; align-items:center; gap:10px; }
    .cm-view-label { font-size:.76rem; font-weight:700; color:var(--ink-3); }
    .cm-view-toggle { display:inline-flex; background:var(--soft); border-radius:8px; padding:3px; }
    .cm-view-toggle button { border:none; background:none; padding:6px 14px; border-radius:6px; font-size:.78rem; font-weight:700; color:var(--ink-3); cursor:pointer; font-family:inherit; }
    .cm-view-toggle button.on { background:#fff; color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.08); }
    .cm-bulk-actions { display:flex; align-items:center; gap:10px; font-size:.8rem; color:var(--ink-3); }
    .cm-bulk-actions button.del { background:var(--red); color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:.76rem; font-weight:700; cursor:pointer; font-family:inherit; }

    .cm-bulk-drop { border:1.5px dashed var(--line); border-radius:14px; padding:64px 24px; text-align:center; cursor:pointer; background:var(--paper); margin-bottom:14px; }
    .cm-bulk-drop.over { border-color:var(--red); background:rgba(200,16,46,.04); }
    .cm-bulk-icon { width:36px; height:36px; color:var(--ink-3); margin-bottom:10px; }
    .cm-bulk-msg { font-size:.92rem; font-weight:600; color:var(--ink-3); max-width:360px; margin:0 auto; }

    .cm-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
    .cm-item { border:1px solid var(--line); border-radius:10px; background:#FAFBFC; padding:12px; }
    .cm-item.cm-selected { border-color:var(--red); background:rgba(200,16,46,.03); }
    .cm-item.cm-editing { grid-column:1 / -1; }
    .cm-top { display:flex; margin-bottom:6px; }
    .cm-logo { width:100%; height:80px; object-fit:contain; background:#fff; border:1px solid var(--line); border-radius:8px; padding:8px; box-sizing:border-box; }
    .cm-info { margin-top:8px; text-align:center; }
    .cm-info strong { display:block; font-size:.82rem; }
    .cm-case-badge { display:inline-block; margin-top:4px; font-size:.68rem; font-weight:700; color:var(--ink-3); background:var(--soft); padding:2px 8px; border-radius:99px; }
    .cm-case-badge.cm-empty { opacity:.55; }
    .cm-actions { display:flex; justify-content:center; gap:10px; margin-top:8px; font-size:.76rem; font-weight:700; }
    .cm-actions a { color:var(--red); }
    .cm-actions a.del { color:var(--ink-3); }

    /* List view: rows instead of a grid */
    .cm-list.cm-view-list { display:flex; flex-direction:column; gap:6px; }
    .cm-view-list .cm-item:not(.cm-editing) { display:flex; align-items:center; gap:12px; padding:8px 12px; }
    .cm-view-list .cm-top { margin:0; }
    .cm-view-list .cm-logo { width:36px; height:36px; padding:3px; flex-shrink:0; }
    .cm-view-list .cm-info { margin-top:0; text-align:left; flex:1; display:flex; align-items:center; gap:10px; }
    .cm-view-list .cm-info strong { display:inline; }
    .cm-view-list .cm-actions { margin-top:0; }

    .cm-edit { display:flex; gap:20px; }
    .cm-edit-logo { width:160px; flex-shrink:0; }
    .cm-edit-fields { flex:1; display:flex; flex-direction:column; gap:8px; min-width:0; }
    .cm-edit label { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin:0; }
    .cm-edit-actions { display:flex; gap:8px; margin-top:4px; }
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

        <?php if ($slug === 'clientes' && $key === 'clients'): ?>
          <?php clientsManagerRender($orig); $first = false; continue; ?>
        <?php endif; ?>

        <?php if (!is_array($orig)): ?>
          <?php $v = (string) $orig; ?>
          <?php if (!pagesIsImgKey($key) && (mb_strlen($v) > 120 || strpos($v, "\n") !== false)): ?>
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
