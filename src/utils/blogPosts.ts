import { getBlogPosts, resolveBlogImage } from './cmsApi';

export interface BlogPostSummary {
  slug: string;
  title: string;
  date: string;
  author: string;
  image: string;
}

function toTimestamp(d: string): number {
  return d ? new Date(d).getTime() : 0;
}

export function formatDate(d: string): string {
  if (!d) return '';
  let dd: string, mm: string, yy: string;
  if (d.includes('-')) {
    [yy, mm, dd] = d.split('-');
  } else {
    [dd, mm, yy] = d.split('/');
  }
  const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  return `${parseInt(dd)} ${months[parseInt(mm) - 1]} ${yy}`;
}

export async function getLatestPosts(limit = 3): Promise<BlogPostSummary[]> {
  const posts = await getBlogPosts();
  const summaries: BlogPostSummary[] = posts.map((p) => ({
    slug: p.slug,
    title: p.title,
    date: p.post_date ?? '',
    author: p.author ?? 'Equipe Mclair',
    image: resolveBlogImage(p.featured_image, p.image_url),
  }));
  return summaries.sort((a, b) => toTimestamp(b.date) - toTimestamp(a.date)).slice(0, limit);
}
