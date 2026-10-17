/**
 * Texto próprio das cinco páginas de serviço que vêm do CMS e ganharam
 * conteúdo ampliado (tarefa T10 da revisão de 17/10/2026):
 *   /servicos/marketing-de-autoridade/
 *   /servicos/branding-estrategico/
 *   /servicos/marketing-digital/
 *   /servicos/consultoria-em-comunicacao/
 *   /servicos/mentorias-exclusivas/
 *
 * Mesmo princípio de assessoria-imprensa.ts: o CMS continua mandando no
 * hero (intro, CTA, entregas) e no bloco "headline + texto"; o que está aqui
 * soma o resto da página (como funciona, cases, quem conduz, FAQ e
 * formulário) e alimenta title, description e FAQPage em
 * src/pages/servicos/[...slug].astro. O componente que desenha a página é
 * components/servicos/ServicoAmpliado.astro.
 *
 * Fatos e números saem só dos cases publicados em /cases/ e das páginas
 * /sobre/, /kelly-pinheiro/ e /mentorias/. Nada de prazo, preço ou
 * depoimento inventado. Busca principal de cada página: Semrush, base br,
 * 17/10/2026 (o mapa de palavras não tinha cluster para estes serviços).
 *
 * Os textos com link (abertura, explicações, etapas, cases, listas e
 * Kelly) saem em set:html porque são fixos do código. As respostas do FAQ
 * são texto simples, iguais no HTML e no JSON-LD.
 */
import type { Pergunta } from './assessoria-imprensa';

const wa = (texto: string) => 'https://wa.me/5511988600372?text=' + encodeURIComponent(texto);

const KELLY = '/kelly-pinheiro/';
const HUB = '/servicos/assessoria-de-imprensa/';
const CRISE = '/servicos/gestao-de-crise/';
const MEDIA_TRAINING = '/servicos/media-training/';

export type CaseLigado = { href: string; nome: string; texto: string };

export type ServicoAmpliado = {
  seo: { title: string; h1: string; description: string; keywords: string };
  serviceType: string;
  wa: string;
  aberturaTitulo: string;
  abertura: string[];
  lateral: string;
  entregasIntro: string;
  explicacoes: Record<string, string>;
  etapasTitulo: string;
  etapasIntro?: string;
  etapas: { titulo: string; texto: string }[];
  casesTitulo: string;
  casesIntro: string;
  cases: CaseLigado[];
  paraQuem: { titulo: string; itens: string[] };
  naoE: { titulo: string; itens: string[] };
  kelly: { foto: string; alt: string; paragrafos: string[] };
  faqTitulo: string;
  faq: Pergunta[];
  form: {
    id: string;
    titulo: string;
    assunto: string;
    waIntro: string;
    perguntaLabel: string;
    perguntaDica: string;
    perguntaRotulo: string;
    opcoesLabel: string;
    opcoes: string[];
    opcoesRotulo: string;
    texto: string;
  };
};

const AVISO_FORM = 'Quando você enviar, a mensagem chega à Kelly Pinheiro por e-mail e o WhatsApp da Mclair abre com o texto pronto.';

/* --------------------------- Marketing de autoridade --------------------------- */

