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

/* ------------------------------------------------------------------------
 * Páginas filhas do hub: /servicos/assessoria-de-imprensa/sao-paulo/ e
 * /servicos/assessoria-de-imprensa/quanto-custa/. Mesmo princípio: as
 * perguntas abaixo são as mesmas no HTML e no FAQPage.
 * --------------------------------------------------------------------- */

export const URL_HUB_ASSESSORIA = '/servicos/assessoria-de-imprensa/';
export const URL_ASSESSORIA_SP = '/servicos/assessoria-de-imprensa/sao-paulo/';
export const URL_ASSESSORIA_PRECO = '/servicos/assessoria-de-imprensa/quanto-custa/';

/**
 * Cidades com página própria de assessoria de imprensa. Alimenta o bloco
 * "Onde atendemos" do rodapé. Para uma cidade nova, crie a página em
 * src/pages/servicos/assessoria-de-imprensa/{cidade}.astro e acrescente
 * aqui; o rodapé passa a listar sozinho.
 */
export const CIDADES_COM_PAGINA: { nome: string; href: string }[] = [
  { nome: 'São Paulo', href: URL_ASSESSORIA_SP },
];

export const SEO_ASSESSORIA_SP = {
  title: 'Assessoria de Imprensa em São Paulo | Mclair',
  h1: 'Assessoria de imprensa em São Paulo',
  description:
    'Agência de assessoria de imprensa em São Paulo, fundada pela jornalista Kelly Pinheiro. Clientes na Folha de S.Paulo, Exame, InfoMoney, IstoÉ Dinheiro e CNN Brasil. Fale com a Mclair.',
  keywords:
    'assessoria de imprensa sp, assessoria de imprensa em sp, assessoria de imprensa são paulo, assessoria de imprensa em são paulo, agência de assessoria de imprensa em são paulo, mclair',
};

export const WA_ASSESSORIA_SP =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa em São Paulo e quero conversar sobre a minha empresa.');

export const FAQ_ASSESSORIA_SP: Pergunta[] = [
  {
    pergunta: 'Quanto custa uma assessoria de imprensa em São Paulo?',
    resposta:
      'Depende do escopo: quantos porta-vozes, quantas pautas por mês, se o alvo é a imprensa de São Paulo, a nacional ou a estrangeira, e se entram media training ou gestão de crise. A Mclair monta a proposta depois do diagnóstico, que é a primeira conversa. A página sobre quanto custa assessoria de imprensa explica os modelos de cobrança do mercado.',
  },
  {
    pergunta: 'A Mclair atende presencialmente em São Paulo?',
    resposta:
      'A agência tem base em São Paulo, então encontros presenciais podem ser combinados quando o trabalho pede, como a preparação de um porta-voz antes de uma entrevista importante. O dia a dia da conta corre por vídeo, telefone e WhatsApp, o que vale também para clientes de outras cidades.',
  },
  {
    pergunta: 'Vocês garantem matéria na Folha, no Estadão ou na Exame?',
    resposta:
      'Não. Quem decide o que sai é o editor de cada veículo, e nenhuma assessoria séria vende esse espaço. O que a Mclair entrega é pauta com interesse jornalístico, apresentada ao repórter que cobre o assunto. Foi assim que clientes nossos saíram na Folha de S.Paulo, na Exame e no InfoMoney.',
  },
  {
    pergunta: 'Em quais veículos de São Paulo clientes da Mclair já saíram?',
    resposta:
      'Entre os veículos com redação em São Paulo estão Folha de S.Paulo, Exame, InfoMoney, IstoÉ Dinheiro, Valor Investe, CNN Brasil, UOL e as emissoras SBT, Record TV, Band e Gazeta. Cada case publicado em mclair.com.br/cases mostra a matéria, com veículo e data.',
  },
  {
    pergunta: 'Minha empresa não fica em São Paulo. Faz diferença?',
    resposta:
      'Para a imprensa, não. O jornalista avalia a pauta, não o CEP de quem envia. A Mclair atende o Brasil todo de forma remota e leva clientes de outras cidades à imprensa nacional e à imprensa regional da praça deles.',
  },
  {
    pergunta: 'Como escolher uma agência de assessoria de imprensa em São Paulo?',
    resposta:
      'Peça matérias publicadas com link, veículo e data, pergunte quem vai cuidar da sua conta no dia a dia e desconfie de quem garante publicação ou cobra por matéria saída. Vale também conferir se a agência já trabalhou com o seu setor e com as editorias que o seu cliente lê.',
  },
  {
    pergunta: 'Qual a diferença entre assessoria de imprensa e relações públicas?',
    resposta:
      'A assessoria de imprensa cuida da relação com jornalistas e veículos: pauta, release, entrevista e clipping. Relações públicas é um campo mais amplo, que inclui também governo, comunidade, eventos e públicos internos. Na prática, a assessoria de imprensa é a parte de relações públicas voltada à mídia.',
  },
];

