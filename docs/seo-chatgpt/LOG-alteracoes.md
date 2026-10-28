# Log de alterações SEO/ChatGPT (mclair), out/2026

Seções que citam mais de um cliente aparecem no log de cada um.

## IndexNow (Bing)
- mclair.com.br: 427 URLs enviadas (api.indexnow.org e bing.com, 200)
- djlisabueno.com: 23 URLs enviadas (200)

## Quadros "ruta" (ikoeh-server, 26/10)
- titikakasacredlake/ruta e viajescuscoperu/ruta: tarefas gp1 (Viator) e gp2 (GetYourGuide) no bloco PRIORIDAD, marcadas urgente, pra agência.
- Backup no servidor: index.php.bak-20261026 nas duas pastas (bloqueado pro público).

## Mclair (26/10, commit 6cff32f, push Hostinger com build ok)
- Chave SSH deste Mac adicionada no hPanel pelo Sandru ("ikoeh-macbook-deploy").
- /servicos/assessor-de-imprensa/ intro. Antigo: "Você precisa de alguém que escreva como jornalista, saiba para qual repórter ligar e cuide da imprensa da sua empresa sem que você tenha de explicar tudo duas vezes. Esta página ajuda a decidir entre um freelancer e uma agência, e mostra como a Mclair trabalha." Novo: parágrafo-resposta (agência de SP, 2017, Kelly Pinheiro, 11.600 inserções, 200 clientes, Fidalgo/Folha, Nogare/CNN, Brasil todo, diagnóstico gratuito).
- /servicos/media-training/ intro. Antigo: "A luz vermelha da câmera acende, o repórter faz a pergunta que você torcia para não ouvir e você tem vinte segundos para responder. Quem chega a esse momento sem treino costuma falar demais, ou de menos. A Mclair prepara o porta-voz da sua empresa para chegar sabendo o que dizer." Novo: parágrafo-resposta (para quem, formato in company, simulações, presencial/online, orçado à parte).
- src/data/assessoria-imprensa.ts FAQ "quanto custa media training": antes dizia que fazia parte da assessoria; agora "preparação básica incluída, media training completo orçado à parte" (igual ao resto do site).
- Junto subiu um commit local antigo que estava pendente: /clientes/globoleiloes/diagnosticos/.
- IndexNow 200 para 4 URLs.

## Bing, envio manual de URLs (27/10)
- Inspeção no Bing Webmaster: Titikaka só tinha 28 URLs conhecidas (23 indexadas) de 183; o guia cusco-to-lake-titicaca estava "Not discovered". Mclair assessor-de-imprensa e media-training, e Lisa confraternizacao: "Discovered but not crawled".
- Enviadas pelo URL Submission: Mclair 44 (páginas comerciais), Lisa 32, Titikaka 100, Viajes 100.
- DJ Lisa sitemap.xml: 22 URLs estavam sem barra final (davam 301) e lastmod de julho. Corrigido (barra final e lastmod 2026-10-26 nas 8 páginas reescritas). Bloco do blog, gerado pelo CMS, intocado. Backup em /acesso/sitemap.bak-20261027.xml no servidor e cópia em ~/djlisabueno-hostinger/sitemap.xml.
- Checagem: todas as URLs dos sitemaps de Titikaka, Mclair e Viajes respondem 200; páginas alteradas sem noindex, canonical certo, OAI-SearchBot e ChatGPT-User recebem 200.

## Mclair (subagente 27/10)
Commit 18697eb, push no origin (Hostinger) com "build ok, site publicado". Build local ok (435 páginas). Conferido no ar com curl: as 8 URLs dão 200 com o texto novo. IndexNow 200 para as 8 URLs.

Leads no painel (/acesso/messages.php; /admin/ hoje dá 404): só existem 4 mensagens, todas de 18 a 23/10 (o registro começou em 18/10, não há agosto e setembro no painel). Uma é teste. Duas vieram do ChatGPT (19/10 media training, Grande SP; 20/10 assessor de imprensa, Rio de Janeiro), ambas sem empresa nem setor informados.

