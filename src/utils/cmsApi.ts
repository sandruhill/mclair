// Fetches content from the MySQL-backed export API at build time.
// Used by every page that used to read from Keystatic/git-tracked files.
const EXPORT_URL = 'https://mclair.com.br/admin/api-export.php';

async function fetchType<T = any>(type: string): Promise<T[]> {
  const token = import.meta.env.CMS_EXPORT_TOKEN;
  const res = await fetch(`${EXPORT_URL}?type=${type}`, {
    headers: { 'X-Export-Token': token },
  });
  if (!res.ok) throw new Error(`CMS export fetch failed (${type}): HTTP ${res.status}`);
  return res.json();
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

export const getBlogPosts = () => fetchType<DbBlogPost>('blog_posts');
export const getServices = () => fetchType<DbService>('services');
export const getCases = () => fetchType<DbCase>('cases');
export const getSingletons = () => fetchType<DbSingleton>('singletons');

export async function getSingleton(slug: string): Promise<any> {
  const all = await getSingletons();
  return all.find((s) => s.slug === slug)?.data ?? {};
}
