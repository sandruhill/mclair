/*
 * Lightbox das páginas de post: galerias (.galeria) e imagens soltas do texto
 * (.post-content img que não estão dentro de um link). Sem biblioteca.
 * Teclado (setas, Esc), arraste no celular, legenda, contador, foco preso,
 * rolagem da página travada, e sem animação para quem pediu menos movimento.
 */
(function () {
  'use strict';
  var itens = [];      // { src, alt, legenda, el }
  var atual = -1;
  var caixa, img, legenda, contador, btnAnt, btnProx, btnFechar, focoAnterior;
  var reduz = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Roda no carregamento e a cada troca de página do ClientRouter; a lista é
  // refeita, mas cada elemento recebe o clique uma vez só (data-lb).
  function coletar() {
    itens = [];
    document.querySelectorAll('.galeria .galeria-link, .post-content img').forEach(function (el) {
      var src, alt, leg;
      if (el.classList.contains('galeria-link')) {
        var im = el.querySelector('img');
        src = el.getAttribute('href'); alt = im ? im.getAttribute('alt') || '' : ''; leg = el.getAttribute('data-legenda') || '';
      } else {
        if (el.closest('a') || el.closest('.galeria') || el.closest('.autor-card, .byline')) return;
        src = el.currentSrc || el.getAttribute('src'); alt = el.getAttribute('alt') || '';
        var fig = el.closest('figure');
        leg = fig && fig.querySelector('figcaption') ? fig.querySelector('figcaption').textContent : '';
        if (!leg && alt) leg = alt;
      }
      if (!src) return;
      itens.push({ src: src, alt: alt, legenda: leg, el: el });
      if (el.dataset.lb) return;
      el.dataset.lb = '1';
      var abrirEste = function () { var i = itens.findIndex(function (it) { return it.el === el; }); if (i >= 0) abrir(i); };
      el.addEventListener('click', function (ev) { ev.preventDefault(); abrirEste(); });
      if (!el.classList.contains('galeria-link')) { el.style.cursor = 'zoom-in'; el.setAttribute('tabindex', '0'); el.setAttribute('role', 'button'); el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); abrirEste(); } }); }
    });
  }

  function montar() {
    if (caixa) return;
    caixa = document.createElement('div');
    caixa.className = 'lb';
    caixa.setAttribute('role', 'dialog');
    caixa.setAttribute('aria-modal', 'true');
    caixa.setAttribute('aria-label', 'Imagem ampliada');
    caixa.hidden = true;
    caixa.innerHTML =
      '<button type="button" class="lb-fechar" aria-label="Fechar (Esc)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>' +
      '<button type="button" class="lb-ant" aria-label="Imagem anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>' +
      '<figure class="lb-figura"><img class="lb-img" alt="" /><figcaption class="lb-legenda"></figcaption></figure>' +
      '<button type="button" class="lb-prox" aria-label="Próxima imagem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>' +
      '<p class="lb-contador" aria-live="polite"></p>';
    document.body.appendChild(caixa);
    img = caixa.querySelector('.lb-img');
    legenda = caixa.querySelector('.lb-legenda');
    contador = caixa.querySelector('.lb-contador');
    btnAnt = caixa.querySelector('.lb-ant');
    btnProx = caixa.querySelector('.lb-prox');
    btnFechar = caixa.querySelector('.lb-fechar');
    btnFechar.addEventListener('click', fechar);
    btnAnt.addEventListener('click', function () { ir(-1); });
    btnProx.addEventListener('click', function () { ir(1); });
    caixa.addEventListener('click', function (ev) { if (ev.target === caixa || ev.target.classList.contains('lb-figura')) fechar(); });
    caixa.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { ev.preventDefault(); fechar(); }
      else if (ev.key === 'ArrowLeft') { ev.preventDefault(); ir(-1); }
      else if (ev.key === 'ArrowRight') { ev.preventDefault(); ir(1); }
      else if (ev.key === 'Tab') prenderFoco(ev);
    });
    // arraste horizontal no toque
    var x0 = null, y0 = null;
    caixa.addEventListener('touchstart', function (ev) { if (ev.touches.length === 1) { x0 = ev.touches[0].clientX; y0 = ev.touches[0].clientY; } }, { passive: true });
    caixa.addEventListener('touchend', function (ev) {
      if (x0 === null) return;
      var dx = ev.changedTouches[0].clientX - x0, dy = ev.changedTouches[0].clientY - y0;
      x0 = y0 = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) ir(dx < 0 ? 1 : -1);
    }, { passive: true });
  }

  function prenderFoco(ev) {
    var focaveis = [btnFechar, btnAnt, btnProx].filter(function (b) { return !b.hidden; });
    var i = focaveis.indexOf(document.activeElement);
    if (ev.shiftKey) { if (i <= 0) { ev.preventDefault(); focaveis[focaveis.length - 1].focus(); } }
    else if (i === -1 || i === focaveis.length - 1) { ev.preventDefault(); focaveis[0].focus(); }
  }

  function mostrar(i) {
    atual = (i + itens.length) % itens.length;
    var it = itens[atual];
    img.classList.add('lb-trocando');
    img.src = it.src;
    img.alt = it.alt;
    legenda.textContent = it.legenda;
    legenda.hidden = !it.legenda;
    contador.textContent = itens.length > 1 ? (atual + 1) + ' de ' + itens.length : '';
    btnAnt.hidden = btnProx.hidden = itens.length < 2;
    var fim = function () { img.classList.remove('lb-trocando'); img.removeEventListener('load', fim); };
    if (img.complete) fim(); else img.addEventListener('load', fim);
    // pré-carrega a vizinha
    if (itens.length > 1) { var pre = new Image(); pre.src = itens[(atual + 1) % itens.length].src; }
  }
  function ir(passo) { if (itens.length > 1) mostrar(atual + passo); }

  function abrir(i) {
    montar();
    // Volta o foco para a imagem clicada (clique de mouse não foca o elemento).
    focoAnterior = (document.activeElement && document.activeElement !== document.body) ? document.activeElement : itens[i].el;
    caixa.hidden = false;
    document.documentElement.classList.add('lb-aberto');
    if (!reduz) { caixa.classList.remove('lb-visivel'); requestAnimationFrame(function () { caixa.classList.add('lb-visivel'); }); }
    else caixa.classList.add('lb-visivel');
    mostrar(i);
    btnFechar.focus();
  }
  function fechar() {
    if (!caixa || caixa.hidden) return;
    caixa.classList.remove('lb-visivel');
    var esconder = function () { caixa.hidden = true; document.documentElement.classList.remove('lb-aberto'); if (focoAnterior && focoAnterior.focus) focoAnterior.focus(); };
    if (reduz) esconder(); else setTimeout(esconder, 180);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', coletar); else coletar();
  // Astro ClientRouter: páginas trocam sem recarregar
  document.addEventListener('astro:page-load', coletar);
})();
