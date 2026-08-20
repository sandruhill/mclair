// One-off migration script: reads all Keystatic-managed content (blog posts,
// servicos, cases, singletons) from the git-tracked filesystem and emits a
// single SQL file that populates the cmstest_* tables for the isolated CMS
// prototype. Read-only against the source files -- never touches them.
import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';

const ROOT = path.resolve(import.meta.dirname, '..');
const out = [];

function esc(v) {
  if (v === null || v === undefined) return 'NULL';
  if (typeof v === 'number') return String(v);
  return "'" + String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}
function jsonCol(v) {
  return esc(JSON.stringify(v ?? []));
}

// ---- blog posts ----
const blogDir = path.join(ROOT, 'src/content/blog');
for (const file of fs.readdirSync(blogDir)) {
  if (!file.endsWith('.md')) continue;
  const slug = file.replace(/\.md$/, '');
  const raw = fs.readFileSync(path.join(blogDir, file), 'utf-8');
  const m = raw.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (!m) { console.error('SKIP (no frontmatter):', file); continue; }
  const fm = yaml.load(m[1]) || {};
  const content = m[2].trim();

  out.push(
    `INSERT INTO cmstest_blog_posts (slug, title, subtitle, meta_description, post_date, author, featured_image, image_url, hero_video, category, keywords, about_topics, faq_items, content_md) VALUES (` +
    [
      esc(slug), esc(fm.title), esc(fm.subtitle), esc(fm.metaDescription),
      esc(fm.date instanceof Date ? fm.date.toISOString().slice(0, 10) : fm.date),
      esc(fm.author), esc(fm.featuredImage), esc(fm.image), esc(fm.heroVideo),
      esc(fm.category), jsonCol(fm.keywords), jsonCol(fm.aboutTopics), jsonCol(fm.faqItems),
      esc(content),
    ].join(', ') + `) ON DUPLICATE KEY UPDATE title=VALUES(title);`
  );
}

// ---- servicos ----
const servicosDir = path.join(ROOT, 'src/content/servicos');
for (const file of fs.readdirSync(servicosDir)) {
  if (!file.endsWith('.json')) continue;
  const slug = file.replace(/\.json$/, '');
  const d = JSON.parse(fs.readFileSync(path.join(servicosDir, file), 'utf-8'));
  out.push(
    `INSERT INTO cmstest_services (slug, num, color, accent, bg, title, headline, intro, full_desc, home_desc, items, image, cta, meta_description) VALUES (` +
    [
      esc(slug), esc(d.num), esc(d.color), esc(d.accent), esc(d.bg), esc(d.title),
      esc(d.headline), esc(d.intro), esc(d.desc), esc(d.homeDesc), jsonCol(d.items),
      esc(d.image), esc(d.cta), esc(d.metaDescription),
    ].join(', ') + `) ON DUPLICATE KEY UPDATE title=VALUES(title);`
  );
}

// ---- cases ----
const casesDir = path.join(ROOT, 'src/content/cases');
for (const file of fs.readdirSync(casesDir)) {
  if (!file.endsWith('.json')) continue;
  const slug = file.replace(/\.json$/, '');
  const d = JSON.parse(fs.readFileSync(path.join(casesDir, file), 'utf-8'));
  out.push(
    `INSERT INTO cmstest_cases (slug, client, num, color, accent, sector, challenge, solution, results, tags, img, logo, gallery, home_result, meta_description) VALUES (` +
    [
      esc(slug), esc(d.client), esc(d.num), esc(d.color), esc(d.accent), esc(d.sector),
      esc(d.challenge), esc(d.solution), jsonCol(d.results), jsonCol(d.tags),
      esc(d.img), esc(d.logo), jsonCol(d.gallery), esc(d.homeResult), esc(d.metaDescription),
    ].join(', ') + `) ON DUPLICATE KEY UPDATE client=VALUES(client);`
  );
}

// ---- singletons ----
const singletonsDir = path.join(ROOT, 'src/content/singletons');
for (const file of fs.readdirSync(singletonsDir)) {
  if (!file.endsWith('.json')) continue;
  const slug = file.replace(/\.json$/, '');
  const d = JSON.parse(fs.readFileSync(path.join(singletonsDir, file), 'utf-8'));
  out.push(
    `INSERT INTO cmstest_singletons (slug, data) VALUES (${esc(slug)}, ${jsonCol(d.content ?? d)}) ON DUPLICATE KEY UPDATE data=VALUES(data);`
  );
}

const sqlPath = path.join(ROOT, 'scripts/cms-content-import.sql');
fs.writeFileSync(sqlPath, out.join('\n') + '\n');
console.log(`Wrote ${out.length} statements to ${sqlPath}`);
