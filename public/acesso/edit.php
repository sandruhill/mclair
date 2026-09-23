<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
$myId = (int) $_SESSION['cms_user_id'];

// Sem slug na URL = post novo. O editor atende os dois modos de propósito: duas
// telas separadas divergiriam na primeira vez que alguém mexesse num campo.
$isNew = ($slug === '');

/** Título -> endereço do post: sem acento, sem pontuação, separado por hífen. */
function cmsSlugify(string $texto): string {
    $t = trim($texto);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $t) ?: $t;
    } else {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    }
    $t = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($t));
    $t = trim((string) $t, '-');
    if (strlen($t) <= 80) return $t;
    // Corta no último hífen antes do limite, para não partir palavra no meio.
    $curto = substr($t, 0, 80);
    $ultimo = strrpos($curto, '-');
    return rtrim($ultimo > 40 ? substr($curto, 0, $ultimo) : $curto, '-');
}

/** slug é a chave primária, então um título repetido vira -2, -3, e assim por diante. */
function cmsSlugUnico(PDO $pdo, string $base): string {
    $stmt = $pdo->prepare('SELECT 1 FROM cmstest_blog_posts WHERE slug = ?');
    $tentativa = $base;
    for ($n = 2; $n <= 200; $n++) {
        $stmt->execute([$tentativa]);
        if (!$stmt->fetchColumn()) return $tentativa;
        $tentativa = $base . '-' . $n;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cmsCsrfValidate($_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
    }

    // keywords: comma-separated text -> JSON array (stored format used by the build)
    $kwArr = array_values(array_filter(array_map('trim', explode(',', $_POST['keywords'] ?? '')), 'strlen'));
    $kwJson = json_encode($kwArr, JSON_UNESCAPED_UNICODE);

    $authorId = $_POST['author_id'] !== '' ? (int) $_POST['author_id'] : null;
    $authorName = $authorId ? null : trim($_POST['author_other'] ?? '');
    if ($authorId) {
        $u = $pdo->prepare('SELECT username FROM cmstest_users WHERE id = ?');
        $u->execute([$authorId]);
        $authorName = $u->fetchColumn() ?: $authorName;
    }

    if ($isNew) {
        $titulo = trim((string) ($_POST['title'] ?? ''));
        if ($titulo === '') {
            http_response_code(422);
            die('O post precisa de um título. Volte e preencha o campo.');
        }
        $base = cmsSlugify($titulo);
        if ($base === '') $base = 'post-' . date('Y-m-d');
        $slug = cmsSlugUnico($pdo, $base);

        $stmt = $pdo->prepare('INSERT INTO cmstest_blog_posts (slug, title, subtitle, category, post_date, featured_image, hero_video, meta_description, keywords, content_md, author_id, author, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $slug, $titulo, $_POST['subtitle'], $_POST['category'], $_POST['post_date'],
            $_POST['featured_image'], $_POST['hero_video'], $_POST['meta_description'], $kwJson,
            $_POST['content_md'], $authorId, $authorName, $myId,
        ]);
        queueRebuild();
        header('Location: editar?slug=' . urlencode($slug) . '&created=1&t=' . time());
        exit;
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

if ($isNew) {
    // Formulário em branco. O autor já vem marcado como quem está logado, que é
    // o caso comum; dá para trocar no select.
    $post = [
        'title' => '', 'subtitle' => '', 'meta_description' => '',
        'post_date' => date('Y-m-d'), 'author' => '', 'author_id' => $myId,
        'featured_image' => '', 'hero_video' => '', 'category' => 'geral',
        'keywords' => '', 'content_md' => '', 'updated_by_name' => null,
    ];
} else {
    $stmt = $pdo->prepare('
      SELECT p.*, u.username AS updated_by_name
      FROM cmstest_blog_posts p
      LEFT JOIN cmstest_users u ON u.id = p.updated_by
      WHERE p.slug = ?
    ');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) { http_response_code(404); die('Post não encontrado'); }
}

$authorOptions = $pdo->query('SELECT id, username FROM cmstest_users ORDER BY username')->fetchAll();

$categories = ['assessoria-de-imprensa','marketing-de-autoridade','branding-estrategico','marketing-digital','comunicacao','marca-pessoal','negocios','geral'];

// keywords: JSON array in the DB -> comma-separated for the input
$kwDecoded = json_decode($post['keywords'] ?? '', true);
$kwText = is_array($kwDecoded) ? implode(', ', $kwDecoded) : (string) ($post['keywords'] ?? '');

$heroMode = !empty($post['hero_video']) ? 'video' : 'image';

adminLayoutTop(
    'blog',
    $isNew ? 'Novo post' : 'Editando post',
    ['label' => 'Posts do blog', 'href' => '/acesso/posts'],
    $isNew ? null : 'https://mclair.com.br/blog/' . urlencode($slug)
);
?>

<link rel="stylesheet" href="/acesso/vendor/toastui-editor.min.css" />
<style>
  /* Editor visual: encosta no visual do painel em vez do tema padrão da lib. */
  #wysiwyg { margin-top: 0; }
  .toastui-editor-defaultUI { border-radius: 8px; border-color: var(--line); font-family: inherit; }
  .toastui-editor-defaultUI-toolbar { background: var(--soft); border-radius: 8px 8px 0 0; border-bottom: 1px solid var(--line); }
  .toastui-editor-defaultUI .ProseMirror { font-family: inherit; font-size: .95rem; line-height: 1.7; }
  .toastui-editor-mode-switch { border-radius: 0 0 8px 8px; }

  /* Capa: a área preta inteira recebe a imagem arrastada. */
  .hero-drop { position: relative; cursor: pointer; border-radius: 10px 10px 0 0; overflow: hidden; }
  .hero-drop .hero-preview { pointer-events: none; }
  .hero-drop-hint {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px; text-align: center;
    padding: 12px; background: rgba(0,0,0,.55); color: #fff;
    opacity: 0; transition: opacity .15s ease; pointer-events: none;
  }
  .hero-drop-hint strong { font-size: .85rem; font-weight: 700; }
  .hero-drop-hint span { font-size: .75rem; opacity: .8; }
  /* Sem imagem, a instrução fica sempre à vista; com imagem, aparece no hover. */
  .hero-drop:hover .hero-drop-hint,
  .hero-drop.arrastando .hero-drop-hint,
  .hero-drop-hint.enviando { opacity: 1; }
  .hero-drop:has(.hero-preview .empty) .hero-drop-hint { opacity: 1; }
  .hero-drop.arrastando { outline: 2px dashed var(--red); outline-offset: -4px; }
  .hero-drop:has(.hero-preview .empty) .hero-preview .empty { visibility: hidden; }
</style>

<?php if (isset($_GET['created'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/<?= urlencode($slug) ?>"><?= cmsCheckIcon() ?><span class="msg-text">Post criado. Entra no ar na próxima republicação, em alguns minutos.</span></div>
<?php elseif (isset($_GET['saved'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/<?= urlencode($slug) ?>"><?= cmsCheckIcon() ?><span class="msg-text">Post salvo.</span></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(cmsCsrfToken()) ?>" />
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

  <div class="editor-grid">
    <!-- Left: content -->
    <div>
      <div class="card">
        <label style="margin-top:0">Título</label>
        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" />

        <label>Subtítulo</label>
        <input type="text" name="subtitle" value="<?= htmlspecialchars($post['subtitle']) ?>" />
        <p class="hint">Aparece logo abaixo do título no post publicado.</p>

        <label>Conteúdo</label>
        <div id="wysiwyg"></div>
        <textarea name="content_md" id="contentMd" hidden><?= htmlspecialchars($post['content_md']) ?></textarea>
      </div>
    </div>

    <!-- Right: metadata + SEO -->
    <div>
      <div class="card">
        <div class="side-sec">
          <strong>Capa</strong>
          <div class="hero-card" style="margin-top:10px;margin-bottom:0">
            <div class="hero-drop" id="heroDrop" title="Arraste uma imagem ou clique para escolher">
              <div class="hero-preview" id="heroPreview" style="min-height:140px">
                <span class="hero-chip" id="heroChip">Capa</span>
                <div class="empty">Sem mídia de capa</div>
              </div>
              <div class="hero-drop-hint" id="heroDropHint">
                <strong>Arraste uma imagem aqui</strong>
                <span>ou clique para escolher do computador</span>
              </div>
              <input type="file" id="heroFile" accept="image/*" style="display:none" />
            </div>
            <div class="hero-controls">
              <div class="hero-tabs" role="tablist">
                <button type="button" id="tabImage" onclick="setHeroMode('image')">Imagem</button>
                <button type="button" id="tabVideo" onclick="setHeroMode('video')">Vídeo</button>
              </div>
              <div id="paneImage">
                <label style="margin-top:0">Endereço da imagem</label>
                <input type="text" name="featured_image" id="featuredImage" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="/blog-images/exemplo.jpg" oninput="renderHero()" />
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
          <label>Autor</label>
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
          <div class="editor-actions">
            <button type="submit" class="btn" style="width:100%;justify-content:center"><?= $isNew ? 'Criar post' : 'Salvar' ?></button>
            <a href="/acesso/posts" class="btn secondary" style="width:100%;justify-content:center;margin-top:8px">Cancelar</a>
          </div>
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

<script src="/acesso/vendor/toastui-editor-all.min.js"></script>
<script src="/acesso/vendor/i18n-pt-br.js"></script>
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
// ---- Media picker modal ----
// Galeria do site: o editor visual já traz upload por arrastar, isto aqui é
// para reaproveitar imagem que já foi enviada antes.
var imgCurTab = 'up';
var imgPicked = '';

function mdImage() {
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
  editor.exec('addImage', { imageUrl: url, altText: '' });
  editor.focus();
  imgModalClose();
}

// ---- Editor visual ----
// O banco guarda markdown (o build roda marked.parse em content_md), então o
// editor abre em WYSIWYG mas o que vai no POST é sempre getMarkdown().
// Botão extra na barra: abre a galeria de imagens já enviadas ao site.
function botaoGaleria() {
  var b = document.createElement('button');
  b.type = 'button';
  b.textContent = 'Galeria';
  b.style.cssText = 'background:none;border:0;font:inherit;font-size:11px;font-weight:700;cursor:pointer;padding:0 6px;color:inherit';
  b.addEventListener('click', function () { mdImage(); });
  return b;
}

var campoMd = document.getElementById('contentMd');
var editor = new toastui.Editor({
  el: document.getElementById('wysiwyg'),
  height: '560px',
  initialEditType: 'wysiwyg',
  previewStyle: 'vertical',
  initialValue: campoMd.value,
  language: 'pt-BR',
  usageStatistics: false,
  autofocus: false,
  toolbarItems: [
    ['heading', 'bold', 'italic', 'strike'],
    ['hr', 'quote'],
    ['ul', 'ol'],
    ['table', 'link'],
    ['code', 'codeblock'],
    [{ name: 'galeria', tooltip: 'Imagem já enviada', el: botaoGaleria() }]
  ],
  hooks: {
    // Arrastar ou colar imagem dentro do texto: sobe pelo mesmo upload.php do painel.
    addImageBlobHook: function (blob, callback) {
      var fd = new FormData();
      fd.append('file', blob);
      fd.append('csrf', CMS_CSRF);
      fetch('/acesso/upload.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.j.url) { alert(res.j.error || 'Falha no upload da imagem.'); return; }
          callback(res.j.url, '');
        })
        .catch(function () { alert('Falha de rede ao enviar a imagem.'); });
      return false;
    }
  }
});
// O que o PHP recebe é o markdown, não o HTML do editor.
campoMd.form.addEventListener('submit', function () {
  campoMd.value = editor.getMarkdown();
});

// ---- Capa: a área preta é a zona de arrastar ----
(function () {
  var zona = document.getElementById('heroDrop');
  var arquivo = document.getElementById('heroFile');
  var campoUrl = document.getElementById('featuredImage');
  var dica = document.getElementById('heroDropHint');
  if (!zona) return;

  function subir(f) {
    if (!f) return;
    dica.classList.add('enviando');
    dica.querySelector('strong').textContent = 'Enviando...';
    dica.querySelector('span').textContent = '';
    var fd = new FormData();
    fd.append('file', f);
    fd.append('csrf', CMS_CSRF);
    fetch('/acesso/upload.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        dica.classList.remove('enviando');
        if (!res.ok || !res.j.url) {
          dica.querySelector('strong').textContent = res.j.error || 'Falha no upload.';
          dica.querySelector('span').textContent = 'Tente de novo';
          return;
        }
        campoUrl.value = res.j.url;
        campoUrl.dispatchEvent(new Event('input', { bubbles: true }));
        setHeroMode('image');
        dica.querySelector('strong').textContent = 'Arraste uma imagem aqui';
        dica.querySelector('span').textContent = 'ou clique para trocar';
      })
      .catch(function () {
        dica.classList.remove('enviando');
        dica.querySelector('strong').textContent = 'Falha de rede.';
        dica.querySelector('span').textContent = 'Tente de novo';
      });
  }

  zona.addEventListener('click', function () { arquivo.click(); });
  arquivo.addEventListener('change', function () { subir(arquivo.files[0]); arquivo.value = ''; });
  ['dragenter', 'dragover'].forEach(function (e) {
    zona.addEventListener(e, function (ev) { ev.preventDefault(); zona.classList.add('arrastando'); });
  });
  ['dragleave', 'drop'].forEach(function (e) {
    zona.addEventListener(e, function (ev) { ev.preventDefault(); zona.classList.remove('arrastando'); });
  });
  zona.addEventListener('drop', function (ev) {
    var f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
    if (f) subir(f);
  });
})();
</script>

<?php adminLayoutBottom(); ?>
