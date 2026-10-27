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
      'Em boa parte das pautas, sim: o jornalista quer ouvir quem decide. Por isso a assessoria inclui um treinamento básico do porta-voz e o acompanhamento de todas as entrevistas. O media training completo, para TV, rádio, podcast ou entrevista por escrito, é contratado à parte.',
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
export const URL_ASSESSORIA_BSB = '/servicos/assessoria-de-imprensa/brasilia/';
export const URL_ASSESSORIA_POA = '/servicos/assessoria-de-imprensa/porto-alegre/';

/**
 * Cidades com página própria de assessoria de imprensa. Alimenta o bloco
 * "Onde atendemos" do rodapé. Para uma cidade nova, crie a página em
 * src/pages/servicos/assessoria-de-imprensa/{cidade}.astro e acrescente
 * aqui; o rodapé passa a listar sozinho.
 */
export const CIDADES_COM_PAGINA: { nome: string; href: string; resumo: string }[] = [
  { nome: 'São Paulo', href: URL_ASSESSORIA_SP, resumo: 'A base da Mclair e a imprensa de economia e negócios do país.' },
  { nome: 'Rio de Janeiro', href: '/servicos/assessoria-de-imprensa/rio-de-janeiro/', resumo: 'As redações nacionais que ficam no Rio, energia, saúde e economia criativa.' },
  { nome: 'Belo Horizonte', href: '/servicos/assessoria-de-imprensa/belo-horizonte/', resumo: 'Mineração, café, as startups de BH e a imprensa mineira.' },
  { nome: 'Curitiba', href: '/servicos/assessoria-de-imprensa/curitiba/', resumo: 'Indústria, tecnologia e a imprensa paranaense.' },
  { nome: 'Porto Alegre', href: URL_ASSESSORIA_POA, resumo: 'Indústria da Serra, agro e a imprensa gaúcha.' },
  { nome: 'Brasília', href: URL_ASSESSORIA_BSB, resumo: 'Entidades de classe, associações e a imprensa política.' },
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
      'Varia com o número de porta-vozes, a duração e o formato: preparação para TV, rádio, podcast ou entrevista por escrito, com ou sem simulação gravada. Na Mclair, a assessoria de imprensa já inclui uma preparação básica dos porta-vozes. O media training completo, com simulações gravadas, é orçado à parte, e o diagnóstico define quem passa por ele.',
  },
  {
    pergunta: 'Assessoria de imprensa vale a pena?',
    resposta:
      'Vale quando a empresa tem o que contar e alguém disponível para falar com a imprensa. Uma matéria editorial tem credibilidade que anúncio não compra, continua no Google por anos e pode virar fonte para ferramentas de inteligência artificial. Não vale para quem precisa de venda na semana seguinte: isso é papel de mídia paga.',
  },
];

/* ------------------------------------------------------------------------
 * Páginas de cidade fora de São Paulo (pesquisa de SERP de 15/10/2026,
 * ~/cofre-sandru/pesquisas/mclair/sul-df-go-mt.md). A Mclair não tem
 * escritório nessas cidades: o texto diz isso, e nenhum case é apresentado
 * como local. Veículos da cidade aparecem só como contexto.
 * --------------------------------------------------------------------- */

export const SEO_ASSESSORIA_BSB = {
  title: 'Assessoria de Imprensa em Brasília e no DF | Mclair',
  h1: 'Assessoria de imprensa em Brasília',
  description:
    'Assessoria de imprensa em Brasília e no DF para associações, entidades de classe e porta-vozes, com media training e gestão de crise. Atendimento remoto da Mclair.',
  keywords:
    'assessoria de imprensa brasília, assessoria de imprensa em brasília, assessoria de imprensa df, assessoria de imprensa brasilia, assessoria de imprensa distrito federal, mclair',
};

export const WA_ASSESSORIA_BSB =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa em Brasília e quero conversar sobre a minha entidade ou empresa.');

