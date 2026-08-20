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
    header('Location: editar?slug=' . urlencode($slug) . '&saved=1');
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

adminLayoutTop('blog', 'Editando post', ['label' => 'Posts do blog', 'href' => '/admin/posts']);
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>

<form method="post">
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

  <!-- Hero media -->
  <div class="hero-card">
    <div class="hero-preview" id="heroPreview">
      <span class="hero-chip" id="heroChip">Capa</span>
      <div class="empty">Sem mídia de capa — cole uma URL abaixo</div>
    </div>
    <div class="hero-controls">
      <div class="hero-tabs" role="tablist">
        <button type="button" id="tabImage" onclick="setHeroMode('image')">Imagem</button>
        <button type="button" id="tabVideo" onclick="setHeroMode('video')">Vídeo</button>
      </div>
      <div id="paneImage">
        <label style="margin-top:0">Imagem de capa (URL)</label>
        <input type="text" name="featured_image" id="featuredImage" class="img-url" data-imgdrop-nothumb value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="/blog-images/exemplo.jpg" oninput="renderHero()" />
      </div>
      <div id="paneVideo">
        <label style="margin-top:0">Vídeo de capa (URL — YouTube ou arquivo .mp4)</label>
        <input type="text" name="hero_video" id="heroVideo" value="<?= htmlspecialchars($post['hero_video'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=..." oninput="renderHero()" />
        <p class="hint">Deixe em branco para usar a imagem como capa.</p>
      </div>
    </div>
  </div>

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
          <span class="div"></span>
          <button type="button" title="Título (H2)" onclick="mdLine('## ')">H2</button>
          <button type="button" title="Subtítulo (H3)" onclick="mdLine('### ')">H3</button>
          <span class="div"></span>
          <button type="button" title="Link" onclick="mdLink(this)">🔗</button>
          <span class="div"></span>
          <button type="button" title="Lista" onclick="mdLine('- ')">•&nbsp;Lista</button>
          <button type="button" title="Lista numerada" onclick="mdLine('1. ', true)">1.&nbsp;Lista</button>
          <button type="button" title="Citação" onclick="mdLine('> ')">&ldquo;&nbsp;Citação</button>
        </div>
        <textarea name="content_md" id="contentMd" style="min-height:480px;font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars($post['content_md']) ?></textarea>
      </div>
    </div>

    <!-- Right: metadata + SEO -->
    <div>
      <div class="card">
        <div class="side-sec">
          <strong>Publicação</strong>
          <label>Subtítulo</label>
          <input type="text" name="subtitle" value="<?= htmlspecialchars($post['subtitle']) ?>" />
          <label>Autor</label>
          <?php if ($isAuthorRole): ?>
            <input type="text" value="<?= htmlspecialchars($_SESSION['cms_username']) ?>" disabled />
          <?php else: ?>
            <select name="author_id" id="authorSelect" onchange="document.getElementById('authorOtherWrap').style.display = this.value === '' ? '' : 'none'">
              <option value="">— outro (digitar abaixo) —</option>
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
          <a href="/admin/posts" class="btn secondary" style="width:100%;justify-content:center;margin-top:8px">Cancelar</a>
        </div>
      </div>
    </div>
  </div>
</form>

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
  var chip = heroMode === 'video' ? 'Capa — vídeo' : 'Capa — imagem';
  var media;
  if (!url) {
    media = '<div class="empty">Sem mídia de capa — cole uma URL abaixo</div>';
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
</script>

<?php adminLayoutBottom(); ?>
