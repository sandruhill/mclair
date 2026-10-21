// Matérias conquistadas para clientes, montadas no build a partir dos cases do
// CMS (mesma fonte de /cases/{slug}/). Case novo publicado no painel entra em
// /mclair-na-midia/ no próximo build, sem lista mantida à mão.
//
// De onde sai cada matéria, em ordem de preferência:
//  1. campo estruturado do case (materias ou press: veiculo, titulo, url, data),
//     se um dia existir no banco;
//  2. link externo para um veículo dentro do texto do case (desafio e solução);
//  3. legenda da galeria do case que cita um veículo pelo nome. A imagem da
//     galeria é o recorte da matéria publicada, e o link aponta para ela.
// Nada é inventado: legenda sem veículo reconhecido fica de fora.

import type { DbCase } from './cmsApi';

export type Materia = {
  veiculo: string;
  titulo: string;
  href: string;
  externo: boolean;
  imagem: string | null;
  data: string | null;
  caseSlug: string;
  cliente: string;
};

// Domínio do link para o nome do veículo. Sufixo mais longo ganha
// (valorinveste.globo.com antes de globo.com).
const DOMINIOS: Record<string, string> = {
  'folha.uol.com.br': 'Folha de S.Paulo',
  'folhavitoria.com.br': 'Folha Vitória',
  'exame.com': 'Exame',
  'valor.globo.com': 'Valor Econômico',
  'valorinveste.globo.com': 'Valor Investe',
  'oglobo.globo.com': 'O Globo',
  'g1.globo.com': 'g1',
  'extra.globo.com': 'Extra',
  'epocanegocios.globo.com': 'Época Negócios',
  'globo.com': 'Globo.com',
  'estadao.com.br': 'Estadão',
  'infomoney.com.br': 'InfoMoney',
  'istoedinheiro.com.br': 'IstoÉ Dinheiro',
  'istoe.com.br': 'IstoÉ',
  'cnnbrasil.com.br': 'CNN Brasil',
  'band.uol.com.br': 'Band',
  'uol.com.br': 'UOL',
  'canaltech.com.br': 'Canaltech',
  'olhardigital.com.br': 'Olhar Digital',
  'startupi.com.br': 'Startupi',
  'baguete.com.br': 'Baguete',
  'itforum.com.br': 'IT Forum',
  'revistahsm.com.br': 'HSM Management',
  'mitsloanreview.com.br': 'MIT Sloan Review Brasil',
  'opovo.com.br': 'O Povo',
  'veja.abril.com.br': 'Veja',
  'forbes.com.br': 'Forbes Brasil',
  'metropoles.com': 'Metrópoles',
  'terra.com.br': 'Terra',
  'r7.com': 'R7',
  'sbt.com.br': 'SBT',
  'jovempan.com.br': 'Jovem Pan',
  'gazetadopovo.com.br': 'Gazeta do Povo',
  'correiobraziliense.com.br': 'Correio Braziliense',
  'em.com.br': 'Estado de Minas',
  'meioemensagem.com.br': 'Meio & Mensagem',
  'propmark.com.br': 'Propmark',
  'moneytimes.com.br': 'Money Times',
  'seudinheiro.com': 'Seu Dinheiro',
  'tecmundo.com.br': 'TecMundo',
  'abcdacomunicacao.com.br': 'ABC da Comunicação',
  'observatorio3setor.org.br': 'Observatório do Terceiro Setor',
  'grandesnomesdapropaganda.com.br': 'Grandes Nomes da Propaganda',
};

// Link que nunca é matéria: o próprio site, redes sociais, mensageiros.
const NAO_E_MATERIA = [
  'mclair.com.br', 'wa.me', 'whatsapp.com', 'instagram.com', 'facebook.com',
  'linkedin.com', 'x.com', 'twitter.com', 'tiktok.com', 'youtube.com', 'youtu.be',
  'google.com', 'schema.org',
];

