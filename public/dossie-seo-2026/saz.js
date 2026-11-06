/* Sazonalidade da busca: desenha o gráfico a partir de #saz-dados.
 * A linha de hoje, o texto "agora" e as contas saem da data do navegador,
 * então a seção nunca fica velha. Para atualizar os números basta trocar
 * dados/sazonalidade.json (gerado pelo Google Trends, 5 anos). */
(function () {
  'use strict';
  var el = document.getElementById('saz-dados');
  var graf = document.getElementById('saz-graf');
  if (!el || !graf) return;
  var D;
  try { D = JSON.parse(el.textContent); } catch (e) { return; }

  var MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  var MESES_LONGO = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  var CORES = { br: '#C8102E', sp: '#1d6fa3', com: '#a8620f', ano: '#1C1A17' };
  var NS = 'http://www.w3.org/2000/svg';

  var series = D.series;
  var porId = {};
  series.forEach(function (s) { porId[s.id] = s; });
  var ativo = { br: true, sp: false, com: false };
  var anoSel = '';

  /* ---- Ano por cima da média: valores do ano escalados para a mesma base.
     Ano incompleto não pode usar a própria média (falta o dezembro fraco),
     então a escala vem da média esperada dos meses que existem. ---- */
  var anos = {};
  porId.br.mensal.forEach(function (p) { anos[p[0].slice(0, 4)] = true; });
  var listaAnos = Object.keys(anos).filter(function (a) {
    return porId.br.mensal.filter(function (p) { return p[0].slice(0, 4) === a; }).length >= 6;
  }).sort();

  function serieAno(ano) {
    var s = porId.br, v = [], soma = 0, esp = 0;
    for (var m = 0; m < 12; m++) v.push(null);
    s.mensal.forEach(function (p) {
      if (p[0].slice(0, 4) !== ano) return;
      var m = +p[0].slice(5, 7) - 1;
      v[m] = p[1]; soma += p[1]; esp += s.idx[m];
    });
    if (!soma) return v;
    var k = esp / soma;
    return v.map(function (x) { return x === null ? null : Math.round(x * k); });
  }

  /* ---- Controles ---- */
  var botoes = document.getElementById('saz-botoes');
  series.forEach(function (s) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'saz-bt';
    b.style.setProperty('--c', CORES[s.id]);
    b.setAttribute('aria-pressed', ativo[s.id] ? 'true' : 'false');
    b.innerHTML = '<i></i>';
    b.appendChild(document.createTextNode(s.id === 'com' ? '"' + s.termo + '", Brasil' : '"' + s.termo + '", ' + s.nome));
    b.addEventListener('click', function () {
      var liga = !ativo[s.id];
      if (!liga && Object.keys(ativo).filter(function (k) { return ativo[k]; }).length === 1 && !anoSel) return;
      ativo[s.id] = liga;
      b.setAttribute('aria-pressed', liga ? 'true' : 'false');
      desenha();
    });
    botoes.appendChild(b);
  });
  var sel = document.getElementById('saz-ano');
  listaAnos.slice().reverse().forEach(function (a) {
    var o = document.createElement('option');
    o.value = a;
    o.textContent = a + (porId.br.mensal.filter(function (p) { return p[0].slice(0, 4) === a; }).length < 12 ? ' (até agora)' : '');
    sel.appendChild(o);
  });
  sel.addEventListener('change', function () { anoSel = sel.value; desenha(); });
  document.getElementById('saz-fonte').textContent = D.fonte + '. ' + D.base;

  /* ---- Texto "agora" ---- */
  function agora() {
    var hoje = new Date(), m = hoje.getMonth(), idx = porId.br.idx;
    var v = idx[m], dif = v - 100;
    var nivel = Math.abs(dif) <= 4 ? 'na média do ano' : (dif > 0 ? dif + '% acima da média' : Math.abs(dif) + '% abaixo da média');
    var t = '<b>Agora, em ' + MESES_LONGO[m] + ',</b> a busca por assessoria de imprensa costuma ficar ' + nivel + ' (' + v + ').';
    // Próximo mês fraco (abaixo de 85) e próximo pico (o maior dos próximos 6 meses, se passar de 104)
    var fraco = null, pico = null;
    for (var i = 1; i <= 11; i++) {
      var mm = (m + i) % 12;
      if (fraco === null && idx[mm] < 85) fraco = { m: mm, i: i };
      if (i <= 6 && idx[mm] >= 105 && (!pico || idx[mm] > idx[pico.m])) pico = { m: mm, i: i };
    }
    function quando(alvo) {
      var ini = new Date(hoje.getFullYear() + (alvo.m < m ? 1 : 0), alvo.m, 1);
      var sem = Math.round((ini - hoje) / 6048e5);
      return sem <= 1 ? 'começa nesta semana' : 'começa em ' + sem + ' semanas';
    }
    if (idx[m] < 85) {
      t += ' É o mês mais fraco do ano; a comparação justa é com ' + MESES_LONGO[m] + ' do ano anterior, não com o mês passado.';
    } else if (fraco && fraco.i <= 2) {
      t += ' ' + MESES_LONGO[fraco.m].charAt(0).toUpperCase() + MESES_LONGO[fraco.m].slice(1) + ', o mês mais fraco (' + idx[fraco.m] + '), ' + quando(fraco) + '.';
    }
    if (pico) {
      t += ' O próximo pico é ' + MESES_LONGO[pico.m] + ' (' + idx[pico.m] + '), que ' + quando(pico) + ': página nova para esse pico precisa estar no ar ' + (pico.i <= 2 ? 'já' : 'até o fim de ' + MESES_LONGO[(pico.m + 10) % 12]) + '.';
    }
    document.getElementById('saz-agora').innerHTML = t;
  }

  /* ---- Desenho ---- */
  function no(tag, at, pai) {
    var n = document.createElementNS(NS, tag);
    for (var k in at) n.setAttribute(k, at[k]);
    if (pai) pai.appendChild(n);
    return n;
  }
  function txt(x, y, s, cls, anc, pai) {
    var n = no('text', { x: x, y: y, 'class': cls || '', 'text-anchor': anc || 'middle' }, pai);
    n.textContent = s;
    return n;
  }

  var dica = document.getElementById('saz-dica');

  function desenha() {
    var W = Math.max(300, graf.clientWidth), estreito = W < 560;
    var H = estreito ? 300 : 380;
    var ml = 36, mr = estreito ? 10 : 16, mt = 30, mb = 34;
    var pw = W - ml - mr, ph = H - mt - mb;

    var linhas = series.filter(function (s) { return ativo[s.id]; }).map(function (s) {
      return { id: s.id, v: s.idx, cor: CORES[s.id], nome: s.id === 'com' ? 'Comunicação' : s.nome };
    });
    if (anoSel) linhas.push({ id: 'ano', v: serieAno(anoSel), cor: CORES.ano, nome: anoSel + ', Brasil', ano: true });

    var todos = [];
    linhas.forEach(function (l) { l.v.forEach(function (x) { if (x !== null) todos.push(x); }); });
    var lo = Math.min(60, Math.floor(Math.min.apply(null, todos) / 10) * 10);
    var hi = Math.max(120, Math.ceil(Math.max.apply(null, todos) / 10) * 10);
    function X(i) { return ml + pw * i / 11; }
    function Y(v) { return mt + ph * (hi - v) / (hi - lo); }

    graf.innerHTML = '';
    var svg = no('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img',
      'aria-label': 'Busca por assessoria de imprensa mês a mês, índice 100 igual à média do ano. ' +
        linhas.map(function (l) { return l.nome + ': ' + l.v.map(function (x, i) { return MESES[i] + ' ' + (x === null ? 'sem dado' : x); }).join(', '); }).join('. ') }, graf);

    // Faixa de dezembro
    no('rect', { x: X(10.5), y: mt, width: X(11) - X(10.5) + mr - 2, height: ph, 'class': 'faixa' }, svg);
    txt(X(11) - 4, mt + ph - 8, 'recesso', 'faixa-t', 'end', svg);

    // Grade
    for (var g = lo; g <= hi; g += (hi - lo > 60 ? 20 : 10)) {
      no('line', { x1: ml, x2: W - mr, y1: Y(g), y2: Y(g), 'class': g === 100 ? 'base' : 'grade' }, svg);
      txt(ml - 8, Y(g) + 4, g, 'eixo', 'end', svg);
    }
    var hoje = new Date(), mh = hoje.getMonth();
    var dias = new Date(hoje.getFullYear(), mh + 1, 0).getDate();
    MESES.forEach(function (m, i) {
      if (estreito && i % 2 && i !== mh) return;
      txt(X(i), H - 12, m, 'eixo' + (i === mh ? ' mes-hoje' : ''), 'middle', svg);
    });

    // Hoje: posição dentro do mês, para a linha andar dia a dia
    var xh = X(Math.min(11, mh + (hoje.getDate() - 1) / dias));
    no('line', { x1: xh, x2: xh, y1: mt - 6, y2: mt + ph, 'class': 'hoje' }, svg);
    txt(xh, mt - 12, 'hoje, ' + hoje.getDate() + '/' + (mh + 1), 'hoje-t', xh > W - 60 ? 'end' : 'middle', svg);

    // Linhas
    linhas.forEach(function (l) {
      var d = '', pena = false;
      l.v.forEach(function (v, i) {
        if (v === null) { pena = false; return; }
        d += (pena ? 'L' : 'M') + X(i).toFixed(1) + ',' + Y(v).toFixed(1);
        pena = true;
      });
      no('path', { d: d, 'class': 'linha' + (l.ano ? ' ano' : ''), stroke: l.cor }, svg);
      l.v.forEach(function (v, i) {
        if (v === null) return;
        no('circle', { cx: X(i), cy: Y(v), r: l.ano ? 3 : 4, fill: l.ano ? '#fff' : l.cor, stroke: l.cor, 'stroke-width': 2 }, svg);
      });
    });

    // Rótulos de valor só quando há uma linha (com várias, o toque mostra)
    if (linhas.length === 1) {
      var l = linhas[0];
      l.v.forEach(function (v, i) {
        if (v === null || (estreito && i % 2 && i !== 11 && i !== 2)) return;
        var acima = i === 0 || v >= l.v[i - 1] || l.v[i - 1] === null;
        txt(X(i), Y(v) + (acima ? -11 : 20), v, 'val', 'middle', svg);
      });
    }

    // Guia e dica ao passar o mouse ou tocar
    var guia = no('line', { y1: mt, y2: mt + ph, 'class': 'guia', visibility: 'hidden' }, svg);
    function mostra(ev) {
      var r = svg.getBoundingClientRect();
      var px = (ev.clientX - r.left) * W / r.width;
      var i = Math.max(0, Math.min(11, Math.round((px - ml) / pw * 11)));
      guia.setAttribute('x1', X(i)); guia.setAttribute('x2', X(i));
      guia.setAttribute('visibility', 'visible');
      var h = '<b>' + MESES_LONGO[i] + '</b>';
      linhas.forEach(function (l) {
        h += '<span><i style="color:' + l.cor + '">' + l.nome + '</i><em>' + (l.v[i] === null ? 'sem dado' : l.v[i]) + '</em></span>';
      });
      dica.innerHTML = h;
      dica.hidden = false;
      var cx = graf.offsetLeft + X(i) * r.width / W;
      var larg = dica.offsetWidth, caixa = graf.parentNode.clientWidth;
      dica.style.left = Math.max(6, Math.min(caixa - larg - 6, cx + (cx > caixa / 2 ? -larg - 14 : 14))) + 'px';
      dica.style.top = (graf.offsetTop + 30) + 'px';
    }
    function some() { guia.setAttribute('visibility', 'hidden'); dica.hidden = true; }
    svg.addEventListener('pointermove', mostra);
    svg.addEventListener('pointerdown', mostra);
    svg.addEventListener('pointerleave', some);
  }

  agora();
  desenha();
  var t;
  window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(desenha, 120); });
  // Página aberta de um dia para o outro: refaz a linha de hoje e o texto
  setInterval(function () { agora(); desenha(); }, 36e5);
})();