const MARKETING_AUTORIDADE: ServicoAmpliado = {
  seo: {
    title: 'Marketing de Autoridade para Empresas e Executivos | Mclair',
    h1: 'Marketing de autoridade para empresas e executivos',
    description:
      'Marketing de autoridade com imprensa, conteúdo e marca pessoal para a sua empresa ou o seu executivo virar referência. Cases com Exame, CNN Brasil e HSM.',
    keywords:
      'marketing de autoridade, autoridade de marca, marketing de autoridade para empresas, posicionamento de autoridade, marca pessoal executivo, mclair',
  },
  serviceType: 'Marketing de autoridade',
  wa: wa('Olá Mclair! Vim pela página de Marketing de Autoridade e quero conversar sobre o posicionamento da minha empresa ou do meu executivo.'),
  aberturaTitulo: 'Autoridade é o que aparece quando alguém procura o seu nome',
  abertura: [
    'Antes de responder ao seu e-mail, o diretor de compras digita o nome da sua empresa no Google. Antes de chamar um executivo para o conselho, alguém lê o que ele já escreveu. Marketing de autoridade é o trabalho de fazer com que, nessa busca, apareça a prova de que você sabe do que fala: uma entrevista, um artigo assinado, uma coluna, um episódio de podcast, um site que explica o método.',
    'Na Mclair, essa prova se constrói com três frentes que se reforçam: presença na imprensa, conteúdo com substância e uma marca, pessoal ou corporativa, que diga com clareza quem é. Kelly Pinheiro chama o contrário disso de marketing de fachada, muito post e pouca prova. A proposta dela tem nome, desmarketização: tirar o ruído do caminho e deixar aparecer o que o profissional ou a empresa realmente sabe fazer.',
    `Uma matéria na Exame ou na CNN Brasil tem um peso que nenhum post próprio alcança, porque é um terceiro dizendo que você é fonte. Por isso a <a href="${HUB}">assessoria de imprensa</a> está no centro do trabalho, e o conteúdo próprio serve para sustentar o que a imprensa publicou.`,
  ],
  lateral: 'A primeira conversa é o diagnóstico: o que o mercado já sabe sobre você e o que deveria saber. A proposta vem depois dela.',
  entregasIntro: 'O que entra no trabalho, frente por frente:',
  explicacoes: {
    'Mentoria em posicionamento digital e marca pessoal':
      'Encontros com o executivo ou especialista para definir em que tema ele quer ser lembrado, qual história sustenta essa escolha e onde ela deve aparecer primeiro. Para quem prefere aprender o método em um programa próprio, o caminho são as <a href="/mentorias/">mentorias Bolder e Bolder Press</a>.',
    'Estratégias de conteúdo para Marketing de Autoridade':
      'Pauta de artigos, colunas, posts e episódios a partir do que o cliente sabe e o mercado ainda não ouviu. Conteúdo que ensina alguma coisa, assinado por quem entende do assunto.',
    'Gestão de presença digital multiplataforma':
      'LinkedIn, Instagram, site e canais próprios falando a mesma língua, com calendário e revisão, para que quem chega por um deles encontre a mesma pessoa nos outros.',
    'Diagnóstico e auditoria de autoridade de marca':
      'Antes de produzir qualquer coisa, um levantamento do que já existe: o que o Google mostra sobre o nome, o que a imprensa já publicou, como os concorrentes se posicionam e onde há espaço vazio.',
    'Planejamento estratégico de comunicação B2B':
      'Para quem vende para empresas, o plano liga autoridade a venda: que temas interessam ao comprador, quais veículos ele lê e quem, dentro da empresa, deve falar sobre cada assunto.',
    'Monitoramento de reputação e percepção de mercado':
      'Acompanhamento do que sai na imprensa e nas redes sobre a marca e o porta-voz, com relatório periódico e ajuste de rota quando a percepção não bate com o posicionamento.',
  },
  etapasTitulo: 'Como o trabalho anda',
  etapas: [
    { titulo: 'Diagnóstico', texto: 'A conversa inicial e a auditoria mostram o ponto de partida: o que já se diz sobre você, o que falta dizer e o que atrapalha.' },
    { titulo: 'Território', texto: 'A escolha de um ou dois temas em que a marca ou o especialista quer ser referência, com a narrativa e as provas que sustentam essa escolha.' },
    { titulo: 'Conteúdo e canais', texto: 'Calendário de artigos, posts, vídeos e episódios nos canais que o público certo usa, não nos que estão na moda.' },
    { titulo: 'Imprensa', texto: `As pautas com interesse jornalístico vão para as redações pela <a href="${HUB}">assessoria de imprensa</a>, com o porta-voz preparado para a entrevista.` },
    { titulo: 'Medição e ajuste', texto: 'Relatório com o que saiu, onde saiu e o que mudou na percepção. O plano é revisto a cada ciclo.' },
  ],
  casesTitulo: 'Especialistas que viraram fonte',
  casesIntro: 'Cada case tem as matérias publicadas, com veículo e data, na própria página.',
  cases: [
    { href: '/cases/diego-nogare/', nome: 'Diego Nogare', texto: 'Especialista em dados e inteligência artificial, com passagem por Microsoft, Deloitte, Bayer e Itaú. A pauta construída com ele rendeu duas matérias seguidas na home da CNN Brasil, artigo na Exame sobre vibe coding, coluna na HSM Management e textos no IT Forum e no Baguete.' },
    { href: '/cases/claudia-elisa/', nome: 'Claudia Elisa', texto: 'Conselheira, mentora e palestrante com mais de 30 anos de carreira. A Mclair construiu três marcas (Claudia Elisa, o podcast Liderança em Pauta e a Mentoria Sharp) e levou Claudia a artigo na Exame e a entrevista de TV sobre empresas familiares.' },
    { href: '/cases/alexandre-magno/', nome: 'Alexandre Magno', texto: 'Depois da comunicação da aquisição da Emergee pelo Itaú, veio o trabalho de marca pessoal do consultor de transformação organizacional e o lançamento da The Cynefin Co. Brazil, com cobertura na HSM Management e entrevista ao MIT Sloan Review Brasil.' },
  ],
  paraQuem: {
    titulo: 'Para quem faz sentido',
    itens: [
      'Executivos e especialistas que sabem muito e são pouco conhecidos fora da própria empresa.',
      'Empresas B2B com venda longa, em que o cliente pesquisa bastante antes de marcar a primeira reunião.',
      'Consultores, conselheiros e mentores que vivem da própria reputação.',
      'Fundadores que querem que a empresa seja lembrada também pelo que eles pensam sobre o setor.',
    ],
  },
  naoE: {
    titulo: 'Para quem não faz',
    itens: [
      'Quem quer seguidor rápido. Autoridade leva meses para aparecer e anos para se consolidar.',
      'Quem não terá tempo para escrever, gravar ou dar entrevista. A equipe prepara e organiza, mas a voz precisa ser sua.',
    ],
  },
  kelly: {
    foto: 'kelly-pinheiro-blazer-branco',
    alt: 'Kelly Pinheiro, jornalista e fundadora da Mclair, de blazer branco',
    paragrafos: [
      `A estratégia passa por <a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a>, jornalista desde 2004 e fundadora da Mclair. Ela foi colunista da Exame por dois anos e meio e tem artigos na HSM e no Grandes Nomes da Propaganda, ou seja, conhece por dentro o caminho que propõe aos clientes.`,
      'A conta é atendida por uma equipe de jornalistas, estrategistas de marca e especialistas em digital, com Kelly à frente da relação com o cliente.',
    ],
  },
  faqTitulo: 'Perguntas sobre marketing de autoridade',
  faq: [
    {
      pergunta: 'O que é marketing de autoridade?',
      resposta:
        'É o conjunto de ações que faz uma empresa ou um profissional ser reconhecido como referência em um tema. Junta presença na imprensa, conteúdo próprio com substância e uma marca coerente em todos os canais. O objetivo é ser lembrado e procurado antes da concorrência.',
    },
    {
      pergunta: 'Qual a diferença entre marketing de autoridade e marketing de conteúdo?',
      resposta:
        'O marketing de conteúdo produz material para atrair e converter público. O marketing de autoridade também usa conteúdo, mas mira reputação: quem fala, sobre qual tema e com quais provas de terceiros, como entrevistas e artigos em veículos que o mercado respeita.',
    },
    {
      pergunta: 'Quanto tempo leva para construir autoridade?',
      resposta:
        'Não há prazo fixo. O primeiro artigo ou a primeira entrevista dependem da pauta e do calendário das redações. O reconhecimento de mercado vem da presença constante, ao longo de meses e anos, e o relatório mostra essa evolução.',
    },
    {
      pergunta: 'Serve para empresa ou só para pessoa física?',
      resposta:
        'Para as duas. Em empresas, o trabalho costuma combinar a marca corporativa com um ou mais porta-vozes, como na EximiaCo, de Elemar Jr. Para profissionais independentes, a marca pessoal é o centro, como nos projetos de Claudia Elisa e Diego Nogare.',
    },
    {
      pergunta: 'Vocês garantem que vou sair na Exame ou na CNN Brasil?',
      resposta:
        'Não. Quem decide o que sai é o jornalista. A Mclair constrói pauta com interesse real e apresenta ao repórter que cobre o assunto. Foi assim que clientes de marketing de autoridade saíram na Exame, na CNN Brasil e na HSM Management.',
    },
    {
      pergunta: 'Preciso escrever os artigos sozinho?',
      resposta:
        'Não. A equipe ajuda a definir a pauta, conversa com você, organiza as ideias e revisa o texto. O conhecimento e a opinião são seus; a forma de chegar ao leitor é trabalho conjunto.',
    },
    {
      pergunta: 'Quanto custa o marketing de autoridade da Mclair?',
      resposta:
        'Depende do escopo: quantas pessoas falam pela marca, quais canais entram, se há assessoria de imprensa junto e com que frequência. A proposta é montada depois do diagnóstico, que é a primeira conversa.',
    },
  ],
  form: {
    id: 'wa-form-marketing-autoridade',
    titulo: 'Conte em que tema você quer ser referência',
    assunto: 'Marketing de autoridade (página do serviço)',
    waIntro: 'Olá Mclair! Vim pela página de Marketing de Autoridade e quero conversar sobre posicionamento.',
    perguntaLabel: 'Quem vai ser a voz e em que tema?',
    perguntaDica: 'Pode ser você, um executivo da empresa ou a própria marca. Diga o assunto em que quer ser lembrado.',
    perguntaRotulo: 'Voz e tema',
    opcoesLabel: 'Onde você está hoje?',
    opcoes: ['Começando do zero', 'Já publico, mas sem estratégia', 'Já saio na imprensa e quero ir além', 'Ainda não sei'],
    opcoesRotulo: 'Ponto de partida',
    texto: 'Se já existe um lançamento, um livro ou um evento pela frente, conte a data. Isso muda a ordem do plano.',
  },
};

