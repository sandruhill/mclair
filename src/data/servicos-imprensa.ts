/**
 * Conteúdo fixo das páginas de serviço ligadas à assessoria de imprensa que
 * têm rota própria (fora do CMS):
 *   /servicos/assessor-de-imprensa/
 *   /servicos/gestao-de-crise/
 *   /servicos/media-training/
 * As perguntas abaixo saem iguais no HTML e no FAQPage. Title, H1 e
 * perguntas seguem o mapa de palavras de 16/10/2026 (clusters C, G1 e F).
 * Toda prova citada vem dos cases publicados em /cases/ e da página
 * /kelly-pinheiro/; nada aqui foi inventado.
 *
 * Não criar no CMS serviços com os slugs "assessor-de-imprensa",
 * "gestao-de-crise" ou "media-training": as rotas estáticas destas páginas
 * disputariam a mesma URL com src/pages/servicos/[...slug].astro.
 */
import type { Pergunta } from './assessoria-imprensa';

export const URL_ASSESSOR = '/servicos/assessor-de-imprensa/';
export const URL_CRISE = '/servicos/gestao-de-crise/';
export const URL_MEDIA_TRAINING = '/servicos/media-training/';

const wa = (texto: string) => 'https://wa.me/5511988600372?text=' + encodeURIComponent(texto);

/* ----------------------------- Assessor de imprensa ----------------------------- */

export const SEO_ASSESSOR = {
  title: 'Contratar assessor de imprensa: jornalista dedicado à sua marca | Mclair',
  h1: 'Assessor de imprensa para sua empresa: jornalista que conhece as redações',
  description:
    'Vai contratar um assessor de imprensa? Compare freelancer e agência com honestidade e conheça a Mclair, onde quem conduz a sua conta é jornalista. Fale pelo WhatsApp.',
  keywords:
    'contratar assessor de imprensa, assessoria de imprensa freelancer, contratar jornalista, contratar jornalista freelancer, jornalista para assessoria de imprensa, assessor de imprensa freelancer',
};

export const WA_ASSESSOR = wa('Olá Mclair! Vim pela página sobre contratar assessor de imprensa e quero conversar sobre a minha empresa.');

export const FAQ_ASSESSOR: Pergunta[] = [
  {
    pergunta: 'O que faz um assessor de imprensa?',
    resposta:
      'Ele encontra o que é notícia dentro da sua empresa, transforma isso em pauta, release ou artigo e leva ao jornalista que cobre o assunto. Depois acompanha a redação, prepara quem vai dar entrevista e reúne no clipping o que saiu.',
  },
  {
    pergunta: 'Qual a função do assessor de imprensa?',
    resposta:
      'Fazer a ponte entre a empresa e a imprensa. Para a empresa, ele traduz o negócio em fatos que interessam ao leitor de um veículo. Para o jornalista, ele é uma fonte confiável, que responde rápido, entrega dado correto e indica quem pode falar sobre o tema.',
  },
  {
    pergunta: 'Como contratar um jornalista freelancer?',
    resposta:
      'Peça matérias que ele já conseguiu publicar, com veículo, data e link. Pergunte quais editorias ele acompanha, quanto tempo terá para a sua conta e quem cobre as ausências dele. Combine por escrito o escopo, a forma de pagamento e como ele vai mostrar o que foi feito a cada mês.',
  },
  {
    pergunta: 'Quanto custa um assessor de imprensa?',
    resposta:
      'Depende do escopo: quantas pautas por mês, quantos porta-vozes, se o alvo é a imprensa regional, nacional ou estrangeira. Freelancers costumam cobrar por hora, por texto ou por um pacote mensal menor. Agências trabalham com fee mensal ou projeto. A página sobre quanto custa assessoria de imprensa explica cada modelo.',
  },
  {
    pergunta: 'É melhor contratar um assessor freelancer ou uma agência?',
    resposta:
      'Para uma demanda pequena e pontual, como um lançamento local ou um release por mês, um bom freelancer pode bastar. Quando a empresa precisa de presença constante, tem mais de um porta-voz, corre risco de crise ou quer veículos nacionais, a agência leva vantagem, porque divide o trabalho entre jornalistas e não para quando alguém sai de férias.',
  },
  {
    pergunta: 'O assessor de imprensa precisa ser jornalista?',
    resposta:
      'Desde 2009, por decisão do Supremo Tribunal Federal, o diploma de jornalismo deixou de ser obrigatório para exercer a profissão. Na prática, ajuda muito ter passado por redação: quem já fechou matéria sabe o que o editor descarta, escreve no formato que o repórter usa e entende o prazo de cada veículo. Na Mclair, quem conduz a conta é jornalista.',
  },
  {
    pergunta: 'Como é um contrato com assessor de imprensa freelancer?',
    resposta:
      'Costuma ser um contrato de prestação de serviço, com escopo mensal ou por entrega, valor, prazo, forma de reajuste e regra para encerrar. Vale incluir confidencialidade, quem é dono dos textos e dos contatos levantados, e o formato do relatório. Desconfie de contrato que promete publicação garantida.',
  },
];

