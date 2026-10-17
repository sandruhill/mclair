// Posts quase repetidos: o menor aponta a canônica para o mais completo e sai
// do sitemap. Os dois continuam no ar (ninguém perde link antigo), mas o
// Google passa a tratar o par como uma página só, a maior.
//
// Comparação feita em 17/10/2026 no site no ar (texto do post, trechos de 5
// palavras em comum):
// - Correios: mesmo título; o "-2" (10/08/2026) tem cerca de 1.200 palavras
//   e o outro (18/08/2026) cerca de 980, com 83% do texto contido no maior.
// - Guiando: mesmo título; o "-1" (12/01/2021) tem cerca de 300 palavras e o
//   outro (04/05/2021) cerca de 200, com 62% do texto em comum.
// - Zé Conecta: títulos quase iguais; o da Fundação (05/08/2021) tem cerca de
//   800 palavras e o outro (06/08/2021) cerca de 390, com 97% contido no maior.
//
// Chave: slug do post menor. Valor: slug do post para onde a canônica aponta.
// Para desfazer, basta tirar a linha.
export const CANONICO_DO_POST: Record<string, string> = {
  'correios-promovem-licitacao-publica-de-imoveis-em-oito-estados-com-descontos-de':
    'correios-promovem-licitacao-publica-de-imoveis-em-oito-estados-com-descontos-de-2',
  'guiando-tem-20-vagas-abertas-100-remoto':
    'guiando-tem-20-vagas-abertas-100-remoto-1',
  'projeto-ze-conecta-amplia-inclusao-digital-a-alunos-em-situacao-vulner':
    'projeto-ze-conecta-amplia-a-inclusao-digital-para-alunos-da-fundacao-j',
};

// Caminhos (com barra final) que saem do sitemap por terem canônica em outro post.
export const POSTS_COM_CANONICA_FORA = Object.keys(CANONICO_DO_POST).map((s) => `/blog/${s}/`);
