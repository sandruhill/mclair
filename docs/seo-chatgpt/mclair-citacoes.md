# Mclair: onde ser citada para voltar ao ChatGPT

Levantamento feito em 26/10/2026. Status de cada URL testado com curl nesse dia.
Legenda: **OK** = respondeu 200. **Existe, bloqueia robô** = respondeu 403 para script, mas o site está no ar. **[CONFIRMAR URL]** = não respondeu ou não achei a página exata, conferir à mão.

Lógica: o GPT-6 cita menos sites e prefere domínios fortes no Google e com muitas menções de terceiros. A Mclair tem um ativo raro: 39 matérias com link em 27 veículos (página Mclair na mídia), só que quase nenhuma delas menciona a própria Mclair. Os veículos citam o cliente, não a agência. O plano abaixo transforma esse trabalho em menções da Mclair.

---

## Antes da lista: 4 correções técnicas

1. **GPTBot recebe 429 do firewall da Hostinger.** Testei hoje com o user-agent do GPTBot em /servicos/media-training/ e /servicos/assessor-de-imprensa/: 429. OAI-SearchBot e ChatGPT-User passaram (200). O próprio robots.txt já tem o aviso. Falta liberar no hPanel (proteção anti-bot). Quem faz: agência. Impacto: alto.
2. **Bing Webmaster Tools + IndexNow.** Verificar mclair.com.br em https://www.bing.com/webmasters, enviar o sitemap-index.xml e checar indexação de /servicos/assessor-de-imprensa/ e /servicos/media-training/. Ligar IndexNow no deploy do Astro para avisar o Bing a cada publicação. Quem faz: agência.
3. **Teste de Googlebot e Bingbot falsos deu 403.** É provável que seja o firewall barrando user-agent falsificado (meu IP não é do Google), o que é normal. Confirmar pela Inspeção de URL do Search Console e pela ferramenta equivalente do Bing que os robôs reais passam.
4. **Schema `sameAs` só tem Instagram e LinkedIn.** Acrescentar o perfil de LinkedIn da Kelly, a página de autora na Exame e os perfis de diretório criados nesta lista (Clutch, Google, Bing). Quem faz: agência.

---

## Lista priorizada (do maior para o menor impacto)

