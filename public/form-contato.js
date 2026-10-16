/*
 * Envio dos formulários do site: e-mail para a Kelly + WhatsApp.
 *
 * window.McContato.enviarEmail(dados)  manda os dados para /api/contato.php
 *   sem esperar resposta (fetch com keepalive, sobrevive à troca de aba).
 * window.McContato.abrirWhatsApp(texto)  abre o wa.me com a mensagem pronta.
 *
 * Os dois precisam ser chamados dentro do próprio handler do submit: o
 * window.open só passa pelo bloqueador de pop-up se acontecer no gesto do
 * usuário, e o fetch é disparado antes dele, sem await. Se o e-mail falhar,
 * o WhatsApp abre do mesmo jeito.
 *
 * Formulários com o atributo data-mc-contato são ligados aqui mesmo (ver
 * ligarDeclarativo): validação, mensagens de erro, e-mail e WhatsApp.
 * Os formulários antigos (contato, modal, Kelly) mantêm a validação própria
 * e só chamam enviarEmail antes do window.open.
 */
(function () {
  if (window.McContato) return;

  var WA_NUM = '5511988600372';
  var ENDPOINT = '/api/contato.php';

  function enviarEmail(dados) {
    try {
      var corpo = {};
      for (var k in dados) {
        if (Object.prototype.hasOwnProperty.call(dados, k) && dados[k] != null && dados[k] !== '') corpo[k] = dados[k];
      }
      if (!corpo.pagina) corpo.pagina = location.pathname;
      var json = JSON.stringify(corpo);
      if (window.fetch) {
        return fetch(ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: json,
          credentials: 'same-origin',
          keepalive: json.length < 60000
        }).then(function (r) { return r.ok; }).catch(function () { return false; });
      } else if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([json], { type: 'application/json' }));
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
  function limpa(campo) {
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
      if (msg) { marca(c, msg); if (!primeiro) primeiro = c; } else { limpa(c); }
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
        if (ok) aviso.textContent += ' Seus dados também chegaram à Kelly por e-mail.';
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
    if (c && c.form && c.form.hasAttribute('data-mc-contato') && c.getAttribute('aria-invalid') === 'true' && !erroDe(c)) limpa(c);
  });
  document.addEventListener('focusout', function (e) {
    var c = e.target;
    if (!c || !c.form || !c.form.hasAttribute('data-mc-contato') || !c.name || c.name === 'website') return;
    if (!(c.value || '').trim()) return; // campo vazio só acusa no envio
    var msg = erroDe(c);
    if (msg) marca(c, msg); else limpa(c);
  });

  window.McContato = { enviarEmail: enviarEmail, abrirWhatsApp: abrirWhatsApp };
})();