/* ---------------------------- Branding estratégico ---------------------------- */

const BRANDING: ServicoAmpliado = {
  seo: {
    title: 'Agência de Branding Estratégico em São Paulo | Mclair',
    h1: 'Branding estratégico para empresas e marcas pessoais',
    description:
      'Agência de branding em São Paulo: posicionamento, naming, identidade visual e rebranding num sistema só. Cases como nexinvoice, Insight RH e EximiaCo.',
    keywords:
      'agência de branding, branding estratégico, rebranding, naming, identidade visual, posicionamento de marca, agência de branding são paulo, mclair',
  },
  serviceType: 'Branding',
  wa: wa('Olá Mclair! Vim pela página de Branding Estratégico e quero conversar sobre a marca da minha empresa.'),
  aberturaTitulo: 'Primeiro a estratégia, depois o logo',
  abertura: [
    'Empresa com marca confusa passa a reunião inteira explicando o que faz. Empresa com marca clara chega à reunião já explicada. Branding estratégico é o trabalho de decidir quem a empresa é, para quem fala e por que merece ser escolhida, e de traduzir essa decisão em nome, identidade visual, tom de voz e materiais.',
    'Na Mclair, o desenho vem depois dessa decisão. Antes de qualquer esboço vem a pergunta que quase ninguém responde bem na primeira conversa: o que a sua marca promete que a concorrente não pode prometer? Com essa resposta, cada escolha de cor, nome e palavra passa a ter critério. Sem ela, o logo mais bonito vira enfeite.',
    'Como a Mclair nasceu como agência de comunicação, a marca nova não fica na gaveta. Ela sai para o site, para as redes e, quando há notícia, para a imprensa. Vários dos projetos de branding abaixo vieram junto com assessoria de imprensa e gestão de redes sociais.',
  ],
  lateral: 'Marca nova, rebranding, nome para um produto ou marca pessoal: a primeira conversa mostra qual é o projeto, e a proposta vem depois.',
  entregasIntro: 'O que entra no projeto de branding:',
  explicacoes: {
    'Estratégia de marca e posicionamento de mercado':
      'Estudo do público, dos concorrentes e do que a empresa faz melhor, até chegar a um posicionamento que caiba em uma frase e sirva de filtro para as decisões seguintes.',
    'Identidade visual e manual de marca completo':
      'Logo, cores, tipografia e o manual que explica como usar cada elemento, para que a marca saia igual no site, no cartão de visita e na apresentação comercial.',
    'Tom de voz, linguagem e personalidade da marca':
      'Como a marca fala: as palavras que usa, as que evita e o grau de formalidade com cada público. É o que faz um post e um e-mail soarem como a mesma empresa.',
    'Redesign e rebranding estratégico':
      'Para marcas que mudaram de tamanho, de público ou de negócio. Às vezes a resposta é uma revisão da identidade; às vezes, um nome novo. O diagnóstico diz qual.',
    'Aplicações e materiais gráficos institucionais':
      'Papelaria, apresentações, embalagens, brindes, site e redes com a identidade aplicada. Nos projetos de Claudia Elisa e do nexinvoice, isso chegou às embalagens e aos brindes.',
    'Brand guidelines e governança de marca':
      'Regras para quem vai usar a marca depois que o projeto termina, da equipe interna aos fornecedores, para que ela não se perca no primeiro folder feito às pressas.',
  },
  etapasTitulo: 'Como funciona um projeto de branding',
  etapas: [
    { titulo: 'Imersão', texto: 'Conversas com fundadores e equipe, leitura do mercado e dos concorrentes. Na Zbra, por exemplo, o trabalho começou com benchmarking e estudo de público e segmento.' },
    { titulo: 'Posicionamento e conceito', texto: 'A frase que resume a marca, os atributos que ela precisa transmitir e, quando é o caso, o nome. Foi nessa etapa que nasceram o nexinvoice e o Insight LAB.' },
    { titulo: 'Identidade visual', texto: 'Logo, paleta, tipografia e elementos gráficos criados a partir do conceito. Se fizer sentido, até uma mascote, como a Annie do nexinvoice.' },
    { titulo: 'Linguagem e manual', texto: 'Tom de voz e manual de marca com as regras de uso, entregues para a equipe do cliente.' },
    { titulo: 'Lançamento', texto: 'A marca chega ao mercado pelo site, pelas redes e, quando há notícia, pela imprensa. O rebranding da Zbra saiu na Exame e no Grandes Nomes da Propaganda.' },
  ],
  casesTitulo: 'Marcas que a Mclair criou ou reposicionou',
  casesIntro: 'Os projetos completos, com imagens da identidade aplicada, estão nas páginas de cada case.',
  cases: [
    { href: '/cases/nexinvoice/', nome: 'Guiando / nexinvoice', texto: 'Nome, conceito e identidade visual de uma solução de automação financeira criados do zero, além da Annie, robô que homenageia a cientista da NASA Annie J. Easley. Quando o nexinvoice foi adquirido pela Asaas, a comunicação visual feita pela Mclair foi mantida.' },
    { href: '/cases/insight-rh/', nome: 'Insight RH', texto: 'Rebranding da consultoria de liderança e gestão de pessoas, com site, redes sociais, landing page com teste de maturidade de liderança e o naming do Insight LAB, a escola de liderança da empresa.' },
    { href: '/cases/elemar-jr/', nome: 'Elemar Jr. / EximiaCo', texto: 'Branding completo da empresa de arquitetura de software, da identidade ao posicionamento, com as submarcas EximiaCo.tech e EximiaCo.ms e o podcast Drops da EximiaCo.' },
    { href: '/cases/zbra/', nome: 'Zbra', texto: 'Depois do rebranding, toda a comunicação da empresa de tecnologia foi ajustada à nova marca, das redes sociais à imprensa, com cobertura na Exame e no Grandes Nomes da Propaganda.' },
    { href: '/cases/silene-chiconini/', nome: 'Silene Chiconini', texto: 'Marca pessoal para uma consultora em reposicionamento de carreira, que trabalha na interseção entre comunicação e sustentabilidade, com identidade visual e paleta próprias.' },
  ],
  paraQuem: {
    titulo: 'Quando chamar',
    itens: [
      'A empresa cresceu e continua com cara de quando era pequena.',
      'Um produto ou negócio novo precisa de nome e identidade antes de chegar ao mercado.',
      'Houve fusão, aquisição ou mudança de foco, e a marca antiga não conta mais a história certa.',
      'Um profissional quer transformar a própria trajetória em marca pessoal, com nome, visual e discurso coerentes.',
    ],
  },
  naoE: {
    titulo: 'Quando não é o caso',
    itens: [
      'Quando o que falta é só um logo para ontem. Branding estratégico pede tempo de conversa antes do desenho.',
      'Quando ninguém da liderança vai participar da imersão. A marca sai das respostas de quem decide.',
      'Quando o problema é de produto ou de preço. Marca nova não conserta oferta fraca.',
    ],
  },
  kelly: {
    foto: 'kelly-pinheiro-mesa-janela',
    alt: 'Kelly Pinheiro, fundadora da Mclair, à mesa perto da janela',
    paragrafos: [
      `Os projetos de branding são conduzidos sob a direção de <a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a>, jornalista desde 2004, pós-graduada em Marketing Estratégico e fundadora da Mclair. Antes de abrir a agência, em 2017, ela trabalhou em comunicação corporativa e gestão de conteúdo para empresas nacionais e multinacionais.`,
      'A equipe junta estrategistas de marca e jornalistas. É essa mistura que faz a marca nova chegar ao mercado com uma história para contar, e não só com um manual bonito.',
    ],
  },
  faqTitulo: 'Perguntas sobre branding',
  faq: [
    {
      pergunta: 'O que é branding estratégico?',
      resposta:
        'É a gestão da marca a partir de uma decisão de posicionamento: quem a empresa é, para quem fala e por que deve ser escolhida. Nome, identidade visual, linguagem e materiais são consequência dessa decisão, não o ponto de partida.',
    },
    {
      pergunta: 'Qual a diferença entre branding e identidade visual?',
      resposta:
        'Identidade visual é a parte que se vê: logo, cores, tipografia. Branding é o conjunto, que inclui também posicionamento, nome, tom de voz e a experiência que a marca entrega. Dá para ter um logo bonito e um branding fraco.',
    },
    {
      pergunta: 'Quando vale a pena fazer um rebranding?',
      resposta:
        'Quando a marca deixou de representar a empresa: mudou o público, o tamanho, o portfólio ou o próprio negócio. Também depois de uma fusão ou aquisição. Se o problema é só desgaste visual, uma revisão da identidade pode bastar.',
    },
    {
      pergunta: 'Vocês criam o nome da empresa ou do produto?',
      resposta:
        'Sim. Naming faz parte do serviço quando o projeto pede. O nexinvoice, do time Guiando, e o Insight LAB, da Insight RH, foram batizados pela Mclair.',
    },
    {
      pergunta: 'Vocês fazem marca pessoal?',
      resposta:
        'Sim. Claudia Elisa, Silene Chiconini e Elemar Jr. são exemplos de projetos de marca pessoal da Mclair, com identidade visual, posicionamento e, em alguns casos, assessoria de imprensa.',
    },
    {
      pergunta: 'Quanto tempo leva um projeto de branding?',
      resposta:
        'Depende do tamanho. Uma marca pessoal é um projeto diferente de um rebranding com submarcas e site novo. O cronograma é fechado na proposta, depois da primeira conversa.',
    },
    {
      pergunta: 'Quanto custa um projeto de branding na Mclair?',
      resposta:
        'O valor sai do escopo: se há naming, quantas submarcas, quantas aplicações e se o lançamento inclui imprensa e redes sociais. A proposta vem depois do diagnóstico.',
    },
  ],
  form: {
    id: 'wa-form-branding',
    titulo: 'Conte o que a sua marca precisa',
    assunto: 'Branding estratégico (página do serviço)',
    waIntro: 'Olá Mclair! Vim pela página de Branding Estratégico e quero conversar sobre a minha marca.',
    perguntaLabel: 'O que está acontecendo com a marca hoje?',
    perguntaDica: 'Marca nova, rebranding, nome para um produto, marca pessoal. Uma ou duas frases bastam.',
    perguntaRotulo: 'Situação',
    opcoesLabel: 'O que você procura?',
    opcoes: ['Marca nova', 'Rebranding', 'Nome para empresa ou produto', 'Marca pessoal', 'Ainda não sei'],
    opcoesRotulo: 'Tipo de projeto',
    texto: 'Se puder, mande também o site ou o perfil atual da marca na mensagem. Ajuda a primeira conversa a ir direto ao ponto.',
  },
};