export const SEO_ASSESSORIA_PRECO = {
  title: 'Quanto Custa Assessoria de Imprensa: Valores e Modelos | Mclair',
  h1: 'Quanto custa uma assessoria de imprensa',
  description:
    'Como o mercado cobra assessoria de imprensa (fee mensal, projeto, pauta, evento), o que faz o valor subir ou cair e os sinais de alerta antes de contratar. Peça um diagnóstico à Mclair.',
  keywords:
    'quanto custa assessoria de imprensa, assessoria de imprensa preço, valor assessoria de imprensa, preço assessoria de imprensa, quanto custa um assessor de imprensa, tabela de preços assessoria de imprensa',
};

export const WA_ASSESSORIA_PRECO =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Li a página sobre quanto custa assessoria de imprensa e quero marcar o diagnóstico da minha empresa.');

export const FAQ_ASSESSORIA_PRECO: Pergunta[] = [
  {
    pergunta: 'Quanto custa assessoria de imprensa?',
    resposta:
      'O valor sai do escopo: número de porta-vozes, frequência de pautas, abrangência regional, nacional ou internacional, e serviços que entram junto, como media training e gestão de crise. Desconfie de preço fechado antes de uma conversa sobre o que a empresa tem para dizer. Na Mclair, a proposta vem depois do diagnóstico.',
  },
  {
    pergunta: 'Quanto custa um assessor de imprensa freelancer?',
    resposta:
      'O freelancer costuma cobrar por hora, por texto ou por um pacote mensal menor que o de uma agência. A diferença está no que vem junto: uma agência divide a conta entre jornalistas, mantém o relacionamento com as redações quando alguém sai de férias e tem método de clipping e relatório. Para um volume pequeno e pontual, o freelancer pode bastar.',
  },
  {
    pergunta: 'Existe tabela de preços de assessoria de imprensa?',
    resposta:
      'Não existe tabela oficial para contratos de agência. O Sindicato dos Jornalistas Profissionais no Estado de São Paulo publica uma tabela de referência para trabalhos avulsos de jornalista, como redação de texto e hora de trabalho, que serve de parâmetro para freelancer. O fee de uma agência é negociado caso a caso.',
  },
  {
    pergunta: 'Pago por matéria publicada ou por mês?',
    resposta:
      'O mais comum, e o que a Mclair pratica, é pagar pelo trabalho: pauta, release, relacionamento com as redações, preparação de porta-voz e relatório. Cobrar por matéria publicada cria um incentivo errado, porque empurra a agência para veículos fáceis e pautas fracas, e quem decide o que sai é o jornalista.',
  },
  {
    pergunta: 'Dá para contratar só um projeto, sem contrato mensal?',
    resposta:
      'No mercado, sim: lançamento de produto, evento, feira ou uma notícia específica podem ser contratados como projeto, com começo e fim. Presença constante na imprensa pede trabalho contínuo, porque relacionamento com redação se constrói com o tempo. O diagnóstico mostra qual dos dois o seu caso pede.',
  },
  {
    pergunta: 'Quanto custa um media training?',
    resposta:
      'Varia com o número de porta-vozes, a duração e o formato: preparação para TV, rádio, podcast ou entrevista por escrito, com ou sem simulação gravada. Na Mclair, ele faz parte da preparação de porta-vozes dentro da assessoria de imprensa, e o diagnóstico define quem passa por ele.',
  },
  {
    pergunta: 'Assessoria de imprensa vale a pena?',
    resposta:
      'Vale quando a empresa tem o que contar e alguém disponível para falar com a imprensa. Uma matéria editorial tem credibilidade que anúncio não compra, continua no Google por anos e pode virar fonte para ferramentas de inteligência artificial. Não vale para quem precisa de venda na semana seguinte: isso é papel de mídia paga.',
  },
];
