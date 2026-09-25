/**
 * Markdown do post -> HTML publicado. Um lugar só para os três pontos que
 * renderizam posts (página do post, índice do blog, categorias):
 *   1. parágrafos colados viram parágrafos (normalizarParagrafos);
 *   2. blocos $$galeria saem do caminho do marked;
 *   3. marked;
 *   4. links de vídeo sozinhos viram player;
 *   5. galerias entram no lugar; imagens com #w=&h= ganham width/height.
 */
import { marked } from 'marked';
import { normalizarParagrafos } from './markdown';
import { embutirVideos } from './videoEmbed';
import { extrairGalerias, embutirGalerias, dimensoesDasImagens } from './galeria';

export function renderizarPost(md: string | null | undefined): string {
  const { md: semGalerias, galerias } = extrairGalerias(normalizarParagrafos(md));
  const html = marked.parse(semGalerias) as string;
  return dimensoesDasImagens(embutirGalerias(embutirVideos(html), galerias));
}
