/*
 * Atualização automática para quem edita o site.
 *
 * Carregado nas páginas de post. Só faz alguma coisa no navegador de quem
 * usa o painel (/acesso/ grava o marcador "mclair:painel" no localStorage);
 * para qualquer outro visitante o arquivo é lido uma vez, cai no cache e a
 * primeira linha encerra. Quando o painel confirma que uma publicação nova
 * está no ar, ele avisa por BroadcastChannel (e por localStorage, para
 * navegador sem o canal); esta aba confere, sem cache, se o servidor já
 * entrega uma versão mais nova desta página e, se sim, recarrega.
 */
(function () {
  'use strict';
  var marcador = null;
  try { marcador = localStorage.getItem('mclair:painel'); } catch (e) { return; }

  var meta = document.querySelector('meta[name="build-id"]');
  var buildAtual = meta ? meta.getAttribute('content') : '';
  var caminho = location.pathname;

  // Depois de um reload: se a página ainda veio velha (cache no caminho),
  // busca de novo com um parâmetro na URL, que nenhum cache tem guardado.
  function esperadoPendente() {
    var esperado = null;
    try { esperado = sessionStorage.getItem('mclair:esperado:' + caminho); } catch (e) {}
    if (!esperado) return;
    try { sessionStorage.removeItem('mclair:esperado:' + caminho); } catch (e) {}
    if (buildAtual && Number(buildAtual) < Number(esperado) && location.search.indexOf('v=') === -1) {
      location.replace(caminho + '?v=' + esperado);
    }
  }

  var verificando = false;
  function recarregarSeNovo(builtAt, tentativa) {
    if (verificando && !tentativa) return;
    verificando = true;
    tentativa = tentativa || 1;
    fetch(caminho + '?_=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        var m = html.match(/<meta name="build-id" content="(\d+)"/);
        var novo = m ? m[1] : '';
        if (novo && novo !== buildAtual) {
          try { sessionStorage.setItem('mclair:esperado:' + caminho, String(novo)); } catch (e) {}
          location.reload();
          return;
        }
        // O rsync ainda pode estar copiando esta página: insiste por ~1 min.
        if (tentativa < 20) setTimeout(function () { recarregarSeNovo(builtAt, tentativa + 1); }, 3000);
        else verificando = false;
      })
      .catch(function () { verificando = false; });
  }

  function aoReceber(msg) {
    if (!msg || !msg.url) return;
    var alvo;
    try { alvo = new URL(msg.url, location.origin).pathname; } catch (e) { return; }
    var mesmaPagina = alvo.replace(/\/+$/, '') === caminho.replace(/\/+$/, '');
    if (mesmaPagina || msg.all) recarregarSeNovo(msg.builtAt);
  }

  function ativar() {
    esperadoPendente();
    try {
      new BroadcastChannel('mclair-publicacao').onmessage = function (ev) { aoReceber(ev.data); };
    } catch (e) {}
  }

  // O evento storage chega de outra aba mesmo antes do marcador existir:
  // serve tanto de fallback ao canal quanto para uma aba aberta antes do
  // primeiro login no painel.
  window.addEventListener('storage', function (ev) {
    if (ev.key === 'mclair:painel' && ev.newValue && !marcador) { marcador = ev.newValue; ativar(); }
    if (ev.key === 'mclair:publicado' && ev.newValue) {
      try { aoReceber(JSON.parse(ev.newValue)); } catch (e) {}
    }
  });

  if (marcador) ativar();
})();
