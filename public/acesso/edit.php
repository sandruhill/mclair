<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/_author_modal.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
$myId = (int) $_SESSION['cms_user_id'];

// Sem slug na URL = post novo. O editor atende os dois modos de propósito: duas
// telas separadas divergiriam na primeira vez que alguém mexesse num campo.
$isNew = ($slug === '');

// Autores com assinatura (tabela de db/migrations/2026-09-25-blog-autores.sql).
// Sem a migração, o campo volta a ser só o nome digitado, como sempre foi.
$authorsReady = cmsAuthorsReady($pdo);
$postDateHasTime = cmsPostDateHasTime($pdo);

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

    // Autores: a lista ordenada de ids vem em authors[]; o primeiro vai em
    // blog_author_id, todos vão para a tabela de vários autores (quando
    // existe) e o nome de exibição (`author`, usado nas listas e no JSON do
    // site) vira "A", "A e B"... Sem autor escolhido, vale o nome digitado,
    // como nos posts migrados. A gravação em si é feita depois do INSERT/UPDATE
    // por cmsPostAuthorsSave (precisa do slug).
    $authorIds = $authorsReady ? array_map('intval', (array) ($_POST['authors'] ?? [])) : [];
    $authorName = trim((string) ($_POST['author_other'] ?? ''));
    $authorName = $authorName !== '' ? $authorName : null;

    // Data e hora (fuso de Brasília). Sem a migração, só o dia. Data no futuro
    // não agenda nada (todo post sai no próximo build), então vira "agora" e
    // o editor avisa.
    $dataAjustada = false;
    $postDate = cmsNormalizarPostDate((string) ($_POST['post_date'] ?? ''), $postDateHasTime, $dataAjustada);
    $_POST['post_date'] = $postDate;
    $_POST['meta_description'] = mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 500);

    // Rede de segurança no servidor para o problema dos parágrafos colados
    // (ver cmsNormalizarParagrafos em db.php): o JS do editor já manda
    // certo, mas o banco nunca mais recebe "a\nb" onde deveria ser "a\n\nb".
    $conteudo = cmsNormalizarParagrafos((string) ($_POST['content_md'] ?? ''));

    if ($isNew) {
        $titulo = trim((string) ($_POST['title'] ?? ''));
        if ($titulo === '') {
            http_response_code(422);
            die('O post precisa de um título. Volte e preencha o campo.');
        }
        $base = cmsSlugify($titulo);
        if ($base === '') $base = 'post-' . date('Y-m-d');
        $slug = cmsSlugUnico($pdo, $base);

        $stmt = $pdo->prepare('INSERT INTO cmstest_blog_posts (slug, title, subtitle, category, post_date, featured_image, hero_video, meta_description, keywords, content_md, author, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $slug, $titulo, $_POST['subtitle'], $_POST['category'], $_POST['post_date'],
            $_POST['featured_image'], $_POST['hero_video'], $_POST['meta_description'], $kwJson,
            $conteudo, $authorName, $myId,
        ]);
        if ($authorsReady) cmsPostAuthorsSave($pdo, $slug, $authorIds, $authorName);
        queueRebuild();
        header('Location: editar?slug=' . urlencode($slug) . '&created=1&t=' . time() . ($dataAjustada ? '&data_ajustada=1' : ''));
        exit;
    }

    $stmt = $pdo->prepare('UPDATE cmstest_blog_posts SET title = ?, subtitle = ?, category = ?, post_date = ?, featured_image = ?, hero_video = ?, meta_description = ?, keywords = ?, content_md = ?, author = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([
        $_POST['title'], $_POST['subtitle'], $_POST['category'], $_POST['post_date'],
        $_POST['featured_image'], $_POST['hero_video'], $_POST['meta_description'], $kwJson,
        $conteudo, $authorName, $myId, $slug,
    ]);
    if ($authorsReady) cmsPostAuthorsSave($pdo, $slug, $authorIds, $authorName);
    queueRebuild();
    header('Location: editar?slug=' . urlencode($slug) . '&saved=1&t=' . time() . ($dataAjustada ? '&data_ajustada=1' : ''));
    exit;
}

