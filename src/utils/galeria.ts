/**
 * Galeria de imagens dentro do post.
 *
 * No markdown é um bloco personalizado do Toast UI, que sobrevive à ida e
 * volta do editor visual:
 *
 *   $$galeria
 *   cols: 3
 *   /uploads/a.jpg|1600|1200|Legenda da foto
 *   /uploads/b.jpg|||
 *   $$
 *
 * Primeira linha opcional "cols: auto|2|3|4"; depois uma imagem por linha:
 * url|largura|altura|legenda (largura/altura opcionais, evitam pulo de
 * layout). O marked não conhece esse bloco, então ele é trocado por um
 * comentário HTML antes do marked e pelo HTML da galeria depois.
 */

export type ImagemDaGaleria = { url: string; w: number | null; h: number | null; legenda: string };
export type Galeria = { cols: 'auto' | 2 | 3 | 4; imagens: ImagemDaGaleria[] };

const RE_BLOCO = /^\$\$galeria[ \t]*\r?\n([\s\S]*?)^\$\$[ \t]*$/gm;

export function lerGaleria(corpo: string): Galeria {
  const g: Galeria = { cols: 'auto', imagens: [] };
  for (const bruta of corpo.split(/\r?\n/)) {
    const linha = bruta.trim();
    if (!linha) continue;
    const c = /^cols?:\s*(auto|2|3|4)$/i.exec(linha);
    if (c) { g.cols = c[1] === 'auto' ? 'auto' : (Number(c[1]) as 2 | 3 | 4); continue; }
    const partes = linha.split('|');
    const url = (partes[0] || '').trim();
    if (!/^(\/[^\s"<>]+|https?:\/\/[^\s"<>]+)$/i.test(url)) continue;
    const w = parseInt(partes[1] || '', 10) || null;
    const h = parseInt(partes[2] || '', 10) || null;
    g.imagens.push({ url, w, h, legenda: partes.slice(3).join('|').trim() });
  }
  return g;
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

export function htmlDaGaleria(g: Galeria): string {
  if (!g.imagens.length) return '';
  const n = g.imagens.length;
  const cols = g.cols === 'auto' ? (n === 1 ? 1 : n === 2 || n === 4 ? 2 : 3) : g.cols;
  const itens = g.imagens.map((im, i) => {
    const dims = im.w && im.h ? ` width="${im.w}" height="${im.h}"` : '';
    const alt = esc(im.legenda || `Imagem ${i + 1} de ${n}`);
    return (
      `<figure class="galeria-item">` +
      `<a href="${esc(im.url)}" class="galeria-link" data-legenda="${esc(im.legenda)}">` +
      `<img src="${esc(im.url)}" alt="${alt}"${dims} loading="lazy" decoding="async" />` +
      `</a>` +
      (im.legenda ? `<figcaption>${esc(im.legenda)}</figcaption>` : '') +
      `</figure>`
    );
  });
  return `<div class="galeria galeria-${cols}" data-cols="${cols}" data-total="${n}">${itens.join('')}</div>`;
}

/** Tira os blocos do markdown (viram comentários) e devolve o HTML de cada um. */
export function extrairGalerias(md: string): { md: string; galerias: string[] } {
  const galerias: string[] = [];
  const saida = md.replace(RE_BLOCO, (_tudo, corpo: string) => {
    const html = htmlDaGaleria(lerGaleria(corpo));
    if (!html) return '';
    galerias.push(html);
    return `<!--mclair-galeria-${galerias.length - 1}-->`;
  });
  return { md: saida, galerias };
}

/** Depois do marked: comentários viram as galerias. */
export function embutirGalerias(html: string, galerias: string[]): string {
  if (!galerias.length) return html;
  return html.replace(/(?:<p>)?<!--mclair-galeria-(\d+)-->(?:<\/p>)?/g, (_m, i) => galerias[Number(i)] ?? '');
}

/**
 * Imagem solta no texto com "#w=800&h=600" no endereço (o upload do painel
 * grava assim) vira <img width height>, sem o sufixo, para não pular o layout.
 */
export function dimensoesDasImagens(html: string): string {
  return html.replace(/<img([^>]*?)\ssrc="([^"#]+)#w=(\d+)&(?:amp;)?h=(\d+)"([^>]*)>/g, (_m, antes, src, w, h, depois) => {
    const jaTem = /\swidth=/.test(antes + depois);
    return `<img${antes} src="${src}"${jaTem ? '' : ` width="${w}" height="${h}"`}${depois}>`;
  });
}