/* ------------------------------- Gestão de crise ------------------------------- */

export const SEO_CRISE = {
  title: 'Gestão de crise de imagem e reputação | Mclair Comunicação',
  h1: 'Gestão de crise de imagem',
  description:
    'Assessoria de crise para empresas e executivos: plano antes, porta-voz, nota oficial e imprensa durante, reputação depois. Atuação da Mclair na aquisição da Emergee pelo Itaú.',
  keywords:
    'gestão de crise, gerenciamento de crise, gestão de crise de imagem, gerenciamento de crise de imagem, assessoria de crise, gestão de reputação, crise de imagem',
};

export const WA_CRISE = wa('Olá Mclair! Vim pela página de gestão de crise e preciso conversar sobre a minha empresa.');

export const FAQ_CRISE: Pergunta[] = [
  {
    pergunta: 'O que é gestão de crise?',
    resposta:
      'É o trabalho de prever, conter e responder a um fato que ameaça a reputação de uma empresa ou de uma pessoa. Envolve decidir o que dizer, quem fala e quando, e acompanhar o que a imprensa e as redes publicam.',
  },
  {
    pergunta: 'Como fazer gestão de crise?',
    resposta:
      'Comece apurando os fatos antes de falar. Depois defina um porta-voz, escreva uma posição oficial curta e verdadeira, avise primeiro quem é diretamente afetado e responda à imprensa no prazo do jornalista. Durante todo o processo, monitore o que está sendo publicado e ajuste a resposta. No fim, registre o que aprendeu.',
  },
  {
    pergunta: 'Quais são os 4 Rs da gestão de crise?',
    resposta:
      'Não existe uma lista única, e autores diferentes usam palavras diferentes. A versão mais comum fala em prontidão (preparar o plano antes), resposta (agir nas primeiras horas), resolução (resolver a causa) e recuperação (reconstruir a confiança). Há também versões que trocam prontidão por redução de riscos.',
  },
  {
    pergunta: 'Como fazer gestão de crise nas redes sociais?',
    resposta:
      'Responda no mesmo canal em que a crise começou, com a mesma posição que foi dada à imprensa. Não apague comentários críticos nem discuta caso a caso em público. Leve para o atendimento privado quem precisa de solução individual e publique atualizações quando houver fato novo.',
  },
  {
    pergunta: 'O que é crise de imagem?',
    resposta:
      'É quando um fato, verdadeiro ou não, muda para pior o que clientes, funcionários, investidores ou a imprensa pensam de uma marca ou de uma pessoa.',
  },
  {
    pergunta: 'O que é gestão de reputação?',
    resposta:
      'É o cuidado contínuo com a forma como a empresa é vista, fora dos momentos de crise. Inclui presença na imprensa, porta-vozes preparados e coerência entre o que a empresa diz e o que faz. Quem cuida da reputação no dia a dia enfrenta uma crise com mais crédito junto aos jornalistas.',
  },
  {
    pergunta: 'Qual o papel da assessoria de imprensa numa crise?',
    resposta:
      'Ela organiza a relação com os jornalistas: recebe os pedidos de entrevista, escreve a nota oficial, prepara o porta-voz e corrige informações erradas com a redação. Também monitora o que sai.',
  },
  {
    pergunta: 'Quais são exemplos de gestão de crise?',
    resposta:
      'Um exemplo da própria Mclair é a comunicação da aquisição da Emergee pelo Itaú. Uma venda de empresa mexe com clientes, equipe e mercado ao mesmo tempo, e o trabalho foi conduzir a narrativa e proteger a reputação do CEO, Alexandre Magno, durante esse período sensível.',
  },
];

