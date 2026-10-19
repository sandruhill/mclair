/*
 * Envio dos formulários do site: grava em Mensagens (painel), e-mail para a
 * Kelly e WhatsApp.
 *
 * window.McContato.enviarEmail(dados)  manda os dados para /api/contato.php
 *   sem esperar resposta (fetch com keepalive, sobrevive à troca de aba).
 *   Resolve com true quando o servidor gravou. Nesse momento empurra no
 *   dataLayer o evento generate_lead (form_id, page_path, lead_id), que o
 *   GTM usa como conversão. Sem nome, e-mail ou telefone no evento.
 * window.McContato.abrirWhatsApp(texto)  abre o wa.me com a mensagem pronta.
 *
 * Os dois precisam ser chamados dentro do próprio handler do submit: o
 * window.open só passa pelo bloqueador de pop-up se acontecer no gesto do
 * usuário, e o fetch é disparado antes dele, sem await. Se o envio falhar,
 * o WhatsApp abre do mesmo jeito.
 *
 * Formulários com o atributo data-mc-contato são ligados aqui mesmo (ver
 * ligarDeclarativo): validação, mensagens de erro, envio e WhatsApp.
 * Os formulários antigos (contato, modal, Kelly, leiloeiros) mantêm a
 * validação própria e só chamam enviarEmail antes do window.open.
 *
 * Também aqui:
 *  - Visita: primeiro e último toque (utm, gclid, fbclid, referrer, página de
 *    entrada) e a lista de páginas vistas, no localStorage do próprio site.
 *    Vai junto com o formulário e só com ele. Quem recusou cookies no aviso
 *    (mclair:consentimento = recusado) não tem nada gravado.
 *  - Rascunho: assim que a pessoa preencheu nome e e-mail (ou telefone) e
 *    ainda não enviou, um registro parcial vai para o servidor com uma chave
 *    desta aba. Se ela enviar depois, o mesmo registro é completado; se
 *    fechar a página, fica no painel como "abandonado".
 */
