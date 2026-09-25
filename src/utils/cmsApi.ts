// Fetches content from the MySQL-backed export API at build time.
// Used by every page that used to read from Keystatic/git-tracked files.
// CMS_EXPORT_URL só existe para build local contra um mock da API (testes);
// em produção não é definida e vale o endereço real.
const EXPORT_URL = import.meta.env.CMS_EXPORT_URL || 'https://mclair.com.br/acesso/api-export.php';

// Building now runs on the same server that serves this API, and every page/component
// that needs a content type calls its getter independently -- without caching, a full
// build fires dozens of concurrent self-requests at the PHP-FPM pool, which can exhaust
// it and cause sporadic failures. One fetch per type per build avoids that entirely.
const cache = new Map<string, Promise<any[]>>();

async function fetchType<T = any>(type: string): Promise<T[]> {
  if (cache.has(type)) return cache.get(type)!;
  const promise = (async () => {
    const token = import.meta.env.CMS_EXPORT_TOKEN;
    const res = await fetch(`${EXPORT_URL}?type=${type}`, {
      headers: { 'X-Export-Token': token },
    });
    if (!res.ok) throw new Error(`CMS export fetch failed (${type}): HTTP ${res.status}`);
    return res.json();
  })();
  cache.set(type, promise);
  return promise;
}

export type DbBlogPost = {
  slug: string;
  title: string;
  subtitle: string;
  meta_description: string;
  post_date: string;
  author: string;
  featured_image: string | null;
  image_url: string | null;
  hero_video: string | null;
  category: string;
  keywords: string[];
  about_topics: string[];
  faq_items: { question: string; answer: string }[];
  content_md: string;
  // Só existe depois de db/migrations/2026-09-25-blog-autores.sql; antes
  // disso a chave nem vem no JSON.
  blog_author_id?: number | null;
};

// Autor do blog (assinatura no fim do post + página /blog/autores/<slug>/).
export type DbBlogAuthor = {
  id: number;
  slug: string;
  name: string;
  job_title: string | null;
  photo_url: string | null;
  bio_short: string | null;
  bio_long: string | null;
  topics: string[];
  social_links: { type: string; url: string }[];
  post_count: number;
  // Segunda migração (2026-09-26). Antes dela: 'pessoa' e null.
  kind: 'pessoa' | 'organizacao';
  gender: 'm' | 'f' | null;
};

// Linha da tabela de vários autores por post (segunda migração).
export type DbBlogPostAuthor = { post_slug: string; author_id: number; position: number };

export type DbService = {
  slug: string;
  parent_slug: string | null;
  num: string;
  color: string;
  accent: string;
  bg: string;
  title: string;
  headline: string;
  intro: string;
  full_desc: string;
  home_desc: string;
  items: string[];
  image: string | null;
  cta: string;
  meta_description: string;
};

export type DbCase = {
  slug: string;
  client: string;
  num: string;
  color: string;
  accent: string;
  sector: string;
  challenge: string;
  solution: string;
  results: { v: string; l: string }[];
  tags: string[];
  img: string | null;
  logo: string | null;
  gallery: { src: string; caption: string }[];
  home_result: string;
  meta_description: string;
};

export type DbSingleton = { slug: string; data: any };

// Every blog post migrated from the old CMS carries an `image_url` pointing at
// mclair.com.br/images/galeria/... -- a folder that lived on the previous host
// (Locaweb) and was never part of this repo, so it 404s now that DNS points
// here. There's no way to recover those specific files, so any post without a
// real featured_image (and none currently have one) falls back to a shared
// branded placeholder instead of a broken image icon.
const BROKEN_IMAGE_URL_PATTERN = '/images/galeria/';
// Absolute + PNG (not the site's own SVG) because Facebook/Twitter/LinkedIn
// crawlers don't reliably resolve relative og:image URLs and don't support
// SVG there at all -- a relative SVG here silently means no preview image
// on every post lacking its own featured_image.
const DEFAULT_BLOG_IMAGE = 'https://mclair.com.br/blog-images/capa-padrao-og.png';

export function resolveBlogImage(featuredImage?: string | null, imageUrl?: string | null): string {
  if (featuredImage) return featuredImage;
  if (imageUrl && !imageUrl.includes(BROKEN_IMAGE_URL_PATTERN)) return imageUrl;
  return DEFAULT_BLOG_IMAGE;
}

