<?php
// Modal de autor (criar/editar), compartilhado entre /acesso/autores e o
// editor de post. Chamar cmsAuthorModal() uma vez, no fim da página, depois
// de _layout_top.php (usa CMS_CSRF e o /acesso/upload.php do painel).
//
// API em JS:
//   autorModal.abrir(dados|null, function (autor) { ... })  -- dados = objeto de cmsAuthorDecode()
//   autorModal.fechar()
// O callback recebe o autor já salvo (JSON de authors-api.php).

function cmsAuthorModal(): void {
    static $emitido = false;
    if ($emitido) return;
    $emitido = true;
    $tipos = cmsAuthorSocialTypes();
    $v2 = cmsAuthorsV2Ready(cmsDb());
?>
<style>
  .am-backdrop { display:none; position:fixed; inset:0; background:rgba(26,27,30,.5); z-index:120; align-items:flex-start; justify-content:center; padding:24px 16px; overflow-y:auto; }
  .am-backdrop.open { display:flex; }
  .am { background:var(--paper); border-radius:14px; width:100%; max-width:920px; box-shadow:0 30px 70px -24px rgba(20,16,12,.4); margin:auto; }
  .am-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px 14px; border-bottom:1px solid var(--line); }
  .am-head h2 { margin:0; font-size:1.05rem; }
  .am-head button { background:none; border:none; cursor:pointer; font-size:1rem; color:var(--ink-3); padding:4px 6px; }
  .am-body { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:0; }
  .am-form { padding:6px 22px 18px; border-right:1px solid var(--line); }
  .am-form label:first-of-type { margin-top:10px; }
  .am-side { padding:16px 22px 18px; background:var(--soft); border-radius:0 14px 14px 0; }
  .am-side > strong { display:block; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); margin-bottom:10px; }
  .am-intro { font-size:.8rem; color:var(--ink-3); margin:12px 0 0; line-height:1.5; }
  .am-foot { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:14px 22px; border-top:1px solid var(--line); }
  .am-err { flex:1; font-size:.78rem; font-weight:600; color:var(--red); margin:0; }
  .am-count { float:right; font-weight:400; text-transform:none; letter-spacing:0; }

  /* Foto: zona de arrastar redonda, que também é a prévia */
  .am-foto { display:flex; align-items:center; gap:14px; margin-top:8px; }
  .am-foto-zona { position:relative; width:84px; height:84px; border-radius:50%; border:1.5px dashed var(--line); background:var(--soft); cursor:pointer; overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--ink-3); font-size:.62rem; font-weight:700; text-align:center; line-height:1.3; padding:6px; }
  .am-foto-zona.over { border-color:var(--red); background:rgba(200,16,46,.06); }
  .am-foto-zona.has-img { border-style:solid; padding:0; }
  .am-foto-zona img { display:none; width:100%; height:100%; object-fit:cover; }
  .am-foto-zona.has-img img { display:block; }
  .am-foto-zona.has-img span { display:none; }
  .am-foto-acoes { font-size:.74rem; color:var(--ink-3); line-height:1.5; }
  .am-foto-acoes button { background:none; border:none; padding:0; color:var(--red); font-weight:700; font-size:.74rem; cursor:pointer; font-family:inherit; }

  /* Links: uma linha por rede */
  .am-links { display:flex; flex-direction:column; gap:8px; margin-top:8px; }
  .am-link { display:grid; grid-template-columns:130px minmax(0,1fr) auto; gap:8px; align-items:center; }
  .am-link select, .am-link input[type=text] { padding:8px 10px; font-size:.85rem; }
  .am-link button { background:none; border:none; color:var(--red); font-weight:700; font-size:.74rem; cursor:pointer; padding:6px 2px; font-family:inherit; }
  .am-add { margin-top:8px; padding:8px 12px; font-size:.78rem; }

  /* Prévia: mesma composição do cartão publicado no site */
  .am-card { background:#fff; border:1.5px solid var(--line); border-radius:12px; padding:18px; display:flex; gap:14px; align-items:flex-start; }
  .am-card-foto { width:56px; height:56px; border-radius:50%; overflow:hidden; background:rgba(200,16,46,.08); color:var(--red); font-weight:900; font-size:1.3rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .am-card-foto img { width:100%; height:100%; object-fit:cover; display:block; }
  .am-card-por { font-size:.7rem; color:var(--ink-3); margin:0 0 2px; }
  .am-card-nome { font-size:.95rem; font-weight:800; color:var(--ink); line-height:1.25; margin:0; text-wrap:balance; }
  .am-card-cargo { font-size:.74rem; font-weight:700; color:var(--red); margin:3px 0 0; text-wrap:pretty; }
  .am-card-bio { font-size:.8rem; line-height:1.6; color:var(--ink); margin:8px 0 0; text-wrap:pretty; }
  .am-card-links { display:flex; flex-wrap:wrap; gap:4px 12px; margin:10px 0 0; padding:0; list-style:none; font-size:.7rem; font-weight:700; color:var(--ink-3); }
  .am-card-links li { border-bottom:1px solid var(--line); }
  .am-card-vazio { font-size:.78rem; color:var(--ink-3); margin:8px 0 0; line-height:1.5; }
  .am-side .hint { margin-top:10px; line-height:1.5; }
  .am-radios { display:flex; flex-wrap:wrap; gap:6px 18px; margin-top:6px; }
  .am-radio { display:inline-flex; align-items:center; gap:6px; margin:0; font-size:.85rem; font-weight:600; text-transform:none; letter-spacing:0; color:var(--ink); cursor:pointer; }
  .am-radio input { margin:0; accent-color:var(--red); }

  @media (max-width: 860px) {
    .am-body { grid-template-columns:1fr; }
    .am-form { border-right:none; border-bottom:1px solid var(--line); }
    .am-side { border-radius:0 0 14px 14px; }
    .am-link { grid-template-columns:1fr; }
    .am-backdrop { padding:12px 8px; }
  }
</style>

<div class="am-backdrop" id="amBackdrop">
  <div class="am" role="dialog" aria-modal="true" aria-labelledby="amTitulo">
    <div class="am-head">
      <h2 id="amTitulo">Novo autor</h2>
      <button type="button" onclick="autorModal.fechar()" aria-label="Fechar">fechar</button>
    </div>
    <form id="amForm" onsubmit="return false">
      <input type="hidden" name="id" id="amId" value="" />
      <input type="hidden" name="photo_url" id="amFoto" value="" />
      <div class="am-body">
        <div class="am-form">
          <p class="am-intro">Estes dados aparecem na assinatura no fim de cada artigo e na página pública do autor, em mclair.com.br/blog/autores/. Preencha o que tiver: só o nome é obrigatório.</p>

          <label>Foto</label>
          <div class="am-foto">
            <div class="am-foto-zona" id="amFotoZona" title="Arraste a foto aqui ou clique para escolher">
              <img id="amFotoImg" alt="" />
              <span>Arraste a foto ou clique</span>
            </div>
            <div class="am-foto-acoes">
              Quadrada, rosto centralizado (ou o logotipo, no caso de organização), pelo menos 400 x 400 px. JPG, PNG ou WEBP até 8 MB.<br />
              <button type="button" id="amFotoRemover" style="display:none">Remover foto</button>
            </div>
            <input type="file" id="amFotoArquivo" accept="image/*" style="display:none" />
          </div>

          <label>Nome</label>
          <input type="text" name="name" id="amNome" placeholder="Ex.: Kelly Pinheiro" maxlength="190" autocomplete="off" />
          <p class="hint">Como aparece no site. Nome completo, sem cargo.</p>

          <?php if ($v2): ?>
          <label>Quem assina</label>
          <div class="am-radios">
            <label class="am-radio"><input type="radio" name="kind" value="pessoa" checked /> Uma pessoa</label>
            <label class="am-radio"><input type="radio" name="kind" value="organizacao" /> Uma organização (a própria Mclair, por exemplo)</label>
          </div>
          <p class="hint">Organização aparece como "Publicado por" e, para o Google, é a empresa, não uma pessoa. A foto vira o logotipo.</p>

          <div id="amGeneroWrap">
            <label>Tratamento</label>
            <div class="am-radios">
              <label class="am-radio"><input type="radio" name="gender" value="f" /> Autora</label>
              <label class="am-radio"><input type="radio" name="gender" value="m" /> Autor</label>
              <label class="am-radio"><input type="radio" name="gender" value="" checked /> Não definir</label>
            </div>
            <p class="hint">Define a concordância em tudo o que o site escreve sobre a pessoa: "Autora no blog Mclair", "Sobre a autora". Sem definir, o site usa formas neutras.</p>
          </div>
          <?php endif; ?>

          <label>Cargo ou função</label>
          <input type="text" name="job_title" id="amCargo" placeholder="Ex.: CEO e fundadora da Mclair" maxlength="190" autocomplete="off" />
          <p class="hint">Uma linha só. Vai logo abaixo do nome e entra nos dados estruturados que o Google lê.</p>

          <label>Bio curta <span class="am-count" id="amBioCurtaCount">0/300</span></label>
          <textarea name="bio_short" id="amBioCurta" maxlength="500" style="min-height:84px" placeholder="Ex.: Jornalista com 20 anos de experiência em comunicação estratégica. Ajuda empresas e executivos a construir autoridade de marca."></textarea>
          <p class="hint">É o texto da assinatura no fim do artigo. Duas ou três frases, até 300 caracteres, funcionam melhor.</p>

          <label>Bio completa</label>
          <textarea name="bio_long" id="amBioLonga" style="min-height:120px" placeholder="Trajetória, formação, onde atua hoje. Pode ter mais de um parágrafo."></textarea>
          <p class="hint">Aparece só na página do autor. Se ficar em branco, a página usa a bio curta.</p>

          <label>Áreas de especialidade</label>
          <input type="text" name="topics" id="amTemas" placeholder="Ex.: ESG, governança corporativa, conselhos de administração" autocomplete="off" />
          <p class="hint">Separe por vírgula. Ajuda o Google e as ferramentas de IA a ligar o autor aos temas sobre os quais escreve.</p>

          <label>Redes e links</label>
          <div class="am-links" id="amLinks"></div>
          <button type="button" class="btn secondary am-add" onclick="autorModal.addLink()">+ Adicionar link</button>
          <p class="hint">Cole o endereço completo, começando com https://. Só perfis públicos: e-mail e WhatsApp não entram aqui.</p>
        </div>

        <div class="am-side">
          <strong>Como vai aparecer</strong>
          <div class="am-card" id="amCard">
            <div class="am-card-foto" id="amCardFoto">?</div>
            <div style="min-width:0;flex:1">
              <p class="am-card-por" id="amCardPor">Escrito por</p>
              <p class="am-card-nome" id="amCardNome">Nome do autor</p>
              <p class="am-card-cargo" id="amCardCargo" style="display:none"></p>
              <p class="am-card-bio" id="amCardBio" style="display:none"></p>
              <p class="am-card-vazio" id="amCardVazio">A prévia muda conforme você preenche os campos ao lado.</p>
              <ul class="am-card-links" id="amCardLinks"></ul>
            </div>
          </div>
          <p class="hint">Esta assinatura entra abaixo do fim do texto em todo artigo deste autor, com link para a página dele. Mudou algo aqui, muda em todos os artigos na próxima republicação.</p>
        </div>
      </div>
      <div class="am-foot">
        <p class="am-err" id="amErro"></p>
        <button type="button" class="btn secondary" onclick="autorModal.fechar()">Cancelar</button>
        <button type="submit" class="btn" id="amSalvar" onclick="autorModal.salvar()">Salvar autor</button>
      </div>
    </form>
  </div>
</div>

<script>
var autorModal = (function () {
  var TIPOS = <?= json_encode($tipos, JSON_UNESCAPED_UNICODE) ?>;
  var aoSalvar = null;
  var el = function (id) { return document.getElementById(id); };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ---- Links (repetidor) ----
  function addLink(tipo, url) {
    var box = el('amLinks');
    if (box.children.length >= 8) return;
    var row = document.createElement('div');
    row.className = 'am-link';
    var opts = '';
    Object.keys(TIPOS).forEach(function (k) {
      opts += '<option value="' + k + '"' + (k === tipo ? ' selected' : '') + '>' + esc(TIPOS[k]) + '</option>';
    });
    row.innerHTML = '<select name="social_type[]">' + opts + '</select>'
      + '<input type="text" name="social_url[]" placeholder="https://..." value="' + esc(url || '') + '" autocomplete="off" />'
      + '<button type="button" title="Remover este link">remover</button>';
    row.querySelector('button').addEventListener('click', function () { row.remove(); previa(); });
    row.querySelector('select').addEventListener('change', previa);
    row.querySelector('input').addEventListener('input', previa);
    box.appendChild(row);
    if (!url) row.querySelector('input').focus();
    previa();
  }

  // ---- Foto ----
  function fotoRender() {
    var url = el('amFoto').value.trim();
    var zona = el('amFotoZona');
    zona.classList.toggle('has-img', url !== '');
    el('amFotoImg').src = url || '';
    el('amFotoRemover').style.display = url ? '' : 'none';
    previa();
  }
  function fotoSubir(f) {
    if (!f) return;
    var zona = el('amFotoZona');
    zona.querySelector('span').textContent = 'Enviando...';
    var fd = new FormData();
    fd.append('file', f);
    fd.append('csrf', CMS_CSRF);
    fetch('/acesso/upload.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        zona.querySelector('span').textContent = 'Arraste a foto ou clique';
        if (!res.ok || !res.j.url) { erro(res.j.error || 'Falha no upload da foto.'); return; }
        erro('');
        el('amFoto').value = res.j.url;
        fotoRender();
      })
      .catch(function () {
        zona.querySelector('span').textContent = 'Arraste a foto ou clique';
        erro('Falha de rede ao enviar a foto. Tente de novo.');
      });
  }

  // ---- Prévia ao vivo ----
  function previa() {
    var nome = el('amNome').value.trim();
    var cargo = el('amCargo').value.trim();
    var bio = el('amBioCurta').value.trim();
    var foto = el('amFoto').value.trim();
    el('amBioCurtaCount').textContent = el('amBioCurta').value.length + '/300';
    el('amBioCurtaCount').style.color = el('amBioCurta').value.length > 300 ? 'var(--red)' : '';

    el('amCardNome').textContent = nome || 'Nome do autor';
    el('amCardCargo').textContent = cargo;
    el('amCardCargo').style.display = cargo ? '' : 'none';
    el('amCardBio').textContent = bio;
    el('amCardBio').style.display = bio ? '' : 'none';
    el('amCardVazio').style.display = (cargo || bio) ? 'none' : '';

    var f = el('amCardFoto');
    var org = kindAtual() === 'organizacao';
    if (foto) f.innerHTML = '<img src="' + esc(foto) + '" alt=""' + (org ? ' style="object-fit:contain;padding:8px;background:#fff"' : '') + ' />';
    else f.textContent = iniciais(nome) || '?';
    el('amCardPor').textContent = org ? 'Publicado por' : 'Escrito por';
    var gw = el('amGeneroWrap');
    if (gw) gw.style.display = org ? 'none' : '';

    var ul = el('amCardLinks');
    ul.innerHTML = '';
    el('amLinks').querySelectorAll('.am-link').forEach(function (row) {
      if (!row.querySelector('input').value.trim()) return;
      var li = document.createElement('li');
      li.textContent = TIPOS[row.querySelector('select').value] || 'Link';
      ul.appendChild(li);
    });
  }

  function erro(msg) { el('amErro').textContent = msg || ''; }
  function kindAtual() {
    var r = el('amForm').querySelector('input[name=kind]:checked');
    return r ? r.value : 'pessoa';
  }
  function marcar(nome, valor) {
    var rs = el('amForm').querySelectorAll('input[name=' + nome + ']');
    Array.prototype.forEach.call(rs, function (r) { r.checked = (r.value === (valor == null ? '' : String(valor))); });
  }
  function iniciais(nome) {
    var p = nome.trim().split(/\s+/).filter(Boolean);
    if (!p.length) return '';
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  // ---- Abrir / fechar / salvar ----
  function abrir(dados, cb) {
    aoSalvar = cb || null;
    dados = dados || {};
    el('amTitulo').textContent = dados.id ? 'Editar autor' : 'Novo autor';
    el('amSalvar').textContent = dados.id ? 'Salvar alterações' : 'Salvar autor';
    el('amId').value = dados.id || '';
    el('amFoto').value = dados.photo_url || '';
    el('amNome').value = dados.name || '';
    el('amCargo').value = dados.job_title || '';
    el('amBioCurta').value = dados.bio_short || '';
    el('amBioLonga').value = dados.bio_long || '';
    el('amTemas').value = (dados.topics || []).join(', ');
    marcar('kind', dados.kind || 'pessoa');
    marcar('gender', dados.gender || '');
    el('amLinks').innerHTML = '';
    (dados.social_links || []).forEach(function (l) { addLink(l.type, l.url); });
    erro('');
    fotoRender();
    el('amBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(function () { el('amNome').focus(); }, 50);
  }

  function fechar() {
    el('amBackdrop').classList.remove('open');
    document.body.style.overflow = '';
  }

  function salvar() {
    var nome = el('amNome').value.trim();
    if (nome.length < 2) { erro('Informe o nome do autor.'); el('amNome').focus(); return; }
    var fd = new FormData(el('amForm'));
    fd.append('csrf', CMS_CSRF);
    fd.append('action', 'save');
    var btn = el('amSalvar');
    btn.disabled = true;
    erro('');
    fetch('/acesso/authors-api.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok || !res.j.ok) { erro(res.j.error || 'Não foi possível salvar. Tente de novo.'); return; }
        fechar();
        if (aoSalvar) aoSalvar(res.j.author);
      })
      .catch(function () { btn.disabled = false; erro('Falha de rede ao salvar. Tente de novo.'); });
  }

  // ---- Ligações de evento (uma vez) ----
  ['amNome', 'amCargo', 'amBioCurta'].forEach(function (id) { el(id).addEventListener('input', previa); });
  Array.prototype.forEach.call(el('amForm').querySelectorAll('input[name=kind], input[name=gender]'), function (r) { r.addEventListener('change', previa); });
  var zona = el('amFotoZona'), arquivo = el('amFotoArquivo');
  zona.addEventListener('click', function () { arquivo.click(); });
  arquivo.addEventListener('change', function () { fotoSubir(arquivo.files[0]); arquivo.value = ''; });
  zona.addEventListener('dragover', function (e) { e.preventDefault(); zona.classList.add('over'); });
  zona.addEventListener('dragleave', function () { zona.classList.remove('over'); });
  zona.addEventListener('drop', function (e) { e.preventDefault(); zona.classList.remove('over'); fotoSubir(e.dataTransfer.files[0]); });
  el('amFotoRemover').addEventListener('click', function () { el('amFoto').value = ''; fotoRender(); });
  el('amBackdrop').addEventListener('click', function (e) { if (e.target === el('amBackdrop')) fechar(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && el('amBackdrop').classList.contains('open')) fechar(); });
  // Enter num campo de texto salva, como num formulário comum; nas textareas continua sendo quebra de linha.
  el('amForm').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); salvar(); }
  });

  return { abrir: abrir, fechar: fechar, salvar: salvar, addLink: function () { addLink('linkedin', ''); } };
})();
</script>
<?php
}