(function () {
  if (window.McContato) return;

  var WA_NUM = '5511988600372';
  var ENDPOINT = '/api/contato.php';
  var K_VISITA = 'mclair:visita';
  var K_TOQUES = 'mclair:toques';
  var K_CONSENT = 'mclair:consentimento';
  var DIAS_VISITA = 90, MAX_PAGINAS = 60;
  var CHAVES_TOQUE = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid'];
  // Formulários que ganham rascunho automático (os declarativos entram pelo atributo).
  var FORMS_RASCUNHO = 'form[data-mc-contato], #contact-form, #wa-form-kelly, #leiloes-form';

  function ls(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* bloqueado: segue sem */ } }
  function lsDel(k) { try { window.localStorage.removeItem(k); } catch (e) { /* bloqueado */ } }
  function ss(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } }
  function ssSet(k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* bloqueado */ } }
  function ssDel(k) { try { window.sessionStorage.removeItem(k); } catch (e) { /* bloqueado */ } }
  function limpa(s, max) { return String(s || '').replace(/[\u0000-\u001f\u007f-\u009f​-‏‪-‮⁠-⁯﻿]/g, '').replace(/\s+/g, ' ').trim().slice(0, max); }
  function recusou() { return ls(K_CONSENT) === 'recusado'; }

  // ---------- Visita: toques e páginas ----------
  function toqueAgora() {
    var t = { ts: Date.now() }, algum = false;
    try {
      var q = new URLSearchParams(location.search);
      CHAVES_TOQUE.forEach(function (k) { var v = q.get(k); if (v) { t[k] = limpa(v, 200); algum = true; } });
    } catch (e) { /* sem query */ }
    try {
      var ref = document.referrer || '';
      if (ref && new URL(ref).host !== location.host) { t.ref = ref.slice(0, 300); algum = true; }
    } catch (e) { /* referrer inválido */ }
    t.landing = (location.pathname + location.search).slice(0, 300);
    return algum ? t : null;
  }
  function toquesLer() {
    try {
      var v = ls(K_TOQUES), o = v ? JSON.parse(v) : null;
      if (!o || typeof o !== 'object') return null;
      var agora = Date.now();
      ['primeiro', 'ultimo'].forEach(function (k) { if (o[k] && (typeof o[k].ts !== 'number' || agora - o[k].ts > DIAS_VISITA * 864e5)) delete o[k]; });
      return o.primeiro || o.ultimo ? o : null;
    } catch (e) { return null; }
  }
  function toquesGravar() {
    if (recusou()) { lsDel(K_TOQUES); return; }
    if (/^\/(acesso|api)(\/|$)/.test(location.pathname)) return;
    var o = toquesLer() || {}, t = toqueAgora();
    // O primeiro toque nunca é sobrescrito; o último troca a cada visita com origem.
    if (!o.primeiro) o.primeiro = t || { ts: Date.now(), landing: (location.pathname + location.search).slice(0, 300) };
    if (t) o.ultimo = t;
    lsSet(K_TOQUES, JSON.stringify(o));
  }
  function visitaLer() {
    try {
      var v = ls(K_VISITA), j = v ? JSON.parse(v) : null;
      if (!j || j.v !== 1 || !Array.isArray(j.paginas)) return null;
      var agora = Date.now();
      j.paginas = j.paginas.filter(function (p) { return p && typeof p.p === 'string' && typeof p.ts === 'number' && agora - p.ts < DIAS_VISITA * 864e5 && p.ts <= agora + 6e4; }).slice(-MAX_PAGINAS);
      return j;
    } catch (e) { return null; }
  }
  function visitaGravar() {
    if (recusou()) { lsDel(K_VISITA); return; }
    var caminho = location.pathname || '/';
    if (!/^\/[A-Za-z0-9\-._~%\/]*$/.test(caminho) || /^\/(acesso|api)(\/|$)/.test(caminho)) return;
    var agora = Date.now(), j = visitaLer();
    if (!j) j = { v: 1, inicio: agora, paginas: [] };
    var ultima = j.paginas[j.paginas.length - 1];
    if (ultima && ultima.p === caminho && agora - ultima.ts < 5000) return; // recarga: mesma vista
    j.paginas.push({ p: caminho.slice(0, 160), t: limpa(document.title, 120), ts: agora });
    if (j.paginas.length > MAX_PAGINAS) j.paginas = j.paginas.slice(-MAX_PAGINAS);
    lsSet(K_VISITA, JSON.stringify(j));
  }
  function registrarVisita() { toquesGravar(); visitaGravar(); }
  registrarVisita();
  document.addEventListener('astro:page-load', registrarVisita);

  function gaClientId() {
    try {
      var m = document.cookie.match(/(?:^|;\s*)_ga=([^;]+)/);
      if (!m) return '';
      var partes = decodeURIComponent(m[1]).split('.');
      return partes.length >= 4 ? partes[partes.length - 2] + '.' + partes[partes.length - 1] : '';
    } catch (e) { return ''; }
  }

  // ---------- Chave do rascunho (por formulário, nesta aba) ----------
  function chaveDe(formId, criar) {
    var k = 'mclair:chave:' + (formId || 'form');
    var v = ss(k);
    if (v && /^[0-9a-f]{32}$/.test(v)) return v;
    if (!criar) return '';
    var bytes = new Uint8Array(16), hex = '';
    try { (window.crypto || window.msCrypto).getRandomValues(bytes); } catch (e) { for (var i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256); }
    for (var j = 0; j < 16; j++) hex += (bytes[j] < 16 ? '0' : '') + bytes[j].toString(16);
    ssSet(k, hex);
    return hex;
  }
  function chaveApagar(formId) { ssDel('mclair:chave:' + (formId || 'form')); }

  // Dados comuns a envio e rascunho: página, visita, navegador.
  function enriquecer(corpo, modo) {
    if (!corpo.pagina) corpo.pagina = location.pathname;
    corpo.titulo = limpa(document.title, 200);
    corpo.chave = chaveDe(corpo.formulario, true);
    if (modo) corpo.modo = modo;
    if (!recusou()) {
      var visita = {};
      var t = toquesLer(); if (t) visita.toques = t;
      var j = visitaLer(); if (j && j.paginas.length) visita.jornada = { inicio: j.inicio, paginas: j.paginas };
      try { var ref = document.referrer || ''; if (ref && new URL(ref).host !== location.host) visita.ref = ref.slice(0, 300); } catch (e) { /* sem referrer */ }
      corpo.visita = visita;
      var ga = gaClientId(); if (ga) corpo.ga = ga;
    }
    try { corpo.tela = window.screen.width + 'x' + window.screen.height; } catch (e) { /* sem tela */ }
    try { corpo.fuso = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* sem fuso */ }
    corpo.idioma = (navigator.language || '').slice(0, 24);
    return corpo;
  }

  function enviarEmail(dados) {
    try {
      var corpo = {};
      for (var k in dados) {
        if (Object.prototype.hasOwnProperty.call(dados, k) && dados[k] != null && dados[k] !== '') corpo[k] = dados[k];
      }
      enriquecer(corpo, '');
      var formId = corpo.formulario || '';
      // A partir daqui nenhum rascunho sai para este formulário (o WhatsApp
      // abrindo em outra aba dispara o pagehide no mesmo instante).
      estadoDe(formId).enviado = true;
      var json = JSON.stringify(corpo);
      if (window.fetch) {
        return fetch(ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: json,
          credentials: 'same-origin',
          keepalive: json.length < 60000
        }).then(function (r) {
          return r.json().then(function (j) { return { ok: r.ok && j && j.ok, j: j }; }, function () { return { ok: r.ok, j: null }; });
        }).then(function (res) {
          if (res.ok) {
            marcarEnviado(formId);
            if (res.j && res.j.id) {
              window.dataLayer = window.dataLayer || [];
              window.dataLayer.push({ event: 'generate_lead', form_id: formId, page_path: location.pathname, lead_id: res.j.id });
            }
          }
          return !!res.ok;
        }).catch(function () { return false; });
      } else if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([json], { type: 'application/json' }));
        marcarEnviado(formId);
      }
    } catch (e) { /* o WhatsApp abre de qualquer jeito */ }
    return Promise.resolve(false);
  }

  function abrirWhatsApp(texto) {
    var url = 'https://wa.me/' + WA_NUM + '?text=' + encodeURIComponent(texto);
    var w = window.open(url, '_blank', 'noopener,noreferrer');
    // Com noopener o retorno é sempre null, então não dá para saber se foi
    // bloqueado; quem quiser um plano B usa o link visível do WhatsApp.
    return w;
  }

  function emailValido(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }
  function digitos(v) { return (v.match(/\d/g) || []).length; }

  // ---------- Rascunho automático ----------
  var rascunhos = {}; // formId -> { assinatura, enviado, timer }
  function estadoDe(formId) { return rascunhos[formId] || (rascunhos[formId] = { assinatura: '', enviado: false, timer: 0 }); }
  function marcarEnviado(formId) { estadoDe(formId).enviado = true; chaveApagar(formId); }

  function coletar(form) {
    var dados = { formulario: form.id };
    var servicos = [];
    Array.prototype.slice.call(form.querySelectorAll('input[name], select[name], textarea[name]')).forEach(function (c) {
      if (c.type === 'hidden' || c.name === 'website') return;
      if (c.type === 'checkbox' || c.type === 'radio') { if (c.checked) servicos.push(c.value); return; }
      var v = (c.value || '').trim();
      if (v) dados[c.name] = v;
    });
    if (servicos.length) dados.servicos = servicos.join(', ');
    return dados;
  }
  function prontoParaRascunho(d) {
    var nome = d.nome || d.name || '';
    var email = d.email || '';
    var tel = d.telefone || d.whatsapp || d.tel || '';
    return nome.length >= 2 && (emailValido(email) || digitos(tel) >= 10);
  }
  function mandarRascunho(form, usarBeacon) {
    var st = estadoDe(form.id);
    if (st.enviado) return;
    var hp = form.querySelector('[name="website"]');
    if (hp && hp.value) return;
    var d = coletar(form);
    if (!prontoParaRascunho(d)) return;
    var assinatura = JSON.stringify(d);
    if (assinatura === st.assinatura) return;
    st.assinatura = assinatura;
    var corpo = enriquecer(d, 'rascunho');
    var json = JSON.stringify(corpo);
    try {
      if (usarBeacon && navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([json], { type: 'application/json' }));
      } else if (window.fetch) {
        fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: json, credentials: 'same-origin', keepalive: true }).catch(function () {});
      }
    } catch (e) { /* rascunho é melhor esforço */ }
  }
  function agendarRascunho(form) {
    var st = estadoDe(form.id);
    clearTimeout(st.timer);
    st.timer = setTimeout(function () { mandarRascunho(form, false); }, 700);
  }
  function formRascunho(el) {
    var f = el && el.form;
    return f && f.id && f.matches && f.matches(FORMS_RASCUNHO) ? f : null;
  }
  document.addEventListener('focusout', function (e) { var f = formRascunho(e.target); if (f) agendarRascunho(f); });
  document.addEventListener('change', function (e) { var f = formRascunho(e.target); if (f) agendarRascunho(f); });
  function aoSair() {
    document.querySelectorAll(FORMS_RASCUNHO).forEach(function (f) { if (f.id) mandarRascunho(f, true); });
  }
  window.addEventListener('pagehide', aoSair);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') aoSair(); });
  document.addEventListener('astro:before-swap', aoSair);

  // ---------- Formulários declarativos (data-mc-contato) ----------
  function caixaErro(campo) {
    var id = campo.id + '-erro';
    var el = document.getElementById(id);
    if (!el) {
      el = document.createElement('p');
      el.id = id;
      el.className = 'mc-erro';
      var alvo = campo.closest('.mc-campo') || campo.parentElement;
      alvo.appendChild(el);
    }
    var desc = (campo.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
    if (desc.indexOf(id) < 0) { desc.push(id); campo.setAttribute('aria-describedby', desc.join(' ')); }
    return el;
  }
  function marca(campo, msg) {
    var el = caixaErro(campo);
    el.textContent = msg;
    campo.setAttribute('aria-invalid', 'true');
  }
  function limpaErro(campo) {
    var el = document.getElementById(campo.id + '-erro');
    if (el) el.textContent = '';
    campo.removeAttribute('aria-invalid');
  }
  function erroDe(campo) {
    var v = (campo.value || '').trim();
    var nome = campo.getAttribute('data-nome-campo') || 'este campo';
    if (campo.required && !v) return 'Preencha ' + nome + '.';
    if (!v) return '';
    if (campo.type === 'email' && !emailValido(v)) return 'Confira o e-mail: falta algo como nome@empresa.com.br.';
    if (campo.type === 'tel' && digitos(v) < 10) return 'Coloque o WhatsApp com DDD, por exemplo (11) 90000-0000.';
    return '';
  }

  function enviarDeclarativo(form) {
    var campos = Array.prototype.slice.call(form.querySelectorAll('input[name], select[name], textarea[name]'))
      .filter(function (c) { return c.name !== 'website' && c.type !== 'hidden'; });
    var primeiro = null;
    campos.forEach(function (c) {
      var msg = erroDe(c);
      if (msg) { marca(c, msg); if (!primeiro) primeiro = c; } else { limpaErro(c); }
    });
    var aviso = form.querySelector('[data-mc-aviso]');
    if (primeiro) {
      if (aviso) aviso.textContent = 'Faltou ajustar um ou mais campos. Veja as mensagens em vermelho.';
      primeiro.focus();
      return;
    }

    var dados = { formulario: form.id, assunto: form.getAttribute('data-assunto') || '' };
    var hp = form.querySelector('[name="website"]');
    if (hp) dados.website = hp.value;
    var linhas = [];
    campos.forEach(function (c) {
      var v = (c.value || '').trim();
      if (!v) return;
      dados[c.name] = v;
      var rotulo = c.getAttribute('data-wa');
      if (rotulo) linhas.push('*' + rotulo + ':* ' + v);
    });
    var texto = (form.getAttribute('data-wa-intro') || 'Olá Mclair!') + '\n\n' + linhas.join('\n')
      + '\n\n(Página: mclair.com.br' + location.pathname + ')';

    var envio = dados.website ? Promise.resolve(true) : enviarEmail(dados);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: 'wa_form_submit', form_id: form.id, assunto: dados.assunto });
    abrirWhatsApp(texto);

    if (aviso) {
      aviso.textContent = 'O WhatsApp abriu em outra aba com a sua mensagem pronta. É só tocar em enviar.';
      envio.then(function (ok) {
        if (ok) aviso.textContent += ' Seus dados também chegaram à Kelly.';
      });
    }
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.hasAttribute || !form.hasAttribute('data-mc-contato')) return;
    e.preventDefault();
    enviarDeclarativo(form);
  });
  // Erro some assim que a pessoa corrige; aparece ao sair do campo.
  document.addEventListener('input', function (e) {
    var c = e.target;
    if (c && c.form && c.form.hasAttribute('data-mc-contato') && c.getAttribute('aria-invalid') === 'true' && !erroDe(c)) limpaErro(c);
  });
  document.addEventListener('focusout', function (e) {
    var c = e.target;
    if (!c || !c.form || !c.form.hasAttribute('data-mc-contato') || !c.name || c.name === 'website') return;
    if (!(c.value || '').trim()) return; // campo vazio só acusa no envio
    var msg = erroDe(c);
    if (msg) marca(c, msg); else limpaErro(c);
  });

  window.McContato = { enviarEmail: enviarEmail, abrirWhatsApp: abrirWhatsApp };
})();
