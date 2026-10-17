// @ts-check
import fs from 'node:fs';
import { FORA_DO_GOOGLE } from './src/data/fora-do-google.ts';
import { POSTS_COM_CANONICA_FORA } from './src/data/canonicos-posts.ts';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Real per-post dates for sitemap lastmod, instead of a single build-time
// timestamp for every URL. CMS entries (YYYY-MM-DD frontmatter) win over
// legacy JSON (DD/MM/YYYY) on slug collisions, matching the blog's own
// merge precedence in src/pages/blog/[...page].astro.
function buildBlogDateMap() {
  const map = new Map();

  try {
    const legacy = JSON.parse(fs.readFileSync(path.join(__dirname, 'src/data/blog.json'), 'utf-8'));
    for (const post of legacy) {
      if (!post.slug || !post.date) continue;
      const [dd, mm, yy] = post.date.split('/');
      if (dd && mm && yy) {
        const d = new Date(`${yy}-${mm}-${dd}`);
        if (!isNaN(d.getTime())) map.set(post.slug, d);
      }
    }
  } catch {
    // legacy blog.json missing or unreadable — skip
  }

  try {
    const blogDir = path.join(__dirname, 'src/content/blog');
    for (const file of fs.readdirSync(blogDir)) {
      if (!file.endsWith('.md')) continue;
      const slug = file.replace(/\.md$/, '');
      const raw = fs.readFileSync(path.join(blogDir, file), 'utf-8');
      const match = raw.match(/^date:\s*(\d{4}-\d{2}-\d{2})/m);
      if (match) {
        const d = new Date(match[1]);
        if (!isNaN(d.getTime())) map.set(slug, d);
      }
    }
  } catch {
    // CMS content dir missing or unreadable — skip
  }

  return map;
}

const blogDates = buildBlogDateMap();

// Services/cases used to be a hand-maintained list here, so a new case or
// service created in the CMS silently stayed out of the sitemap until
// someone remembered to add it by hand. Pull the real slugs from the same
// export API the pages themselves build from. This runs in plain Node at
// config-eval time (not Vite), so the token comes from process.env -- set
// by rebuild.sh sourcing secrets.env before `npm run build`, same as in
// production. Falls back to the last known-good static list if the fetch
// fails (offline dev, token not set locally, API hiccup) so a bad fetch
// here can never take the whole build down.
const FALLBACK_CUSTOM_PAGES = [
  'https://mclair.com.br/sobre/',
  'https://mclair.com.br/kelly-pinheiro/',
  'https://mclair.com.br/servicos/',
  'https://mclair.com.br/servicos/marketing-de-autoridade/',
  'https://mclair.com.br/servicos/assessoria-de-imprensa/',
  'https://mclair.com.br/servicos/branding-estrategico/',
  'https://mclair.com.br/servicos/marketing-digital/',
  'https://mclair.com.br/servicos/consultoria-em-comunicacao/',
  'https://mclair.com.br/servicos/mentorias-exclusivas/',
  'https://mclair.com.br/mentorias/',
  'https://mclair.com.br/cases/',
  'https://mclair.com.br/cases/claudia-elisa/',
  'https://mclair.com.br/cases/fidalgo/',
  'https://mclair.com.br/cases/alexandre-magno/',
  'https://mclair.com.br/cases/elemar-jr/',
  'https://mclair.com.br/cases/bms-abimaq/',
  'https://mclair.com.br/cases/diego-nogare/',
  'https://mclair.com.br/cases/insight-rh/',
  'https://mclair.com.br/cases/lambda3/',
  'https://mclair.com.br/cases/nexinvoice/',
  'https://mclair.com.br/cases/silene-chiconini/',
  'https://mclair.com.br/cases/zbra/',
  'https://mclair.com.br/cases/globo-leiloes/',
  'https://mclair.com.br/cases/vip-leiloes/',
  'https://mclair.com.br/clientes/',
  'https://mclair.com.br/contato/',
  'https://mclair.com.br/marketing-para-leiloeiros/',
];

const EXPORT_URL = process.env.CMS_EXPORT_URL || 'https://mclair.com.br/acesso/api-export.php';
const fetchExport = (type) =>
  fetch(`${EXPORT_URL}?type=${type}`, { headers: { 'X-Export-Token': process.env.CMS_EXPORT_TOKEN } })
    .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`${type}: HTTP ${r.status}`))));

// lastmod das páginas fixas e das páginas do CMS (fora os posts, que usam a
// data do post). Página fixa: data do último commit do próprio arquivo em
// src/pages. Case e serviço do CMS: coluna updated_at, se a API exportar.
// Sem git (build fora do repositório) ou sem a coluna, a URL sai sem lastmod,
// que é melhor que a data do build.
const pageDates = new Map();

