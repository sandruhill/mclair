// Segunda passada: le audit-out/crawl-raw.json (ja em disco, gerado pelo
// audit-crawler.mjs) e produz todos os CSVs/relatorios pedidos. Nao refaz
// nenhuma requisicao de rede.
import { readFileSync, writeFileSync } from 'node:fs';

const OUT_DIR = new URL('../audit-out/', import.meta.url).pathname;
const raw = JSON.parse(readFileSync(OUT_DIR + 'crawl-raw.json', 'utf-8'));
const ORIGIN = 'https://mclair.com.br';

function csvEscape(v) {
  if (v === null || v === undefined) return '';
  const s = String(v).replace(/[\r\n]+/g, ' ').trim();
  // Always quote (not just when a comma/quote is spotted) -- URLs in this
  // dataset can carry stray unescaped quote/comma characters from decades-old
  // blog HTML, and a conditional heuristic missed some of them.
  return '"' + s.replace(/"/g, '""') + '"';
}
function writeCsv(name, header, rows) {
  const lines = [header.join(',')];
  for (const row of rows) lines.push(header.map((h) => csvEscape(row[h])).join(','));
  writeFileSync(OUT_DIR + name, lines.join('\n') + '\n', 'utf-8');
  console.log(`wrote ${name} (${rows.length} rows)`);
}

const byUrl = new Map(raw.map((r) => [r.url, r]));

// ---------- 1. AUDITORIA-CRAWLER.csv ----------
const crawlerHeader = ['URL', 'Type', 'Status', 'RedirectChainLength', 'RedirectChain', 'Canonical',
  'Title', 'TitleLength', 'MetaDescription', 'MetaLength', 'H1', 'H1Count', 'Robots', 'SchemaTypes',
  'InternalLinks', 'ExternalLinks', 'Images', 'ImagesMissingAlt', 'WordCount', 'Issues'];
writeCsv('AUDITORIA-CRAWLER.csv', crawlerHeader, raw.map((r) => r.row));

// ---------- 2. Internal linking graph ----------
const inlinks = new Map(); // url -> Set of source urls
const anchors = new Map(); // url -> [anchor texts]
for (const r of raw) {
  for (const link of r.links) {
    const target = link.href.split('#')[0].replace(/\?.*$/, '');
    if (!inlinks.has(target)) inlinks.set(target, new Set());
    inlinks.get(target).add(r.url);
    if (!anchors.has(target)) anchors.set(target, []);
    anchors.get(target).push(link.text);
  }
}

// BFS depth from home
const HOME = ORIGIN + '/';
const adjacency = new Map();
for (const r of raw) {
  adjacency.set(r.url, [...new Set(r.links.map((l) => l.href.split('#')[0].replace(/\?.*$/, '')))]);
}
const depth = new Map([[HOME, 0]]);
let frontier = [HOME];
while (frontier.length) {
  const next = [];
  for (const u of frontier) {
    for (const v of adjacency.get(u) || []) {
      if (!depth.has(v) && byUrl.has(v)) {
        depth.set(v, depth.get(u) + 1);
        next.push(v);
      }
    }
  }
  frontier = next;
}

const internalLinkingRows = raw.map((r) => {
  const url = r.url;
  const ins = inlinks.get(url) || new Set();
  const outs = adjacency.get(url) || [];
  const anchorTexts = anchors.get(url) || [];
  const topAnchors = [...new Set(anchorTexts)].slice(0, 5).join(' | ');
  const orphan = ins.size === 0 && url !== HOME;
  const issues = [];
  if (orphan) issues.push('orphan (0 inlinks internos rastreados)');
  if (!depth.has(url)) issues.push('inalcancavel a partir da home por links internos');
  else if (depth.get(url) >= 4) issues.push(`profundidade ${depth.get(url)} (4+ cliques)`);
  if (ins.size > 0 && ins.size <= 1 && r.type !== 'blog-post') issues.push('poucos links recebidos (<=1)');
  const genericAnchors = anchorTexts.filter((a) => /^(clique aqui|leia mais|saiba mais|aqui|veja mais)$/i.test(a.trim()));
  if (genericAnchors.length > 3) issues.push(`${genericAnchors.length} anchors genéricos`);
  return {
    URL: url,
    Inlinks: ins.size,
    Outlinks: outs.length,
    Depth: depth.has(url) ? depth.get(url) : '',
    Orphan: orphan ? 'sim' : 'nao',
    TopAnchors: topAnchors,
    Issues: issues.join(' | '),
  };
});
writeCsv('AUDITORIA-INTERNAL-LINKING.csv', ['URL', 'Inlinks', 'Outlinks', 'Depth', 'Orphan', 'TopAnchors', 'Issues'], internalLinkingRows);

// ---------- 3. Broken links (internal 404s found via crawl + external HEAD check) ----------
const brokenRows = [];
for (const r of raw) {
  if (r.row.Status !== 200) {
    // this URL itself is broken -- report who links to it
    const linkers = inlinks.get(r.url) || new Set();
    for (const from of linkers) {
      brokenRows.push({ SourceURL: from, TargetURL: r.url, Status: r.row.Status, Type: 'interno' });
    }
    if (linkers.size === 0) brokenRows.push({ SourceURL: '(sitemap)', TargetURL: r.url, Status: r.row.Status, Type: 'interno' });
  }
}
// external links: dedup and HEAD-check
const allExternal = new Map();
for (const r of raw) {
  for (const l of r.externalLinks || []) {
    if (!allExternal.has(l.href)) allExternal.set(l.href, new Set());
    allExternal.get(l.href).add(r.url);
  }
}
console.log(`checking ${allExternal.size} unique external links...`);
// A real browser UA + a GET (not HEAD) up front -- a lot of sites 403/999 a
// bare HEAD from an unknown client (bot-blocking), which reads as "broken"
// but isn't. This cuts most of those false positives.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
async function checkExternal(href) {
  for (const method of ['GET']) {
    try {
      const res = await fetch(href, {
        method, redirect: 'follow', signal: AbortSignal.timeout(12000),
        headers: { 'User-Agent': UA, 'Accept': 'text/html,*/*' },
      });
      if (res.status >= 400) return String(res.status);
      return null;
    } catch (e) {
      return 'erro: ' + String(e?.cause?.code || e).slice(0, 80);
    }
  }
}
const extEntries = [...allExternal.entries()];
let idx = 0;
async function extPool() {
  while (idx < extEntries.length) {
    const i = idx++;
    const [href, sources] = extEntries[i];
    const problem = await checkExternal(href);
    if (problem !== null) {
      const likelyBotBlock = ['403', '999', '429'].includes(problem);
      for (const from of sources) {
        brokenRows.push({
          SourceURL: from, TargetURL: href, Status: problem, Type: 'externo',
          Note: likelyBotBlock ? 'possível bot-block (site recusa requisição automatizada, não necessariamente quebrado)' : '',
        });
      }
    }
  }
}
await Promise.all(new Array(6).fill(0).map(extPool));
writeCsv('LINKS-QUEBRADOS.csv', ['SourceURL', 'TargetURL', 'Status', 'Type', 'Note'], brokenRows);

// ---------- 4. Images ----------
const imageRows = [];
for (const r of raw) {
  for (const img of r.images || []) {
    const issues = [];
    if (!img.src) issues.push('sem src');
    if (img.alt === undefined) issues.push('alt ausente');
    else if (img.alt === '') issues.push('alt vazio (ok se decorativa)');
    else if (/^(image|imagem|img|photo|foto)\d*$/i.test(img.alt.trim())) issues.push('alt genérico');
    if (!img.width || !img.height) issues.push('sem width/height (risco de CLS)');
    if (!img.loading) issues.push('sem atributo loading');
    imageRows.push({
      Page: img.page, Src: img.src, Alt: img.alt ?? '', Width: img.width || '', Height: img.height || '',
      Loading: img.loading || '', Issues: issues.join(' | '),
    });
  }
}
writeCsv('AUDITORIA-IMAGENS.csv', ['Page', 'Src', 'Alt', 'Width', 'Height', 'Loading', 'Issues'], imageRows);

// ---------- 5. Schema ----------
const schemaRows = raw.map((r) => ({
  URL: r.url,
  SchemaTypes: r.row.SchemaTypes,
  Erros: r.row.SchemaErrors || '',
  Warnings: '',
}));
writeCsv('AUDITORIA-SCHEMA.csv', ['URL', 'SchemaTypes', 'Erros', 'Warnings'], schemaRows);

// ---------- 6. SEO-TITLES.csv ----------
const titleRows = raw.filter((r) => r.row.Status === 200).map((r) => {
  const t = r.row.Title;
  const problems = [];
  if (!t) problems.push('ausente');
  if (t.length > 65) problems.push('muito longo');
  if (t.length > 0 && t.length < 15) problems.push('muito curto');
  return { URL: r.url, TituloAtual: t, TitleLength: t.length, Problema: problems.join(', ') || 'OK' };
});
// duplicate detection
const titleCounts = new Map();
for (const row of titleRows) titleCounts.set(row.TituloAtual, (titleCounts.get(row.TituloAtual) || 0) + 1);
for (const row of titleRows) {
  if (titleCounts.get(row.TituloAtual) > 1 && row.TituloAtual) {
    row.Problema = row.Problema === 'OK' ? 'duplicado' : row.Problema + ', duplicado';
  }
}
writeCsv('SEO-TITLES.csv', ['URL', 'TituloAtual', 'TitleLength', 'Problema'], titleRows);

// ---------- 7. MAPA-CONTEUDO-BLOG.csv ----------
const blogPosts = raw.filter((r) => r.type === 'blog-post' && r.row.Status === 200);
function likelyType(row) {
  const author = (row.ArticleAuthor || '').toLowerCase();
  const section = (row.ArticleSection || '').toLowerCase();
  if (/release|assessoria|comunicado/.test(section)) return 'Release de cliente';
  if (row.WordCount < 150) return 'Indeterminado';
  return 'Editorial Mclair';
}
const serviceKeywords = {
  'Marketing de Autoridade': /autoridade|posicionamento|marca pessoal|thought leadership/i,
  'Assessoria de Imprensa': /imprensa|assessoria|release|mídia espontânea|jornalista/i,
  'Branding Estratégico': /branding|marca|identidade visual|reposicionamento/i,
  'Marketing Digital': /digital|redes sociais|instagram|seo|tráfego|ads/i,
  'Consultoria em Comunicação': /consultoria|estratégia de comunicação/i,
  'Mentorias': /mentoria|bolder/i,
};
function serviceRelevance(text) {
  const hits = [];
  for (const [svc, re] of Object.entries(serviceKeywords)) if (re.test(text)) hits.push(svc);
  return hits.join('|') || 'nenhum claro';
}
const blogRows = blogPosts.map((r) => ({
  URL: r.url,
  Title: r.row.Title,
  Date: r.row.ArticlePublished,
  Author: r.row.ArticleAuthor,
  Category: r.row.ArticleSection,
  LikelyType: likelyType(r.row),
  WordCount: r.row.WordCount,
  InternalLinks: r.row.InternalLinks,
  ServiceRelevance: serviceRelevance(r.row.Title + ' ' + r.row.ArticleSection),
  PossibleDuplicate: '',
  Notes: r.row.Issues,
}));
// simple duplicate flag: same title normalized, or very similar
const norm = (s) => (s || '').toLowerCase().replace(/[^a-z0-9 ]/g, '').trim();
const byNormTitle = new Map();
for (const row of blogRows) {
  const key = norm(row.Title);
  if (!byNormTitle.has(key)) byNormTitle.set(key, []);
  byNormTitle.get(key).push(row);
}
for (const [key, group] of byNormTitle) {
  if (group.length > 1) for (const row of group) row.PossibleDuplicate = 'sim (mesmo título de outra URL)';
}
writeCsv('MAPA-CONTEUDO-BLOG.csv', ['URL', 'Title', 'Date', 'Author', 'Category', 'LikelyType', 'WordCount', 'InternalLinks', 'ServiceRelevance', 'PossibleDuplicate', 'Notes'], blogRows);

// ---------- 8. CANIBALIZACAO-SEO.csv ----------
// crude token-overlap similarity on Title, grouped by dominant service keyword, non-blog + blog service-relevant pages only
function tokenize(s) {
  return new Set((s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter((w) => w.length > 3));
}
function jaccard(a, b) {
  const inter = [...a].filter((x) => b.has(x)).length;
  const union = new Set([...a, ...b]).size;
  return union ? inter / union : 0;
}
const candidates = raw.filter((r) => r.row.Status === 200 && ['servico', 'servico-sub', 'case', 'institucional', 'blog-post'].includes(r.type));
const canibRows = [];
for (let i = 0; i < candidates.length; i++) {
  for (let j = i + 1; j < candidates.length; j++) {
    const a = candidates[i], b = candidates[j];
    const sim = jaccard(tokenize(a.row.Title), tokenize(b.row.Title));
    if (sim >= 0.5 && a.row.Title && b.row.Title) {
      canibRows.push({
        Topic: a.row.Title.length < b.row.Title.length ? a.row.Title : b.row.Title,
        URLA: a.url, URLB: b.url, Similarity: sim.toFixed(2),
        Intent: a.type === b.type ? 'mesma tipologia' : `${a.type} vs ${b.type}`,
        Recommendation: sim > 0.75 ? 'revisar títulos, possível sobreposição' : 'monitorar',
      });
    }
  }
}
canibRows.sort((a, b) => b.Similarity - a.Similarity);
writeCsv('CANIBALIZACAO-SEO.csv', ['Topic', 'URLA', 'URLB', 'Similarity', 'Intent', 'Recommendation'], canibRows);

// ---------- Summary stats for the main report (small, safe to print) ----------
const summary = {
  totalUrls: raw.length,
  statusCounts: {},
  noH1: 0, multiH1: 0, noCanonical: 0, noTitle: 0, noMetaDesc: 0, titleTooLong: 0, titleTooShort: 0,
  metaTooLong: 0, metaTooShort: 0, noindex: 0, missingAlt: 0, orphans: 0, deepPages: 0,
  duplicateTitles: 0, brokenInternal: 0, brokenExternal: 0, schemaErrors: 0, noOgImage: 0,
};
for (const r of raw) {
  summary.statusCounts[r.row.Status] = (summary.statusCounts[r.row.Status] || 0) + 1;
  if (r.row.Status !== 200) continue;
  if (r.row.H1Count === 0) summary.noH1++;
  if (r.row.H1Count > 1) summary.multiH1++;
  if (!r.row.Canonical) summary.noCanonical++;
  if (!r.row.Title) summary.noTitle++;
  if (!r.row.MetaDescription) summary.noMetaDesc++;
  if (r.row.TitleLength > 65) summary.titleTooLong++;
  if (r.row.TitleLength > 0 && r.row.TitleLength < 15) summary.titleTooShort++;
  if (r.row.MetaLength > 165) summary.metaTooLong++;
  if (r.row.MetaLength > 0 && r.row.MetaLength < 50) summary.metaTooShort++;
  if (/noindex/i.test(r.row.Robots)) summary.noindex++;
  if (r.row.ImagesMissingAlt > 0) summary.missingAlt += r.row.ImagesMissingAlt;
  if (r.row.SchemaErrors) summary.schemaErrors++;
  if (!r.row.OgImage) summary.noOgImage++;
}
summary.orphans = internalLinkingRows.filter((r) => r.Orphan === 'sim').length;
summary.deepPages = internalLinkingRows.filter((r) => typeof r.Depth === 'number' && r.Depth >= 4).length;
summary.duplicateTitles = [...titleCounts.values()].filter((c) => c > 1).length;
summary.brokenInternal = brokenRows.filter((r) => r.Type === 'interno').length;
summary.brokenExternal = brokenRows.filter((r) => r.Type === 'externo').length;

writeFileSync(OUT_DIR + 'summary.json', JSON.stringify(summary, null, 2), 'utf-8');
console.log(JSON.stringify(summary, null, 2));
