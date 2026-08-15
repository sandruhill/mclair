import { createReader } from '@keystatic/core/reader';
import keystaticConfig from '../../keystatic.config';
import legacyPosts from '../data/blog.json';

export interface BlogPostSummary {
  slug: string;
  title: string;
  date: string;
  author: string;
  image: string;
}

function toTimestamp(d: string): number {
  if (!d) return 0;
  if (d.includes('-')) return new Date(d).getTime();
  const [dd, mm, yy] = d.split('/');
  return new Date(`${yy}-${mm}-${dd}`).getTime();
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
  let cmsPosts: BlogPostSummary[] = [];
  try {
    const reader = createReader(process.cwd(), keystaticConfig);
    const all = await reader.collections.blog.all();
    cmsPosts = all.map((p) => ({
      slug: p.slug,
      title: p.entry.title.name,
      date: p.entry.date ?? '',
      author: p.entry.author ?? 'Equipe Mclair',
      image: (p.entry as any).featuredImage ?? p.entry.image ?? '',
    }));
  } catch {
    // No CMS posts yet
  }

  const cmsSlugs = new Set(cmsPosts.map((p) => p.slug));
  const merged = [
    ...cmsPosts,
    ...(legacyPosts as BlogPostSummary[]).filter((p) => !cmsSlugs.has(p.slug)),
  ].sort((a, b) => toTimestamp(b.date) - toTimestamp(a.date));

  return merged.slice(0, limit);
}
