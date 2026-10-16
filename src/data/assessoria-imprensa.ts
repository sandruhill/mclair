/**
 * Conteúdo fixo da página /servicos/assessoria-de-imprensa/ que precisa
 * existir em dois lugares: no HTML visível (componente
 * AssessoriaImprensa.astro) e no JSON-LD (FAQPage e areaServed, montados em
 * src/pages/servicos/[...slug].astro). Um arquivo só para os dois não
 * divergirem. Fatos e números vêm dos cases publicados no site e da página
 * /sobre/; nada aqui foi inventado.
 */

export const SLUG_ASSESSORIA = 'assessoria-de-imprensa';

export const SEO_ASSESSORIA = {
  title: 'Agência de Assessoria de Imprensa | Mclair',
  h1: 'Assessoria de imprensa para empresas em São Paulo e no Brasil',
  description:
    'Agência de assessoria de imprensa com base em São Paulo e atuação no Brasil todo. Mais de 11.600 inserções na mídia desde 2017, clientes na Folha, Exame, Valor e CNN. Peça uma proposta.',
  keywords:
    'agência de assessoria de imprensa, empresa de assessoria de imprensa, contratar assessoria de imprensa, assessoria de imprensa para empresas, assessoria de imprensa são paulo, assessoria de imprensa, mclair',
};

/** Mensagem pronta que identifica a página de origem no WhatsApp. */
export const WA_ASSESSORIA =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa e quero conversar sobre uma proposta para a minha empresa.');

/** Cidades do plano de atendimento (areaServed), sem endereço. */
export const CIDADES_ATENDIDAS = [
  'São Paulo',
  'Rio de Janeiro',
  'Belo Horizonte',
  'Porto Alegre',
  'Curitiba',
  'Florianópolis',
  'Joinville',
  'Brasília',
  'Goiânia',
];

export type Pergunta = { pergunta: string; resposta: string };

/** Mesmas perguntas no HTML e no FAQPage. Texto simples, sem HTML. */
export const FAQ_ASSESSORIA: Pergunta[] = [
  {
    pergunta: 'Quanto tempo leva até sair a primeira matéria?',
    resposta:
      'Depende da pauta e do calendário das redações. Uma parceria de leiloeira com banco vira notícia na semana do leilão; o posicionamento de um especialista pede uma construção mais longa, com artigos e entrevistas. O diagnóstico define as primeiras pautas e o cronograma, e você acompanha tudo no relatório.',
  },
  {
    pergunta: 'Vocês garantem publicação na Folha, na Exame ou em outro veículo específico?',
    resposta:
      'Não. Nenhuma assessoria de imprensa séria garante espaço editorial, porque quem decide o que publica é o jornalista. O que a Mclair garante é método: pauta com interesse real, release bem escrito e relacionamento direto com as redações certas. Foi assim que clientes nossos saíram na Folha de S.Paulo, na Exame, no Valor Investe, na IstoÉ Dinheiro, no InfoMoney e na CNN Brasil.',
  },
  {
    pergunta: 'Quanto custa contratar a assessoria de imprensa da Mclair?',
    resposta:
      'O valor depende do escopo: número de porta-vozes, frequência de pautas, abrangência regional, nacional ou internacional, e se entram media training, gestão de crise ou conteúdo para as redes. A proposta é montada depois do diagnóstico, que é a primeira conversa, e vem com o que está incluso e como medimos o resultado.',
  },
  {
    pergunta: 'A Mclair atende empresas fora de São Paulo?',
    resposta:
      'Sim. A base da agência é em São Paulo e o atendimento é remoto para o Brasil todo. As redações nacionais estão a um e-mail ou um telefonema de distância de qualquer cidade, e os clientes dos nossos cases saíram em veículos de alcance nacional e regional.',
  },
  {
    pergunta: 'Assessoria de imprensa é a mesma coisa que propaganda?',
    resposta:
      'Não. Na propaganda a empresa compra o espaço e controla a mensagem. Na assessoria de imprensa a matéria é editorial: sai porque o jornalista julgou a pauta relevante para o público dele. Por isso tem mais credibilidade e por isso não se paga pela publicação.',
  },
  {
    pergunta: 'Como eu acompanho o que foi publicado?',
    resposta:
      'Toda matéria, menção ou entrevista entra no clipping, com veículo, data e link. O relatório periódico reúne o clipping, as pautas em andamento e as oportunidades que apareceram nas redações.',
  },
  {
    pergunta: 'Minha empresa é pequena ou sou um profissional independente. Faz sentido?',
    resposta:
      'Faz, desde que você tenha algo relevante a dizer para o público de algum veículo. Especialistas como Diego Nogare, em dados e inteligência artificial, e Claudia Elisa, em liderança, construíram presença na Exame, na CNN Brasil e na HSM Management. O que define o tamanho do trabalho é o escopo, não o porte da empresa.',
  },
  {
    pergunta: 'Vocês fazem assessoria de imprensa internacional?',
    resposta:
      'Sim. Para o projeto Brazil Machinery Solutions, da ABIMAQ com a ApexBrasil, a Mclair atuou com correspondentes estrangeiros e jornalistas de comércio internacional, com conteúdo em português e inglês. Foram mais de 2 mil inserções, 1.734 publicações na imprensa internacional e R$ 9,14 milhões em retorno de mídia.',
  },
  {
    pergunta: 'E se surgir uma crise?',
    resposta:
      'Assessoria de crise e gestão de reputação fazem parte do serviço. A Mclair conduziu, por exemplo, a comunicação da aquisição da Emergee pelo Itaú, um momento sensível em que o que importa é controlar a narrativa e proteger a reputação de quem está no centro da notícia.',
  },
  {
    pergunta: 'Preciso dar entrevistas pessoalmente?',
    resposta:
      'Em boa parte das pautas, sim: o jornalista quer ouvir quem decide. Por isso o trabalho inclui o posicionamento do porta-voz e o media training, em TV, rádio, podcast ou por escrito.',
  },
];