if ($isNew) {
    // Formulário em branco. O autor já vem marcado como quem está logado, que é
    // o caso comum; dá para trocar no select.
    $post = [
        'title' => '', 'subtitle' => '', 'meta_description' => '',
        'post_date' => date('Y-m-d H:i'), 'author' => '', 'blog_author_id' => null,
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

$authorOptions = $authorsReady ? cmsAuthorsAll($pdo) : [];
$postAuthorIds = ($authorsReady && !$isNew) ? cmsPostAuthorIds($pdo, $slug) : [];
$authorsV2 = $authorsReady && cmsAuthorsV2Ready($pdo);

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
  /* Respiro entre parágrafos, como no site: sem isso o texto parece "tudo junto" mesmo gravado certo. */
  .toastui-editor-contents .ProseMirror > p { margin: 0 0 .9em; }
  .toastui-editor-mode-switch { border-radius: 0 0 8px 8px; }
  /* Barra de ferramentas acompanha a rolagem quando o editor é mais alto que a tela. */
  .toastui-editor-defaultUI-toolbar { position: sticky; top: 0; z-index: 6; }
  .toastui-editor-defaultUI { border-radius: 8px 8px 0 0; }

  /* Linha de publicação: aviso "Salvo/Publicado" e, quando o post está no
     ar, o convite para o próximo. O botão já ocupa o lugar (invisível) para
     a linha não mudar de tamanho na hora em que aparece. */
  .pub-row { display:flex; justify-content:flex-end; align-items:center; gap:10px; margin:0 0 18px; flex-wrap:wrap; }
  .pub-row #savedMsg { margin:0; }
  .btn-novo {
    --verde:#1B8A76; /* verde da paleta do site; branco sobre ele passa de 4.5:1 */
    background:var(--verde); color:#fff; padding:7px 14px; font-size:.8rem; gap:7px;
    visibility:hidden; opacity:0; transform:translateY(8px);
  }
  .btn-novo:hover { background:#166F5F; }
  .btn-novo.on { visibility:visible; animation:novoEntra .8s cubic-bezier(.22,1,.36,1) forwards, novoBrilho 1.8s ease-out .7s 1; }
  @keyframes novoEntra { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
  @keyframes novoBrilho { 0% { box-shadow:0 0 0 0 rgba(27,138,118,.55); } 60% { box-shadow:0 0 0 14px rgba(27,138,118,0); } 100% { box-shadow:0 0 0 0 rgba(27,138,118,0); } }
  @media (prefers-reduced-motion: reduce) { .btn-novo.on { animation:none; opacity:1; transform:none; } }
  @media (max-width: 768px) { .pub-row { justify-content:stretch; } .pub-row #savedMsg, .btn-novo { width:100%; justify-content:center; } }

  /* Contadores e barra de calor dos campos com limite. */
  .contador { float:right; font-weight:600; text-transform:none; letter-spacing:0; color:var(--ink-3); }
  .contador.quente { color:#9a1f1f; }
  .calor { position:relative; height:5px; border-radius:3px; background:var(--soft); margin:7px 0 0; }
  .calor i { display:block; height:100%; width:0; border-radius:3px; background:#1B8A76; transition:width .25s ease, background-color .25s ease; }
  .calor b { position:absolute; top:-3px; width:2px; height:11px; margin-left:-1px; border-radius:1px; background:#C7CBD1; }
  .calor b.lim { background:var(--ink-3); }
  .contagem { margin-top:8px; }
  @media (prefers-reduced-motion: reduce) { .calor i { transition:none; } }

  /* Barra do editor: o botão do bloco onde o cursor está (tabela, lista...)
     ganha um fundo discreto em vez do sprite azul da biblioteca. */
  .toastui-editor-toolbar-icons:not(:disabled):hover { background-color:rgba(26,27,30,.07); border-radius:6px; }
  .toastui-editor-toolbar-icons:not(:disabled).active, .toastui-editor-toolbar-icons:not(:disabled).active:hover { background-position-y:3px; background-color:rgba(200,16,46,.12); border-radius:6px; box-shadow:inset 0 0 0 1px rgba(200,16,46,.25); }

  /* Data e hora: campo de data com calendário próprio (sem o seletor nativo). */
  .dt-linha { display:flex; gap:6px; align-items:stretch; margin-top:6px; }
  .dt-campo { flex:1 1 auto; min-width:0; display:inline-flex; align-items:center; gap:8px; padding:9px 12px; border:1px solid var(--line); border-radius:8px; background:var(--paper); font:inherit; font-size:.9rem; color:var(--ink); cursor:pointer; text-align:left; white-space:nowrap; }
  .dt-campo:hover, .dt-campo[aria-expanded=true] { border-color:var(--red); }
  .dt-campo svg { color:var(--ink-3); flex-shrink:0; }
  /* A hora fica igual ao botão da data ao lado: mesma fonte, borda, altura e cor. */
  .dt-hora { flex:0 0 auto; width:104px; padding:9px 10px; border:1px solid var(--line); border-radius:8px; background:var(--paper); color:var(--ink); font-family:inherit; font-size:.9rem; font-variant-numeric:tabular-nums; box-sizing:border-box; -webkit-appearance:none; appearance:none; cursor:pointer; }
  .dt-hora:hover, .dt-hora:focus { border-color:var(--red); outline:none; }
  .dt-hora::-webkit-datetime-edit, .dt-hora::-webkit-datetime-edit-fields-wrapper { font-family:inherit; padding:0; }
  .dt-hora::-webkit-datetime-edit-hour-field:focus, .dt-hora::-webkit-datetime-edit-minute-field:focus { background:rgba(200,16,46,.12); color:var(--ink); border-radius:3px; }
  .dt-hora::-webkit-calendar-picker-indicator { opacity:.45; cursor:pointer; }
  .dt-hora::-webkit-calendar-picker-indicator:hover { opacity:.8; }
  .dt-agora { display:inline-block; margin-top:6px; padding:0; border:none; background:none; font:inherit; font-size:.76rem; font-weight:700; color:var(--red); cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
  .dt-agora:hover { color:var(--ink); }
  .dt-pop { margin-top:6px; padding:12px; border:1px solid var(--line); border-radius:12px; background:var(--paper); box-shadow:0 12px 30px -10px rgba(0,0,0,.18); }
  .dt-pop[hidden] { display:none; }
  .dt-pop-cabeca { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .dt-pop-cabeca strong { font-size:.85rem; }
  .dt-pop-cabeca button, .dt-pop-pe button { border:none; background:none; font:inherit; font-size:1rem; cursor:pointer; color:var(--ink-3); padding:4px 8px; border-radius:6px; }
  .dt-pop-cabeca button:hover, .dt-pop-pe button:hover { background:var(--soft); color:var(--ink); }
  .dt-grade { display:grid; grid-template-columns:repeat(7, 1fr); gap:2px; }
  .dt-grade .dt-dia-sem { font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-3); text-align:center; padding:4px 0; }
  .dt-grade button { border:none; background:none; font:inherit; font-size:.82rem; padding:7px 0; border-radius:8px; cursor:pointer; color:var(--ink); }
  .dt-grade button:hover { background:var(--soft); }
  .dt-grade button:focus-visible { outline:2px solid rgba(200,16,46,.35); outline-offset:-2px; }
  .dt-grade button.fora { color:#C7CBD1; }
  .dt-grade button.hoje { font-weight:800; color:var(--red); }
  .dt-grade button.sel, .dt-grade button.sel:hover { background:var(--red); color:#fff; }
  .dt-grade button.futuro { color:#C7CBD1; text-decoration:line-through; cursor:not-allowed; }
  .dt-pop-pe { display:flex; justify-content:space-between; margin-top:8px; font-size:.78rem; font-weight:700; }
  .dt-futuro { color:#9a1f1f; }

  /* Alça de altura: a borda de baixo do editor, arrastável. */
  .editor-alca { height: 14px; margin-top: -1px; border: 1px solid var(--line); border-top: none; border-radius: 0 0 8px 8px; background: var(--soft); cursor: ns-resize; display: flex; align-items: center; justify-content: center; touch-action: none; user-select: none; -webkit-user-select: none; }
  .editor-alca span { width: 36px; height: 3px; border-radius: 3px; background: #C7CBD1; }
  .editor-alca:hover span, .editor-alca.arrastando span { background: var(--red); }
  .editor-alca.arrastando { background: rgba(200,16,46,.05); }

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

  /* Autores escolhidos: uma linha por autor, na ordem em que assinam. */
  .au-list { display:flex; flex-direction:column; gap:6px; }
  .au-list:empty { display:none; }
  .au-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:var(--soft); }
  .au-item .au-foto { width:30px; height:30px; border-radius:50%; object-fit:cover; background:#fff; flex-shrink:0; }
  .au-item .au-inicial { width:30px; height:30px; border-radius:50%; background:rgba(200,16,46,.1); color:var(--red); font-weight:800; font-size:.75rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .au-item .au-nome { flex:1; min-width:0; font-size:.85rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .au-item .au-nome small { display:block; font-weight:600; color:var(--ink-3); font-size:.7rem; }
  .au-item button { background:none; border:none; padding:2px 4px; cursor:pointer; font-family:inherit; font-size:.72rem; font-weight:700; color:var(--ink-3); }
  .au-item button:hover { color:var(--ink); }
  .au-item button.au-rem { color:var(--red); }
</style>

<?php if (isset($_GET['data_ajustada'])): ?><div class="msg err">A data estava no futuro. Como não existe agendamento (todo post sai na próxima republicação), ela foi ajustada para agora.</div><?php endif; ?>
<?php if (isset($_GET['created']) || isset($_GET['saved'])): ?>
<div class="pub-row">
  <div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/<?= urlencode($slug) ?>"><?= cmsCheckIcon() ?><span class="msg-text"><?= isset($_GET['created']) ? 'Post criado. Entra no ar na próxima republicação, em alguns minutos.' : 'Post salvo.' ?></span></div>
  <!-- Aparece só quando a publicação é confirmada no ar (evento mclair:publicado). O espaço já fica reservado, para nada pular. -->
  <a class="btn btn-novo" id="novoPostBtn" href="/acesso/posts/novo" aria-hidden="true" tabindex="-1">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
    Escrever novo post
  </a>
</div>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(cmsCsrfToken()) ?>" />
  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />

  <div class="editor-grid">
    <!-- Left: content -->
    <div>
      <div class="card">
        <label style="margin-top:0">Título <span class="contador" id="tituloCont"></span></label>
        <input type="text" name="title" id="tituloInput" value="<?= htmlspecialchars($post['title']) ?>" />
        <div class="calor" id="tituloCalor" aria-hidden="true"><i></i><b style="left:37.5%" title="60 caracteres: até aqui o Google mostra inteiro"></b><b class="lim" style="left:87.5%" title="140 caracteres: limite bom para SEO"></b></div>
        <p class="hint">Até 140 caracteres é bom para SEO. O Google costuma mostrar cerca de 60 no resultado; este site corta o título da aba em 52 e acrescenta " | Blog Mclair".</p>

        <label>Subtítulo <span class="contador" id="subCont"></span></label>
        <input type="text" name="subtitle" id="subInput" value="<?= htmlspecialchars($post['subtitle']) ?>" />
        <p class="hint">Aparece logo abaixo do título no post publicado.</p>

        <label>Conteúdo</label>
        <div id="wysiwyg"></div>
        <div class="editor-alca" id="editorAlca" title="Arraste para mudar a altura do editor; clique duas vezes para voltar ao automático" role="separator" aria-orientation="horizontal" aria-label="Altura do editor"><span></span></div>
        <p class="hint contagem" id="contagemTexto" aria-live="polite">0 palavras</p>
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
                <input type="hidden" name="featured_image" id="featuredImage" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" oninput="renderHero()" />
                <p class="hint" style="margin-top:0">Arraste a imagem na área acima, ou clique nela para escolher do computador.</p>
                <button type="button" class="btn secondary" id="removerCapa" style="display:none;margin-top:8px;width:100%;justify-content:center">Remover capa</button>
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
          <label>Autor<?= $authorsV2 ? 'es' : '' ?></label>
          <?php if ($authorsReady): ?>
          <div id="authorList" class="au-list"></div>
          <div style="display:flex;gap:6px;align-items:stretch;margin-top:6px">
            <select id="authorAdd" onchange="autorAdicionarDoSelect()" style="min-width:0">
              <option value="">Escolher autor cadastrado...</option>
              <?php foreach ($authorOptions as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= $a['kind'] === 'organizacao' ? ' (organização)' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn secondary" style="white-space:nowrap;padding:8px 12px;font-size:.78rem" onclick="autorModal.abrir(null, autorCriado)" title="Cadastrar um autor novo sem sair do post">+ novo autor</button>
          </div>
          <p class="hint"><?= $authorsV2 ? 'Um ou mais autores, na ordem em que assinam. ' : '' ?>Com autor cadastrado, o artigo ganha a assinatura com foto no início e a bio no fim do texto, e entra na página do autor no site. O cadastro fica em Posts do blog, Autores.</p>
          <?php if (!$authorsV2): ?><p class="hint"><?= htmlspecialchars(CMS_AUTHORS_V2_HINT) ?></p><?php endif; ?>
          <div id="authorOtherWrap" style="<?= $postAuthorIds ? 'display:none' : '' ?>">
            <input type="text" name="author_other" value="<?= htmlspecialchars($postAuthorIds ? '' : ($post['author'] ?? '')) ?>" placeholder="Nome do autor" style="margin-top:6px" />
            <p class="hint">Só o nome, sem assinatura. É como estão os posts migrados do site antigo.</p>
          </div>
          <?php else: ?>
          <input type="text" name="author_other" value="<?= htmlspecialchars($post['author'] ?? '') ?>" placeholder="Nome do autor" />
          <p class="hint"><?= htmlspecialchars(CMS_AUTHORS_MIGRATION_HINT) ?></p>
          <?php endif; ?>
          <label>Categoria</label>
          <select name="category">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= $post['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
          <label>Data e hora de postagem</label>
          <input type="hidden" name="post_date" id="postDate" value="<?= htmlspecialchars((string) $post['post_date']) ?>" />
          <div class="dt-linha">
            <button type="button" class="dt-campo" id="dtAbrir" aria-haspopup="dialog" aria-expanded="false" title="Escolher a data">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
              <span id="dtTexto">escolher</span>
            </button>
            <input type="time" id="dtHora" class="dt-hora" step="60" aria-label="Hora" />
          </div>
          <button type="button" class="dt-agora" id="dtAgora" title="Usar a data e a hora de agora">Usar agora</button>
          <div class="dt-pop" id="dtPop" role="dialog" aria-label="Calendário" hidden>
            <div class="dt-pop-cabeca">
              <button type="button" id="dtAnt" aria-label="Mês anterior">‹</button>
              <strong id="dtMes"></strong>
              <button type="button" id="dtProx" aria-label="Próximo mês">›</button>
            </div>
            <div class="dt-grade" id="dtGrade" role="grid"></div>
            <div class="dt-pop-pe"><button type="button" id="dtHoje">Hoje</button><button type="button" id="dtFechar">Fechar</button></div>
          </div>
          <p class="hint" id="dtAviso"><?= $postDateHasTime ? 'Horário de Brasília. Não existe agendamento: o post entra no ar na próxima republicação, com a data que estiver aqui.' : 'Só o dia é gravado por enquanto (a hora depende de db/migrations/2026-09-26-blog-post-date-datetime.sql).' ?></p>
        </div>

        <div class="side-sec">
          <strong>SEO</strong>
          <label>Meta description <span class="contador" id="metaCont"></span></label>
          <textarea name="meta_description" id="metaInput" maxlength="140" style="min-height:90px;font-size:.85rem"><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
          <div class="calor" id="metaCalor" aria-hidden="true"><i></i><b class="lim" style="left:100%" title="140 caracteres: limite"></b></div>
          <p class="hint">Resumo para o Google, até 140 caracteres. Colar um texto maior corta na última palavra que cabe.</p>
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
// ---- Parágrafos: o que o editor mostra é o que o site publica ----
// O Toast UI grava cada Enter como quebra simples de linha ("a\nb"), que para
// o markdown do site é a mesma frase: o post inteiro saía num único <p>. E ao
// abrir "a\n\nb" ele mostra um parágrafo vazio entre os dois. A tradução é
// feita nos dois sentidos, direto no documento do editor (não em regex sobre
// o texto), então lista, tabela, citação e código passam intactos:
//  - ao abrir, os parágrafos vazios somem;
//  - ao salvar, cada par de parágrafos ganha a linha em branco que o markdown
//    exige, e parágrafos vazios (inclusive os de espaço em branco que o Word
//    cola) são descartados.
function ehParagrafo(n) { return n.type.name === 'paragraph'; }
function ehParagrafoVazio(n) {
  if (!ehParagrafo(n)) return false;
  var soTexto = true;
  n.forEach(function (f) { if (!f.isText) soTexto = false; });
  return soTexto && n.textContent.replace(/[\s ]+/g, '') === '';
}
function semParagrafosVazios(node, comLinhaEmBranco) {
  var tipo = node.type.name;
  if (tipo !== 'doc' && tipo !== 'blockQuote') return node;
  var filhos = [];
  var anterior = null;
  node.forEach(function (f) {
    var atual = semParagrafosVazios(f, comLinhaEmBranco);
    if (ehParagrafoVazio(atual)) return;
    if (comLinhaEmBranco && anterior && ehParagrafo(anterior) && ehParagrafo(atual)) {
      filhos.push(node.type.schema.nodes.paragraph.create());
    }
    filhos.push(atual);
    anterior = atual;
  });
  if (!filhos.length) filhos.push(node.type.schema.nodes.paragraph.create());
  return node.type.create(node.attrs, filhos, node.marks);
}
function markdownParaSalvar() {
  try {
    var doc = editor.wwEditor.getModel();
    return editor.convertor.toMarkdownText(semParagrafosVazios(doc, true));
  } catch (e) {
    // Sem os internos do editor (versão diferente), vai o markdown cru; o
    // PHP aplica a mesma regra ao salvar.
    return editor.getMarkdown();
  }
}
function compactarParaEdicao() {
  try {
    var view = editor.wwEditor.view;
    var doc = view.state.doc;
    var novo = semParagrafosVazios(doc, false);
    if (novo.eq(doc)) return;
    var tr = view.state.tr.replaceWith(0, doc.content.size, novo.content);
    tr.setMeta('addToHistory', false);
    view.dispatch(tr);
  } catch (e) {}
}
compactarParaEdicao();

// ---- Colagem do Word e do Google Docs ----
// O Google Docs envolve tudo o que copia num <b style="font-weight:normal">,
// e o editor lia isso como "tudo em negrito". O Word manda <o:p> e parágrafos
// só com espaço. O HTML colado é limpo antes de entrar no editor; a colagem
// em si continua sendo a do editor (o evento é reemitido já limpo).
function limparHtmlColado(html) {
  var doc = new DOMParser().parseFromString(html, 'text/html');
  function desembrulhar(el) {
    while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el);
    el.parentNode.removeChild(el);
  }
  function embrulhar(el, tag) {
    var w = doc.createElement(tag);
    el.parentNode.insertBefore(w, el);
    w.appendChild(el);
  }
  doc.querySelectorAll('b[id^="docs-internal-guid"], b[style*="font-weight:normal"], b[style*="font-weight: normal"], b[style*="font-weight:400"], b[style*="font-weight: 400"]').forEach(desembrulhar);
  doc.querySelectorAll('span[style]').forEach(function (s) {
    var peso = s.style.fontWeight;
    if (peso === 'bold' || peso === 'bolder' || parseInt(peso, 10) >= 600) embrulhar(s, 'strong');
    if (s.style.fontStyle === 'italic') embrulhar(s, 'em');
  });
  doc.querySelectorAll('meta, style, script, link, o\\:p').forEach(function (n) { n.parentNode.removeChild(n); });
  return doc.body.innerHTML;
}
(function () {
  var view;
  try { view = editor.wwEditor.view; } catch (e) { return; }
  if (!view || !view.dom) return;
  var reemitindo = false;
  view.dom.addEventListener('paste', function (ev) {
    if (reemitindo) return;
    var cd = ev.clipboardData;
    if (!cd) return;
    if (cd.files && cd.files.length) return; // imagem colada: o hook de upload cuida
    var html = cd.getData('text/html');
    if (!html) return;
    var limpo;
    try {
      var dt = new DataTransfer();
      dt.setData('text/html', limparHtmlColado(html));
      dt.setData('text/plain', cd.getData('text/plain') || '');
      limpo = new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true });
    } catch (e) {
      return; // navegador sem suporte: segue a colagem normal
    }
    ev.preventDefault();
    ev.stopImmediatePropagation();
    reemitindo = true;
    try { view.dom.dispatchEvent(limpo); } finally { reemitindo = false; }
  }, true);
})();

// O que o PHP recebe é o markdown, não o HTML do editor.
campoMd.form.addEventListener('submit', function () {
  campoMd.value = markdownParaSalvar();
});

// ---- "Escrever novo post" depois de publicar, e alterações não salvas ----
(function () {
  var botao = document.getElementById('novoPostBtn');
  var alterado = false;
  function marcar() { alterado = true; }
  editor.on('change', marcar);
  campoMd.form.addEventListener('input', marcar);
  campoMd.form.addEventListener('submit', function () { alterado = false; });
  window.addEventListener('beforeunload', function (e) {
    if (!alterado) return;
    e.preventDefault();
    e.returnValue = '';
  });
  if (!botao) return;
  document.addEventListener('mclair:publicado', function () {
    botao.classList.add('on');
    botao.removeAttribute('aria-hidden');
    botao.removeAttribute('tabindex');
  });
  botao.addEventListener('click', function (e) {
    if (!alterado || botao.dataset.confirmado) return;
    e.preventDefault();
    // Mesmo padrão inline do painel: nada de confirm() nativo.
    var box = document.createElement('span');
    box.className = 'ci';
    var m = document.createElement('span');
    m.className = 'ci-msg';
    m.textContent = 'Há alterações não salvas neste post.';
    var sim = document.createElement('button');
    sim.type = 'button'; sim.className = 'ci-yes'; sim.textContent = 'sair sem salvar';
    var nao = document.createElement('button');
    nao.type = 'button'; nao.className = 'ci-no'; nao.textContent = 'ficar';
    sim.addEventListener('click', function () { alterado = false; location.href = botao.href; });
    nao.addEventListener('click', function () { box.remove(); botao.style.display = ''; });
    box.append(m, sim, nao);
    botao.style.display = 'none';
    botao.after(box);
  });
})();

// ---- Contadores: título, subtítulo, meta description e texto ----
(function () {
  var VERDE = [27, 138, 118], AMBAR = [199, 119, 0], VERMELHO = [200, 16, 46];
  function mistura(a, b, f) { return 'rgb(' + a.map(function (v, i) { return Math.round(v + (b[i] - v) * f); }).join(',') + ')'; }
  function corCalor(r) {
    if (r >= 1) return mistura(VERMELHO, VERMELHO, 0);
    if (r >= .85) return mistura(AMBAR, VERMELHO, (r - .85) / .15);
    if (r >= .45) return mistura(VERDE, AMBAR, (r - .45) / .4);
    return mistura(VERDE, VERDE, 0);
  }
  // barra: limite = onde fica vermelha; escala = 100% da largura
  function calor(barra, n, limite, escala) {
    var i = barra.querySelector('i');
    i.style.width = Math.min(100, n / escala * 100) + '%';
    i.style.backgroundColor = corCalor(n / limite);
  }
  function contador(el, n, limite) {
    el.textContent = limite ? n + '/' + limite : n;
    el.classList.toggle('quente', !!limite && n >= limite);
  }
  var titulo = document.getElementById('tituloInput'), tituloCont = document.getElementById('tituloCont'), tituloCalor = document.getElementById('tituloCalor');
  var sub = document.getElementById('subInput'), subCont = document.getElementById('subCont');
  var meta = document.getElementById('metaInput'), metaCont = document.getElementById('metaCont'), metaCalor = document.getElementById('metaCalor');
  function atualizarTitulo() { var n = titulo.value.length; contador(tituloCont, n, 140); calor(tituloCalor, n, 140, 160); }
  function atualizarSub() { contador(subCont, sub.value.length, 0); }
  function atualizarMeta() { var n = meta.value.length; contador(metaCont, n, 140); calor(metaCalor, n, 140, 140); }
  titulo.addEventListener('input', atualizarTitulo);
  sub.addEventListener('input', atualizarSub);
  meta.addEventListener('input', atualizarMeta);
  // Colar além de 140 na meta: corta na última palavra inteira que cabe.
  meta.addEventListener('paste', function (e) {
    var colado = (e.clipboardData || window.clipboardData).getData('text');
    if (!colado) return;
    var ini = meta.selectionStart, fim = meta.selectionEnd;
    var novo = meta.value.slice(0, ini) + colado + meta.value.slice(fim);
    if (novo.length <= 140) return;
    e.preventDefault();
    var corte = novo.slice(0, 140);
    var esp = corte.lastIndexOf(' ');
    if (esp > 100) corte = corte.slice(0, esp);
    meta.value = corte.replace(/[\s,;:]+$/, '');
    meta.setSelectionRange(meta.value.length, meta.value.length);
    atualizarMeta();
  });
  atualizarTitulo(); atualizarSub(); atualizarMeta();

  // Texto: conta o que o leitor vê, sem a sintaxe do markdown. Atualiza no
  // máximo a cada 300 ms para não pesar em post longo.
  var saida = document.getElementById('contagemTexto');
  function textoVisivel(md) {
    return md
      .replace(/```[\s\S]*?```/g, function (b) { return b.replace(/```[^\n]*/g, ''); })
      .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
      .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
      .replace(/<[^>]+>/g, ' ')
      .replace(/^\s{0,3}(#{1,6}\s+|>\s?|[-*+]\s+|\d+[.)]\s+)/gm, '')
      .replace(/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/gm, '')
      .replace(/\|/g, ' ')
      .replace(/(\*\*|__|~~|`|\*|_)/g, '')
      .replace(/\\([\\`*_{}\[\]()#+\-.!|])/g, '$1');
  }
  function contarTexto() {
    var t = textoVisivel(editor.getMarkdown());
    var palavras = (t.match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu) || []).length;
    var comEspaco = t.replace(/\s+/g, ' ').trim().length;
    var semEspaco = t.replace(/\s/g, '').length;
    saida.textContent = palavras.toLocaleString('pt-BR') + (palavras === 1 ? ' palavra' : ' palavras') + ' · ' + comEspaco.toLocaleString('pt-BR') + ' caracteres com espaço · ' + semEspaco.toLocaleString('pt-BR') + ' sem espaço';
  }
  var agendado = null;
  editor.on('change', function () {
    if (agendado) return;
    agendado = setTimeout(function () { agendado = null; contarTexto(); }, 300);
  });
  contarTexto();
})();

// ---- Data e hora: calendário próprio + hora + "Agora" ----
(function () {
  var TEM_HORA = <?= $postDateHasTime ? 'true' : 'false' ?>;
  var oculto = document.getElementById('postDate');
  var abrir = document.getElementById('dtAbrir'), texto = document.getElementById('dtTexto');
  var hora = document.getElementById('dtHora'), agora = document.getElementById('dtAgora');
  var pop = document.getElementById('dtPop'), grade = document.getElementById('dtGrade'), mesEl = document.getElementById('dtMes');
  var aviso = document.getElementById('dtAviso');
  var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  var DIAS = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
  var p2 = function (n) { return (n < 10 ? '0' : '') + n; };

  // valor gravado: "AAAA-MM-DD" ou "AAAA-MM-DD HH:MM[:SS]"
  var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(oculto.value) || [];
  var hoje = new Date();
  var sel = m.length ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());
  hora.value = m[4] ? m[4] + ':' + m[5] : (m.length ? '09:00' : p2(hoje.getHours()) + ':' + p2(hoje.getMinutes()));
  hora.style.display = TEM_HORA ? '' : 'none';
  var vendo = new Date(sel.getFullYear(), sel.getMonth(), 1);

  function ehFuturo(d, h) {
    var ag = new Date();
    var c = new Date(d.getFullYear(), d.getMonth(), d.getDate(), +(h || '00:00').slice(0, 2), +(h || '00:00').slice(3, 5));
    return c.getTime() > ag.getTime() + 5 * 60000;
  }
  function gravar() {
    var v = sel.getFullYear() + '-' + p2(sel.getMonth() + 1) + '-' + p2(sel.getDate());
    if (TEM_HORA && hora.value) v += ' ' + hora.value;
    oculto.value = v;
    texto.textContent = p2(sel.getDate()) + '/' + p2(sel.getMonth() + 1) + '/' + sel.getFullYear();
    var fut = ehFuturo(sel, TEM_HORA ? hora.value : '00:00');
    aviso.classList.toggle('dt-futuro', fut);
    aviso.textContent = fut
      ? 'Essa data está no futuro. Não existe agendamento: ao salvar, ela vira "agora" e o post sai na próxima republicação.'
      : (TEM_HORA ? 'Horário de Brasília. Não existe agendamento: o post entra no ar na próxima republicação, com a data que estiver aqui.' : 'Só o dia é gravado por enquanto (a hora depende de db/migrations/2026-09-26-blog-post-date-datetime.sql).');
  }
  function mesmoDia(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
  function desenhar(focar) {
    var nomeMes = MESES[vendo.getMonth()];
    mesEl.textContent = nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1) + ' de ' + vendo.getFullYear();
    grade.innerHTML = '';
    DIAS.forEach(function (d) { var s = document.createElement('span'); s.className = 'dt-dia-sem'; s.textContent = d; grade.appendChild(s); });
    var primeiro = new Date(vendo.getFullYear(), vendo.getMonth(), 1);
    var inicio = new Date(primeiro); inicio.setDate(1 - primeiro.getDay()); // semana começa no domingo
    for (var i = 0; i < 42; i++) {
      var d = new Date(inicio); d.setDate(inicio.getDate() + i);
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = d.getDate();
      b.dataset.iso = d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate());
      b.setAttribute('aria-label', d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear());
      if (d.getMonth() !== vendo.getMonth()) b.classList.add('fora');
      if (mesmoDia(d, hoje)) b.classList.add('hoje');
      if (mesmoDia(d, sel)) { b.classList.add('sel'); b.setAttribute('aria-selected', 'true'); }
      var fut = d.getTime() > new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate()).getTime();
      if (fut) { b.classList.add('futuro'); b.disabled = true; b.title = 'Data futura: não existe agendamento'; }
      b.tabIndex = mesmoDia(d, sel) ? 0 : -1;
      (function (dd) { b.addEventListener('click', function () { sel = dd; gravar(); fechar(); }); })(d);
      grade.appendChild(b);
    }
    if (focar) { var f = grade.querySelector('button.sel') || grade.querySelector('button:not(.fora):not(:disabled)'); if (f) f.focus(); }
  }
  function abrirPop() { pop.hidden = false; abrir.setAttribute('aria-expanded', 'true'); vendo = new Date(sel.getFullYear(), sel.getMonth(), 1); desenhar(true); }
  function fechar() { pop.hidden = true; abrir.setAttribute('aria-expanded', 'false'); abrir.focus(); }
  abrir.addEventListener('click', function () { pop.hidden ? abrirPop() : fechar(); });
  document.getElementById('dtFechar').addEventListener('click', fechar);
  document.getElementById('dtAnt').addEventListener('click', function () { vendo.setMonth(vendo.getMonth() - 1); desenhar(false); });
  document.getElementById('dtProx').addEventListener('click', function () { vendo.setMonth(vendo.getMonth() + 1); desenhar(false); });
  document.getElementById('dtHoje').addEventListener('click', function () { sel = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate()); gravar(); fechar(); });
  agora.addEventListener('click', function () {
    var ag = new Date();
    sel = new Date(ag.getFullYear(), ag.getMonth(), ag.getDate());
    hora.value = p2(ag.getHours()) + ':' + p2(ag.getMinutes());
    gravar();
  });
  hora.addEventListener('input', gravar);
  // Teclado dentro da grade: setas andam pelos dias, Enter escolhe, Esc fecha.
  grade.addEventListener('keydown', function (e) {
    var b = document.activeElement;
    if (!b || !b.dataset || !b.dataset.iso) return;
    var passo = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
    if (passo) {
      e.preventDefault();
      var d = new Date(+b.dataset.iso.slice(0, 4), +b.dataset.iso.slice(5, 7) - 1, +b.dataset.iso.slice(8, 10));
      d.setDate(d.getDate() + passo);
      if (d.getMonth() !== vendo.getMonth() || d.getFullYear() !== vendo.getFullYear()) { vendo = new Date(d.getFullYear(), d.getMonth(), 1); desenhar(false); }
      var alvo = grade.querySelector('button[data-iso="' + d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()) + '"]');
      if (alvo && !alvo.disabled) alvo.focus();
    } else if (e.key === 'Escape') { e.preventDefault(); fechar(); }
  });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target) && e.target !== abrir && !abrir.contains(e.target)) fechar(); });
  gravar();
})();

// ---- Altura do editor ----
// Por padrão o editor cresce até a base da coluna da direita (capa,
// publicação, SEO), para as duas colunas terminarem juntas; no celular, com
// uma coluna só, usa uma altura fixa. A alça na borda de baixo deixa o
// usuário escolher outra altura, lembrada neste navegador; clique duplo na
// alça volta ao automático.
(function () {
  var MIN = 320, PADRAO_CELULAR = 440, CHAVE = 'mclair:editorAltura';
  var alca = document.getElementById('editorAlca');
  var caixa = document.getElementById('wysiwyg');
  var lateral = document.querySelector('.editor-grid > div:last-child > .card');
  var cartaoTexto = caixa.closest('.card');
  var escolhida = null;
  try { escolhida = parseInt(localStorage.getItem(CHAVE) || '', 10) || null; } catch (e) {}

  function duasColunas() { return window.innerWidth > 980; }
  function alturaAutomatica() {
    if (!duasColunas() || !lateral) return PADRAO_CELULAR;
    var baseLateral = lateral.getBoundingClientRect().bottom;
    var r = caixa.getBoundingClientRect();
    // tudo o que fica abaixo do editor dentro do cartão (alça, contagem, respiro)
    var rodape = cartaoTexto.getBoundingClientRect().bottom - r.bottom;
    return Math.max(MIN, Math.round(baseLateral - r.top - rodape));
  }
  function aplicar(altura) {
    editor.setHeight(Math.max(MIN, Math.round(altura)) + 'px');
  }
  function ajustar() {
    aplicar(escolhida || alturaAutomatica());
  }
  ajustar();
  window.addEventListener('resize', ajustar);
  if (window.ResizeObserver && lateral) {
    // A coluna da direita muda de tamanho (prévia da capa carregou, autor
    // adicionado): o editor acompanha, a não ser que o usuário tenha fixado.
    new ResizeObserver(function () { if (!escolhida) ajustar(); }).observe(lateral);
  }

  var arrastando = false, yInicial = 0, alturaInicial = 0;
  alca.addEventListener('pointerdown', function (e) {
    if (e.button !== 0) return;
    e.preventDefault();
    arrastando = true;
    yInicial = e.clientY;
    alturaInicial = caixa.getBoundingClientRect().height;
    alca.classList.add('arrastando');
    alca.setPointerCapture(e.pointerId);
  });
  alca.addEventListener('pointermove', function (e) {
    if (!arrastando) return;
    aplicar(alturaInicial + (e.clientY - yInicial));
  });
  function soltar(e) {
    if (!arrastando) return;
    arrastando = false;
    alca.classList.remove('arrastando');
    escolhida = Math.round(caixa.getBoundingClientRect().height);
    try { localStorage.setItem(CHAVE, String(escolhida)); } catch (err) {}
  }
  alca.addEventListener('pointerup', soltar);
  alca.addEventListener('pointercancel', soltar);
  alca.addEventListener('dblclick', function () {
    escolhida = null;
    try { localStorage.removeItem(CHAVE); } catch (err) {}
    ajustar();
  });
})();

// ---- Autores ----
// AUTORES tem todo o cadastro; ESCOLHIDOS é a ordem em que assinam o post.
// Cada escolhido vira um hidden input authors[] no form; sem escolhido,
// aparece o campo de nome livre.
var AUTORES = <?= json_encode(array_map(fn($a) => ['id' => $a['id'], 'name' => $a['name'], 'photo_url' => $a['photo_url'], 'kind' => $a['kind'], 'job_title' => $a['job_title']], $authorOptions), JSON_UNESCAPED_UNICODE) ?>;
var ESCOLHIDOS = <?= json_encode(array_values($postAuthorIds)) ?>;
var MULTI_AUTOR = <?= $authorsV2 ? 'true' : 'false' ?>;

function autorPorId(id) {
  for (var i = 0; i < AUTORES.length; i++) if (AUTORES[i].id === id) return AUTORES[i];
  return null;
}
function autorIniciais(nome) {
  var p = nome.trim().split(/\s+/);
  return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
}
function autorRender() {
  var box = document.getElementById('authorList');
  var wrap = document.getElementById('authorOtherWrap');
  var sel = document.getElementById('authorAdd');
  if (!box) return;
  box.innerHTML = '';
  ESCOLHIDOS.forEach(function (id, i) {
    var a = autorPorId(id);
    if (!a) return;
    var row = document.createElement('div');
    row.className = 'au-item';
    var foto = a.photo_url
      ? '<img class="au-foto" src="' + a.photo_url.replace(/"/g, '&quot;') + '" alt="" />'
      : '<span class="au-inicial">' + autorIniciais(a.name) + '</span>';
    row.innerHTML = foto + '<span class="au-nome"></span>'
      + (ESCOLHIDOS.length > 1 && i > 0 ? '<button type="button" class="au-up" title="Assina antes">subir</button>' : '')
      + '<button type="button" class="au-rem" title="Tirar este autor do post">remover</button>'
      + '<input type="hidden" name="authors[]" value="' + a.id + '" />';
    var nome = row.querySelector('.au-nome');
    nome.textContent = a.name;
    if (a.job_title) { var s = document.createElement('small'); s.textContent = a.job_title; nome.appendChild(s); }
    var up = row.querySelector('.au-up');
    if (up) up.addEventListener('click', function () { ESCOLHIDOS.splice(i - 1, 0, ESCOLHIDOS.splice(i, 1)[0]); autorRender(); });
    row.querySelector('.au-rem').addEventListener('click', function () { ESCOLHIDOS.splice(i, 1); autorRender(); });
    box.appendChild(row);
  });
  if (wrap) wrap.style.display = ESCOLHIDOS.length ? 'none' : '';
  if (sel) {
    Array.prototype.forEach.call(sel.options, function (o) {
      if (!o.value) return;
      o.disabled = ESCOLHIDOS.indexOf(parseInt(o.value, 10)) !== -1;
    });
    sel.value = '';
    // Sem a migração de vários autores, o segundo autor não seria gravado: o
    // select some depois do primeiro em vez de prometer o que não vai.
    sel.style.display = (!MULTI_AUTOR && ESCOLHIDOS.length) ? 'none' : '';
  }
}
function autorAdicionar(id) {
  id = parseInt(id, 10);
  if (!id || ESCOLHIDOS.indexOf(id) !== -1) return;
  if (!MULTI_AUTOR) ESCOLHIDOS = [];
  ESCOLHIDOS.push(id);
  autorRender();
}
function autorAdicionarDoSelect() {
  autorAdicionar(document.getElementById('authorAdd').value);
}
// "+ novo autor" salvou: entra no cadastro e já assina o post, sem recarregar.
function autorCriado(autor) {
  AUTORES.push({ id: autor.id, name: autor.name, photo_url: autor.photo_url, kind: autor.kind, job_title: autor.job_title });
  var sel = document.getElementById('authorAdd');
  if (sel) {
    var opt = document.createElement('option');
    opt.value = autor.id;
    opt.textContent = autor.name + (autor.kind === 'organizacao' ? ' (organização)' : '');
    sel.appendChild(opt);
  }
  autorAdicionar(autor.id);
}
autorRender();

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
        sincronizarRemover();
        dica.querySelector('strong').textContent = 'Arraste uma imagem aqui';
        dica.querySelector('span').textContent = 'ou clique para trocar';
      })
      .catch(function () {
        dica.classList.remove('enviando');
        dica.querySelector('strong').textContent = 'Falha de rede.';
        dica.querySelector('span').textContent = 'Tente de novo';
      });
  }

  var botaoRemover = document.getElementById('removerCapa');
  function sincronizarRemover() {
    botaoRemover.style.display = campoUrl.value.trim() ? '' : 'none';
  }
  botaoRemover.addEventListener('click', function (ev) {
    ev.stopPropagation();
    campoUrl.value = '';
    campoUrl.dispatchEvent(new Event('input', { bubbles: true }));
    sincronizarRemover();
  });
  campoUrl.addEventListener('input', sincronizarRemover);
  sincronizarRemover();

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

<?php if ($authorsReady) cmsAuthorModal(); ?>

<?php adminLayoutBottom(); ?>
