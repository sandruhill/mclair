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
