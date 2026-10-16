// Guias permanentes de /guias/. Uma busca, uma página: cada guia é dono das
// consultas listadas em `donoDe` (mapa de palavras de 16/10/2026, clusters
// H, I e J). O hub e os links "leia também" saem desta lista; guia novo entra
// aqui e ganha lugar no hub sozinho.
export interface Guia {
  slug: string;
  path: string;
  titulo: string;
  resumo: string;
  donoDe: string[];
}

export const BASE = 'https://mclair.com.br';
export const URL_GUIAS = '/guias/';
export const URL_HUB_ASSESSORIA = '/servicos/assessoria-de-imprensa/';
export const URL_PRECO = '/servicos/assessoria-de-imprensa/quanto-custa/';
export const URL_KELLY = '/kelly-pinheiro/';
export const ID_KELLY = `${BASE}/kelly-pinheiro/#pessoa`;
export const ID_ORG = `${BASE}/#organization`;

export const PUBLICADO = '2026-10-16';
export const REVISADO = '2026-10-16';

export function wa(texto: string): string {
  return 'https://wa.me/5511988600372?text=' + encodeURIComponent(texto);
}

export function dataPorExtenso(iso: string): string {
  const [a, m, d] = iso.split('-').map(Number);
  const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  return `${d} de ${meses[m - 1]} de ${a}`;
}

export const GUIAS: Guia[] = [
  {
    slug: 'o-que-e-assessoria-de-imprensa',
    path: '/guias/o-que-e-assessoria-de-imprensa/',
    titulo: 'O que é assessoria de imprensa',
    resumo: 'O que um assessor de imprensa faz no dia a dia, como o trabalho funciona e o que dá para esperar dele.',
    donoDe: ['o que é assessoria de imprensa', 'o que faz uma assessoria de imprensa', 'o que faz um assessor de imprensa', 'assessoria de imprensa o que é', 'mídia espontânea'],
  },
  {
    slug: 'press-release',
    path: '/guias/press-release/',
    titulo: 'Press release: o que é e como fazer',
    resumo: 'Como escrever um release que o jornalista abre, com um modelo comentado parte por parte.',
    donoDe: ['press release', 'o que é press release', 'como fazer um press release', 'modelo de press release'],
  },
  {
    slug: 'como-aparecer-na-midia',
    path: '/guias/como-aparecer-na-midia/',
    titulo: 'Como aparecer na mídia',
    resumo: 'O caminho para a sua empresa sair na imprensa sem pagar anúncio e virar fonte dos jornalistas.',
    donoDe: ['como aparecer na mídia', 'como sair na imprensa', 'como virar fonte de jornalista', 'como divulgar minha empresa na imprensa'],
  },
];

export function outrosGuias(slug: string): Guia[] {
  return GUIAS.filter((g) => g.slug !== slug);
}