function gitDate(file) {
  try {
    const out = execFileSync('git', ['log', '-1', '--format=%cI', '--', file], {
      cwd: __dirname,
      stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    const d = out ? new Date(out) : null;
    return d && !isNaN(d.getTime()) ? d : null;
  } catch {
    return null;
  }
}

const maisRecente = (...datas) => datas.filter(Boolean).sort((a, b) => b - a)[0] ?? null;

// Arquivos .astro sem colchete no nome viram URL direta (index.astro = a pasta).
function addStaticPageDates() {
  const raiz = path.join(__dirname, 'src/pages');
  const andar = (dir) => {
    let entradas = [];
    try { entradas = fs.readdirSync(dir, { withFileTypes: true }); } catch { return; }
    for (const e of entradas) {
      const abs = path.join(dir, e.name);
      if (e.isDirectory()) { if (!e.name.includes('[')) andar(abs); continue; }
      if (!e.name.endsWith('.astro') || e.name.includes('[') || e.name === '404.astro') continue;
      const rel = path.relative(raiz, abs).replace(/\\/g, '/').replace(/\.astro$/, '').replace(/(^|\/)index$/, '');
      const d = gitDate(path.relative(__dirname, abs));
      if (d) pageDates.set(`/${rel ? rel + '/' : ''}`, d);
    }
  };
  andar(raiz);
}
addStaticPageDates();

function cmsDate(row) {
  const v = row?.updated_at;
  if (!v) return null;
  const d = new Date(String(v).replace(' ', 'T'));
  return isNaN(d.getTime()) ? null : d;
}

async function buildCustomPages() {
  const token = process.env.CMS_EXPORT_TOKEN;
  if (!token) return FALLBACK_CUSTOM_PAGES;
  try {
    const fetchType = fetchExport;
    const [services, cases] = await Promise.all([fetchType('services'), fetchType('cases')]);
    // O hub de assessoria é serviço do CMS, mas quase todo o texto mora em
    // código; vale a data mais nova entre o registro e esses dois arquivos.
    const hubAssessoria = maisRecente(
      gitDate('src/components/servicos/AssessoriaImprensa.astro'),
      gitDate('src/data/assessoria-imprensa.ts'),
    );
    // Services can be nested (child.parent_slug -> parent), same ancestry walk
    // src/pages/servicos/[...slug].astro uses to build each real URL path.
    const bySlug = new Map(services.map((s) => [s.slug, s]));
    const servicePath = (slug) => {
      const chain = [];
      let cur = bySlug.get(slug);
      const seen = new Set();
      while (cur && !seen.has(cur.slug)) {
        seen.add(cur.slug);
        chain.unshift(cur.slug);
        cur = cur.parent_slug ? bySlug.get(cur.parent_slug) : undefined;
      }
      return chain.join('/');
    };
    for (const sv of services) {
      const p = `/servicos/${servicePath(sv.slug)}/`;
      const d = sv.slug === 'assessoria-de-imprensa' ? maisRecente(cmsDate(sv), hubAssessoria) : cmsDate(sv);
      if (d) pageDates.set(p, d);
    }
    for (const c of cases) {
      const d = cmsDate(c);
      if (d) pageDates.set(`/cases/${c.slug}/`, d);
    }
    const urls = [
      'https://mclair.com.br/sobre/',
      'https://mclair.com.br/kelly-pinheiro/',
      'https://mclair.com.br/servicos/',
      ...services.map((s) => `https://mclair.com.br/servicos/${servicePath(s.slug)}/`),
      'https://mclair.com.br/mentorias/',
      'https://mclair.com.br/cases/',
      ...cases.map((c) => `https://mclair.com.br/cases/${c.slug}/`),
      'https://mclair.com.br/clientes/',
      'https://mclair.com.br/contato/',
      'https://mclair.com.br/marketing-para-leiloeiros/',
    ];
    return urls.length > 10 ? urls : FALLBACK_CUSTOM_PAGES; // sanity floor
  } catch {
    return FALLBACK_CUSTOM_PAGES;
  }
}
const customPages = await buildCustomPages();

// Os posts vêm do CMS (MySQL), não de src/data/blog.json nem de
// src/content/blog, que não existem mais. Sem isto o mapa de datas ficava
// vazio e o sitemap saía com o horário do build em todas as 404 URLs, o
// que ensina o Google a ignorar o lastmod. Agora vale a data do post.
async function addCmsBlogDates(map) {
  if (!process.env.CMS_EXPORT_TOKEN) return;
  try {
    const posts = await fetchExport('blog_posts');
    for (const p of posts) {
      if (!p?.slug || !p?.post_date) continue;
      const d = new Date(String(p.post_date).slice(0, 10));
      if (!isNaN(d.getTime())) map.set(p.slug, d);
    }
  } catch {
    // API fora do ar: fica sem lastmod nos posts, não derruba o build
  }
}
await addCmsBlogDates(blogDates);

export default defineConfig({
  site: 'https://mclair.com.br',
  output: 'static',
  // Every canonical URL on the site is trailing-slash form and the server
  // 301s the bare form to it -- without this, Astro's own paginate() helper
  // (page.url.prev/next) generates URLs without the slash, so blog
  // pagination links were paying that redirect on every click.
  trailingSlash: 'always',
  integrations: [
    sitemap({
      // Sem lastmod global: data do build em toda URL não é data de mudança.
      // Posts levam a data do post, vinda do CMS.
      // Páginas fixas e do CMS levam a data da última mudança (pageDates).
      serialize(item) {
        const match = item.url.match(/\/blog\/([^/]+)\/?$/);
        if (match) {
          const date = blogDates.get(match[1]);
          if (date) item.lastmod = date.toISOString();
        } else {
          const date = pageDates.get(new URL(item.url).pathname);
          if (date) item.lastmod = date.toISOString();
        }
        return item;
      },
      customPages,
      // Fora: 404, posts tirados do Google e posts repetidos com canônica em
      // outro post (src/data/canonicos-posts.ts).
      filter: (page) =>
        !/\/404\/?$/.test(page) &&
        !FORA_DO_GOOGLE.some((p) => page.endsWith(p)) &&
        !POSTS_COM_CANONICA_FORA.some((p) => page.endsWith(p)),
    }),
  ],
});