/* ------------------------------ Marketing digital ------------------------------ */

const MARKETING_DIGITAL: ServicoAmpliado = {
  seo: {
    title: 'Marketing Digital para Empresas: SEO, Ads e Redes | Mclair',
    h1: 'Marketing digital para empresas, com papel para cada canal',
    description:
      'Marketing digital para empresas: SEO, conteúdo, redes, Google Ads e Meta Ads com papel definido no funil. Case Globo Leilões: 5,7 milhões de visualizações.',
    keywords:
      'marketing digital para empresas, agência de marketing digital, agência de marketing b2b, marketing b2b, seo, google ads, meta ads, gestão de redes sociais, mclair',
  },
  serviceType: 'Marketing digital',
  wa: wa('Olá Mclair! Vim pela página de Marketing Digital e quero conversar sobre os canais digitais da minha empresa.'),
  aberturaTitulo: 'Cada canal com uma função',
  abertura: [
    'Muita empresa tem Instagram, site, uma campanha no Google e uma newsletter, e nenhum desses canais sabe o que o outro está fazendo. O resultado aparece no fim do mês: muito clique e pouco cliente. O marketing digital da Mclair começa dando a cada canal uma função.',
    'O SEO atrai quem já está pesquisando o assunto. O conteúdo e as redes aquecem quem ainda não está pronto para comprar. A mídia paga acelera quando há pressa ou uma data, como um lançamento ou um leilão. O e-mail e o CRM cuidam de quem já deixou contato. E tudo é medido, para que o dinheiro saia do que não funciona e vá para o que funciona.',
    `Numa agência que nasceu na imprensa, a reputação entra na conta. Uma matéria na Exame vira post, argumento de anúncio e link para o site. No case da Globo Leilões, a <a href="${HUB}">assessoria de imprensa</a> e o digital trabalharam juntos, leilão a leilão.`,
  ],
  lateral: 'A primeira conversa olha o que você já tem no ar: site, redes, campanhas e números. A proposta sai desse diagnóstico.',
  entregasIntro: 'O que entra no trabalho, canal por canal:',
  explicacoes: {
    'Estratégia e planejamento digital integrado':
      'Um plano que define público, canais, verba e metas, e diz qual canal faz o quê. É o documento que impede a empresa de estar em toda rede sem estar bem em nenhuma.',
    'SEO e marketing de conteúdo estratégico':
      'Pesquisa das buscas que o seu cliente faz, páginas e artigos que respondem a elas e ajustes técnicos para o Google entender o que você vende.',
    'Gestão de mídias sociais com foco em engajamento':
      'Calendário, roteiros, Reels, Stories e posts pensados para o público que compra, não só para quem curte. Na Globo Leilões, o conteúdo semanal aquecia a base antes de cada praça.',
    'Tráfego pago: Google Ads e Meta Ads':
      'Campanhas de pesquisa, de redes sociais e de remarketing, com verba controlada, meta de custo por contato e ajuste frequente.',
    'Automação de marketing e CRM':
      'E-mails e fluxos automáticos para quem já se cadastrou, com os contatos organizados no CRM, para que nenhum interessado fique sem resposta.',
    'Analytics, relatórios e otimização contínua':
      'Medição do clique ao contato e relatório em linguagem de negócio: o que trouxe cliente, o que custou caro e o que muda no ciclo seguinte.',
  },
  etapasTitulo: 'Como funciona',
  etapas: [
    { titulo: 'Diagnóstico', texto: 'Levantamento do que já existe: site, redes, campanhas, contas de anúncio e dados de venda. Muitas vezes o primeiro ganho está em consertar a medição.' },
    { titulo: 'Plano por canal', texto: 'Cada canal recebe uma função no funil, uma meta e uma verba, combinadas com o cliente.' },
    { titulo: 'Produção e campanhas', texto: 'Conteúdo, páginas, anúncios e e-mails no ar, seguindo o calendário do negócio: lançamentos, datas do setor, eventos.' },
    { titulo: 'Medição', texto: 'Acompanhamento dos números durante o mês e relatório periódico com o que trouxe resultado e o que não trouxe.' },
    { titulo: 'Ajuste', texto: 'O que não performa sai, o que funciona ganha verba. O plano é revisto a cada ciclo.' },
  ],
  casesTitulo: 'Digital ligado à reputação',
  casesIntro: 'Nos cases abaixo, o digital trabalhou junto com marca e imprensa. Os números são os publicados em cada página.',
  cases: [
    { href: '/cases/globo-leiloes/', nome: 'Globo Leilões', texto: 'Leiloeira parceira da Caixa que precisava transformar alcance em cadastros e lances. A Mclair juntou assessoria de imprensa a cada novo leilão, conteúdo semanal em Reels e Stories e e-mail marketing para os contatos captados. O case registra 685 inserções na mídia, 5,7 milhões de visualizações no Instagram e no TikTok e 963 mil contas alcançadas, em média, por mês.' },
    { href: '/cases/insight-rh/', nome: 'Insight RH', texto: 'Uma consultoria de liderança sem área de marketing organizada. O trabalho criou site, redes sociais e uma landing page com teste interativo de maturidade de liderança, dentro de um rebranding completo.' },
    { href: '/cases/lambda3/', nome: 'Lambda3', texto: 'Cinco anos como braço de comunicação da consultoria de tecnologia, desde 2018, com assessoria de imprensa e comunicação digital integradas, produção de infoprodutos e campanhas de employer branding nas redes.' },
    { href: '/cases/zbra/', nome: 'Zbra', texto: 'Gestão das redes sociais alinhada à marca nova, com a série ZTalks e conteúdo de cultura de equipe, junto com assessoria de imprensa para os porta-vozes.' },
  ],
  paraQuem: {
    titulo: 'Para quem faz sentido',
    itens: [
      'Empresas que já investem em digital e não sabem dizer o que dá retorno.',
      'Negócios com datas fortes, como leilões, lançamentos e eventos, que precisam de alcance concentrado em poucos dias.',
      'Empresas B2B que querem ser encontradas por quem pesquisa a solução no Google.',
      'Marcas que passaram por rebranding e precisam levar a nova identidade para todos os canais.',
    ],
  },
  naoE: {
    titulo: 'Para quem não faz',
    itens: [
      'Quem quer só seguidores. A meta aqui é contato e venda, e curtida é meio.',
      'Quem não tem site ou página para receber o clique. Nesse caso, o primeiro passo é construir esse destino.',
      'Quem não consegue atender os contatos que chegam. Lead sem resposta é verba perdida.',
    ],
  },
  kelly: {
    foto: 'kelly-pinheiro-cafe-notebook',
    alt: 'Kelly Pinheiro, fundadora da Mclair, trabalhando no notebook',
    paragrafos: [
      `A estratégia digital fica sob a direção de <a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a>, jornalista desde 2004, pós-graduada em Marketing Estratégico e fundadora da Mclair. Em 2020, a agência ganhou um braço tecnológico e digital, no mesmo ano em que cresceu 200% em clientes e em faturamento.`,
      'O dia a dia das contas é tocado por especialistas em performance digital, ao lado dos jornalistas e estrategistas de marca da casa. É o que permite usar a mesma história na imprensa, no anúncio e na rede social.',
    ],
  },
  faqTitulo: 'Perguntas sobre marketing digital',
  faq: [
    {
      pergunta: 'O que entra no marketing digital da Mclair?',
      resposta:
        'Planejamento, SEO e conteúdo, redes sociais, Google Ads e Meta Ads, automação e CRM, e relatórios. Nem todo cliente precisa de tudo: o diagnóstico define quais canais entram e com que peso.',
    },
    {
      pergunta: 'Vocês atendem empresas B2B?',
      resposta:
        'Sim. Lambda3, Zbra e Insight RH são exemplos de clientes que vendem para outras empresas. No B2B, o ciclo de venda é mais longo, e o conteúdo e o SEO costumam pesar mais do que no varejo.',
    },
    {
      pergunta: 'Quanto devo investir em anúncios?',
      resposta:
        'Não existe um número certo para todo mundo. A verba depende do custo por clique do seu setor, da meta de contatos e da margem do produto. O plano parte de um valor combinado e é ajustado pelo resultado.',
    },
    {
      pergunta: 'Em quanto tempo aparecem os resultados?',
      resposta:
        'A mídia paga traz visitas desde o primeiro dia de campanha. SEO e conteúdo levam meses para ganhar posição no Google. Por isso o plano costuma combinar os dois.',
    },
    {
      pergunta: 'Como vou saber se está funcionando?',
      resposta:
        'Pelo relatório: contatos gerados, custo de cada um, de onde vieram e, quando o CRM permite medir, quantos viraram venda. Curtida e alcance aparecem, mas não são a meta.',
    },
    {
      pergunta: 'Dá para juntar marketing digital com assessoria de imprensa?',
      resposta:
        'Dá. No case Globo Leilões, as matérias na imprensa e o conteúdo nas redes trabalharam juntos antes de cada leilão, e uma reforçava a outra.',
    },
    {
      pergunta: 'Quanto custa o marketing digital da Mclair?',
      resposta:
        'Depende de quantos canais entram, do volume de conteúdo e da gestão de mídia paga. A verba dos anúncios é separada do valor do serviço. A proposta vem depois do diagnóstico.',
    },
  ],
  form: {
    id: 'wa-form-marketing-digital',
    titulo: 'Conte onde o seu digital está hoje',
    assunto: 'Marketing digital (página do serviço)',
    waIntro: 'Olá Mclair! Vim pela página de Marketing Digital e quero conversar sobre a minha empresa.',
    perguntaLabel: 'Qual é o principal objetivo?',
    perguntaDica: 'Mais contatos, mais vendas, lançar um produto, organizar o que já existe.',
    perguntaRotulo: 'Objetivo',
    opcoesLabel: 'Já investe em anúncios?',
    opcoes: ['Ainda não', 'Sim, no Google', 'Sim, no Instagram e no Facebook', 'Sim, nos dois', 'Não sei dizer'],
    opcoesRotulo: 'Anúncios',
    texto: 'Se tiver site, mande o endereço na mensagem. A primeira conversa já começa olhando para ele.',
  },
};

