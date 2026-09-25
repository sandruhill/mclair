/**
 * Normalização do markdown dos posts antes do marked.
 *
 * O editor visual do painel (Toast UI 3) grava "um Enter" como uma quebra
 * simples de linha ("a\nb"), e não como parágrafo ("a\n\nb"). Para o
 * CommonMark, quebra simples é apenas um espaço dentro do mesmo parágrafo,
 * então o post inteiro saía num único <p>. O editor foi corrigido, mas
 * posts já gravados assim continuam no banco até o reparo rodar, e uma
 * colagem antiga pode reaparecer em qualquer edição futura. Esta função é a
 * mesma regra aplicada em três lugares (JS do editor, PHP ao salvar, SQL
 * de reparo): duas linhas de texto comum coladas viram dois parágrafos.
 *
 * O que NÃO é tocado, de propósito:
 *  - itens de lista, linhas de tabela, citações, títulos e HTML inline,
 *    que dependem de quebras simples para existir;
 *  - blocos de código cercados por ``` ou ~~~;
 *  - quebra forçada do próprio markdown (dois espaços ou barra no fim da
 *    linha), que o autor pediu de propósito;
 *  - linha seguinte indentada (continuação de item de lista).
 */

// Linha que já tem significado próprio no markdown quando isolada.
const LINHA_ESPECIAL = /^(?:[-*+] |\d+[.)] |\||>|#|<|(?:=+|-+)[ \t]*$)/;

export function normalizarParagrafos(md: string | null | undefined): string {
  if (!md) return '';
  const linhas = md.replace(/\r\n?/g, '\n').split('\n');
  const saida: string[] = [];
  let dentroDeCodigo = false;

  for (let i = 0; i < linhas.length; i++) {
    let linha = linhas[i];

    // ``` e ~~~ (código) e $$ (bloco personalizado do editor, ex.: galeria)
    if (/^\s*(```|~~~|\$\$)/.test(linha)) {
      dentroDeCodigo = !dentroDeCodigo;
      saida.push(linha);
      continue;
    }
    if (dentroDeCodigo) {
      saida.push(linha);
      continue;
    }

    // Linha só de espaços ou tabs é linha em branco para o CommonMark (o
    // marked já trata assim; aqui fica explícito para o texto gravado não
    // carregar lixo de colagem). NBSP fica de fora de propósito: para o
    // CommonMark ele é texto, e os posts migrados dependem disso.
    if (/^[ \t]+$/.test(linha)) linha = '';
    saida.push(linha);

    const proxima = linhas[i + 1];
    if (proxima === undefined) continue;
    if (linha === '' || /^[ \t]*$/.test(proxima)) continue;
    if (LINHA_ESPECIAL.test(linha) || LINHA_ESPECIAL.test(proxima)) continue;
    if (/(  |\\)$/.test(linha)) continue;
    if (/^[ \t]/.test(proxima)) continue;

    saida.push('');
  }

  return saida.join('\n');
}
