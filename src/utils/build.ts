// Carimbo do build: um número por execução do `astro build` (o módulo é
// avaliado uma vez por processo), igual em todas as páginas. Vai numa meta
// tag para /ao-vivo.js saber se o servidor já entrega uma versão mais nova
// da página do que a que está aberta.
export const BUILD_ID = String(Date.now());