/* ------------------------- Consultoria em comunicação ------------------------- */

const CONSULTORIA: ServicoAmpliado = {
  seo: {
    title: 'Consultoria de Comunicação para Empresas | Mclair',
    h1: 'Consultoria de comunicação para empresas',
    description:
      'Consultoria de comunicação para empresas: diagnóstico, comparação com concorrentes e plano de comunicação com prioridades, com a jornalista Kelly Pinheiro.',
    keywords:
      'consultoria de comunicação, consultoria em comunicação, consultoria em comunicação empresarial, plano de comunicação, diagnóstico de comunicação, comunicação estratégica, mclair',
  },
  serviceType: 'Consultoria em comunicação',
  wa: wa('Olá Mclair! Vim pela página de Consultoria em Comunicação e quero conversar sobre a comunicação da minha empresa.'),
  aberturaTitulo: 'Um olhar de fora, com plano na mão',
  abertura: [
    'Às vezes a empresa não precisa de mais um fornecedor executando. Precisa de alguém de fora que olhe tudo o que ela comunica, do site à fala do presidente, e diga com franqueza o que funciona, o que atrapalha e por onde começar. É isso que a consultoria em comunicação da Mclair faz.',
    'O trabalho termina num plano de comunicação com as ações em ordem de impacto. A empresa decide o que executar, quando e com quem: com a equipe interna, com outros fornecedores ou com a própria Mclair.',
    `A consultoria é conduzida por quem já esteve dentro de áreas de comunicação. Antes de fundar a Mclair, <a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a> trabalhou em comunicação corporativa, gestão de conteúdo e gerenciamento de crise para instituições governamentais e para empresas nacionais e multinacionais.`,
  ],
  lateral: 'A primeira conversa serve para entender o momento da empresa e o tamanho do diagnóstico. A proposta vem depois.',
  entregasIntro: 'O que a consultoria cobre:',
  explicacoes: {
    'Diagnóstico completo de comunicação corporativa':
      'Leitura de tudo o que a empresa publica e diz: site, redes, materiais comerciais, presença na imprensa, comunicação interna e discurso dos porta-vozes, com conversas com a liderança e comparação com os concorrentes diretos.',
    'Desenvolvimento de plano estratégico integrado':
      'Públicos, mensagens, canais e ações num documento só, com responsável e prazo, para que marketing, imprensa e RH falem a mesma coisa.',
    'Gestão de crises e reputação de marca':
      `Mapa dos riscos de reputação e protocolo de resposta: quem fala, quem aprova, em quanto tempo. Para uma crise já em andamento, veja a página de <a href="${CRISE}">gestão de crise de imagem</a>.`,
    'Comunicação institucional e stakeholders':
      'Como a empresa se apresenta a investidores, parceiros, governo, comunidade e colaboradores, cada público com a sua mensagem e o seu canal.',
    'Treinamento e capacitação de porta-vozes':
      `Preparação de quem fala pela empresa, com mensagens-chave e simulação de entrevista. O formato completo está na página de <a href="${MEDIA_TRAINING}">media training</a>.`,
    'Planejamento de comunicação anual':
      'Calendário do ano com datas do setor, lançamentos, resultados e eventos, para que a comunicação deixe de ser reação e passe a ter agenda.',
  },
  etapasTitulo: 'Como funciona a consultoria',
  etapas: [
    { titulo: 'Escuta', texto: 'Conversas com a liderança e com as áreas que falam com o mercado: o que a empresa quer ser, o que acredita ser e o que os números mostram.' },
    { titulo: 'Diagnóstico', texto: 'Análise dos canais, dos materiais e da presença na imprensa, lado a lado com os concorrentes diretos.' },
    { titulo: 'Lacunas', texto: 'O relatório aponta onde a empresa perde presença, onde se contradiz e onde há espaço que ninguém ocupa.' },
    { titulo: 'Plano de ação', texto: 'As ações vêm em ordem de impacto: o que fazer primeiro, o que pode esperar e o que não vale o esforço.' },
    { titulo: 'Apresentação', texto: 'O plano é apresentado à liderança. Se a empresa quiser, a Mclair acompanha a execução ou assume parte dela.' },
  ],
  casesTitulo: 'Comunicação reorganizada, na prática',
  casesIntro: 'Nem todo case da Mclair começou como consultoria, mas em todos estes o trabalho passou por diagnóstico e plano antes da execução.',
  cases: [
    { href: '/cases/insight-rh/', nome: 'Insight RH', texto: 'A consultoria de liderança não tinha uma área de marketing e comunicação organizada. A Mclair estruturou essa área: marca, canais, site, a esteira de produtos com trilhas por nível de desenvolvimento e uma parceria corporativa com o Ibiti Projeto.' },
    { href: '/cases/zbra/', nome: 'Zbra', texto: 'Antes de mexer nas redes e na imprensa, a Mclair fez benchmarking e estudo de público e segmento para alinhar toda a comunicação ao novo posicionamento da empresa depois do rebranding.' },
    { href: '/cases/alexandre-magno/', nome: 'Alexandre Magno', texto: 'A aquisição da Emergee pelo Itaú pedia uma comunicação cuidadosa, com foco em conduzir a narrativa e proteger a reputação. Depois dela, veio o lançamento da The Cynefin Co. Brazil.' },
    { href: '/cases/bms-abimaq/', nome: 'BMS / ABIMAQ + ApexBrasil', texto: 'No programa Brazil Machinery Solutions, a cobertura foi planejada por regiões e setores de interesse, com conteúdo em português e inglês. Foram mais de 2 mil inserções na mídia e 1.734 publicações na imprensa internacional.' },
  ],
  paraQuem: {
    titulo: 'Para quem faz sentido',
    itens: [
      'Empresas diante de uma mudança grande: fusão, aquisição, troca de comando, expansão.',
      'Equipes internas de comunicação que querem um olhar de fora antes de montar o plano do ano.',
      'Empresas com vários fornecedores de comunicação, em que cada um parece falar uma língua.',
      'Lideranças que querem saber, com franqueza, como a empresa é vista.',
    ],
  },
  naoE: {
    titulo: 'Para quem não faz',
    itens: [
      `Quem está no meio de uma crise. Aí a urgência é outra, e o caminho é a <a href="${CRISE}">gestão de crise</a>.`,
      'Quem quer só execução, sem parar para olhar o conjunto. Nesse caso, contratar o serviço específico resolve mais rápido.',
      'Quem não vai abrir os números e a agenda da liderança. Diagnóstico feito só de fora fica raso.',
    ],
  },
  kelly: {
    foto: 'kelly-pinheiro-escritorio-sorriso',
    alt: 'Kelly Pinheiro, CEO da Mclair, sorrindo no escritório',
    paragrafos: [
      `<a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a> é jornalista desde 2004, pós-graduada em Marketing Estratégico e fundou a Mclair em São Paulo, em 2017. Foi colunista da Exame por dois anos e meio e hoje, além de liderar a agência, é CMO da Aspin Seguros, empresa familiar fundada há 30 anos pelo pai dela.`,
      'Ou seja: ela conhece a comunicação dos dois lados da mesa, como fornecedora e como quem contrata. É esse olhar que entra no diagnóstico.',
    ],
  },
  faqTitulo: 'Perguntas sobre consultoria em comunicação',
  faq: [
    {
      pergunta: 'O que faz uma consultoria de comunicação?',
      resposta:
        'Analisa como a empresa se comunica com seus públicos, aponta o que funciona e o que atrapalha, e entrega um plano de ação. Diferente de uma agência de execução, o foco é diagnóstico e direção.',
    },
    {
      pergunta: 'Qual a diferença entre consultoria e assessoria de comunicação?',
      resposta:
        'A assessoria executa o dia a dia: pautas, releases, redes, eventos. A consultoria olha o conjunto e define o que deve ser feito. Muitas empresas começam pela consultoria e depois contratam a execução, com a equipe interna ou com uma agência.',
    },
    {
      pergunta: 'O que é um plano de comunicação?',
      resposta:
        'É o documento que diz o que a empresa vai comunicar, para quem, por quais canais, quando e como vai medir. Um bom plano tem prioridades e responsáveis, e cabe em poucas páginas.',
    },
    {
      pergunta: 'Preciso contratar a Mclair para executar o plano?',
      resposta:
        'Não. O plano é da empresa, que decide o que executar, quando e com quem. Se quiser, a Mclair assume parte da execução, como assessoria de imprensa, branding ou marketing digital.',
    },
    {
      pergunta: 'A consultoria inclui gestão de crise?',
      resposta:
        'Inclui o mapa de riscos e o protocolo de resposta. Se a crise já está acontecendo, o trabalho é outro, mais rápido, e a página de gestão de crise explica como a Mclair atua nesses casos.',
    },
    {
      pergunta: 'Quanto tempo dura uma consultoria em comunicação?',
      resposta:
        'Depende do tamanho da empresa e de quantas áreas entram no diagnóstico. O cronograma é combinado na proposta.',
    },
    {
      pergunta: 'Quanto custa uma consultoria em comunicação?',
      resposta:
        'O valor depende do escopo: quantas áreas e públicos são analisados, a profundidade da comparação com concorrentes e se há acompanhamento depois da entrega do plano. A proposta vem depois da primeira conversa.',
    },
  ],
  form: {
    id: 'wa-form-consultoria',
    titulo: 'Conte o momento da sua empresa',
    assunto: 'Consultoria em comunicação (página do serviço)',
    waIntro: 'Olá Mclair! Vim pela página de Consultoria em Comunicação e quero conversar sobre a minha empresa.',
    perguntaLabel: 'O que motivou a busca por uma consultoria?',
    perguntaDica: 'Uma mudança que vem aí, a sensação de que a comunicação não está funcionando, o plano do ano.',
    perguntaRotulo: 'Motivo',
    opcoesLabel: 'Tamanho da empresa',
    opcoes: ['Até 50 pessoas', 'De 50 a 500 pessoas', 'Mais de 500 pessoas', 'Prefiro não dizer'],
    opcoesRotulo: 'Porte',
    texto: 'Se houver uma data importante pela frente, como um anúncio ou uma assembleia, diga qual. Ela define o ritmo do diagnóstico.',
  },
};

