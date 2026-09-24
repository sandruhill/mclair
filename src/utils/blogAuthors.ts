/**
 * Autores do blog: URL do perfil, vínculo post -> autor e o Person do JSON-LD.
 *
 * O silo público é blog > autores > autor: /blog/autores/ lista quem tem
 * artigo publicado e /blog/autores/<slug>/ é a página de cada um. Autor sem
 * nenhum post não ganha página nem entra na lista (e portanto nem no
 * sitemap): uma página vazia é conteúdo fraco para o Google e não tem de
 * onde ser linkada. Ela passa a existir sozinha no primeiro artigo.
 */
import type { DbBlogAuthor, DbBlogPost } from './cmsApi';

export const AUTORES_PATH = '/blog/autores';

export function urlDoAutor(slug: string): string {
  return `${AUTORES_PATH}/${slug}/`;
}

/** @id estável do Person: a URL do perfil + #person. Todo BlogPosting aponta para ele. */
export function idDaPessoa(base: string, slug: string): string {
  return `${base}${urlDoAutor(slug)}#person`;
}

export type LinkSocial = { type: string; url: string };

export const ROTULO_SOCIAL: Record<string, string> = {
  linkedin: 'LinkedIn',
  instagram: 'Instagram',
  x: 'X',
  facebook: 'Facebook',
  youtube: 'YouTube',
  tiktok: 'TikTok',
  site: 'Site',
  outro: 'Link',
};

/** Só links http(s) válidos; o painel já valida, isto é a segunda barreira. */
export function linksSociais(autor: DbBlogAuthor): LinkSocial[] {
  return (autor.social_links ?? []).filter((l) => /^https?:\/\/\S+$/i.test(l.url));
}

export function primeiroNome(nome: string): string {
  return nome.trim().split(/\s+/)[0] || nome;
}

export function mapaDeAutores(autores: DbBlogAuthor[]): Map<number, DbBlogAuthor> {
  return new Map(autores.map((a) => [a.id, a]));
}

export function autorDoPost(post: Pick<DbBlogPost, 'blog_author_id'>, mapa: Map<number, DbBlogAuthor>): DbBlogAuthor | null {
  const id = post.blog_author_id;
  if (id === null || id === undefined) return null;
  return mapa.get(Number(id)) ?? null;
}

export function contagemDePosts(posts: Pick<DbBlogPost, 'blog_author_id'>[]): Map<number, number> {
  const n = new Map<number, number>();
  for (const p of posts) {
    const id = p.blog_author_id;
    if (id === null || id === undefined) continue;
    n.set(Number(id), (n.get(Number(id)) ?? 0) + 1);
  }
  return n;
}

/** Autores com pelo menos um artigo, em ordem alfabética (pt-BR). */
export function autoresComPosts(autores: DbBlogAuthor[], posts: Pick<DbBlogPost, 'blog_author_id'>[]): DbBlogAuthor[] {
  const n = contagemDePosts(posts);
  return autores
    .filter((a) => (n.get(a.id) ?? 0) > 0)
    .map((a) => ({ ...a, post_count: n.get(a.id) ?? 0 }))
    .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
}

function absoluta(base: string, url: string): string {
  return url.startsWith('http') ? url : `${base}${url}`;
}

/** Person completo, para a página do perfil (mainEntity) e para o author dos posts. */
export function schemaPessoa(autor: DbBlogAuthor, base: string): Record<string, any> {
  const sameAs = linksSociais(autor).map((l) => l.url);
  const descricao = autor.bio_short || autor.bio_long || '';
  return {
    '@type': 'Person',
    '@id': idDaPessoa(base, autor.slug),
    name: autor.name,
    url: `${base}${urlDoAutor(autor.slug)}`,
    ...(autor.job_title && { jobTitle: autor.job_title }),
    ...(autor.photo_url && { image: absoluta(base, autor.photo_url) }),
    ...(descricao && { description: descricao }),
    ...(sameAs.length && { sameAs }),
    ...(autor.topics.length && { knowsAbout: autor.topics }),
    // A organização já é declarada uma vez em Layout.astro; aqui só a referência.
    worksFor: { '@id': `${base}/#organization` },
  };
}

/** Versão curta para dentro do BlogPosting: @id + o que o Google pede inline (name, url). */
export function schemaAutorDoPost(autor: DbBlogAuthor, base: string): Record<string, any> {
  return {
    '@type': 'Person',
    '@id': idDaPessoa(base, autor.slug),
    name: autor.name,
    url: `${base}${urlDoAutor(autor.slug)}`,
    ...(autor.job_title && { jobTitle: autor.job_title }),
    ...(autor.photo_url && { image: absoluta(base, autor.photo_url) }),
  };
}
