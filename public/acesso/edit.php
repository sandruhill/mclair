<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
$myId = (int) $_SESSION['cms_user_id'];
$isAuthorRole = cmsRole() === 'author';

// "Autor" role can only ever touch their own posts.
if ($isAuthorRole) {
    $owns = $pdo->prepare('SELECT 1 FROM cmstest_blog_posts WHERE slug = ? AND author_id = ?');
    $owns->execute([$slug, $myId]);
    if (!$owns->fetchColumn()) { http_response_code(403); die('Você só pode editar os seus próprios posts.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // keywords: comma-separated text -> JSON array (stored format used by the build)
    $kwArr = array_values(array_filter(array_map('trim', explode(',', $_POST['keywords'] ?? '')), 'strlen'));
    $kwJson = json_encode($kwArr, JSON_UNESCAPED_UNICODE);

    if ($isAuthorRole) {
        // authorship stays locked to themselves regardless of what's posted
        $authorId = $myId;
        $authorName = $_SESSION['cms_username'];
    } else {
        $authorId = $_POST['author_id'] !== '' ? (int) $_POST['author_id'] : null;
        $authorName = $authorId ? null : trim($_POST['author_other'] ?? '');
    }
    if ($authorId) {
        $u = $pdo->prepare('SELECT username FROM cmstest_users WHERE id = ?');
        $u->execute([$authorId]);
        $authorName = $u->fetchColumn() ?: $authorName;
    }

    $stmt = $pdo->prepare('UPDATE cmstest_blog_posts SET title = ?, subtitle = ?, category = ?, post_date = ?, featured_image = ?, hero_video = ?, meta_description = ?, keywords = ?, content_md = ?, author_id = ?, author = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([
        $_POST['title'], $_POST['subtitle'], $_POST['category'], $_POST['post_date'],
        $_POST['featured_image'], $_POST['hero_video'], $_POST['meta_description'], $kwJson,
        $_POST['content_md'], $authorId, $authorName, $myId, $slug,
    ]);
    queueRebuild();
    header('Location: editar?slug=' . urlencode($slug) . '&saved=1&t=' . time());
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

$authorOptions = $pdo->query('SELECT id, username FROM cmstest_users ORDER BY username')->fetchAll();

$categories = ['assessoria-de-imprensa','marketing-de-autoridade','branding-estrategico','marketing-digital','comunicacao','marca-pessoal','negocios','geral'];

// keywords: JSON array in the DB -> comma-separated for the input
$kwDecoded = json_decode($post['keywords'] ?? '', true);
$kwText = is_array($kwDecoded) ? implode(', ', $kwDecoded) : (string) ($post['keywords'] ?? '');

$heroMode = !empty($post['hero_video']) ? 'video' : 'image';

adminLayoutTop('blog', 'Editando post', ['label' => 'Posts do blog', 'href' => '/acesso/posts'], 'https://mclair.com.br/blog/' . urlencode($slug));
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/<?= urlencode($slug) ?>"><?= cmsCheckIcon() ?><span class="msg-text">Post salvo.</span></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

  <div class="editor-grid">
    <!-- Left: content -->
    <div>
      <div class="card">
        <label style="margin-top:0">Título</label>
        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" />

        <label>Conteúdo (markdown)</label>
        <div class="mdbar" id="mdbar">
          <button type="button" title="Negrito" onclick="mdWrap('**','**','texto em negrito')"><b>B</b></button>
          <button type="button" title="Itálico" onclick="mdWrap('*','*','texto em itálico')"><i>I</i></button>
          <button type="button" title="Riscado" onclick="mdWrap('~~','~~','texto riscado')"><s>S</s></button>
          <span class="div"></span>
          <button type="button" title="Título (H2)" onclick="mdLine('## ')">H2</button>
          <button type="button" title="Subtítulo (H3)" onclick="mdLine('### ')">H3</button>
          <button type="button" title="Subtítulo (H4)" onclick="mdLine('#### ')">H4</button>
          <span class="div"></span>
          <button type="button" title="Link" onclick="mdLink(this)">🔗</button>
          <button type="button" title="Imagem" onclick="mdImage()">🖼&nbsp;Imagem</button>
          <span class="div"></span>
          <button type="button" title="Lista" onclick="mdLine('- ')">•&nbsp;Lista</button>
          <button type="button" title="Lista numerada" onclick="mdLine('1. ', true)">1.&nbsp;Lista</button>
          <button type="button" title="Citação" onclick="mdLine('> ')">&ldquo;&nbsp;Citação</button>
          <span class="div"></span>
          <button type="button" title="Código inline" onclick="mdWrap('`','`','código')">&lt;/&gt;</button>
          <button type="button" title="Bloco de código" onclick="mdWrap('```\n','\n```','código')">```</button>
          <span class="div"></span>
          <button type="button" title="Tabela" onclick="mdTable()">⊞&nbsp;Tabela</button>
          <button type="button" title="Linha divisória" onclick="mdBlock('---')">―</button>
        </div>
        <textarea name="content_md" id="contentMd" style="min-height:480px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars($post['content_md']) ?></textarea>
      </div>
    </div>

    <!-- Right: metadata + SEO -->
    <div>
      <div class="card">
        <div class="side-sec">
          <strong>Capa</strong>
          <div class="hero-card" style="margin-top:10px;margin-bottom:0">
            <div class="hero-preview" id="heroPreview" style="min-height:140px">
              <span class="hero-chip" id="heroChip">Capa</span>
              <div class="empty">Sem mídia de capa, cole uma URL abaixo</div>
            </div>
            <div class="hero-controls">
              <div class="hero-tabs" role="tablist">
                <button type="button" id="tabImage" onclick="setHeroMode('image')">Imagem</button>
                <button type="button" id="tabVideo" onclick="setHeroMode('video')">Vídeo</button>
              </div>
              <div id="paneImage">
                <label style="margin-top:0">Imagem de capa</label>
                <input type="text" name="featured_image" id="featuredImage" class="img-url" data-imgdrop-nothumb value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="/blog-images/exemplo.jpg" oninput="renderHero()" />
              </div>
              <div id="paneVideo">
                <label style="margin-top:0">Vídeo de capa (URL do YouTube ou .mp4)</label>
                <input type="text" name="hero_video" id="heroVideo" value="<?= htmlspecialchars($post['hero_video'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=..." oninput="renderHero()" />
                <p class="hint">Deixe em branco para usar a imagem como capa.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="side-sec">
          <strong>Publicação</strong>
          <label>Subtítulo</label>
          <input type="text" name="subtitle" value="<?= htmlspecialchars($post['subtitle']) ?>" />
          <label>Autor</label>
          <?php if ($isAuthorRole): ?>
            <input type="text" value="<?= htmlspecialchars($_SESSION['cms_username']) ?>" disabled />
          <?php else: ?>
            <select name="author_id" id="authorSelect" onchange="document.getElementById('authorOtherWrap').style.display = this.value === '' ? '' : 'none'">
              <option value="">Outro (digitar abaixo)</option>
              <?php foreach ($authorOptions as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) $post['author_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="authorOtherWrap" style="<?= $post['author_id'] ? 'display:none' : '' ?>">
              <input type="text" name="author_other" value="<?= htmlspecialchars($post['author_id'] ? '' : ($post['author'] ?? '')) ?>" placeholder="Nome do autor" style="margin-top:6px" />
              <p class="hint">Para autores sem login no painel (colaboradores externos, etc).</p>
            </div>
          <?php endif; ?>
          <label>Categoria</label>
          <select name="category">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= $post['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
          <label>Data de postagem</label>
          <input type="date" name="post_date" value="<?= htmlspecialchars($post['post_date']) ?>" />
        </div>

        <div class="side-sec">
          <strong>SEO</strong>
          <label>Meta description</label>
          <textarea name="meta_description" maxlength="500" style="min-height:90px;font-size:.85rem"><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
          <p class="hint">Resumo para o Google. Ideal até ~160 caracteres.</p>
          <label>Palavras-chave</label>
          <input type="text" name="keywords" value="<?= htmlspecialchars($kwText) ?>" placeholder="marketing, branding, imprensa" />
          <p class="hint">Separe por vírgula.</p>
        </div>

        <div class="side-sec">
          <p class="hint" style="margin:0 0 12px">
            <?php if ($post['updated_by_name']): ?>Última edição por <strong><?= htmlspecialchars($post['updated_by_name']) ?></strong><?php else: ?>Ainda sem edições registradas<?php endif; ?>
          </p>
          <button type="submit" class="btn" style="width:100%;justify-content:center">Salvar</button>
          <a href="/acesso/posts" class="btn secondary" style="width:100%;justify-content:center;margin-top:8px">Cancelar</a>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Media picker modal (toolbar "Imagem" button) -->
<style>
  .imgmodal { display:none; position:fixed; inset:0; background:rgba(26,27,30,.5); z-index:50; align-items:center; justify-content:center; }
  .imgmodal.open { display:flex; }
  .imgmodal .card { width:640px; max-width:92vw; max-height:84vh; overflow:auto; }
  .imggrid { display:grid; grid-template-columns:repeat(auto-fill, minmax(110px, 1fr)); gap:8px; margin-top:12px; }
  .imggrid button { border:2px solid transparent; border-radius:8px; padding:0; background:var(--soft); cursor:pointer; overflow:hidden; aspect-ratio:4/3; }
  .imggrid button img { width:100%; height:100%; object-fit:cover; display:block; }
  .imggrid button.on { border-color:var(--red); }
</style>
<div class="imgmodal" id="imgModal">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div class="hero-tabs" role="tablist">
        <button type="button" id="imgTabUp" onclick="imgTab('up')">Enviar nova</button>
        <button type="button" id="imgTabGal" onclick="imgTab('gal')">Galeria</button>
      </div>
      <button type="button" onclick="imgModalClose()" style="background:none;border:none;cursor:pointer;font-size:1rem;color:var(--ink-3)" title="Fechar">✕</button>
    </div>
    <div id="imgPaneUp">
      <label>Imagem</label>
      <input type="text" id="imgModalUrl" class="img-url" placeholder="/uploads/exemplo.jpg" />
    </div>
    <div id="imgPaneGal" style="display:none">
      <div class="imggrid" id="imgGrid"></div>
      <p class="hint" id="imgGalMsg"></p>
    </div>
    <div style="margin-top:16px;display:flex;align-items:center;gap:10px;justify-content:flex-end">
      <span class="hint" id="imgModalErr" style="margin:0"></span>
      <button type="button" class="btn secondary" onclick="imgModalClose()">Cancelar</button>
      <button type="button" class="btn" onclick="imgModalInsert()">Inserir imagem</button>
    </div>
  </div>
</div>

<script>
// ---- Hero preview ----
var heroMode = <?= json_encode($heroMode) ?>;

function youtubeId(url) {
  var m = url.match(/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{6,20})/);
  return m ? m[1] : null;
}

function setHeroMode(mode) {
  heroMode = mode;
  document.getElementById('tabImage').className = mode === 'image' ? 'on' : '';
  document.getElementById('tabVideo').className = mode === 'video' ? 'on' : '';
  document.getElementById('paneImage').style.display = mode === 'image' ? '' : 'none';
  document.getElementById('paneVideo').style.display = mode === 'video' ? '' : 'none';
  renderHero();
}

function renderHero() {
  var box = document.getElementById('heroPreview');
  var url = (heroMode === 'video'
    ? document.getElementById('heroVideo').value
    : document.getElementById('featuredImage').value).trim();
  var chip = heroMode === 'video' ? 'Capa em vídeo' : 'Capa em imagem';
  var media;
  if (!url) {
    media = '<div class="empty">Sem mídia de capa, cole uma URL abaixo</div>';
  } else if (heroMode === 'video') {
    var yt = youtubeId(url);
    media = yt
      ? '<iframe src="https://www.youtube.com/embed/' + encodeURIComponent(yt) + '" allowfullscreen></iframe>'
      : '<video src="' + encodeURI(url) + '" controls></video>';
  } else {
    media = '<img src="' + encodeURI(url) + '" alt="" />';
  }
  box.innerHTML = '<span class="hero-chip">' + chip + '</span>' + media;
}

setHeroMode(heroMode);

// ---- Markdown toolbar ----
var ta = document.getElementById('contentMd');

function mdWrap(before, after, placeholder) {
  ta.focus();
  var s = ta.selectionStart, e = ta.selectionEnd;
  var sel = ta.value.substring(s, e) || placeholder;
  ta.setRangeText(before + sel + after, s, e, 'select');
  ta.setSelectionRange(s + before.length, s + before.length + sel.length);
}

function mdLine(prefix, numbered) {
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
  var s = ta.selectionStart, e = ta.selectionEnd;
  askUrl(btn, function (url) {
    var sel = ta.value.substring(s, e) || 'texto do link';
    ta.setRangeText('[' + sel + '](' + url + ')', s, e, 'select');
    ta.focus();
  });
}

// Inserts text as its own block at the cursor, padded with blank lines.
function mdBlock(text) {
  ta.focus();
  ta.setRangeText('\n\n' + text + '\n\n', ta.selectionStart, ta.selectionEnd, 'end');
}

function mdTable() {
  mdBlock('| Coluna 1 | Coluna 2 |\n| --- | --- |\n| valor | valor |\n| valor | valor |');
}

// ---- Media picker modal ----
// Cursor position is captured when the modal opens (the textarea loses focus
// once the modal takes over) and the insert happens at that saved range.
var imgIns = { s: 0, e: 0 };
var imgCurTab = 'up';
var imgPicked = '';

function mdImage() {
  imgIns.s = ta.selectionStart;
  imgIns.e = ta.selectionEnd;
  imgPicked = '';
  document.getElementById('imgModalErr').textContent = '';
  document.getElementById('imgModal').classList.add('open');
  imgTab('up');
}

function imgTab(t) {
  imgCurTab = t;
  document.getElementById('imgTabUp').className = t === 'up' ? 'on' : '';
  document.getElementById('imgTabGal').className = t === 'gal' ? 'on' : '';
  document.getElementById('imgPaneUp').style.display = t === 'up' ? '' : 'none';
  document.getElementById('imgPaneGal').style.display = t === 'gal' ? '' : 'none';
  if (t === 'gal') imgLoadGallery();
}

function imgLoadGallery() {
  var grid = document.getElementById('imgGrid');
  var msg = document.getElementById('imgGalMsg');
  grid.innerHTML = '';
  msg.textContent = 'Carregando...';
  fetch('/acesso/media-list.php')
    .then(function (r) { return r.json(); })
    .then(function (list) {
      msg.textContent = list.length ? '' : 'Nenhuma imagem enviada ainda.';
      list.forEach(function (f) {
        var b = document.createElement('button');
        b.type = 'button';
        b.title = f.url;
        var im = document.createElement('img');
        im.src = f.url;
        im.alt = '';
        im.loading = 'lazy';
        b.appendChild(im);
        b.addEventListener('click', function () {
          var on = grid.querySelector('.on');
          if (on) on.classList.remove('on');
          b.classList.add('on');
          imgPicked = f.url;
          document.getElementById('imgModalErr').textContent = '';
        });
        grid.appendChild(b);
      });
    })
    .catch(function () { msg.textContent = 'Falha ao carregar a galeria. Tente de novo.'; });
}

function imgModalClose() {
  document.getElementById('imgModal').classList.remove('open');
}

function imgModalInsert() {
  var url = imgCurTab === 'up'
    ? document.getElementById('imgModalUrl').value.trim()
    : imgPicked;
  if (!url) {
    document.getElementById('imgModalErr').textContent = imgCurTab === 'up'
      ? 'Envie ou cole a URL de uma imagem primeiro.'
      : 'Clique numa imagem da galeria primeiro.';
    return;
  }
  ta.setRangeText('![](' + url + ')', imgIns.s, imgIns.e, 'end');
  ta.focus();
  imgModalClose();
}
</script>

<?php adminLayoutBottom(); ?>