/* ---------------------------- Mentorias exclusivas ---------------------------- */
// /mentorias/ é a página dos programas (etapas e módulos). Esta página ajuda
// a escolher entre eles e entre mentoria e agência, e linka para lá sem
// repetir a descrição de cada etapa.

const MENTORIAS: ServicoAmpliado = {
  seo: {
    title: 'Mentoria de Posicionamento e Marca Pessoal | Mclair',
    h1: 'Mentoria de marca pessoal e assessoria de imprensa',
    description:
      'Mentorias Bolder e Bolder Press com Kelly Pinheiro: marca pessoal e posicionamento para profissionais, assessoria de imprensa para quem é de comunicação.',
    keywords:
      'mentoria de posicionamento, mentoria de marca pessoal, mentoria de marketing, mentoria assessoria de imprensa, mentoria para jornalistas, kelly pinheiro mentoria, bolder press',
  },
  serviceType: 'Mentoria',
  wa: wa('Olá Mclair! Vim pela página de Mentorias Exclusivas e quero saber qual programa faz sentido para mim.'),
  aberturaTitulo: 'Fazer por você ou ensinar você a fazer',
  abertura: [
    'Existem duas formas de trabalhar com a Mclair. Na primeira, a agência faz por você: escreve, pauta, fala com as redações. Na segunda, você aprende a fazer, com a pessoa que comanda esse trabalho dentro da agência. As mentorias são a segunda forma.',
    'São dois programas conduzidos por Kelly Pinheiro. O Bolder, também chamado de Be Bolder, é para profissionais e empreendedores que querem construir marca pessoal e se posicionar como referência no próprio nicho. O Bolder Press é para jornalistas e profissionais de comunicação que querem se especializar em assessoria de imprensa.',
    'A descrição completa de cada programa, com etapas e módulos, está na página das <a href="/mentorias/">mentorias Bolder e Bolder Press</a>. Aqui está o que ajuda a decidir: para quem é cada um, como funciona a participação e quando a mentoria não é o melhor caminho.',
  ],
  lateral: 'Não sabe qual programa escolher? Conte o que você faz hoje e aonde quer chegar, e a equipe indica o caminho.',
  entregasIntro: 'O que vem com as mentorias:',
  explicacoes: {
    'Mentoria Bolder: Marca Pessoal e Autoridade (3 níveis)':
      'Três etapas, de fundamentos a implementação: do diferencial competitivo ao plano de conteúdo em andamento. <a href="/mentorias/#bolder">Ver o programa Bolder</a>.',
    'Mentoria Bolder Press: Assessoria Estratégica (4 módulos)':
      'Quatro módulos sobre o ofício: mapeamento de mídia, criação de pautas, relacionamento e crise, mensuração e uso de IA. <a href="/mentorias/#bolder-press">Ver o programa Bolder Press</a>.',
    'Acompanhamento personalizado com Kelly Pinheiro':
      'Quem conduz os encontros é a própria Kelly, ao longo de dois meses de mentoria.',
    'Posicionamento e diferencial competitivo':
      'O ponto de partida do Bolder: descobrir o que você sabe que os outros não sabem e em que tema vale a pena ser lembrado.',
    'Desenvolvimento de narrativa pessoal e pitch':
      'A trajetória profissional contada de um jeito que caiba numa apresentação, num perfil de LinkedIn ou numa entrevista.',
    'Certificação e rede de alunos exclusiva':
      'Ao fim do programa, o aluno sai com certificado e passa a fazer parte da rede de participantes das mentorias.',
  },
  etapasTitulo: 'Como funciona a participação',
  etapasIntro: 'Os dois programas seguem o mesmo formato. O que muda é o conteúdo e o bônus de cada um.',
  etapas: [
    { titulo: 'Conversa inicial', texto: 'Você conta pelo formulário ou pelo WhatsApp o que faz hoje e aonde quer chegar. A equipe responde com o programa que faz mais sentido, os valores e as datas.' },
    { titulo: 'Escolha do programa', texto: 'Bolder, se o objetivo é a sua marca. Bolder Press, se o objetivo é dominar a assessoria de imprensa como ofício.' },
    { titulo: 'Encontros online', texto: 'Dois meses de mentoria com Kelly Pinheiro, em encontros online.' },
    { titulo: 'Encontro presencial', texto: 'Os dois programas incluem um encontro presencial com a Kelly.' },
    { titulo: 'O bônus de cada trilha', texto: 'No Bolder Press, media training. No Be Bolder, aulas mensais ao vivo com especialistas convidados.' },
  ],
  casesTitulo: 'O método aplicado em clientes da agência',
  casesIntro: 'Os cases abaixo não são de alunos: são de clientes da Mclair. Mostram, na prática, o tipo de trabalho que as mentorias ensinam a fazer.',
  cases: [
    { href: '/cases/claudia-elisa/', nome: 'Claudia Elisa', texto: 'Marca pessoal de uma conselheira e mentora com mais de 30 anos de carreira, com três marcas construídas (Claudia Elisa, Liderança em Pauta e Mentoria Sharp), artigo na Exame e entrevista na TV. É o caminho que o Bolder percorre.' },
    { href: '/cases/diego-nogare/', nome: 'Diego Nogare', texto: 'Especialista em dados e inteligência artificial posicionado na imprensa, com duas matérias seguidas na home da CNN Brasil, artigo na Exame e coluna na HSM Management.' },
    { href: '/cases/fidalgo/', nome: 'Fidalgo Leilões', texto: 'Mais de 1.600 inserções desde 2022, com a Folha de S.Paulo, a Exame IN e o Globo.com. É a rotina de pauta, release e relacionamento com redações que o Bolder Press destrincha.' },
  ],
  paraQuem: {
    titulo: 'Qual programa é o seu',
    itens: [
      '<strong>Bolder</strong>: profissionais liberais, executivos e empreendedores que querem criar marca pessoal e ser reconhecidos como autoridade no próprio nicho.',
      '<strong>Bolder Press</strong>: jornalistas e profissionais de comunicação que querem se especializar em assessoria de imprensa estratégica.',
      'Quem quer entender o método por dentro para depois montar a própria equipe ou cuidar da própria imagem.',
    ],
  },
  naoE: {
    titulo: 'Quando a mentoria não é o caminho',
    itens: [
      `Quando falta tempo para fazer. Nesse caso, faz mais sentido contratar a agência, pelo <a href="/servicos/marketing-de-autoridade/">marketing de autoridade</a> ou pela <a href="${HUB}">assessoria de imprensa</a>.`,
      `Quando a empresa precisa preparar um porta-voz para uma entrevista marcada. Isso é <a href="${MEDIA_TRAINING}">media training</a>, sob medida.`,
      'Quando o objetivo é sair na imprensa na semana que vem. Mentoria é formação, e resultado vem da aplicação depois dela.',
    ],
  },
  kelly: {
    foto: 'kelly-pinheiro-retrato-sorriso',
    alt: 'Kelly Pinheiro, mentora dos programas Bolder e Bolder Press',
    paragrafos: [
      `<a href="${KELLY}" class="pessoa-link">Kelly Pinheiro</a> é jornalista desde 2004 e fundou a Mclair em 2017. O que ela ensina nas mentorias é o que a agência faz todos os dias: a Mclair soma mais de 11.600 inserções na mídia para mais de 200 clientes. Foi colunista da Exame por dois anos e meio e tem artigos na HSM e no Grandes Nomes da Propaganda.`,
      'Ela também conhece o lugar de aluna. Em 2024, fez a Mentoria Sharp, de Claudia Elisa, na segunda turma, ao lado de executivos de empresas como Bradesco, Vivara e Central Ailos.',
    ],
  },
  faqTitulo: 'Perguntas sobre as mentorias',
  faq: [
    {
      pergunta: 'Qual a diferença entre o Bolder e o Bolder Press?',
      resposta:
        'O Bolder trabalha a sua marca pessoal: posicionamento, narrativa e conteúdo de autoridade. O Bolder Press ensina o ofício da assessoria de imprensa: pauta, relacionamento com jornalistas, crise e mensuração. O primeiro é para quem quer ser a fonte; o segundo, para quem quer levar fontes à imprensa.',
    },
    {
      pergunta: 'Quanto tempo dura a mentoria?',
      resposta:
        'Cada programa tem dois meses de mentoria com Kelly Pinheiro, com encontros online e um encontro presencial.',
    },
    {
      pergunta: 'Preciso ser jornalista para fazer o Bolder Press?',
      resposta:
        'O programa foi pensado para jornalistas e profissionais de comunicação que querem se especializar em assessoria de imprensa. Se você é de outra área e quer aparecer na imprensa, o Bolder ou a contratação da assessoria da Mclair costumam fazer mais sentido.',
    },
    {
      pergunta: 'A mentoria substitui contratar uma agência?',
      resposta:
        'Não, são caminhos diferentes. Na mentoria, você aprende e executa. Na agência, a equipe da Mclair executa por você. Quem tem pouco tempo costuma preferir a agência; quem quer dominar o processo, a mentoria.',
    },
    {
      pergunta: 'O que eu levo ao fim do Bolder?',
      resposta:
        'Posicionamento definido, narrativa pessoal e pitch trabalhados, e um plano de conteúdo de autoridade em andamento, com indicadores para acompanhar o resultado.',
    },
    {
      pergunta: 'O Bolder Press fala de inteligência artificial?',
      resposta:
        'Sim. O último módulo trata de mensuração e retorno em assessoria de imprensa e das tendências da chamada Assessoria 4.0, incluindo o uso de inteligência artificial e de tecnologia na relação com a imprensa.',
    },
    {
      pergunta: 'Quanto custa a mentoria?',
      resposta:
        'Valores e datas são informados pela equipe da Mclair na conversa inicial. Mande uma mensagem pelo formulário desta página ou pelo WhatsApp.',
    },
  ],
  form: {
    id: 'wa-form-mentorias',
    titulo: 'Conte em que momento você está',
    assunto: 'Mentorias (página Mentorias Exclusivas)',
    waIntro: 'Olá Mclair! Vim pela página de Mentorias Exclusivas e quero saber mais sobre os programas.',
    perguntaLabel: 'O que você faz hoje e aonde quer chegar?',
    perguntaDica: 'Sua área, seu cargo e o que espera da mentoria.',
    perguntaRotulo: 'Momento',
    opcoesLabel: 'Qual programa te interessa?',
    opcoes: ['Bolder (marca pessoal)', 'Bolder Press (assessoria de imprensa)', 'Ainda não sei'],
    opcoesRotulo: 'Programa',
    texto: 'A equipe responde com o programa indicado, os valores e as próximas datas.',
  },
};

/** Páginas do CMS com conteúdo ampliado, pelo slug do serviço no painel. */
export const SERVICOS_AMPLIADOS: Record<string, ServicoAmpliado> = {
  'marketing-de-autoridade': MARKETING_AUTORIDADE,
  'branding-estrategico': BRANDING,
  'marketing-digital': MARKETING_DIGITAL,
  'consultoria-em-comunicacao': CONSULTORIA,
  'mentorias-exclusivas': MENTORIAS,
};

export { AVISO_FORM };