| # | Onde | URL | Status | Por que importa para ChatGPT/Google | Esforço | Quem faz |
|---|---|---|---|---|---|---|
| 1 | Sites dos próprios clientes citando a Mclair (Fidalgo, Globo Leilões, VIP Leilões, EximiaCo/Elemar Jr., Diego Nogare, Claudia Elisa, Lambda3, Insight RH, Zbra) | https://www.fidalgoleiloes.com.br/ , https://www.globoleiloes.com.br/ , https://vipleiloes.com.br/ , https://eximia.co/ , https://www.diegonogare.net/ , https://www.claudiaelisa.com.br/ | OK | Na home de nenhum deles aparece "Mclair". Uma linha "Assessoria de imprensa: Mclair" na sala de imprensa ou no rodapé, com link, vira menção de terceiro em domínio que já rankeia. São mais de 10 links quase de graça. | Baixo | Cliente (Kelly pede); agência (texto pronto do Pitch 1) |
| 2 | Google Perfil da Empresa | https://business.google.com/ | [CONFIRMAR se já existe perfil] | Base de "assessoria de imprensa em São Paulo" no Google e fonte indireta do ChatGPT para pedidos locais. Pedir avaliações aos clientes de cases citando o serviço (media training, assessoria, crise). | Baixo | Cliente (avaliações); agência (perfil) |
| 3 | LinkedIn da empresa e da Kelly | https://www.linkedin.com/company/mclair-comunicacao (perfil da Kelly [CONFIRMAR URL]) | OK | LinkedIn é muito citado em respostas B2B. Página da empresa completa, com serviços e um post por case, sempre com link para a página do case. | Baixo, contínuo | Agência (rascunho); Kelly publica |
| 4 | Anuário da Comunicação Corporativa e TOP Mega Brasil (Mega Brasil) | https://www.megabrasil.com.br/ | OK | É o ranking de agências que a imprensa cita todo ano (Poder360, Nosso Meio). Entrar na pesquisa do Anuário coloca a Mclair numa lista que o ChatGPT usa para "maiores agências de comunicação". | Médio (enviar dados de faturamento e equipe) | Cliente (dados); agência (preenche) |
| 5 | ABRACOM (Associação Brasileira das Agências de Comunicação) | https://abracom.org.br/ | OK | Associação das agências. Ser associada dá menção institucional e entrada no Censo das agências. [CONFIRMAR custo e se o site lista as associadas.] | Médio, pago | Cliente decide |
| 6 | Aberje | https://www.aberje.com.br/ | OK | Referência em comunicação corporativa; prêmio e conteúdo de autoria. | Médio, pago | Cliente decide |
| 7 | Exame (coluna da Kelly) e Bússola | https://exame.com/ , https://exame.com/bussola/ | OK (página de autora não achada: [CONFIRMAR URL]) | Kelly foi colunista por dois anos e meio. A bio de autora precisa dizer "fundadora da Mclair" com link. Um artigo novo por trimestre mantém a menção viva. | Médio | Kelly escreve; agência edita |
| 8 | Clutch | https://clutch.co/br/pr-firms | Existe, bloqueia robô | Lista "Top PR Firms in Brazil", atualizada em ago/2026, é exatamente o tipo de página que o ChatGPT usa quando alguém pede "melhores agências de PR no Brasil". Perfil gratuito + 3 a 5 avaliações verificadas de clientes. | Médio | Agência (perfil); clientes (avaliações) |
| 9 | Portal dos Jornalistas / Jornalistas&Cia | https://www.portaldosjornalistas.com.br/ , https://www.jornalistasecia.com.br/ | OK | Publica movimentação de agências (contas novas, contratações). Uma nota por conta nova = menção com nome da agência. | Baixo | Agência |
| 10 | Nosso Meio | https://nossomeio.com.br/ | OK | Cobre agências, rankings e Abracom; aceita nota e artigo. | Baixo | Agência |
| 11 | ABC da Comunicação | https://www.abcdacomunicacao.com.br/ | OK | Já publicou sobre a Mclair (spin-off) e artigo de cliente. Canal aberto para nova pauta ou artigo assinado da Kelly. | Baixo | Agência |
| 12 | Meio & Mensagem | https://www.meioemensagem.com.br/ | OK (guia de agências: [CONFIRMAR URL], /agencias deu 404) | Maior veículo do mercado; nota de conta nova ou artigo de opinião. | Alto | Agência |
| 13 | Propmark | https://propmark.com.br/ | OK | Notas de contas e movimentação. | Médio | Agência |
| 14 | Grandes Nomes da Propaganda | https://grandesnomesdapropaganda.com.br/ | OK | Kelly já tem artigos ali e o rebranding da Zbra saiu lá. Manter bio com link. | Baixo | Kelly |
| 15 | HSM Management | https://revistahsm.com.br/ | OK | Kelly já publicou; clientes (Alexandre Magno, Diego Nogare) também. Artigo novo sobre marketing de autoridade. | Médio | Kelly |
| 16 | Comunique-se e Prêmio Comunique-se | https://www.comunique-se.com.br/ , https://www.premiocomuniquese.com.br/ | OK | Portal do mercado de comunicação e jornalismo; artigo e presença em eventos. | Médio | Agência |
| 17 | Acontecendo Aqui (notícias e podcasts de comunicação) | https://acontecendoaqui.com.br/ | OK | Publica entrevistas em podcast com CEOs de agências. Pauta pronta para a Kelly. | Baixo | Agência (pitch) |
| 18 | Podcasts de comunicação: Menu do Líder, Falando bem, que mal tem? | https://www.abcdacomunicacao.com.br/comunicacao-estrategica-reputacao-e-lideranca-sao-temas-do-podcast-menu-do-lider-com-davi-paes-e-lima/ , https://acontecendoaqui.com.br/comunicacao/lancamento-podcast-que-explora-a-comunicacao-corporativa-e-o-marketing/ | OK (links das matérias sobre os podcasts) | Episódio com transcrição e descrição com link é citável. Temas da Kelly: desmarketização, media training, crise. | Médio | Agência (pitch); Kelly grava |
| 19 | Imprensa do mercado de leilões: Money Times, Monitor do Mercado | https://www.moneytimes.com.br/ , https://monitordomercado.com.br/ | OK | O nicho leilões é onde a Mclair tem mais prova (Fidalgo, VIP, Globo). Artigo assinado "como uma leiloeira vira notícia" posiciona a Mclair como a agência do setor. | Médio | Kelly + agência |
| 20 | Sindicato dos Leiloeiros do Estado de São Paulo | [CONFIRMAR URL] | Não achei o site | Palestra ou artigo para leiloeiros associados. Menção no site do sindicato é ouro para "assessoria de imprensa para leiloeiro". | Médio | Kelly |
| 21 | Mundo do Marketing | https://www.mundodomarketing.com.br/ | OK | Artigo sobre marketing de autoridade e marca pessoal. | Médio | Agência |
| 22 | Bing Places | https://www.bingplaces.com/ | OK | Importa do Google; cobre Bing e Copilot. | Baixo | Agência |
| 23 | Sortlist e DesignRush | Sortlist [CONFIRMAR URL] , https://www.designrush.com/agency/public-relations/br | DesignRush existe, bloqueia robô | Diretórios de agência que aparecem em listas "melhores agências". Menor peso que Clutch. | Baixo | Agência |
| 24 | Wikidata (Kelly Pinheiro, jornalista) | https://www.wikidata.org/ | OK | Item de entidade ligando Kelly, Mclair e Exame, com referências das próprias matérias. Ajuda o modelo a não confundir com homônimas (a busca hoje mostra outras "Kelly Pinheiro"). | Médio | Agência |
| 25 | Reclame Aqui | https://www.reclameaqui.com.br/ | Existe, bloqueia robô | Quem pergunta "a Mclair é confiável?" recebe resposta baseada nisso. Perfil reivindicado e limpo basta. | Baixo | Agência |

