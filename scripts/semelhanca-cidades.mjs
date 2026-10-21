#!/usr/bin/env node
// Semelhança entre as páginas de cidade da assessoria de imprensa.
//
// Lê o HTML gerado pelo build (dist/servicos/assessoria-de-imprensa/{cidade}/
// index.html), pega o texto do <main> sem script, style, svg e formulário
// (o formulário é o mesmo componente em todas e não é texto da página),
// troca nomes de cidade e de estado por marcadores e compara cada par pela
// semelhança de Jaccard dos trechos de 5 palavras seguidas (shingles).
// Meta: abaixo de 0,25 em todos os pares.
//
// Uso:
//   node scripts/semelhanca-cidades.mjs [pasta-do-dist] [--meta 0.25]
// Sem pasta, usa ./dist. Sai com código 1 se algum par passar da meta.

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';

const args = process.argv.slice(2);
const iMeta = args.indexOf('--meta');
const META = iMeta >= 0 ? Number(args[iMeta + 1]) : 0.25;
const posicionais = args.filter((a, i) => !a.startsWith('--') && (iMeta < 0 || i !== iMeta + 1));
const DIST = resolve(posicionais[0] ?? 'dist');
const PASTA = join(DIST, 'servicos', 'assessoria-de-imprensa');
const N = 5;

// Filhas do hub que não são cidade.
const NAO_CIDADE = new Set(['quanto-custa']);

// Nomes trocados por marcador. Vale para todas as páginas: "São Paulo"
// aparece também nas outras (é a base da Mclair), e neutralizar em todas
// deixa a medida mais severa, não mais branda.
const CIDADES = [
  'são paulo', 'sp', 'paulistano', 'paulistana', 'paulistanos', 'paulistanas', 'paulista', 'paulistas',
  'brasília', 'brasilia', 'df', 'distrito federal', 'brasiliense', 'brasilienses',
  'porto alegre', 'poa', 'rs', 'rio grande do sul', 'gaúcho', 'gaúcha', 'gaúchos', 'gaúchas', 'porto-alegrense',
  'caxias do sul', 'curitiba', 'florianópolis', 'joinville', 'goiânia', 'cuiabá',
  'paraná', 'pr', 'curitibano', 'curitibana', 'curitibanos', 'curitibanas', 'paranaense', 'paranaenses',
  'rio de janeiro', 'rj', 'carioca', 'cariocas', 'fluminense', 'fluminenses',
  'belo horizonte', 'bh', 'minas gerais', 'mg', 'minas', 'mineiro', 'mineira', 'mineiros', 'mineiras', 'belo-horizontino', 'belo-horizontina',
];

if (!existsSync(PASTA)) {
  console.error(`Pasta não encontrada: ${PASTA}. Rode o build antes.`);
  process.exit(2);
}

const paginas = readdirSync(PASTA, { withFileTypes: true })
  .filter((d) => d.isDirectory() && !NAO_CIDADE.has(d.name) && existsSync(join(PASTA, d.name, 'index.html')))
  .map((d) => d.name)
  .sort();

if (paginas.length < 2) {
  console.error('Menos de duas páginas de cidade no build.');
  process.exit(2);
}

const ENTIDADES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
function decodificar(s) {
  return s
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)))
    .replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)))
    .replace(/&([a-z]+);/gi, (m, n) => ENTIDADES[n.toLowerCase()] ?? m);
}

function textoPrincipal(html) {
  const m = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
  let s = m ? m[1] : html;
  s = s.replace(/<(script|style|svg|form|noscript|template)\b[\s\S]*?<\/\1>/gi, ' ');
  s = s.replace(/<nav\b[\s\S]*?<\/nav>/gi, ' ');
  s = s.replace(/<[^>]+>/g, ' ');
  return decodificar(s);
}

const escapar = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const RE_CIDADES = new RegExp(
  `(?<![\\p{L}\\p{N}])(${[...CIDADES].sort((a, b) => b.length - a.length).map(escapar).join('|')})(?![\\p{L}\\p{N}])`,
  'giu',
);

function palavras(texto) {
  return texto
    .toLowerCase()
    .replace(RE_CIDADES, ' lugar ')
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
}

function trechos(p) {
  const set = new Set();
  for (let i = 0; i + N <= p.length; i++) set.add(p.slice(i, i + N).join(' '));
  return set;
}

function jaccard(a, b) {
  let inter = 0;
  for (const x of a) if (b.has(x)) inter++;
  const uniao = a.size + b.size - inter;
  return uniao ? inter / uniao : 0;
}

const dados = paginas.map((slug) => {
  const html = readFileSync(join(PASTA, slug, 'index.html'), 'utf8');
  const p = palavras(textoPrincipal(html));
  return { slug, nPalavras: p.length, trechos: trechos(p) };
});

console.log(`Páginas de cidade em ${PASTA}:`);
for (const d of dados) console.log(`  ${d.slug.padEnd(16)} ${String(d.nPalavras).padStart(5)} palavras no conteúdo principal, ${d.trechos.size} trechos de ${N} palavras`);
console.log(`\nSemelhança (Jaccard de trechos de ${N} palavras, meta < ${String(META).replace('.', ',')}):`);

let acima = 0;
for (let i = 0; i < dados.length; i++) {
  for (let j = i + 1; j < dados.length; j++) {
    const v = jaccard(dados[i].trechos, dados[j].trechos);
    const ok = v < META;
    if (!ok) acima++;
    console.log(`  ${`${dados[i].slug} x ${dados[j].slug}`.padEnd(34)} ${v.toFixed(3).replace('.', ',')}  ${ok ? 'ok' : 'ACIMA DA META'}`);
  }
}
process.exit(acima ? 1 : 0);
