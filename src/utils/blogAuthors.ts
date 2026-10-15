/**
 * Autores do blog: URL do perfil, vínculo post -> autores, palavras que
 * concordam com o autor (autor/autora/organização) e o JSON-LD.
 *
 * O silo público é blog > autores > autor: /blog/autores/ lista quem tem
 * artigo publicado e /blog/autores/<slug>/ é a página de cada um. Autor sem
 * nenhum post não ganha página nem entra na lista (e portanto nem no
 * sitemap): uma página vazia é conteúdo fraco para o Google e não tem de
 * onde ser linkada. Ela passa a existir sozinha no primeiro artigo.
 *
 * Um post pode ter mais de um autor (tabela cmstest_blog_post_authors, em
 * ordem). Sem essa tabela, vale o blog_author_id do post.
 */
import type { DbBlogAuthor, DbBlogPost, DbBlogPostAuthor } from './cmsApi';

export const AUTORES_PATH = '/blog/autores';

export function urlDoAutor(slug: string): string {
  return `${AUTORES_PATH}/${slug}/`;
}

/**
 * Pessoas que têm página própria no site, fora do blog. O @id canônico da
 * pessoa mora nessa página (ex.: /kelly-pinheiro/#pessoa) e todo nó Person
 * dela (autora do blog, founder no Layout, /sobre/) aponta para o mesmo @id,
 * para o Google e as IAs enxergarem uma entidade só. Chave = slug do autor
 * no CMS. Quem não está aqui segue com o @id da própria página de autor.
 */
export const PESSOAS_CANONICAS: Record<string, { nome: string; path: string; fragmento: string }> = {
  'kelly-pinheiro': { nome: 'Kelly Pinheiro', path: '/kelly-pinheiro/', fragmento: '#pessoa' },
};

/** @id canônico da pessoa (página própria), ou null se ela não tem uma. */
export function idDaPessoa(base: string, slug: string): string | null {
  const p = PESSOAS_CANONICAS[slug];
  return p ? `${base}${p.path}${p.fragmento}` : null;
}

/** Caminho da página própria da pessoa (para link visível), ou null. */
export function paginaDaPessoa(slug: string): string | null {
  return PESSOAS_CANONICAS[slug]?.path ?? null;
}

/** Página própria pelo nome exibido (textos do CMS trazem o nome, não o slug). */
export function paginaPorNome(nome: string | null | undefined): string | null {
  const n = (nome ?? '').trim().toLocaleLowerCase('pt-BR');
  if (!n) return null;
  for (const p of Object.values(PESSOAS_CANONICAS)) {
    if (p.nome.toLocaleLowerCase('pt-BR') === n) return p.path;
  }
  return null;
}

/**
 * Para onde o nome do autor leva: a página própria, se existir; senão a
 * página de autor do blog. "Todos os artigos de Fulano" continua indo para
 * a página de autor (ver AuthorCard.astro).
 */
export function linkDoAutor(autor: Pick<DbBlogAuthor, 'slug' | 'kind'>): string {
  if (autor.kind === 'organizacao') return urlDoAutor(autor.slug);
  return paginaDaPessoa(autor.slug) ?? urlDoAutor(autor.slug);
}