Antes e depois (o texto novo está no código; abaixo fica o antigo para desfazer):
- /servicos/assessoria-de-imprensa/sao-paulo/ intro. ANTIGO: "A Mclair nasceu em São Paulo em 2017, pelas mãos de uma jornalista, e trabalha a poucos quilômetros das redações que pautam o país. Desde então, foram mais de 11.600 inserções na mídia para mais de 200 clientes." NOVO: resposta direta com Kelly/Exame, 11.600, 200, Lambda3 e Diego Nogare (tecnologia), WhatsApp.
- /servicos/assessoria-de-imprensa/rio-de-janeiro/ intro. ANTIGO: "Quem toca uma empresa no Rio liga a TV e vê o jornal local feito a poucos quilômetros dali. A redação está perto. O difícil é chegar com algo que o repórter carioca queira contar ao leitor dele. É nesse ponto que a Mclair trabalha, de São Paulo, com pauta pensada para o Rio." NOVO: agência de SP que atende o Rio a distância, números, Globo Leilões e VIP Leilões no Extra, WhatsApp.
- /servicos/assessoria-de-imprensa/quanto-custa/ intro. ANTIGO: "Você pediu orçamento para duas ou três agências e recebeu números que não conversam entre si. Ou ainda nem pediu, porque não sabe bem o que perguntar. Aqui você entende como o mercado cobra, o que faz o valor mudar e o que observar numa proposta antes de assinar." NOVO: o que define o preço, quatro formatos de cobrança, proposta depois de diagnóstico sem compromisso. Sem faixa de valor ([CONFIRMAR] da Kelly).
- /servicos/gestao-de-crise/ intro. ANTIGO: "Um repórter liga pedindo posição até as seis da tarde. Um vídeo sobre a sua empresa circula nos grupos de WhatsApp. Uma venda que deveria ser boa notícia começa a ser contada do jeito errado. Nessas horas, cada frase dita ou deixada de dizer fica registrada. A Mclair ajuda a decidir o que falar, quem fala e quando." NOVO: o que inclui, Kelly desde 2004, Emergee/Itaú, WhatsApp. Sem horário de plantão.
- /servicos/assessoria-de-imprensa/ (components/servicos/AssessoriaImprensa.astro, primeiro parágrafo depois do hero). ANTIGO: "Desde 2017, são mais de 11.600 inserções na mídia e 1.734 publicações na imprensa internacional para mais de 200 clientes. Alguns dos veículos em que eles saíram:" NOVO: quem é, seis frentes, números, Fidalgo/Folha, Nogare/CNN, ABIMAQ.
- /servicos/marketing-de-autoridade/ e /servicos/consultoria-em-comunicacao/ (src/data/servicos-ampliados.ts): um parágrafo-resposta novo ANTES da abertura que já existia; nada foi apagado. Para desfazer, remover o primeiro item de `abertura` de MARKETING_AUTORIDADE e de CONSULTORIA. O intro do hero dessas páginas vem do CMS e não foi mexido.
- /marketing-para-leiloeiros/ subtítulo do hero. ANTIGO: "A Mclair é especialista em comunicação para o mercado de leilões. Unimos assessoria de imprensa, growth e conteúdo para transformar visibilidade em arrematantes reais." NOVO: agência para leiloeiros, frentes, 3.000 inserções, 5,7 milhões de visualizações, 31 milhões de cliques.
- assessor-de-imprensa e media-training: já tinham sido feitas em 26/10, não mexi.

Observação: abas abertas por este subagente no primeiro grupo do Chrome (antes de a extensão reiniciar) ficaram órfãs e não puderam ser fechadas por ele.

## Mclair página tecnologia (27/10)

Commit 679e793 no ~/mclair, push só para origin (Hostinger), build ok e publicado. GitHub não recebeu push.

