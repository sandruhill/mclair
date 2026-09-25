<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/_author_modal.php';
$pdo = cmsDb();
$podeRemover = in_array(cmsRole(), ['admin', 'editor'], true);
$pronto = cmsAuthorsReady($pdo);
$v2 = $pronto && cmsAuthorsV2Ready($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cmsCsrfValidate($_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        if (!$podeRemover) { http_response_code(403); die('Você não tem permissão para remover autores.'); }
        if (!$pronto) { http_response_code(503); die(CMS_AUTHORS_MIGRATION_HINT); }
        $id = (int) ($_POST['id'] ?? 0);
        $autor = cmsAuthorById($pdo, $id);
        if (!$autor) { header('Location: autores'); exit; }

        // Posts do autor removido: ou vão para outro autor, ou ficam sem
        // assinatura. Nos dois casos o nome de texto (`author`) é preservado
        // ou trocado de propósito; a FK sozinha só zeraria a referência.
        $destino = (int) ($_POST['reassign_to'] ?? 0);
        $novo = ($destino > 0 && $destino !== $id) ? cmsAuthorById($pdo, $destino) : null;
        if (cmsAuthorsV2Ready($pdo)) {
            $q = $pdo->prepare('SELECT post_slug FROM cmstest_blog_post_authors WHERE author_id = ?');
            $q->execute([$id]);
            $slugs = $q->fetchAll(PDO::FETCH_COLUMN);
            if ($novo) {
                // Toma o lugar do removido na mesma posição; se o destino já
                // assina o post, a linha duplicada simplesmente sai.
                $pdo->prepare('UPDATE IGNORE cmstest_blog_post_authors SET author_id = ? WHERE author_id = ?')->execute([$novo['id'], $id]);
            }
            $pdo->prepare('DELETE FROM cmstest_blog_authors WHERE id = ?')->execute([$id]); // CASCADE limpa o que sobrou
            cmsPostAuthorsResync($pdo, $slugs);
        } else {
            if ($novo) {
                $pdo->prepare('UPDATE cmstest_blog_posts SET blog_author_id = ?, author = ? WHERE blog_author_id = ?')
                    ->execute([$novo['id'], $novo['name'], $id]);
            } else {
                $pdo->prepare('UPDATE cmstest_blog_posts SET blog_author_id = NULL WHERE blog_author_id = ?')->execute([$id]);
            }
            $pdo->prepare('DELETE FROM cmstest_blog_authors WHERE id = ?')->execute([$id]);
        }
        queueRebuild();
        header('Location: autores?removed=1&t=' . time());
        exit;
    }
}

$busca = trim((string) ($_GET['q'] ?? ''));
$autores = $pronto ? cmsAuthorsAll($pdo, $busca) : [];
$todos = $pronto ? ($busca === '' ? $autores : cmsAuthorsAll($pdo)) : [];
$comPosts = count(array_filter($todos, fn($a) => $a['post_count'] > 0));
$semAutor = $pronto ? (int) $pdo->query('SELECT COUNT(*) FROM cmstest_blog_posts WHERE blog_author_id IS NULL')->fetchColumn() : 0;

adminLayoutTop('authors', 'Autores', ['label' => 'Posts do blog', 'href' => '/acesso/posts'], 'https://mclair.com.br/blog/autores/');
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/autores/"><?= cmsCheckIcon() ?><span class="msg-text">Autor salvo.</span></div><?php endif; ?>
<?php if (isset($_GET['removed'])): ?><div class="msg ok" id="savedMsg" data-live-url="https://mclair.com.br/blog/autores/"><?= cmsCheckIcon() ?><span class="msg-text">Autor removido.</span></div><?php endif; ?>

<?php if (!$pronto): ?>
<div class="msg err"><?= htmlspecialchars(CMS_AUTHORS_MIGRATION_HINT) ?></div>
<div class="card">
  <p style="margin:0;font-size:.88rem;line-height:1.6">Enquanto isso os posts continuam funcionando como antes, com o nome do autor digitado no editor. Depois da migração, esta tela lista os autores, e o editor de post passa a ter o campo de escolha com a assinatura.</p>
