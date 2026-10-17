// Title e meta description sem corte no meio da palavra.
//
// Antes o post cortava o título em 52 caracteres e a descrição em 160, fosse
// onde fosse, e o Google mostrava "Projeto Zé Conecta amplia a inclusão
// digital para al… | Mclair". Agora: o texto inteiro quando cabe (com uma
// folga pequena, porque cortar três letras custa mais que o Google cortar
// sozinho), senão corta no fim da última palavra que cabe, sem reticências,
// e sem deixar a frase terminar em "de", "para", "e" e afins.

const TITULO_MAX = 60;
const TITULO_FOLGA = 6;
const DESCRICAO_MAX = 155;
const DESCRICAO_FOLGA = 10;

// Palavras que não podem ficar no fim de um texto cortado.
const CONECTIVOS = new Set([
  'a', 'o', 'as', 'os', 'e', 'é', 'ou', 'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos',
  'um', 'uma', 'uns', 'umas', 'para', 'pra', 'por', 'pela', 'pelo', 'pelas', 'pelos', 'com', 'sem',
  'ao', 'aos', 'à', 'às', 'que', 'se', 'sua', 'seu', 'suas', 'seus', 'mais', 'como', 'entre', 'sobre',
]);

function limpar(texto: string): string {
  return (texto ?? '').replace(/\s+/g, ' ').trim();
}

// Corta no fim da última palavra que cabe em `limite`. Tira pontuação solta e
// conectivo do fim. Nunca devolve palavra pela metade.
export function cortarEmPalavra(texto: string, limite: number): string {
  const t = limpar(texto);
  if (t.length <= limite) return t;
  const palavras = t.split(' ');
  const saida: string[] = [];
  let tamanho = 0;
  for (const p of palavras) {
    const novo = tamanho + (saida.length ? 1 : 0) + p.length;
    if (novo > limite) break;
    saida.push(p);
    tamanho = novo;
  }
  // Uma palavra só, maior que o limite (URL, nome colado): melhor inteira.
  if (!saida.length) return palavras[0];
  const semPontaSolta = () => {
    let ultima = saida[saida.length - 1];
    ultima = ultima.replace(/[,;:(\[\-\u2013\u2014/|&]+$/u, '');
    if (ultima) saida[saida.length - 1] = ultima;
    else saida.pop();
  };
  semPontaSolta();
  while (saida.length > 1 && CONECTIVOS.has(saida[saida.length - 1].toLowerCase())) {
    saida.pop();
    semPontaSolta();
  }
  return saida.join(' ');
}

// Title de post: "Título | Blog Mclair" quando cabe; senão "Título | Mclair";
// senão o título inteiro, se passar do limite por pouco; senão corte em
// palavra + " | Mclair". `tituloSeo` (campo próprio do painel, quando existir)
// vale sobre o título do post.
export function tituloDoPost(titulo: string, tituloSeo?: string | null): string {
  const proprio = limpar(tituloSeo ?? '');
  if (proprio) {
    if (/mclair/i.test(proprio)) return proprio;
    return `${proprio} | Mclair`.length <= TITULO_MAX + TITULO_FOLGA ? `${proprio} | Mclair` : proprio;
  }
  const t = limpar(titulo);
  if (`${t} | Blog Mclair`.length <= TITULO_MAX) return `${t} | Blog Mclair`;
  if (`${t} | Mclair`.length <= TITULO_MAX + TITULO_FOLGA) return `${t} | Mclair`;
  if (t.length <= TITULO_MAX + TITULO_FOLGA) return t;
  return `${cortarEmPalavra(t, TITULO_MAX - ' | Mclair'.length)} | Mclair`;
}

// Meta description: inteira até 165 caracteres. Acima disso, fecha na última
// frase completa se ela ocupar boa parte do espaço; senão, corta em palavra.
export function descricaoSeo(texto: string): string {
  const t = limpar(texto);
  if (t.length <= DESCRICAO_MAX + DESCRICAO_FOLGA) return t;
  const trecho = t.slice(0, DESCRICAO_MAX + 1);
  const fimDeFrase = Math.max(trecho.lastIndexOf('. '), trecho.lastIndexOf('! '), trecho.lastIndexOf('? '));
  if (fimDeFrase >= DESCRICAO_MAX * 0.6) return t.slice(0, fimDeFrase + 1);
  return cortarEmPalavra(t, DESCRICAO_MAX);
}