---

## Textos prontos para colar

### Descrição longa (150 palavras)

A Mclair é uma agência de assessoria de imprensa e comunicação estratégica fundada em 2017, em São Paulo, pela jornalista Kelly Pinheiro, que trabalha com imprensa desde 2004 e foi colunista da Exame por dois anos e meio. A equipe de jornalistas, estrategistas de marca e especialistas em digital atende empresas, executivos e especialistas do Brasil todo com assessoria de imprensa, media training, gestão de crise, marketing de autoridade, branding estratégico, marketing digital e consultoria em comunicação. Desde 2017 são mais de 11.600 inserções na mídia e 1.734 publicações na imprensa internacional para mais de 200 clientes, em veículos como Folha de S.Paulo, Exame, CNN Brasil, Valor Investe, InfoMoney, SBT, Record TV e Band. A Mclair é especializada no mercado de leilões, com cases como Fidalgo Leilões, VIP Leilões e Globo Leilões. A agência não promete publicação: trabalha com pauta, porta-voz preparado e clipping com link. Mais em mclair.com.br.

### Descrição curta (50 palavras)

Mclair: assessoria de imprensa e comunicação estratégica em São Paulo, desde 2017. Fundada pela jornalista Kelly Pinheiro, ex-colunista da Exame. Assessoria de imprensa, media training, gestão de crise, marketing de autoridade e branding. Mais de 11.600 inserções para 200+ clientes, com forte atuação no mercado de leilões. mclair.com.br

### Pitch 1: parceria com cliente (pedido de crédito e depoimento)

Assunto: Um pedido rápido sobre a nossa parceria

Oi, [nome], tudo bem? Fiquei muito feliz com [resultado do case, ex.: a matéria na Folha]. Queria pedir uma ajuda pequena: incluir na página de imprensa ou no rodapé do site de vocês a linha "Assessoria de imprensa: Mclair", com link para mclair.com.br/cases/[case]/.

Se puder, deixe também uma avaliação curta no nosso Google e no Clutch contando o que fizemos juntos. Mando os links prontos. Isso ajuda outras empresas a nos encontrarem, inclusive pelas ferramentas de IA. Obrigada! Kelly

### Pitch 2: artigo assinado (Nosso Meio, ABC da Comunicação, Meio & Mensagem)

Assunto: Artigo: a matéria editorial virou fonte do ChatGPT

Oi, [nome]. Proponho um artigo inédito da Kelly Pinheiro, jornalista desde 2004, ex-colunista da Exame e fundadora da Mclair: "Depois do GPT-6, quem não está na imprensa some da resposta da IA". O texto mostra por que as ferramentas de IA passaram a citar menos sites e preferir marcas com menções em veículos, e o que isso muda no trabalho de assessoria de imprensa, com exemplos de clientes de leilões e tecnologia.

Cerca de 4.000 caracteres, exclusivo, entrega em uma semana. Topa?

### Pitch 3: pauta para imprensa de economia (mercado de leilões)

Assunto: Pauta: o que mudou nos leilões de imóveis de bancos em 2026

Oi, [nome]. A Mclair atende leiloeiras parceiras de Caixa, Bradesco, Itaú, Santander e Correios e acompanha de perto o volume de lotes que vai a leilão. Temos porta-vozes disponíveis para explicar [dado novo, CONFIRMAR com o cliente: número de lotes, descontos, estados], quem está comprando e quais cuidados o arrematante iniciante deve ter.

Se fizer sentido, organizo a entrevista ainda esta semana com dados conferidos.
