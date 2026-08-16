// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import keystatic from '@keystatic/astro';
import vercel from '@astrojs/vercel';
import react from '@astrojs/react';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Real per-post lastmod. The migrated `date` frontmatter field is useless for this —
// all 265 posts share the same value (2025-04-08, the migration/scrape date), so using
// it verbatim would still give every URL an identical lastmod, which reads as fake to
// crawlers. Instead, prefer the date embedded in each post's gallery image filename
// (e.g. `26-04-2023_6_...jpg`), a leftover from the original CMS that reflects the real
// per-post date and varies post to post. Falls back to the frontmatter `date` only when
// no dated image is present.
function buildBlogLastmodMap() {
  const map = new Map();
  const blogDir = path.join(__dirname, 'src/content/blog');

  for (const file of fs.readdirSync(blogDir)) {
    if (!file.endsWith('.mdoc')) continue;
    const slug = file.replace(/\.mdoc$/, '');
    const raw = fs.readFileSync(path.join(blogDir, file), 'utf-8');

    const imageMatch = raw.match(/^image:.*?(\d{2})-(\d{2})-(\d{4})/m);
    const dateMatch = raw.match(/^date:\s*(.+)$/m);

    let iso;
    if (imageMatch) {
      const [, d, m, y] = imageMatch;
      iso = new Date(`${y}-${m}-${d}`).toISOString();
    } else if (dateMatch) {
      iso = new Date(dateMatch[1].trim()).toISOString();
    }
    if (iso && !Number.isNaN(Date.parse(iso))) map.set(slug, iso);
  }

  // Legacy posts (src/data/blog.json) — only used for slugs the CMS hasn't taken over yet.
  try {
    const legacy = JSON.parse(fs.readFileSync(path.join(__dirname, 'src/data/blog.json'), 'utf-8'));
    for (const post of legacy) {
      if (map.has(post.slug)) continue;
      const imageMatch = String(post.image ?? '').match(/(\d{2})-(\d{2})-(\d{4})/);
      let iso;
      if (imageMatch) {
        const [, d, m, y] = imageMatch;
        iso = new Date(`${y}-${m}-${d}`).toISOString();
      } else if (post.date) {
        const [d, m, y] = String(post.date).split('/');
        iso = new Date(`${y}-${m}-${d}`).toISOString();
      }
      if (iso && !Number.isNaN(Date.parse(iso))) map.set(post.slug, iso);
    }
  } catch { /* no legacy file */ }

  return map;
}

const blogLastmod = buildBlogLastmodMap();
const buildDate = new Date();

export default defineConfig({
  site: 'https://mclair.vercel.app',
  output: 'server',
  adapter: vercel(),
  integrations: [
    sitemap({
      changefreq: 'weekly',
      priority: 0.8,
      lastmod: buildDate,
      serialize(item) {
        const slugMatch = item.url.match(/\/blog\/([^/]+)\/?$/);
        const slug = slugMatch?.[1];
        if (slug && blogLastmod.has(slug)) {
          item.lastmod = blogLastmod.get(slug);
        }
        return item;
      },
      customPages: [
        'https://mclair.vercel.app/sobre',
        'https://mclair.vercel.app/servicos',
        'https://mclair.vercel.app/servicos/marketing-de-autoridade',
        'https://mclair.vercel.app/servicos/assessoria-de-imprensa',
        'https://mclair.vercel.app/servicos/branding-estrategico',
        'https://mclair.vercel.app/servicos/marketing-digital',
        'https://mclair.vercel.app/servicos/consultoria-em-comunicacao',
        'https://mclair.vercel.app/servicos/mentorias-exclusivas',
        'https://mclair.vercel.app/mentorias',
        'https://mclair.vercel.app/cases',
        'https://mclair.vercel.app/cases/claudia-elisa',
        'https://mclair.vercel.app/cases/fidalgo',
        'https://mclair.vercel.app/cases/alexandre-magno',
        'https://mclair.vercel.app/cases/elemar-jr',
        'https://mclair.vercel.app/cases/bms-abimaq',
        'https://mclair.vercel.app/clientes',
        'https://mclair.vercel.app/contato',
      ],
    }),
    react(),
    keystatic(),
  ],
});
