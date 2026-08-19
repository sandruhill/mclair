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
        'https://olive-gnat-658393.hostingersite.com/sobre',
        'https://olive-gnat-658393.hostingersite.com/servicos',
        'https://olive-gnat-658393.hostingersite.com/servicos/marketing-de-autoridade',
        'https://olive-gnat-658393.hostingersite.com/servicos/assessoria-de-imprensa',
        'https://olive-gnat-658393.hostingersite.com/servicos/branding-estrategico',
        'https://olive-gnat-658393.hostingersite.com/servicos/marketing-digital',
        'https://olive-gnat-658393.hostingersite.com/servicos/consultoria-em-comunicacao',
        'https://olive-gnat-658393.hostingersite.com/servicos/mentorias-exclusivas',
        'https://olive-gnat-658393.hostingersite.com/mentorias',
        'https://olive-gnat-658393.hostingersite.com/cases',
        'https://olive-gnat-658393.hostingersite.com/cases/claudia-elisa',
        'https://olive-gnat-658393.hostingersite.com/cases/fidalgo',
        'https://olive-gnat-658393.hostingersite.com/cases/alexandre-magno',
        'https://olive-gnat-658393.hostingersite.com/cases/elemar-jr',
        'https://olive-gnat-658393.hostingersite.com/cases/bms-abimaq',
        'https://olive-gnat-658393.hostingersite.com/cases/diego-nogare',
        'https://olive-gnat-658393.hostingersite.com/cases/insight-rh',
        'https://olive-gnat-658393.hostingersite.com/cases/lambda3',
        'https://olive-gnat-658393.hostingersite.com/cases/nexinvoice',
        'https://olive-gnat-658393.hostingersite.com/cases/silene-chiconini',
        'https://olive-gnat-658393.hostingersite.com/cases/zbra',
        'https://olive-gnat-658393.hostingersite.com/cases/globo-leiloes',
        'https://olive-gnat-658393.hostingersite.com/cases/vip-leiloes',
        'https://olive-gnat-658393.hostingersite.com/clientes',
        'https://olive-gnat-658393.hostingersite.com/contato',
        'https://olive-gnat-658393.hostingersite.com/marketing-para-leiloeiros',
      ],
    }),
  ],
});
