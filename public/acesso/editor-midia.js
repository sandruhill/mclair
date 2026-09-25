/*
 * Mídia no editor de post: botões da barra (Imagem, Vídeo, Galeria), os três
 * modais (_media_modals.php) e a prévia da galeria dentro do editor.
 *
 * Formato da galeria no markdown (bloco personalizado do Toast UI, sobrevive à
 * ida e volta visual <-> markdown; o site renderiza em src/utils/galeria.ts):
 *   $$galeria
 *   cols: 3
 *   /uploads/a.jpg|1600|1200|Legenda
 *   $$
 * Precisa de: CMS_CSRF (painel) e, depois de criado, o editor (midia.ligar).
 */
var midia = (function () {
  'use strict';
  var editor = null;
  var el = function (id) { return document.getElementById(id); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // ---------- ícones de traço ----------
  var ICONES = {
    imagem: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="M21 16l-5.5-5.5L7 19"/>',
    video: '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/>',
    galeria: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'
  };
  function svg(nome) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONES[nome] + '</svg>'; }

  /** Botão para a barra do Toast UI, com a mesma classe dos botões da biblioteca. */
  function botaoBarra(nome, rotulo, aoClicar) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'toastui-editor-toolbar-icons mm-btn';
    b.setAttribute('aria-label', rotulo);
    b.innerHTML = svg(nome);
    b.addEventListener('click', function (e) { e.preventDefault(); aoClicar(); });
    return b;
  }

  // ---------- upload com progresso ----------
  function subir(arquivo, aoProgredir) {
    return new Promise(function (resolve, reject) {
      if (!arquivo) return reject(new Error('Nenhum arquivo.'));
      if (arquivo.size > 8 * 1024 * 1024) return reject(new Error('"' + arquivo.name + '" passa de 8 MB.'));
      var fd = new FormData();
      fd.append('file', arquivo);
      fd.append('csrf', CMS_CSRF);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '/acesso/upload.php');
      xhr.upload.addEventListener('progress', function (e) { if (aoProgredir && e.lengthComputable) aoProgredir(e.loaded / e.total); });
      xhr.addEventListener('load', function () {
        var j = {};
        try { j = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status >= 200 && xhr.status < 300 && j.url) {
          j.urlComTamanho = j.width && j.height ? j.url + '#w=' + j.width + '&h=' + j.height : j.url;
          resolve(j);
        } else reject(new Error(j.error || 'Falha no upload de "' + arquivo.name + '".'));
      });
      xhr.addEventListener('error', function () { reject(new Error('Falha de rede ao enviar "' + arquivo.name + '".')); });
      xhr.send(fd);
    });
  }

  /** "foto-equipe_2026.JPG" -> "Foto equipe 2026" */
  function altDoNome(nome) {
    var t = String(nome || '').replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
    // prefixos de câmera e de captura não dizem nada sobre a foto
    t = t.replace(/^(img|dsc|dscn|dcim|pxl|image|screenshot|captura de tela)[\s\d]*/i, '').trim();
    if (!t || /^\d+$/.test(t)) return '';
    return t.charAt(0).toUpperCase() + t.slice(1);
  }

  // ---------- modais ----------
  function abrirModal(id) { el(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
  function fecharModal(id) { el(id).classList.remove('open'); document.body.style.overflow = ''; if (editor) editor.focus(); }
  function aba(botao) {
    var modal = botao.dataset.aba;
    var abas = el(modal).querySelectorAll('[data-aba="' + modal + '"]');
    Array.prototype.forEach.call(abas, function (b) { b.classList.toggle('on', b === botao); el(b.dataset.painel).hidden = b !== botao; });
    if (botao.dataset.painel === 'mmImagemEnviadas') carregarEnviadas('mmImagemGrade', 'mmImagemGradeMsg', false);
    if (botao.dataset.painel === 'mmGaleriaEnviadas') carregarEnviadas('mmGaleriaGrade', 'mmGaleriaGradeMsg', true);
  }
  document.addEventListener('click', function (e) {
    var f = e.target.closest('[data-fechar]');
    if (f) { fecharModal(f.dataset.fechar); return; }
    var a = e.target.closest('[data-aba]');
    if (a) { aba(a); return; }
    var m = e.target.closest('.mm');
    if (m && e.target === m) fecharModal(m.id);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var aberto = document.querySelector('.mm.open');
    if (aberto) fecharModal(aberto.id);
  });
  function zonaDeArraste(zona, entrada, aoReceber) {
    zona.addEventListener('click', function () { entrada.click(); });
    entrada.addEventListener('change', function () { aoReceber(Array.prototype.slice.call(entrada.files)); entrada.value = ''; });
    zona.addEventListener('dragover', function (e) { e.preventDefault(); zona.classList.add('over'); });
    zona.addEventListener('dragleave', function () { zona.classList.remove('over'); });
    zona.addEventListener('drop', function (e) { e.preventDefault(); zona.classList.remove('over'); aoReceber(Array.prototype.slice.call(e.dataTransfer.files)); });
  }
  var enviadasCache = null;
  function carregarEnviadas(idGrade, idMsg, multi) {
    var grade = el(idGrade), msg = el(idMsg);
    grade.innerHTML = '';
    msg.textContent = 'Carregando...';
    var pronto = function (lista) {
      msg.textContent = lista.length ? '' : 'Nenhuma imagem enviada ainda.';
      lista.forEach(function (f) {
        var b = document.createElement('button');
        b.type = 'button';
        b.title = f.url;
        b.dataset.url = f.url;
        b.innerHTML = '<img src="' + esc(f.url) + '" alt="" loading="lazy" />';
        if (multi && galeria.itens.some(function (it) { return it.url === f.url; })) b.classList.add('on');
        b.addEventListener('click', function () {
          if (multi) {
            var i = galeria.itens.findIndex(function (it) { return it.url === f.url; });
            if (i === -1) { galeria.itens.push({ url: f.url, w: null, h: null, legenda: '' }); b.classList.add('on'); medir(f.url, galeria.itens[galeria.itens.length - 1]); }
            else { galeria.itens.splice(i, 1); b.classList.remove('on'); }
            galeria.desenhar();
          } else {
            Array.prototype.forEach.call(grade.children, function (o) { o.classList.toggle('on', o === b); });
            imagem.escolher(f.url, null, null, altDoNome(f.url.split('/').pop()));
          }
        });
        grade.appendChild(b);
      });
    };
    if (enviadasCache) return pronto(enviadasCache);
    fetch('/acesso/media-list.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (lista) { enviadasCache = lista; pronto(lista); })
      .catch(function () { msg.textContent = 'Falha ao carregar a lista. Tente de novo.'; });
  }
  /** Imagem já enviada não tem tamanho registrado: mede no navegador. */
  function medir(url, item) {
    var im = new Image();
    im.onload = function () { item.w = im.naturalWidth; item.h = im.naturalHeight; };
    im.src = url;
  }

  // ---------- Imagem ----------
  var imagem = { url: '', w: null, h: null };
  imagem.escolher = function (url, w, h, alt) {
    imagem.url = url; imagem.w = w; imagem.h = h;
    el('mmImagemPreviaImg').src = url;
    el('mmImagemPrevia').hidden = false;
    el('mmImagemAlt').value = alt || '';
    el('mmImagemInfo').textContent = w && h ? w + ' x ' + h + ' px' : '';
    el('mmImagemInserir').disabled = false;
    el('mmImagemErro').textContent = '';
    if (!w) medir(url, imagem);
    el('mmImagemAlt').focus();
  };
  function abrirImagem() {
    imagem.url = ''; imagem.w = imagem.h = null;
    el('mmImagemPrevia').hidden = true;
    el('mmImagemInserir').disabled = true;
    el('mmImagemErro').textContent = '';
    el('mmImagemProgresso').classList.remove('on');
    aba(el('mmImagem').querySelector('[data-painel="mmImagemEnviar"]'));
    abrirModal('mmImagem');
  }
  function imagemReceber(arquivos) {
    var f = arquivos[0];
    if (!f) return;
    var barra = el('mmImagemProgresso');
    barra.classList.add('on');
    barra.querySelector('i').style.width = '0%';
    el('mmImagemErro').textContent = '';
    subir(f, function (p) { barra.querySelector('i').style.width = Math.round(p * 100) + '%'; })
      .then(function (r) { barra.classList.remove('on'); enviadasCache = null; imagem.escolher(r.url, r.width, r.height, altDoNome(f.name)); })
      .catch(function (e) { barra.classList.remove('on'); el('mmImagemErro').textContent = e.message; });
  }
  function imagemInserir() {
    if (!imagem.url) return;
    var alt = el('mmImagemAlt').value.trim();
    var url = imagem.w && imagem.h ? imagem.url + '#w=' + imagem.w + '&h=' + imagem.h : imagem.url;
    editor.exec('addImage', { imageUrl: url, altText: alt });
    fecharModal('mmImagem');
  }

  // ---------- Vídeo ----------
  // Mesmas regras de src/utils/videoEmbed.ts (o site só vira player nesses casos).
  function lerVideo(texto) {
    var u;
    try { u = new URL(texto.trim()); } catch (e) { return null; }
    if (u.protocol !== 'https:' && u.protocol !== 'http:') return null;
    var host = u.hostname.replace(/^www\./, '');
    var m;
    if (host === 'youtu.be') { var id = u.pathname.slice(1).split('/')[0]; return id ? { src: 'https://www.youtube.com/embed/' + encodeURIComponent(id), vertical: false, nome: 'YouTube' } : null; }
    if (host === 'youtube.com' || host === 'm.youtube.com') {
      var v = u.pathname === '/watch' ? u.searchParams.get('v') : ((m = u.pathname.match(/^\/(?:shorts|embed|live|v)\/([^/?#]+)/)) ? m[1] : null);
      return v ? { src: 'https://www.youtube.com/embed/' + encodeURIComponent(v), vertical: /\/shorts\//.test(u.pathname), nome: 'YouTube' } : null;
    }
    if (host === 'vm.tiktok.com' || host === 'vt.tiktok.com') return { erro: 'Link curto do TikTok não funciona. Abra o vídeo no TikTok e copie o endereço completo, do tipo tiktok.com/@perfil/video/123...' };
    if (host === 'tiktok.com') { m = u.pathname.match(/\/video\/(\d+)/); return m ? { src: 'https://www.tiktok.com/embed/v2/' + m[1], vertical: true, nome: 'TikTok' } : { erro: 'Este link do TikTok não é de um vídeo (precisa ter /video/número).' }; }
    if (host === 'instagram.com') { m = u.pathname.match(/^\/(?:reel|reels|p|tv)\/([^/?#]+)/); return m ? { src: 'https://www.instagram.com/' + u.pathname.split('/')[1] + '/' + m[1] + '/embed/', vertical: true, nome: 'Instagram' } : { erro: 'Este link do Instagram não é de um reel ou post.' }; }
    if (host === 'vimeo.com') { m = u.pathname.match(/^\/(\d+)/); return m ? { src: 'https://player.vimeo.com/video/' + m[1], vertical: false, nome: 'Vimeo' } : null; }
    if (/\.(mp4|webm|ogv)(\?|#|$)/i.test(u.pathname)) return { arquivo: u.href, vertical: false, nome: 'Arquivo de vídeo' };
    if (host === 'facebook.com' || host === 'fb.watch' || host === 'drive.google.com') return { erro: 'Vídeo do Facebook ou do Google Drive não vira player. Suba no YouTube (pode ser "não listado") e use esse link.' };
    return null;
  }
  var videoValido = null;
  function abrirVideo() {
    el('mmVideoUrl').value = '';
    videoValido = null;
    el('mmVideoPrevia').className = 'mm-video-previa';
    el('mmVideoPrevia').innerHTML = '';
    el('mmVideoStatus').className = 'hint';
    el('mmVideoStatus').textContent = 'Cole o link completo. O player entra numa linha só, no lugar onde o cursor está.';
    el('mmVideoInserir').disabled = true;
    el('mmVideoErro').textContent = '';
    abrirModal('mmVideo');
    setTimeout(function () { el('mmVideoUrl').focus(); }, 50);
  }
  function videoValidar() {
    var texto = el('mmVideoUrl').value.trim();
    var previa = el('mmVideoPrevia'), status = el('mmVideoStatus');
    previa.className = 'mm-video-previa'; previa.innerHTML = '';
    videoValido = null;
    el('mmVideoInserir').disabled = true;
    status.className = 'hint';
    if (!texto) { status.textContent = 'Cole o link completo. O player entra numa linha só, no lugar onde o cursor está.'; return; }
    var v = lerVideo(texto);
    if (!v) { status.textContent = 'Esse endereço não é de um vídeo reconhecido. Veja a lista abaixo.'; return; }
    if (v.erro) { status.textContent = v.erro; return; }
    videoValido = texto;
    status.className = 'hint mm-ok';
    status.textContent = v.nome + ' reconhecido. Prévia abaixo; no site o player fica com a largura do texto.';
    el('mmVideoInserir').disabled = false;
    previa.className = 'mm-video-previa on' + (v.vertical ? ' vertical' : '');
    if (v.arquivo) {
      var mesmaOrigem = v.arquivo.indexOf(location.origin + '/') === 0;
      previa.innerHTML = mesmaOrigem ? '<video src="' + esc(v.arquivo) + '" controls preload="metadata"></video>' : '<p style="color:#fff;padding:20px;font-size:.85rem;margin:0">Arquivo de vídeo externo: o site mostra um player, mas a prévia aqui não carrega vídeo de outro domínio.</p>';
    } else {
      previa.innerHTML = '<iframe src="' + esc(v.src) + '" title="Prévia do vídeo" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
    }
  }
  function videoInserir() {
    if (!videoValido) return;
    inserirParagrafoComLink(videoValido);
    fecharModal('mmVideo');
  }
  /** Link sozinho num parágrafo, logo abaixo do bloco onde o cursor está: é o que src/utils/videoEmbed.ts transforma em player. */
  function inserirParagrafoComLink(url) {
    var view = editor.wwEditor.view, schema = view.state.schema, sel = view.state.selection;
    var p = schema.nodes.paragraph.create(null, schema.text(url, [schema.marks.link.create({ linkUrl: url })]));
    var pos = sel.$to.depth ? sel.$to.after(1) : sel.to;
    view.dispatch(view.state.tr.insert(pos, p));
  }

  // ---------- Galeria ----------
  var galeria = { itens: [], cols: 'auto', editandoIndice: null };
  function lerBloco(texto) {
    var g = { cols: 'auto', itens: [] };
    String(texto || '').split('\n').forEach(function (linha) {
      linha = linha.trim();
      if (!linha) return;
      var c = /^cols?:\s*(auto|2|3|4)$/i.exec(linha);
      if (c) { g.cols = c[1]; return; }
      var partes = linha.split('|');
      var url = (partes[0] || '').trim();
      if (!/^(\/[^\s"<>]+|https?:\/\/[^\s"<>]+)$/i.test(url)) return;
      g.itens.push({ url: url, w: parseInt(partes[1], 10) || null, h: parseInt(partes[2], 10) || null, legenda: partes.slice(3).join('|').trim() });
    });
    return g;
  }
  function textoDoBloco(g) {
    return ['cols: ' + g.cols].concat(g.itens.map(function (it) { return [it.url, it.w || '', it.h || '', it.legenda.replace(/[\r\n]+/g, ' ')].join('|'); })).join('\n');
  }
  galeria.desenhar = function () {
    var lista = el('mmGaleriaItens');
    lista.innerHTML = '';
    galeria.itens.forEach(function (it, i) {
      var row = document.createElement('div');
      row.className = 'mm-item';
      row.dataset.i = String(i);
      row.innerHTML = '<span class="alca" title="Arraste para mudar a ordem"><svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="2" cy="14" r="1.5"/><circle cx="8" cy="14" r="1.5"/></svg></span>'
        + '<img src="' + esc(it.url) + '" alt="" />'
        + '<div><span class="n">' + (i + 1) + ' de ' + galeria.itens.length + '</span><input type="text" placeholder="Legenda (opcional)" maxlength="200" value="' + esc(it.legenda) + '" /></div>'
        + '<button type="button" class="rem">remover</button>';
      row.querySelector('input').addEventListener('input', function (e) { it.legenda = e.target.value; });
      row.querySelector('.rem').addEventListener('click', function () { galeria.itens.splice(i, 1); galeria.desenhar(); sincronizarGrade(); });
      lista.appendChild(row);
    });
    el('mmGaleriaInserir').disabled = galeria.itens.length === 0;
    el('mmGaleriaInserir').textContent = galeria.editandoIndice === null ? 'Inserir galeria' : 'Salvar galeria';
  };
  function sincronizarGrade() {
    Array.prototype.forEach.call(el('mmGaleriaGrade').children, function (b) {
      b.classList.toggle('on', galeria.itens.some(function (it) { return it.url === b.dataset.url; }));
    });
  }
  function galeriaCols(valor) {
    galeria.cols = valor;
    Array.prototype.forEach.call(el('mmGaleriaCols').children, function (b) { b.classList.toggle('on', b.dataset.cols === valor); });
  }
  function abrirGaleria(bloco, indice) {
    var g = lerBloco(bloco || '');
    galeria.itens = g.itens; galeria.editandoIndice = (indice === undefined ? null : indice);
    galeriaCols(g.cols);
    galeria.desenhar();
    el('mmGaleriaTitulo').textContent = galeria.editandoIndice === null ? 'Galeria de imagens' : 'Editar galeria';
    el('mmGaleriaErro').textContent = '';
    el('mmGaleriaProgresso').classList.remove('on');
    el('mmGaleriaProgressoTxt').textContent = '';
    aba(el('mmGaleria').querySelector('[data-painel="mmGaleriaEnviar"]'));
    abrirModal('mmGaleria');
  }
  function galeriaReceber(arquivos) {
    var fila = arquivos.filter(function (f) { return /^image\//.test(f.type); });
    if (!fila.length) return;
    var barra = el('mmGaleriaProgresso'), txt = el('mmGaleriaProgressoTxt');
    barra.classList.add('on');
    el('mmGaleriaErro').textContent = '';
    var feitos = 0, erros = [];
    (function proximo() {
      var f = fila[feitos];
      if (!f) {
        barra.classList.remove('on');
        txt.textContent = erros.length ? '' : feitos + (feitos === 1 ? ' imagem enviada.' : ' imagens enviadas.');
        if (erros.length) el('mmGaleriaErro').textContent = erros.join(' ');
        enviadasCache = null;
        return;
      }
      txt.textContent = 'Enviando ' + (feitos + 1) + ' de ' + fila.length + ': ' + f.name;
      subir(f, function (p) { barra.querySelector('i').style.width = Math.round(((feitos + p) / fila.length) * 100) + '%'; })
        .then(function (r) { galeria.itens.push({ url: r.url, w: r.width, h: r.height, legenda: '' }); galeria.desenhar(); })
        .catch(function (e) { erros.push(e.message); })
        .then(function () { feitos++; proximo(); });
    })();
  }
  function galeriaInserir() {
    if (!galeria.itens.length) return;
    var texto = textoDoBloco(galeria);
    var view = editor.wwEditor.view, schema = view.state.schema;
    var no = schema.nodes.customBlock.create({ info: 'galeria' }, schema.text(texto));
    if (galeria.editandoIndice !== null) {
      var alvo = blocosDeGaleria()[galeria.editandoIndice];
      if (alvo) view.dispatch(view.state.tr.replaceWith(alvo.pos, alvo.pos + alvo.node.nodeSize, no));
    } else {
      var sel = view.state.selection;
      var pos = sel.$to.depth ? sel.$to.after(1) : sel.to;
      view.dispatch(view.state.tr.insert(pos, no));
    }
    fecharModal('mmGaleria');
  }
  /** Todos os blocos $$galeria do documento, na ordem, com a posição. */
  function blocosDeGaleria() {
    var lista = [];
    editor.wwEditor.view.state.doc.descendants(function (node, pos) {
      if (node.type.name === 'customBlock' && node.attrs.info === 'galeria') lista.push({ node: node, pos: pos });
    });
    return lista;
  }
  // arrastar para reordenar (ponteiro, como o menu do painel)
  (function () {
    var arrastando = null, lista = null, moveu = false, y0 = 0;
    document.addEventListener('pointerdown', function (e) {
      var alca = e.target.closest('#mmGaleriaItens .alca');
      if (!alca || e.button !== 0) return;
      e.preventDefault();
      arrastando = alca.closest('.mm-item'); lista = arrastando.parentElement; y0 = e.clientY; moveu = false;
      document.addEventListener('pointermove', mover);
      document.addEventListener('pointerup', soltar, { once: true });
    });
    function mover(e) {
      if (!arrastando) return;
      if (!moveu) { if (Math.abs(e.clientY - y0) < 4) return; moveu = true; arrastando.classList.add('arrastando'); }
      e.preventDefault();
      var irmaos = Array.prototype.filter.call(lista.children, function (n) { return n !== arrastando; });
      for (var i = 0; i < irmaos.length; i++) {
        var r = irmaos[i].getBoundingClientRect();
        if (e.clientY < r.top + r.height / 2) { if (arrastando.nextSibling !== irmaos[i]) lista.insertBefore(arrastando, irmaos[i]); return; }
      }
      if (lista.lastElementChild !== arrastando) lista.appendChild(arrastando);
    }
    function soltar() {
      document.removeEventListener('pointermove', mover);
      if (arrastando && moveu) {
        var ordem = Array.prototype.map.call(lista.children, function (n) { return galeria.itens[parseInt(n.dataset.i, 10)]; });
        galeria.itens = ordem;
        galeria.desenhar();
      }
      if (arrastando) arrastando.classList.remove('arrastando');
      arrastando = null; lista = null;
    }
  })();

  /** Prévia da galeria dentro do editor (customHTMLRenderer do Toast UI). */
  function renderGaleria(node) {
    var g = lerBloco(node.literal || '');
    var thumbs = g.itens.slice(0, 8).map(function (it) { return '<img src="' + esc(it.url) + '" alt="" />'; }).join('');
    var resumo = g.itens.length + (g.itens.length === 1 ? ' imagem' : ' imagens') + ' · ' + (g.cols === 'auto' ? 'colunas automáticas' : g.cols + ' colunas');
    return [
      { type: 'openTag', tagName: 'div', outerNewLine: true, attributes: { class: 'galeria-previa', contenteditable: 'false' } },
      { type: 'html', content: '<span class="galeria-previa-cabeca"><strong>Galeria</strong> ' + esc(resumo) + ' <span class="galeria-editar">Editar galeria</span></span><span class="galeria-previa-thumbs">' + thumbs + (g.itens.length > 8 ? '<span class="mais">+' + (g.itens.length - 8) + '</span>' : '') + '</span>' },
      { type: 'closeTag', tagName: 'div', outerNewLine: true }
    ];
  }

  function ligar(ed) {
    editor = ed;
    zonaDeArraste(el('mmImagemZona'), el('mmImagemArquivo'), imagemReceber);
    zonaDeArraste(el('mmGaleriaZona'), el('mmGaleriaArquivos'), galeriaReceber);
    el('mmImagemInserir').addEventListener('click', imagemInserir);
    el('mmImagemAlt').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); imagemInserir(); } });
    el('mmVideoUrl').addEventListener('input', videoValidar);
    el('mmVideoUrl').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); videoInserir(); } });
    el('mmVideoInserir').addEventListener('click', videoInserir);
    el('mmGaleriaInserir').addEventListener('click', galeriaInserir);
    Array.prototype.forEach.call(el('mmGaleriaCols').children, function (b) { b.addEventListener('click', function () { galeriaCols(b.dataset.cols); }); });
    // "Editar galeria" na prévia: acha o enésimo bloco no documento (mesma ordem do DOM).
    editor.wwEditor.view.dom.addEventListener('click', function (e) {
      var previa = e.target.closest('.galeria-previa');
      if (!previa) return;
      e.preventDefault();
      var todas = Array.prototype.slice.call(editor.wwEditor.view.dom.querySelectorAll('.galeria-previa'));
      var i = todas.indexOf(previa);
      var bloco = blocosDeGaleria()[i];
      if (bloco) abrirGaleria(bloco.node.textContent, i);
    }, true);
  }

  return { botaoBarra: botaoBarra, subir: subir, altDoNome: altDoNome, abrirImagem: abrirImagem, abrirVideo: abrirVideo, abrirGaleria: function () { abrirGaleria('', undefined); }, renderGaleria: renderGaleria, ligar: ligar, lerVideo: lerVideo, lerBloco: lerBloco, textoDoBloco: textoDoBloco };
})();
