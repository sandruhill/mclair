// @ts-check
import fs from 'node:fs';
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

async function buildCustomPages() {
  const token = process.env.CMS_EXPORT_TOKEN;
  if (!token) return FALLBACK_CUSTOM_PAGES;
  try {
    const fetchType = (type) =>
      fetch(`https://mclair.com.br/acesso/api-export.php?type=${type}`, { headers: { 'X-Export-Token': token } })
        .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`${type}: HTTP ${r.status}`))));
    const [services, cases] = await Promise.all([fetchType('services'), fetchType('cases')]);
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
    const urls = [
      'https://mclair.com.br/sobre/',
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

export default defineConfig({
  site: 'https://mclair.com.br',
  output: 'static',
  integrations: [
    sitemap({
      changefreq: 'weekly',
      priority: 0.8,
      lastmod: new Date(),
      serialize(item) {
        const match = item.url.match(/\/blog\/([^/]+)\/?$/);
        if (match) {
          const date = blogDates.get(match[1]);
          if (date) item.lastmod = date.toISOString();
        }
        return item;
      },
      customPages: [
        'https://mclair.com.br/sobre/',
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
      ],
    }),
  ],
});