export const getBlogPosts = () => fetchType<DbBlogPost>('blog_posts');

// Tolerante de propósito: se a tabela ainda não existe no banco (migração não
// rodada) ou o PHP publicado ainda não conhece o tipo, o site sai sem
// assinaturas em vez de derrubar o build inteiro.
export async function getBlogAuthors(): Promise<DbBlogAuthor[]> {
  try {
    const rows = await fetchType<any>('blog_authors');
    if (!Array.isArray(rows)) return [];
    return rows
      .filter((r) => r && r.slug && r.name)
      .map((r) => ({
        id: Number(r.id),
        slug: String(r.slug),
        name: String(r.name),
        job_title: r.job_title || null,
        photo_url: r.photo_url || null,
        bio_short: r.bio_short || null,
        bio_long: r.bio_long || null,
        topics: Array.isArray(r.topics) ? r.topics.map(String) : [],
        social_links: Array.isArray(r.social_links)
          ? r.social_links.filter((l: any) => l && typeof l.url === 'string').map((l: any) => ({ type: String(l.type || 'outro'), url: String(l.url) }))
          : [],
        post_count: Number(r.post_count ?? 0),
        kind: r.kind === 'organizacao' ? 'organizacao' : 'pessoa',
        gender: r.gender === 'm' || r.gender === 'f' ? r.gender : null,
      }));
  } catch {
    return [];
  }
}

// Vários autores por post. [] quando a tabela ainda não existe (aí vale o
// blog_author_id de cada post).
export async function getBlogPostAuthors(): Promise<DbBlogPostAuthor[]> {
  try {
    const rows = await fetchType<any>('blog_post_authors');
    if (!Array.isArray(rows)) return [];
    return rows
      .filter((r) => r && r.post_slug && r.author_id)
      .map((r) => ({ post_slug: String(r.post_slug), author_id: Number(r.author_id), position: Number(r.position ?? 0) }));
  } catch {
    return [];
  }
}
export const getServices = () => fetchType<DbService>('services');
export const getCases = () => fetchType<DbCase>('cases');
export const getSingletons = () => fetchType<DbSingleton>('singletons');

export async function getSingleton(slug: string): Promise<any> {
  const all = await getSingletons();
  return all.find((s) => s.slug === slug)?.data ?? {};
}

export type DbMenuItem = {
  id: number;
  parent_id: number | null;
  label: string;
  link_type: 'singleton' | 'service' | 'blog_index' | 'custom';
  link_value: string;
  sort_order: number;
};

export type MenuNode = { id: number; label: string; href: string; children: MenuNode[] };

// Fixed routes for the handful of institutional singleton pages the menu
// builder can point at -- keep in sync with pages.php's $MENU_PAGES.
const SINGLETON_ROUTES: Record<string, string> = {
  homepage: '/',
  sobre: '/sobre/',
  clientes: '/clientes/',
  contato: '/contato/',
  mentorias: '/mentorias/',
};

// Every route below is canonicalized WITH a trailing slash (confirmed via
// crawl: the server 301s the no-slash form) -- building hrefs without it
// meant every nav click paid for an extra redirect hop before landing on
// the real page.
function resolveMenuHref(item: DbMenuItem): string {
  switch (item.link_type) {
    case 'singleton': return SINGLETON_ROUTES[item.link_value] ?? '/';
    case 'service': return `/servicos/${item.link_value}/`;
    case 'blog_index': return '/blog/';
    default: return item.link_value || '#';
  }
}

export const getMenuItems = () => fetchType<DbMenuItem>('menu');

// Builds the parent/child tree the public nav renders from -- editing the
// Menu screen in the admin changes this directly, live, on the next build.
export async function getMenuTree(): Promise<MenuNode[]> {
  const items = await getMenuItems();
  const byParent = new Map<number | null, DbMenuItem[]>();
  for (const it of items) {
    const key = it.parent_id ?? null;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key)!.push(it);
  }
  const build = (parentId: number | null): MenuNode[] =>
    (byParent.get(parentId) ?? [])
      .sort((a, b) => a.sort_order - b.sort_order)
      .map((it) => ({ id: it.id, label: it.label, href: resolveMenuHref(it), children: build(it.id) }));
  return build(null);
}
