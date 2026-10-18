// Páginas que continuam no site mas saem do Google (noindex) e do sitemap.
// Posts fora do tema de assessoria de imprensa, que traziam buscas que não
// viram cliente ("o dilema das redes", "quanto vale um canal no youtube").
// Decisão do usuário em 16/10/2026. Para devolver ao Google, tirar daqui.
// A política de privacidade fica fora enquanto é rascunho (PRIVACIDADE_ATIVA
// em src/data/privacidade.ts) e entra sozinha quando for ligada.
import { PRIVACIDADE_ATIVA, CAMINHO_POLITICA } from './privacidade.ts';

export const FORA_DO_GOOGLE = [
  '/blog/o-que-assistir-depois-de-o-dilema-das-redes/',
  '/blog/o-dilema-da-redes-reduz-o-protagonismo-das-pessoas/',
  '/blog/valuation-quanto-vale-um-canal-no-youtube/',
  ...(PRIVACIDADE_ATIVA ? [] : [CAMINHO_POLITICA]),
];

export function foraDoGoogle(caminho: string): boolean {
  const p = caminho.endsWith('/') ? caminho : caminho + '/';
  return FORA_DO_GOOGLE.includes(p);
}