</div>
<?php adminLayoutBottom(); exit; endif; ?>

<style>
  .au-foto { width:44px; height:44px; border-radius:50%; object-fit:cover; display:block; background:var(--soft); }
  .au-foto-vazia { width:44px; height:44px; border-radius:50%; background:rgba(200,16,46,.08); color:var(--red); font-weight:900; display:flex; align-items:center; justify-content:center; }
  .au-nome strong { display:block; font-size:.9rem; }
  .au-nome span { font-size:.76rem; color:var(--ink-3); }
  .au-nome em { font-size:.72rem; color:var(--ink-3); font-style:normal; }
  .au-busca { display:flex; gap:8px; align-items:center; }
  .au-busca input[type=text] { width:220px; padding:8px 12px; font-size:.85rem; }
  .au-busca button { padding:8px 14px; font-size:.8rem; }
  .au-rem { display:inline-flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:.78rem; }
  .au-rem .ci-msg { color:#9a1f1f; font-weight:600; }
  .au-rem select { width:auto; padding:6px 8px; font-size:.78rem; }
  .au-rem button.ci-yes { background:var(--red); color:#fff; border:none; padding:5px 10px; border-radius:6px; font-family:inherit; font-size:.76rem; font-weight:700; cursor:pointer; }
  .au-rem button.ci-no { background:none; border:none; padding:0; color:var(--ink-3); text-decoration:underline; font-family:inherit; font-size:.76rem; font-weight:700; cursor:pointer; }
  .au-vazio { padding:40px 20px; text-align:center; color:var(--ink-3); font-size:.88rem; line-height:1.6; }
  @media (max-width: 768px) { .tablecard-head { flex-wrap:wrap; gap:10px; } .au-busca input[type=text] { width:150px; } }
</style>

<?php if (!$v2): ?><div class="msg err" style="background:#FFF4E5;color:#7A4A00;border:1px solid #F3D9A4"><?= htmlspecialchars(CMS_AUTHORS_V2_HINT) ?></div><?php endif; ?>

<?php adminStatCards([
    ['num' => count($todos), 'lbl' => 'Autores cadastrados'],
    ['num' => $comPosts, 'lbl' => 'Com artigo publicado'],
    ['num' => $semAutor, 'lbl' => 'Posts sem autor cadastrado'],
]); ?>

<div class="tablecard">
  <div class="tablecard-head">
    <div><strong>Autores</strong><span class="count"><?= count($autores) ?></span></div>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <form method="get" class="au-busca">
        <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por nome ou cargo" />
        <?php if ($busca !== ''): ?><a href="autores" class="btn secondary" style="text-decoration:none;padding:8px 12px;font-size:.8rem">limpar</a><?php endif; ?>
        <button type="submit" class="btn secondary">Buscar</button>
      </form>
      <button type="button" class="btn" onclick="autorModal.abrir(null, autorSalvo)">Novo autor</button>
    </div>
  </div>

  <?php if (!$autores): ?>
    <div class="au-vazio">
      <?php if ($busca !== ''): ?>
        Nenhum autor encontrado para "<?= htmlspecialchars($busca) ?>".
      <?php else: ?>
        Nenhum autor cadastrado ainda. Clique em "Novo autor" para criar o primeiro: foto, nome, uma bio curta e os links das redes.<br />
        Depois, no editor de cada post, escolha o autor na lista para a assinatura aparecer no fim do artigo.
      <?php endif; ?>
    </div>
  <?php else: ?>
  <table class="dt">
    <tr><th>Foto</th><th class="s">Nome</th><th>Artigos</th><th>Ações</th></tr>
    <?php foreach ($autores as $a): ?>
    <tr>
      <td><?php if ($a['photo_url']): ?><img class="au-foto" src="<?= htmlspecialchars($a['photo_url']) ?>" alt="" /><?php else: ?><div class="au-foto-vazia"><?= htmlspecialchars(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></div><?php endif; ?></td>
      <td class="au-nome">
        <strong><?= htmlspecialchars($a['name']) ?></strong>
        <?php if ($a['job_title']): ?><span><?= htmlspecialchars($a['job_title']) ?></span><?php endif; ?>
        <?php if ($v2): ?><br /><em><?= htmlspecialchars(cmsAuthorLabel($a)) ?><?= ($a['kind'] === 'pessoa' && !$a['gender']) ? ' (tratamento não definido)' : '' ?></em><?php endif; ?>
        <?php if (!$a['photo_url'] || !$a['bio_short']): ?><br /><em>Falta: <?= implode(' e ', array_filter([$a['photo_url'] ? '' : 'foto', $a['bio_short'] ? '' : 'bio curta'])) ?></em><?php endif; ?>
      </td>
      <td>
        <?php if ($a['post_count'] > 0): ?>
          <a href="https://mclair.com.br/blog/autores/<?= htmlspecialchars($a['slug']) ?>/" target="_blank" rel="noopener" style="font-weight:700;color:var(--red)"><?= (int) $a['post_count'] ?></a>
        <?php else: ?>
          <span style="color:var(--ink-3)">0</span>
        <?php endif; ?>
      </td>
      <td class="dt-actions">
        <button type="button" style="color:var(--red)" onclick='autorModal.abrir(<?= htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>, autorSalvo)'>editar</button>
        <?php if ($podeRemover): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(cmsCsrfToken()) ?>" />
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>" />
          <input type="hidden" name="reassign_to" value="" />
          <button type="button" class="del" data-posts="<?= (int) $a['post_count'] ?>" data-nome="<?= htmlspecialchars($a['name']) ?>" onclick="autorRemover(this)">remover</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<p class="hint" style="margin-top:14px;line-height:1.6">A página pública de cada autor (mclair.com.br/blog/autores/nome/) só existe quando ele tem pelo menos um artigo publicado. Os artigos ganham a assinatura no fim do texto assim que o autor é escolhido no editor do post.</p>

<?php cmsAuthorModal(); ?>
<script>
var AUTORES = <?= json_encode(array_map(fn($a) => ['id' => $a['id'], 'name' => $a['name']], $todos), JSON_UNESCAPED_UNICODE) ?>;

function autorSalvo() {
  location.href = 'autores?saved=1&t=' + Math.floor(Date.now() / 1000);
}

// Remoção: sem posts é a confirmação padrão do painel; com posts, pergunta
// o destino deles antes.
function autorRemover(btn) {
  var n = parseInt(btn.dataset.posts, 10);
  if (!n) {
    btn.dataset.confirm = 'Remover ' + btn.dataset.nome + '?';
    btn.dataset.yes = 'sim, remover';
    askConfirm(btn);
    return;
  }
  var form = btn.form;
  var id = parseInt(form.querySelector('[name=id]').value, 10);
  var box = document.createElement('span');
  box.className = 'au-rem';
  var msg = document.createElement('span');
  msg.className = 'ci-msg';
  msg.textContent = btn.dataset.nome + ' assina ' + n + ' artigo' + (n === 1 ? '' : 's') + '. O que fazer com ele' + (n === 1 ? '' : 's') + '?';
  var sel = document.createElement('select');
  sel.innerHTML = '<option value="">Deixar sem autor cadastrado (o nome no post fica)</option>';
  AUTORES.forEach(function (a) {
    if (a.id === id) return;
    var o = document.createElement('option');
    o.value = a.id;
    o.textContent = 'Passar para ' + a.name;
    sel.appendChild(o);
  });
  var yes = document.createElement('button');
  yes.type = 'button';
  yes.className = 'ci-yes';
  yes.textContent = 'remover autor';
  var no = document.createElement('button');
  no.type = 'button';
  no.className = 'ci-no';
  no.textContent = 'cancelar';
  yes.addEventListener('click', function () {
    form.querySelector('[name=reassign_to]').value = sel.value;
    form.submit();
  });
  no.addEventListener('click', function () { box.remove(); btn.style.display = ''; });
  box.append(msg, sel, yes, no);
  btn.style.display = 'none';
  btn.after(box);
}
</script>

<?php adminLayoutBottom(); ?>
