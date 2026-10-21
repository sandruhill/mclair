/*
 * Guia "Melhores agências de assessoria de imprensa em São Paulo".
 *
 * ESTADO: RASCUNHO esperando a aprovação da Kelly Pinheiro (21/10/2026).
 *
 * Enquanto GUIA_AGENCIAS_ATIVO = false:
 *   - a página existe em CAMINHO_GUIA_AGENCIAS, com noindex e fora do sitemap
 *     (via src/data/fora-do-google.ts);
 *   - mostra a faixa "Rascunho para aprovação da Mclair" no topo;
 *   - não tem link em /guias/, no hub, no rodapé nem no llms.txt.
 *
 * COMO LIGAR (depois da aprovação):
 *   1. Kelly revisa a lista de agências, os endereços e o bloco "E a Mclair?".
 *   2. Trocar GUIA_AGENCIAS_ATIVO para true.
 *   3. Se quiser o guia no índice de /guias/ e no "Outros guias", incluir em
 *      GUIAS (src/components/guias/guias.ts) e no llms.txt.
 *   4. Build e deploy.
 */
export const GUIA_AGENCIAS_ATIVO = false;

export const CAMINHO_GUIA_AGENCIAS = '/guias/melhores-agencias-de-assessoria-de-imprensa-em-sao-paulo/';