// Nome do veículo como aparece em legenda. Ordem importa: o trecho casado sai
// do texto antes do próximo padrão (Folha Vitória antes de Folha, Gazeta do
// Povo antes de Gazeta).
const NOMES: [RegExp, string][] = [
  [/Folha Vit[oó]ria/, 'Folha Vitória'],
  [/Folha de S\.? ?Paulo/, 'Folha de S.Paulo'],
  [/Valor Investe/, 'Valor Investe'],
  [/Valor Econ[oô]mico/, 'Valor Econômico'],
  [/Globo\.com/i, 'Globo.com'],
  [/\bO Globo\b/, 'O Globo'],
  [/[EÉ]poca Neg[oó]cios/, 'Época Negócios'],
  [/\bExame(?: IN)?\b/, 'Exame'],
  [/B[uú]ssola/, 'Bússola'],
  [/Gazeta do Povo/, 'Gazeta do Povo'],
  [/\bO Povo\b/, 'O Povo'],
  [/\bExtra\b/, 'Extra'],
  [/Isto[ÉE] Dinheiro/i, 'IstoÉ Dinheiro'],
  [/InfoMoney/i, 'InfoMoney'],
  [/CNN Brasil/, 'CNN Brasil'],
  [/HSM Management/, 'HSM Management'],
  [/MIT Sloan(?: Management)? Review(?: Brasil)?/, 'MIT Sloan Review Brasil'],
  [/\bIT Forum(?: 365)?/, 'IT Forum'],
  [/\bBaguete\b/, 'Baguete'],
  [/\bUOL\b/, 'UOL'],
  [/Canal ?Tech/i, 'Canaltech'],
  [/Olhar Digital/, 'Olhar Digital'],
  [/\bStartupi\b/, 'Startupi'],
  [/TecMundo/i, 'TecMundo'],
  [/ABC da Comunica[cç][aã]o/, 'ABC da Comunicação'],
  [/Observat[oó]rio do Terceiro Setor/, 'Observatório do Terceiro Setor'],
  [/Grandes Nomes da Propaganda/, 'Grandes Nomes da Propaganda'],
  [/Meio ?& ?Mensagem/, 'Meio & Mensagem'],
  [/\bEstad[aã]o\b/, 'Estadão'],
  [/Forbes/, 'Forbes Brasil'],
  [/Metr[oó]poles/, 'Metrópoles'],
  [/Jovem Pan/, 'Jovem Pan'],
  [/Correio Braziliense/, 'Correio Braziliense'],
  [/InFINItas Ideias/i, 'Podcast InFINItas Ideias'],
  [/\bSBT\b/, 'SBT'],
  [/\bRecord(?: TV)?\b/, 'Record TV'],
  [/\bBand\b/, 'Band'],
  [/\b(?:TV )?Gazeta\b/, 'TV Gazeta'],
  [/\bGloboNews\b/i, 'GloboNews'],
];

function veiculosNaLegenda(texto: string): string[] {
  let resto = texto;
  const achados: string[] = [];
  for (const [re, nome] of NOMES) {
    if (re.test(resto)) {
      if (!achados.includes(nome)) achados.push(nome);
      resto = resto.replace(new RegExp(re.source, re.flags.includes('g') ? re.flags : re.flags + 'g'), ' ');
    }
  }
  return achados;
}

function veiculoDoLink(url: string): string | null {
  let host: string;
  try {
    host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
  } catch {
    return null;
  }
  if (NAO_E_MATERIA.some((d) => host === d || host.endsWith(`.${d}`))) return null;
  const dominio = Object.keys(DOMINIOS)
    .sort((a, b) => b.length - a.length)
    .find((d) => host === d || host.endsWith(`.${d}`));
  return dominio ? DOMINIOS[dominio] : host;
}

