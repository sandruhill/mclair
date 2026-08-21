// Auditoria SEO -- rodada 2. Crawler completo do site publicado
// (https://mclair.com.br), sem tocar em nenhum dado local/DB. Descobre URLs
// via sitemap, busca cada página real (o mesmo HTML que Google/uma IA veria),
// extrai todo o metadata pedido, monta o grafo de links internos e escreve os
// CSVs de auditoria direto em disco -- nada disso passa pela conversa.
import * as cheerio from 'cheerio';
import { writeFileSync, mkdirSync } from 'node:fs';

const ORIGIN = 'https://mclair.com.br';
const CONCURRENCY = 8;
const OUT_DIR = new URL('../audit-out/', import.meta.url).pathname;
mkdirSync(OUT_DIR, { recursive: true });

function csvEscape(v) {
  if (v === null || v === undefined) return '';
  const s = String(v).replace(/\r?\n/g, ' ').trim();
  if (/[",;]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
  return s;
}
function writeCsv(name, header, rows) {
  const lines = [header.join(',')];
  for (const row of rows) lines.push(header.map((h) => csvEscape(row[h])).join(','));
  writeFileSync(OUT_DIR + name, lines.join('\n') + '\n', 'utf-8');
  console.log(`wrote ${name} (${rows.length} rows)`);
}

async function fetchSitemapUrls() {
  const idx = await (await fetch(`${ORIGIN}/sitemap-index.xml`)).text();
  const sitemaps = [...idx.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
  const urls = new Set();
  for (const sm of sitemaps) {
    const xml = await (await fetch(sm)).text();
    for (const m of xml.matchAll(/<loc>([^<]+)<\/loc>/g)) urls.add(m[1]);
  }
  return [...urls];
}

async function fetchWithRedirects(url) {
  const chain = [];
  let current = url;
  for (let i = 0; i < 10; i++) {
    let res;
    try {
      res = await fetch(current, { redirect: 'manual' });
    } catch (e) {
      return { finalUrl: current, status: 0, chain, error: String(e), body: '' };
    }
    if ([301, 302, 307, 308].includes(res.status)) {
      const loc = res.headers.get('location');
      chain.push({ from: current, status: res.status, to: loc });
      current = new URL(loc, current).toString();
      continue;
    }
    const body = await res.text();
    return { finalUrl: current, status: res.status, chain, body, headers: res.headers };
  }
  return { finalUrl: current, status: -1, chain, body: '', error: 'too many redirects' };
}

function wordCount(text) {
  return (text.trim().match(/\S+/g) || []).length;
}

function classifyUrl(pathname) {
  if (pathname === '/') return 'home';
  if (/^\/blog\/\d+\/?$/.test(pathname) || pathname === '/blog/') return 'blog-listing';
  if (/^\/blog\/[^/]+\/?$/.test(pathname)) return 'blog-post';
  if (/^\/servicos\/?$/.test(pathname)) return 'servicos-index';
  if (/^\/servicos\/[^/]+\/?$/.test(pathname)) return 'servico';
  if (/^\/servicos\/[^/]+\/[^/]+\/?$/.test(pathname)) return 'servico-sub';
  if (/^\/cases\/?$/.test(pathname)) return 'cases-index';
  if (/^\/cases\/[^/]+\/?$/.test(pathname)) return 'case';
  if (pathname === '/sobre/') return 'institucional';
  if (pathname === '/contato/') return 'institucional';
  if (pathname === '/clientes/') return 'institucional';
  if (pathname === '/mentorias/') return 'institucional';
  if (pathname === '/marketing-para-leiloeiros/') return 'institucional';
  return 'outro';
}

async function analyzePage(url) {
  const pathname = new URL(url).pathname;
  const type = classifyUrl(url.startsWith('http') ? pathname : url);
  const { finalUrl, status, chain, body, headers } = await fetchWithRedirects(url);
  const row = {
    URL: url,
    Type: type,
    Status: status,
    FinalUrl: finalUrl !== url ? finalUrl : '',
    RedirectChainLength: chain.length,
    RedirectChain: chain.map((c) => `${c.status}->${c.to}`).join(' | '),
  };
  if (status !== 200 || !body) {
    row.Issues = status === 200 ? '' : `status ${status}`;
    return { row, links: [], images: [], schemaTypes: [], canonical: '', h1s: [] };
  }

  const $ = cheerio.load(body);
  const canonical = $('link[rel="canonical"]').attr('href') || '';
  const title = $('title').first().text() || '';
  const metaDesc = $('meta[name="description"]').attr('content') || '';
  const robots = $('meta[name="robots"]').attr('content') || '';
  const h1s = $('h1').map((_, el) => $(el).text().trim()).get();
  const h2s = $('h2').length;
  const ogTitle = $('meta[property="og:title"]').attr('content') || '';
  const ogDesc = $('meta[property="og:description"]').attr('content') || '';
  const ogUrl = $('meta[property="og:url"]').attr('content') || '';
  const ogType = $('meta[property="og:type"]').attr('content') || '';
  const ogImage = $('meta[property="og:image"]').attr('content') || '';
  const twCard = $('meta[name="twitter:card"]').attr('content') || '';

  const schemaBlocks = [];
  $('script[type="application/ld+json"]').each((_, el) => {
    const raw = $(el).contents().text();
    try {
      const parsed = JSON.parse(raw);
      schemaBlocks.push(parsed);
    } catch (e) {
      schemaBlocks.push({ __parseError: String(e), __raw: raw.slice(0, 200) });
    }
  });
  const schemaTypes = [];
  const schemaErrors = [];
  for (const block of schemaBlocks) {
    if (block.__parseError) {
      schemaErrors.push(block.__parseError);
      continue;
    }
    const t = block['@type'];
    if (Array.isArray(t)) schemaTypes.push(...t);
    else if (t) schemaTypes.push(t);
  }

  // Links: internal (same origin, normalized) vs external.
  const internalLinks = [];
  const externalLinks = [];
  $('a[href]').each((_, el) => {
    const href = $(el).attr('href');
    const text = $(el).text().trim().slice(0, 60);
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;
    let abs;
    try {
      abs = new URL(href, url).toString();
    } catch {
      return;
    }
    if (abs.startsWith(ORIGIN)) internalLinks.push({ href: abs, text });
    else externalLinks.push({ href: abs, text });
  });

  // Images
  const images = [];
  $('img').each((_, el) => {
    const src = $(el).attr('src') || $(el).attr('data-src') || '';
    const alt = $(el).attr('alt');
    const width = $(el).attr('width');
    const height = $(el).attr('height');
    const loading = $(el).attr('loading');
    images.push({ src, alt, width, height, loading, page: url });
  });

  // Visible text word count: strip script/style/nav/footer/header chrome, keep <main>/article-ish body.
  const clone = $.root().clone();
  clone.find('script,style,nav,footer,header').remove();
  const text = clone.text().replace(/\s+/g, ' ').trim();
  const wc = wordCount(text);

  // Blog-post specific meta
  const articleAuthor = $('meta[property="article:author"]').attr('content') || $('meta[name="author"]').attr('content') || '';
  const articlePublished = $('meta[property="article:published_time"]').attr('content') || '';
  const articleModified = $('meta[property="article:modified_time"]').attr('content') || '';
  const articleSection = $('meta[property="article:section"]').attr('content') || '';

  const issues = [];
  if (!title) issues.push('sem title');
  if (title.length > 65) issues.push(`title longo (${title.length})`);
  if (title.length > 0 && title.length < 15) issues.push(`title curto (${title.length})`);
  if (!metaDesc) issues.push('sem meta description');
  if (metaDesc.length > 165) issues.push(`description longa (${metaDesc.length})`);
  if (metaDesc.length > 0 && metaDesc.length < 50) issues.push(`description curta (${metaDesc.length})`);
  if (h1s.length === 0) issues.push('sem H1');
  if (h1s.length > 1) issues.push(`${h1s.length} H1s`);
  if (!canonical) issues.push('sem canonical');
  if (canonical && !canonical.startsWith('https://mclair.com.br')) issues.push('canonical host errado');
  if (/noindex/i.test(robots)) issues.push('NOINDEX');
  if (!ogImage) issues.push('sem og:image');
  if (ogImage && !ogImage.startsWith('http')) issues.push('og:image relativo');
  if (schemaErrors.length) issues.push('schema JSON inválido');
  const missingAlt = images.filter((i) => i.alt === undefined).length;

  Object.assign(row, {
    Canonical: canonical,
    CanonicalMatchesUrl: canonical === finalUrl || canonical === finalUrl.replace(/\/$/, ''),
    Title: title,
    TitleLength: title.length,
    MetaDescription: metaDesc,
    MetaLength: metaDesc.length,
    H1: h1s[0] || '',
    H1Count: h1s.length,
    H2Count: h2s,
    Robots: robots,
    OgTitle: ogTitle,
    OgDescription: ogDesc,
    OgUrl: ogUrl,
    OgType: ogType,
    OgImage: ogImage,
    TwitterCard: twCard,
    SchemaTypes: [...new Set(schemaTypes)].join('|'),
    SchemaErrors: schemaErrors.join(' | '),
    InternalLinks: internalLinks.length,
    ExternalLinks: externalLinks.length,
    Images: images.length,
    ImagesMissingAlt: missingAlt,
    WordCount: wc,
    ArticleAuthor: articleAuthor,
    ArticlePublished: articlePublished,
    ArticleModified: articleModified,
    ArticleSection: articleSection,
    Issues: issues.join(' | '),
  });

  return { row, links: internalLinks, images, externalLinks, schemaTypes, canonical, h1s, wc, type };
}

async function pool(items, worker, concurrency) {
  const results = new Array(items.length);
  let i = 0;
  async function run() {
    while (i < items.length) {
      const idx = i++;
      results[idx] = await worker(items[idx], idx);
    }
  }
  await Promise.all(new Array(concurrency).fill(0).map(run));
  return results;
}

const urls = await fetchSitemapUrls();
console.log(`sitemap: ${urls.length} URLs`);

const results = await pool(urls, async (url, idx) => {
  if (idx % 25 === 0) console.log(`  crawling ${idx}/${urls.length}...`);
  return analyzePage(url);
}, CONCURRENCY);

writeFileSync(OUT_DIR + 'crawl-raw.json', JSON.stringify(results.map((r) => ({
  url: r.row.URL, links: r.links, images: r.images, externalLinks: r.externalLinks,
  type: r.type, row: r.row,
})), null, 0), 'utf-8');

console.log('crawl done, raw data at audit-out/crawl-raw.json');
