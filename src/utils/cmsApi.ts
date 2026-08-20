// Fetches content from the MySQL-backed export API at build time.
// Used by every page that used to read from Keystatic/git-tracked files.
const EXPORT_URL = 'https://mclair.com.br/acesso/api-export.php';

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
};

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
const DEFAULT_BLOG_IMAGE = '/blog-images/capa-padrao.svg';

export function resolveBlogImage(featuredImage?: string | null, imageUrl?: string | null): string {
  if (featuredImage) return featuredImage;
  if (imageUrl && !imageUrl.includes(BROKEN_IMAGE_URL_PATTERN)) return imageUrl;
  return DEFAULT_BLOG_IMAGE;
}

export const getBlogPosts = () => fetchType<DbBlogPost>('blog_posts');
export const getServices = () => fetchType<DbService>('services');
export const getCases = () => fetchType<DbCase>('cases');
export const getSingletons = () => fetchType<DbSingleton>('singletons');

export async function getSingleton(slug: string): Promise<any> {
  const all = await getSingletons();
  return all.find((s) => s.slug === slug)?.data ?? {};
}