export const FAQ_ASSESSORIA_BSB: Pergunta[] = [
  {
    pergunta: 'Quanto custa uma assessoria de imprensa em Brasília?',
    resposta:
      'O preço sai do tamanho do trabalho: quantos porta-vozes, com que frequência há posição a defender, se o alvo é a imprensa do DF ou a nacional, e se entram media training e plano de crise. A proposta vem depois de uma conversa de diagnóstico.',
  },
  {
    pergunta: 'A Mclair tem escritório em Brasília?',
    resposta:
      'Não. A equipe trabalha de São Paulo e atende o Distrito Federal de forma remota, por vídeo, telefone e WhatsApp. Quando o trabalho pede um encontro presencial, como o treino de um porta-voz antes de uma entrevista grande, ele pode ser combinado.',
  },
  {
    pergunta: 'Vocês trabalham com associações e entidades de classe?',
    resposta:
      'Sim. Um exemplo é o programa Brazil Machinery Solutions, da ABIMAQ com a ApexBrasil, em que a Mclair produziu textos em vários idiomas, cobriu feiras do setor e escreveu manuais de comunicação. Foram mais de 2 mil inserções na mídia.',
  },
  {
    pergunta: 'Vocês garantem matéria no Correio Braziliense, no Metrópoles ou no Poder360?',
    resposta:
      'Não. Esses veículos vendem espaço publicitário, como publieditorial e brand content, mas isso é anúncio, não assessoria. A Mclair não tem acordo com eles e trabalha para conquistar espaço na reportagem, onde quem decide o que sai é o editor. A assessoria leva ao repórter do assunto um fato novo, um dado conferido e uma fonte disponível na hora do fechamento.',
  },
  {
    pergunta: 'Como preparar o presidente de uma entidade para falar com a imprensa?',
    resposta:
      'Com media training: entrevista simulada, as perguntas mais duras sobre o tema e duas ou três mensagens que ele precisa dizer em poucos segundos, por telefone ou ao vivo.',
  },
  {
    pergunta: 'O que fazer quando a entidade vira alvo de notícia negativa?',
    resposta:
      'Não responder no impulso. Primeiro, reunir os fatos, escolher um único porta-voz e preparar uma nota curta com o que se sabe e o que está sendo feito. Depois, atender os repórteres dentro do prazo deles.',
  },
  {
    pergunta: 'Faz diferença a assessoria não ser de Brasília?',
    resposta:
      'O repórter avalia a pauta e a fonte, não o endereço de quem envia. A Mclair monta a lista de jornalistas de cada assunto, com repórteres da capital quando a pauta pede e veículos nacionais de política e economia.',
  },
];

export const SEO_ASSESSORIA_POA = {
  title: 'Assessoria de Imprensa em Porto Alegre e no RS | Mclair',
  h1: 'Assessoria de imprensa em Porto Alegre',
  description:
    'Assessoria de imprensa em Porto Alegre e no Rio Grande do Sul para indústria, agro e tecnologia, com pauta para a imprensa gaúcha e a nacional. Fale com a Mclair.',
  keywords:
    'assessoria de imprensa porto alegre, assessoria de imprensa em porto alegre, assessoria de imprensa rs, assessoria de imprensa rio grande do sul, assessoria de imprensa caxias do sul, mclair',
};

export const WA_ASSESSORIA_POA =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa em Porto Alegre e quero conversar sobre a minha empresa no Rio Grande do Sul.');

export const FAQ_ASSESSORIA_POA: Pergunta[] = [
  {
    pergunta: 'Quanto custa assessoria de imprensa em Porto Alegre?',
    resposta:
      'Varia com o escopo: número de porta-vozes, pautas por mês, foco na imprensa gaúcha, na nacional ou nas duas, e serviços como media training. A Mclair faz um diagnóstico na primeira conversa e só depois apresenta a proposta.',
  },
  {
    pergunta: 'A Mclair tem escritório no Rio Grande do Sul?',
    resposta:
      'Não. A agência fica em São Paulo e atende empresas gaúchas a distância, com reuniões por vídeo e conversa diária por WhatsApp e telefone. Encontros presenciais podem ser combinados quando o trabalho pede.',
  },
  {
    pergunta: 'Vocês atendem empresas de Caxias do Sul e do interior gaúcho?',
    resposta:
      'Sim, do mesmo jeito que em Porto Alegre. Para uma empresa da Serra ou de outra região do estado, a lista de envio junta o jornal da cidade, a imprensa da capital e os veículos de economia e os setoriais de alcance nacional.',
  },
  {
    pergunta: 'Dá para garantir matéria na Zero Hora, no Correio do Povo ou no Jornal do Comércio?',
    resposta:
      'Não. Esses veículos vendem publieditorial e brand content, mas reportagem não se compra, e a Mclair não tem parceria com eles. A pauta é apresentada ao jornalista que cobre o assunto, com dado do Rio Grande do Sul e porta-voz disponível. Se ele achar relevante para o leitor, publica.',
  },
  {
    pergunta: 'Minha empresa deve focar na imprensa gaúcha ou na nacional?',
    resposta:
      'Depende de quem compra de você. Se o cliente está no estado, a imprensa do RS costuma render mais do que uma nota perdida num portal nacional. Se a empresa exporta ou vende para o país todo, vale combinar as duas, começando pela pauta que tem mais chance em cada uma.',
  },
  {
    pergunta: 'A Mclair já trabalhou com indústria e agronegócio?',
    resposta:
      'Sim. No programa Brazil Machinery Solutions, da ABIMAQ com a ApexBrasil, a Mclair cobriu feiras como a Agrishow e a FEIMEC e levou a indústria de máquinas a mais de 2 mil inserções na mídia, 1.734 delas fora do Brasil.',
  },
  {
    pergunta: 'Como fico sabendo do que saiu sobre a minha empresa?',
    resposta:
      'Cada publicação entra no clipping com o nome do veículo, a data e o link. No relatório periódico vêm também as pautas em andamento e os pedidos de entrevista que chegaram das redações, gaúchas ou não.',
  },
];