function escaparRegex(t: string): string {
  return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
function escaparHtml(t: string): string {
  return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const NOMES_LINKAVEIS = Object.values(PESSOAS_CANONICAS).map((p) => ({
  path: p.path,
  // Nome inteiro, sem pegar pedaço de outra palavra (Kelly Pinheirosa).
  re: new RegExp(`(?<![\\p{L}\\p{N}])${escaparRegex(p.nome)}(?![\\p{L}\\p{N}])`, 'gu'),
}));

/**
 * HTML já renderizado: envolve o nome de quem tem página própria num <a>,
 * só em texto corrido (fora de <a>, de h1 a h6, de script/style). Serve ao
 * corpo dos posts e a qualquer HTML vindo do CMS; o banco não muda.
 * `exceto` = caminho da própria página, para ela não linkar para si mesma.
 */
export function linkarPessoas(html: string | null | undefined, exceto?: string | null): string {
  if (!html) return '';
  const nomes = NOMES_LINKAVEIS.filter((n) => n.path !== exceto);
  if (!nomes.length || !nomes.some((n) => (n.re.lastIndex = 0, n.re.test(html)))) return html;
  const BLOQUEIA = /^(a|h[1-6]|script|style|title|textarea|button)$/i;
  const partes = html.split(/(<[^>]+>)/);
  const pilha: string[] = [];
  return partes
    .map((parte) => {
      if (parte.startsWith('<')) {
        const m = /^<\/?([a-zA-Z][a-zA-Z0-9-]*)/.exec(parte);
        if (m && BLOQUEIA.test(m[1]) && !parte.endsWith('/>')) {
          const tag = m[1].toLowerCase();
          if (parte.startsWith('</')) {
            const i = pilha.lastIndexOf(tag);
            if (i !== -1) pilha.splice(i, 1);
          } else {
            pilha.push(tag);
          }
        }
        return parte;
      }
      if (pilha.length || !parte.trim()) return parte;
      let t = parte;
      for (const n of nomes) {
        n.re.lastIndex = 0;
        t = t.replace(n.re, (nome) => `<a href="${n.path}" class="pessoa-link">${nome}</a>`);
      }
      return t;
    })
    .join('');
}

/** Texto simples (campo do CMS): escapa o HTML e linka os nomes. Usar com set:html. */
export function linkarPessoasEmTexto(texto: string | null | undefined, exceto?: string | null): string {
  return linkarPessoas(escaparHtml(texto ?? ''), exceto);
}

/**
 * @id estável: a URL do perfil + #person, exceto para quem tem página própria
 * (ver PESSOAS_CANONICAS). A Mclair (organização) usa o @id da Organization
 * do Layout.
 */
export function idDoAutor(base: string, autor: DbBlogAuthor): string {
  if (autor.kind === 'organizacao') return `${base}/#organization`;
  return idDaPessoa(base, autor.slug) ?? `${base}${urlDoAutor(autor.slug)}#person`;
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

/** Monograma para quem não tem foto: primeira letra do primeiro e do último nome. */
export function iniciais(nome: string): string {
  const p = nome.trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
}

/** "A", "A e B", "A, B e C". */
export function ligarNomes(nomes: string[]): string {
  if (nomes.length <= 1) return nomes[0] ?? '';
  return `${nomes.slice(0, -1).join(', ')} e ${nomes[nomes.length - 1]}`;
}

/**
 * Palavras que concordam com o autor. Sem tratamento definido, formas
 * neutras (nada de chutar pelo nome).
 */
export function palavras(autor: DbBlogAuthor) {
  if (autor.kind === 'organizacao') {
    return {
      rotulo: 'Organização',
      escritoPor: 'Publicado por',
      tituloPerfil: `${autor.name} | Artigos no blog Mclair`,
      sobre: `Sobre ${autor.name}`,
      artigosDe: `Artigos de ${autor.name}`,
      todosOsArtigos: `Todos os artigos de ${autor.name}`,
    };
  }
  const g = autor.gender;
  return {
    rotulo: g === 'f' ? 'Autora' : g === 'm' ? 'Autor' : 'Perfil',
    escritoPor: 'Escrito por',
    tituloPerfil: `${autor.name} | ${g === 'f' ? 'Autora' : g === 'm' ? 'Autor' : 'Perfil'} no blog Mclair`,
    sobre: g === 'f' ? 'Sobre a autora' : g === 'm' ? 'Sobre o autor' : `Sobre ${autor.name}`,
    artigosDe: `Artigos de ${autor.name}`,
    todosOsArtigos: `Todos os artigos de ${primeiroNome(autor.name)}`,
  };
}

export function mapaDeAutores(autores: DbBlogAuthor[]): Map<number, DbBlogAuthor> {
  return new Map(autores.map((a) => [a.id, a]));
}

/** slug do post -> ids dos autores em ordem, a partir da tabela de vários autores. */
export function mapaDeVinculos(vinculos: DbBlogPostAuthor[]): Map<string, number[]> {
  const m = new Map<string, number[]>();
  for (const v of [...vinculos].sort((a, b) => a.position - b.position || a.author_id - b.author_id)) {
    if (!m.has(v.post_slug)) m.set(v.post_slug, []);
    const lista = m.get(v.post_slug)!;
    if (!lista.includes(v.author_id)) lista.push(v.author_id);
  }
  return m;
}

/** Autores de um post, em ordem. Tabela de vínculos primeiro; senão, blog_author_id. */
export function autoresDoPost(
  post: Pick<DbBlogPost, 'slug' | 'blog_author_id'>,
  autores: Map<number, DbBlogAuthor>,
  vinculos: Map<string, number[]>,
): DbBlogAuthor[] {
  const ids = vinculos.get(post.slug);
  if (ids && ids.length) return ids.map((id) => autores.get(id)).filter((a): a is DbBlogAuthor => !!a);
  const id = post.blog_author_id;
  if (id === null || id === undefined) return [];
  const a = autores.get(Number(id));
  return a ? [a] : [];
}

export function contagemDePosts(
  posts: Pick<DbBlogPost, 'slug' | 'blog_author_id'>[],
  autores: Map<number, DbBlogAuthor>,
  vinculos: Map<string, number[]>,
): Map<number, number> {
  const n = new Map<number, number>();
  for (const p of posts) {
    for (const a of autoresDoPost(p, autores, vinculos)) n.set(a.id, (n.get(a.id) ?? 0) + 1);
  }
  return n;
}

/** Autores com pelo menos um artigo, em ordem alfabética (pt-BR). */
export function autoresComPosts(
  autores: DbBlogAuthor[],
  posts: Pick<DbBlogPost, 'slug' | 'blog_author_id'>[],
  vinculos: DbBlogPostAuthor[] = [],
): DbBlogAuthor[] {
  const n = contagemDePosts(posts, mapaDeAutores(autores), mapaDeVinculos(vinculos));
  return autores
    .filter((a) => (n.get(a.id) ?? 0) > 0)
    .map((a) => ({ ...a, post_count: n.get(a.id) ?? 0 }))
    .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
}

function absoluta(base: string, url: string): string {
  return url.startsWith('http') ? url : `${base}${url}`;
}

/**
 * Nó completo do autor para a página do perfil (mainEntity). Pessoa vira
 * Person; organização vira referência à Organization que Layout.astro já
 * declara em toda página (mesmo @id, nunca uma segunda Organization).
 */
export function schemaDoAutor(autor: DbBlogAuthor, base: string): Record<string, any> {
  const sameAs = linksSociais(autor).map((l) => l.url);
  const descricao = autor.bio_short || autor.bio_long || '';
  if (autor.kind === 'organizacao') {
    return { '@id': `${base}/#organization` };
  }
  // Quem tem página própria tem a URL canônica lá; a página de autora é só
  // a lista de artigos.
  const paginaPropria = paginaDaPessoa(autor.slug);
  return {
    '@type': 'Person',
    '@id': idDoAutor(base, autor),
    name: autor.name,
    url: `${base}${paginaPropria ?? urlDoAutor(autor.slug)}`,
    ...(autor.job_title && { jobTitle: autor.job_title }),
    ...(autor.photo_url && { image: absoluta(base, autor.photo_url) }),
    ...(descricao && { description: descricao }),
    ...(sameAs.length && { sameAs }),
    ...(autor.topics.length && { knowsAbout: autor.topics }),
    worksFor: { '@id': `${base}/#organization` },
  };
}

/** Versão curta para dentro do BlogPosting: @id + o que o Google pede inline (name, url). */
export function schemaAutorDoPost(autor: DbBlogAuthor, base: string): Record<string, any> {
  if (autor.kind === 'organizacao') {
    return { '@type': 'Organization', '@id': `${base}/#organization`, name: autor.name, url: base };
  }
  return {
    '@type': 'Person',
    '@id': idDoAutor(base, autor),
    name: autor.name,
    url: `${base}${paginaDaPessoa(autor.slug) ?? urlDoAutor(autor.slug)}`,
    ...(autor.job_title && { jobTitle: autor.job_title }),
    ...(autor.photo_url && { image: absoluta(base, autor.photo_url) }),
    worksFor: { '@id': `${base}/#organization` },
  };
}

/** author do BlogPosting: objeto com um autor, lista com vários. */
export function schemaAutoresDoPost(autores: DbBlogAuthor[], base: string): Record<string, any> | Record<string, any>[] {
  const lista = autores.map((a) => schemaAutorDoPost(a, base));
  return lista.length === 1 ? lista[0] : lista;
}