function semTags(html: string): string {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

// Links externos no texto do case, em HTML (<a href>) ou markdown ([t](url)).
function linksNoTexto(texto: string): { url: string; titulo: string }[] {
  const out: { url: string; titulo: string }[] = [];
  for (const m of texto.matchAll(/<a\s[^>]*href=["'](https?:\/\/[^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi)) {
    out.push({ url: m[1], titulo: semTags(m[2]) });
  }
  for (const m of texto.matchAll(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g)) {
    out.push({ url: m[2], titulo: m[1].trim() });
  }
  return out;
}

export function materiasDosCases(cases: DbCase[]): Materia[] {
  const lista: Materia[] = [];
  const vistos = new Set<string>();
  const add = (m: Materia) => {
    const chave = `${m.caseSlug}|${m.veiculo}|${m.externo ? m.href : m.titulo}`;
    if (vistos.has(chave)) return;
    vistos.add(chave);
    lista.push(m);
  };

  const ordenados = [...cases]
    .filter((c) => c && c.slug && c.client)
    .sort((a, b) => (a.num ?? '').localeCompare(b.num ?? ''));

  for (const c of ordenados) {
    const base = { caseSlug: c.slug, cliente: c.client };

    // 1. campo estruturado, se existir
    const campo = (c as any).materias ?? (c as any).press;
    if (Array.isArray(campo)) {
      for (const p of campo) {
        const url = typeof p?.url === 'string' ? p.url.trim() : '';
        if (!/^https?:\/\//.test(url)) continue;
        const veiculo = (p.veiculo || p.vehicle || veiculoDoLink(url) || '').toString().trim();
        if (!veiculo) continue;
        add({ ...base, veiculo, titulo: (p.titulo || p.title || veiculo).toString().trim(), href: url, externo: true, imagem: null, data: p.data || p.date || null });
      }
    }

    // 2. links externos no texto do case
    for (const { url, titulo } of linksNoTexto(`${c.challenge ?? ''}\n${c.solution ?? ''}`)) {
      const veiculo = veiculoDoLink(url);
      if (!veiculo) continue;
      add({ ...base, veiculo, titulo: titulo || veiculo, href: url, externo: true, imagem: null, data: null });
    }

    // 3. recortes da galeria com veículo na legenda
    for (const g of c.gallery ?? []) {
      const legenda = semTags(g?.caption ?? '');
      if (!legenda || !g?.src) continue;
      for (const veiculo of veiculosNaLegenda(legenda)) {
        add({ ...base, veiculo, titulo: legenda, href: g.src, externo: false, imagem: g.src, data: null });
      }
    }
  }
  return lista;
}

export type GrupoVeiculo = { veiculo: string; materias: Materia[] };
export type GrupoCliente = { cliente: string; caseSlug: string; materias: Materia[]; veiculos: string[] };

export function porVeiculo(materias: Materia[]): GrupoVeiculo[] {
  const mapa = new Map<string, Materia[]>();
  for (const m of materias) {
    if (!mapa.has(m.veiculo)) mapa.set(m.veiculo, []);
    mapa.get(m.veiculo)!.push(m);
  }
  return [...mapa.entries()]
    .map(([veiculo, ms]) => ({ veiculo, materias: ms }))
    .sort((a, b) => b.materias.length - a.materias.length || a.veiculo.localeCompare(b.veiculo, 'pt-BR'));
}

export function porCliente(materias: Materia[]): GrupoCliente[] {
  const mapa = new Map<string, GrupoCliente>();
  for (const m of materias) {
    if (!mapa.has(m.caseSlug)) mapa.set(m.caseSlug, { cliente: m.cliente, caseSlug: m.caseSlug, materias: [], veiculos: [] });
    const g = mapa.get(m.caseSlug)!;
    g.materias.push(m);
    if (!g.veiculos.includes(m.veiculo)) g.veiculos.push(m.veiculo);
  }
  return [...mapa.values()].sort((a, b) => b.materias.length - a.materias.length || a.cliente.localeCompare(b.cliente, 'pt-BR'));
}