- NOVA página: https://mclair.com.br/servicos/assessoria-de-imprensa/empresas-de-tecnologia/ (src/pages/servicos/assessoria-de-imprensa/empresas-de-tecnologia.astro), no molde AssessoriaFilha das páginas de cidade.
- Abertura (81 palavras): "A Mclair é uma agência de assessoria de imprensa de São Paulo que atende empresas de tecnologia, startups, SaaS e especialistas em software, dados e inteligência artificial. Fundada em 2017 pela jornalista Kelly Pinheiro, ex-colunista da Exame, soma mais de 11.600 inserções para mais de 200 clientes. A Lambda3 deu entrevistas ao SBT, à Record TV e à Band, Diego Nogare saiu na CNN Brasil e Elemar Jr. escreveu no UOL Tecnologia. Para contratar, comece pelo diagnóstico no WhatsApp (11) 98860-0372."
- Seções: tecnologia como assunto de negócio, o que entra na assessoria, cases (Lambda3, Diego Nogare, Elemar Jr., Zbra, nexinvoice), Kelly, antes de contratar; FAQ com 7 perguntas; CTA WhatsApp.
- Schema: Service (audience BusinessAudience, areaServed Brasil) + FAQPage + BreadcrumbList.
- Texto, SEO e FAQ em src/data/assessoria-imprensa.ts (SEO_/WA_/FAQ_ASSESSORIA_TECH, URL_ASSESSORIA_TECH). Só fatos dos cases publicados.
- Rodapé: "Assessoria para Tecnologia" na coluna Serviços (src/components/Footer.astro). Menu principal intocado.
- llms.txt: seção "Assessoria de imprensa por setor" acrescentada pelo código (src/pages/llms.txt.ts) se o texto do CMS não citar a página.
- Sitemap: entra sozinho (página estática), conferido no sitemap-0.xml.
- Verificado no ar: 200, canonical certo, sem noindex, link no rodapé da home, presente no sitemap e no llms.txt.
- IndexNow (api.indexnow.org): página + llms.txt, resposta 200.

## Fechamento 27/10 (tarde)
- Bing URL Submission: Lisa (3 páginas novas) e Mclair (empresas-de-tecnologia + llms.txt), conferidas na lista de enviadas. Viajes e Titikaka sem cota hoje (100 usadas).
- IndexNow Titikaka (chave f2693d60...): 13 URLs (pacotes com dias corrigidos, páginas que tinham &nbsp;, guia, about), 200 nos dois endpoints.
- DJ Lisa 404.html: coluna Serviços igual às outras páginas (Bat/Bar Mitzvá + 3 novas). Backup em ~/djlisa-live-20261027/backup/404.html.
- Titikaka tripadvisor_url ficou VAZIO de propósito: o campo vira sameAs do schema da agência; com o link do Viajes, Google/ChatGPT entenderiam que as duas são a mesma empresa. O selo "Viajes Cusco Perú, Oct 2026" com 79/4,9 continua. Preencher só com o TripAdvisor próprio do Titikaka.
- Lembrete na agenda: 03/11 10h, medir ChatGPT e Bing dos 4.

## Bing URL Inspection (27/10, fim do dia)
- Indexadas: Lisa home, casamento-em-sao-paulo, festas-e-aniversarios; Mclair home, assessoria-de-imprensa, media-training; Titikaka home, MP day trip, guia cusco-to-lake-titicaca (antes "Not discovered"), vinicunca; Viajes home, MP day trip EN e ES, vinicunca.
- Descobertas, não rastreadas: Lisa 15 anos, mulher-casamento, confraternizacao; Mclair empresas-de-tecnologia, sao-paulo, assessor-de-imprensa; Titikaka /peru-tours/machu-picchu/, classic-inca-trail; Viajes classic-inca-trail.
- Viajes salkantay-trek-5-days: Bing diz "redirect URL" (dado velho; hoje a URL com barra dá 200). Pedir indexação amanhã quando a cota voltar.