/* -------------------------------- Media training -------------------------------- */

export const SEO_MEDIA_TRAINING = {
  title: 'Media training para executivos e porta-vozes | Mclair Comunicação',
  h1: 'Media training: prepare seu porta-voz para a imprensa',
  description:
    'Media training sob medida para a sua empresa: mensagens-chave, simulação de entrevista para TV, rádio, podcast e impresso, e preparação para perguntas difíceis. Fale com a Mclair.',
  keywords:
    'media training, media training para executivos, treinamento de porta-voz, o que é media training, media training empresas, preparação de porta-voz',
};

export const WA_MEDIA_TRAINING = wa('Olá Mclair! Vim pela página de media training e quero preparar o porta-voz da minha empresa.');

export const FAQ_MEDIA_TRAINING: Pergunta[] = [
  {
    pergunta: 'O que é media training?',
    resposta:
      'É o treinamento que prepara uma pessoa para falar com a imprensa. O porta-voz entende como o jornalista trabalha, define as mensagens que quer passar e pratica em entrevistas simuladas, para chegar à entrevista de verdade sabendo o que dizer e como dizer.',
  },
  {
    pergunta: 'O que significa media training?',
    resposta:
      'A expressão vem do inglês e quer dizer, ao pé da letra, treinamento para a mídia. No Brasil ela é usada sem tradução para o preparo de executivos, especialistas e qualquer pessoa que vai representar uma organização diante de jornalistas.',
  },
  {
    pergunta: 'Quanto custa um media training?',
    resposta:
      'Varia com o número de porta-vozes, a quantidade de sessões, os formatos treinados (TV, rádio, podcast, entrevista por escrito) e se há gravação com análise. Por isso a Mclair só passa valor depois de entender quem será treinado e para quê. O media training é sempre orçado à parte, mesmo para quem já tem a assessoria de imprensa da Mclair.',
  },
  {
    pergunta: 'Como funciona o media training?',
    resposta:
      'Na Mclair, começa com uma conversa sobre a empresa e os temas que a imprensa pode perguntar. Depois vêm a construção das mensagens-chave, uma parte explicando como uma redação funciona e as simulações de entrevista, com revisão de cada resposta. Antes de uma entrevista real, o porta-voz recebe ainda um briefing sobre o jornalista e o veículo.',
  },
  {
    pergunta: 'Quais são exemplos de media training?',
    resposta:
      'Uma simulação de entrevista ao vivo para TV, com tempo curto. Um treino por telefone com perguntas de um jornal de economia sobre resultado. Um ensaio de coletiva com perguntas hostis, para um momento de crise. Um preparo para podcast, em que a conversa é longa.',
  },
  {
    pergunta: 'O media training é presencial ou online?',
    resposta:
      'Pode ser dos dois jeitos. O presencial ajuda nas simulações de TV e de coletiva, em que postura e olhar contam. O online funciona bem para rádio, podcast e entrevista por vídeo, que hoje é a forma mais comum de muitos veículos ouvirem fontes. A Mclair tem base em São Paulo e atende o Brasil todo.',
  },
  {
    pergunta: 'O media training procura dar dicas e exercitar o quê?',
    resposta:
      'Exercita a clareza da mensagem, a resposta em poucas frases, a calma diante de pergunta difícil e a postura diante de câmera e microfone. As dicas tratam de como o jornalista escolhe as falas e de por que nunca se deve mentir.',
  },
  {
    pergunta: 'Quem deve fazer media training?',
    resposta:
      'Toda pessoa que fala em nome da empresa: presidente, diretores, especialistas técnicos e quem atende a imprensa numa crise. Vale também para profissionais liberais e consultores que querem ser fonte de jornalistas. O ideal é treinar antes da primeira entrevista importante, não depois.',
  },
];
