/*
 * Política de privacidade (LGPD) e aviso de cookies.
 *
 * ESTADO: DESLIGADO até a Mclair aprovar o texto de /politica-de-privacidade/.
 *
 * Enquanto PRIVACIDADE_ATIVA = false:
 *   - o aviso de cookies não aparece (src/components/AvisoCookies.astro);
 *   - o consentimento padrão "negado" do Google NÃO é aplicado
 *     (src/components/ConsentimentoPadrao.astro), então GA4 e pixel da Meta
 *     medem como hoje;
 *   - a página /politica-de-privacidade/ existe, mas com noindex, fora do
 *     sitemap (via src/data/fora-do-google.ts) e com a faixa de rascunho;
 *   - o rodapé continua com "Dúvidas sobre privacidade e dados" -> /contato/.
 *
 * COMO LIGAR (depois da aprovação):
 *   1. Conferir com a Mclair os dados marcados "confirmar com a Mclair" abaixo
 *      e preencher ENCARREGADO (nome e e-mail do encarregado de dados).
 *   2. Antes de publicar, ajustar o GTM (GTM-P3JSQCGS) como descrito no topo
 *      de src/components/ConsentimentoPadrao.astro: sem isso o pixel da Meta
 *      continua disparando para quem recusou.
 *   3. Trocar PRIVACIDADE_ATIVA para true, fazer o build e publicar.
 *   Com true: aviso ligado, consentimento ativo, página indexável e no
 *   sitemap, link "Política de privacidade" no rodapé.
 */
export const PRIVACIDADE_ATIVA = false;

export const CAMINHO_POLITICA = '/politica-de-privacidade/';

// Chave do localStorage com a escolha do visitante: 'aceito' ou 'recusado'.
export const CHAVE_CONSENTIMENTO = 'mclair:consentimento';

// Mudou o texto da política? Atualize a data.
export const POLITICA_ATUALIZADA_EM = '21 de outubro de 2026';

// Dados do controlador tirados de consulta pública ao CNPJ.
// CONFIRMAR COM A MCLAIR: razão social, CNPJ e endereço completo (rua e CEP),
// se ela quiser o endereço na política.
export const CONTROLADOR = {
  razaoSocial: 'M.CLAIR AGENCIA DE COMUNICACAO INTEGRADA LTDA',
  nomeFantasia: 'Mclair Comunicação',
  cnpj: '29.417.788/0001-18',
  cidade: 'São Paulo/SP',
  email: 'kelly@mclair.com.br',
};

// Canal de privacidade. Como agente de tratamento de pequeno porte, a Mclair
// está dispensada de indicar encarregado (Resolução CD/ANPD nº 2/2022,
// art. 11), mas precisa manter um canal de comunicação. Decidido pela Kelly
// em 21/10/2026: e-mail do Thiago.
export const ENCARREGADO = {
  nome: '',
  email: 'thiago@mclair.com.br',
};
