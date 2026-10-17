/*
 * Plano de 12 meses da Mclair (outubro de 2026 a setembro de 2027), o corpo
 * da página /dossie-seo-2026/. Daqui saem "Onde estamos", o cronograma, "O que
 * depende da Mclair" e a lista de tarefas, todos montados de WEEKS e do estado
 * gravado, para nenhum número ficar velho no texto. As tarefas ficam em WEEKS:
 * id (minúsculas e números, até 12), w ("IKOEH" ou "Mclair"), t (texto HTML
 * escrito por nós), g (pronto quando), e (prazo), img (prints em capturas/),
 * s (estado inicial) e desc (motivo de tarefa descartada, que sai da contagem).
 * O estado marcado e as respostas vêm do MySQL, pelo index.php.
 */
"use strict";

const C = "/dossie-seo-2026/capturas/2026-10-15-";
const H = "<code>/servicos/assessoria-de-imprensa/</code>";
const WEEKS = [
  {n:1, d:"outubro de 2026", t:"Ponto de partida, página da Kelly e as fontes que as IAs leem", gate:"15 de novembro: www responde 301, IndexNow responde 202, o H1 sai legível em texto puro, a página de serviço tem de 900 a 1.200 palavras com FAQPage válido, e o Perfil da Empresa no Google está na categoria de assessoria de imprensa com área de atendimento em São Paulo.", tasks:[
    {id:"m1a", w:"IKOEH", s:"done", t:"<b>Dossiê de concorrência entregue (23/09).</b> Ponto de partida medido: 17 palavras-chave no top 100, cerca de 10 visitas orgânicas por mês, 351 páginas no Google e 50 no Bing, e nenhuma das 45 consultas do Search Console com \"assessoria de imprensa\". Descoberta central: as IAs montam a resposta lendo cadastros de terceiros (oHub, Perfil da Empresa no Google, Clutch, Anuário), não o site. No mesmo dia o sitemap foi reenviado ao Bing, que o marcava com erro e 0 URLs descobertas.", g:"dossiê publicado em <code>mclair.com.br/dossie-seo-2026/</code>"},
    {id:"m1b", w:"IKOEH", s:"done", t:"<b>Pesquisa de SERP comercial em 10 praças (15/10).</b> SP, RJ, BH, Curitiba, Porto Alegre, Florianópolis, Joinville, Brasília, Goiânia e Cuiabá, mais Caxias do Sul, Londrina, Maringá e Campo Grande. Volumes do Semrush (base br), quem está no topo, 40 páginas de concorrentes lidas uma a uma. Lição que guia o silo: a F&B tem 535 páginas de cidade iguais (semelhança 0,90) e está na posição 94; a MGA Press tem 26 cidades com texto reescrito (semelhança de 0,23 a 0,34) e aparece entre os primeiros.", g:"as duas pesquisas arquivadas com fonte e data de cada número"},
    {id:"m1c", w:"IKOEH", s:"done", t:"<b>Página pessoal <code>/kelly-pinheiro/</code> no ar.</b> Um único <code>Person</code> canônico (<code>mclair.com.br/kelly-pinheiro/#pessoa</code>) que a página Sobre, a autoria do blog e a <code>Organization</code> (fundadora) passaram a referenciar. Para o Google e as IAs, a Kelly agora é uma pessoa só, com uma página só.", g:"os três blocos de dados apontam para o mesmo @id", img:[{s:C+"kelly-desktop.webp", c:"Abertura da página da Kelly no computador"},{s:C+"kelly-celular.webp", c:"A mesma abertura no celular"}]},
    {id:"m1d", w:"IKOEH", s:"done", t:"<b>O nome \"Kelly Pinheiro\" vira link para a página dela em todo o site, posts do blog inclusive.</b> A troca acontece na hora de montar o site, sem tocar no banco de dados: nenhum post foi reescrito.", g:"o nome aparece como link nos posts publicados"},
    {id:"m1e", w:"IKOEH", s:"done", t:"<b>Página da Kelly redesenhada.</b> A foto acompanha a história enquanto se lê, a citação ocupa a página inteira, o formulário manda direto para o WhatsApp e os logos de clientes ficaram maiores.", img:[{s:C+"kelly-historia.webp", c:"A história com a foto que acompanha a leitura"},{s:C+"kelly-citacao.webp", c:"Citação em página inteira"},{s:C+"kelly-whatsapp.webp", c:"Formulário que abre a conversa no WhatsApp"}]},
    {id:"m1f", w:"IKOEH", s:"done", t:"<b>Logos repetidos removidos no painel.</b> Saíram as cópias da Dra. Arenata Ascencio e da Ynusitado com fundo branco, e \"Nusitado\" foi renomeado para Ynusitado.", g:"cada cliente aparece uma vez na grade", img:[{s:C+"kelly-logos.webp", c:"Grade de clientes sem logos repetidos"}]},
    {id:"m1g", w:"IKOEH", s:"done", t:"<b>Fotos dos posts otimizadas no servidor: de 114 MB para 30 MB, em duas rodadas.</b> Toda foto ficou com até 200 KB, salvo uma de 206 KB. Os originais estão guardados em <code>~/uploads-backup-20261015</code>.", g:"nenhuma foto acima de 206 KB em <code>/uploads/</code>"},
    {id:"m1h", w:"IKOEH", s:"done", t:"<b>O envio de fotos do painel passou a deixar toda foto com até 200 KB.</b> Quem publica não precisa se preocupar com o tamanho: o próprio painel reduz.", g:"foto grande enviada pelo painel sai com até 200 KB"},
    {id:"m1fa", w:"IKOEH", s:"done", t:"<b>Formulários mandam e-mail para a Kelly e abrem o WhatsApp (16/10).</b> Até aqui nenhum formulário do site mandava e-mail: os quatro (contato, janela do WhatsApp, página da Kelly e quanto custa) só abriam o WhatsApp. Agora cada envio chega em <code>kelly@mclair.com.br</code> num e-mail com o layout da Mclair e botões para responder no WhatsApp ou por e-mail, e a pessoa segue para o WhatsApp com a mensagem pronta. Página de envio com proteção contra robô e excesso de envios, revisada. Testado com a caixa do marketing: chegou sem aviso de segurança.", g:"teste recebido sem alerta do Gmail"},
    {id:"m1fb", w:"IKOEH", s:"done", t:"<b>Auditoria técnica para Google e IAs, com as correções no ar (16/10).</b> Robôs de busca e de IA liberados no <code>robots.txt</code>; dados da Mclair para o Google completos (assessoria de imprensa em primeiro, as 9 cidades, Instagram e LinkedIn); sitemap com as datas reais dos posts; menu sem o redirecionamento que custava um 301 em toda página; rodapé com link para os serviços, leiloeiros e a Kelly (leiloeiros não recebia link de nenhuma página); página de erro fora do Google; <code>llms.txt</code> novo com serviços, cases, cidades e a Kelly, servido em UTF-8; dois links quebrados em posts corrigidos.", g:"robots.txt, llms.txt e sitemap conferidos no ar"},
    {id:"m1fc", w:"IKOEH", s:"done", t:"<b>Google e Bing avisados (16/10).</b> Sitemap completo reenviado no Search Console e indexação pedida para São Paulo, Quanto custa, a página de assessoria e a da Kelly. IndexNow ligado: o Bing recebeu as 406 páginas e recebe cada página nova ao publicar. Envio de formulário marcado como conversão no Analytics, ao lado do clique no WhatsApp.", g:"pedidos aceitos na fila prioritária"},
    {id:"m1fd", w:"IKOEH", s:"blocked", e:"bloqueado pela Hostinger", t:"<b>Robô de treino do ChatGPT (GPTBot) bloqueado pela Hostinger.</b> O GPTBot recebe erro 429 em todas as páginas. Em 16/10 o suporte da Hostinger confirmou que é uma regra fixa do servidor, que não aceita exceção no plano da Mclair; a Auditoria de IA do painel está toda liberada e não é a causa. O impacto é pequeno: os robôs do ChatGPT que levam a citações, o OAI-SearchBot (busca) e o ChatGPT-User (leitura na hora da pergunta), entram normalmente. O GPTBot serve para treinar modelos futuros. Testado de novo a cada medição mensal.", g:"curl com o user-agent do GPTBot responde 200"},
    {id:"m1fe", w:"IKOEH", s:"done", t:"<b>Velocidade no celular (16/10).</b> A home tinha nota 29 e levava 14,4 s para mostrar o conteúdo principal. Depois dos ajustes, o PageSpeed real no celular deu nota 68, conteúdo principal em 3,6 s, primeiro conteúdo em 1,0 s e nenhum salto de layout. O que mudou, sem alterar o visual: títulos principais aparecem sem esperar animação, vídeo do fundo da home entra só depois da página (com imagem parada antes), fonte servida pelo próprio site e scripts de medição carregados depois do conteúdo. Ir além pediria mexer no visual da home (vídeo e desfoque do fundo), o que não compensa: as páginas de serviço, que trazem cliente, são mais leves. O número de visitantes reais aparece no Google em algumas semanas e entra na medição mensal.", g:"nota e tempo medidos no PageSpeed"},
    {id:"m1rv", w:"Mclair", e:"revisar e responder aqui", t:"<b>Revisar as páginas novas do site.</b> Ler cada uma e responder nesta tarefa com qualquer frase que não seja verdade para a Mclair ou que a Kelly queira mudar. Pode responder por página, citando o endereço. <b>No ar:</b> <a href='/kelly-pinheiro/'>Kelly Pinheiro</a>; <a href='/servicos/assessoria-de-imprensa/'>Assessoria de imprensa</a> (página reescrita); <a href='/servicos/assessoria-de-imprensa/sao-paulo/'>Assessoria de imprensa em São Paulo</a> (confirmar a frase sobre encontros presenciais); <a href='/servicos/assessoria-de-imprensa/quanto-custa/'>Quanto custa assessoria de imprensa</a> (confirmar a citação da tabela de referência do Sindicato dos Jornalistas para freelancer). <b>Novas de 16/10:</b> <a href='/servicos/assessor-de-imprensa/'>Assessor de imprensa</a> (para quem quer contratar jornalista); <a href='/servicos/gestao-de-crise/'>Gestão de crise</a> (usa o case e o depoimento do Alexandre Magno); <a href='/servicos/media-training/'>Media training</a>; e os <a href='/guias/'>guias</a>: <a href='/guias/o-que-e-assessoria-de-imprensa/'>O que é assessoria de imprensa</a> (confirmar a parte histórica: Ivy Lee em 1906 e a Light em São Paulo em 1914), <a href='/guias/press-release/'>Press release</a> (confirmar a tabela de antecedência de envio) e <a href='/guias/como-aparecer-na-midia/'>Como aparecer na mídia</a>. Os guias saem assinados pela Kelly: se ela quiser mudar qualquer trecho, vale responder aqui. <b>Novas de 17/10:</b> <a href='/servicos/assessoria-de-imprensa/brasilia/'>Brasília</a> e <a href='/servicos/assessoria-de-imprensa/porto-alegre/'>Porto Alegre</a>. Confirmar nas duas a frase \"encontros presenciais podem ser combinados quando o trabalho pede\" (a Kelly viaja a essas cidades?) e, em Porto Alegre, se algum cliente é gaúcho, porque hoje a página avisa que os exemplos não são do Rio Grande do Sul. <b>Reescritas em 17/10:</b> <a href='/servicos/marketing-de-autoridade/'>Marketing de autoridade</a>, <a href='/servicos/branding-estrategico/'>Branding estratégico</a>, <a href='/servicos/marketing-digital/'>Marketing digital</a>, <a href='/servicos/consultoria-em-comunicacao/'>Consultoria em comunicação</a> e <a href='/servicos/mentorias-exclusivas/'>Mentorias</a>. Confirmar: se a equipe ajuda o cliente a escrever os artigos (marketing de autoridade); se a verba de anúncios é cobrada à parte (marketing digital); o nome do programa, que aparece como Bolder em /mentorias/ e Be Bolder na página da Kelly; a duração de dois meses com um encontro presencial; e se o case da Globo Leilões deve mesmo dizer \"+31 milhões de cliques\", número que deixamos fora das páginas novas por parecer alto demais.", g:"todas as páginas revisadas por escrito"},
    {id:"m1ta", w:"IKOEH", t:"<b>Títulos dos posts cortados no meio da palavra.</b> Revisão de 17/10: 221 de 263 posts aparecem no Google com o título cortado no meio (\"...controle e velocidade nas… | Mclair\") e 157 descrições também. A causa é um corte fixo de caracteres no modelo dos posts; corrigir para cortar só em palavra inteira, ou usar o título SEO do painel quando existir.", g:"nenhum título de post cortado no meio da palavra"},
    {id:"m1tb", w:"IKOEH", t:"<b>Post com título \"O\".</b> O post <code>/blog/o/</code>, da Claudia Elisa sobre ESG, está publicado com o título \"O\". Dar o título real no painel.", g:"título real no ar"},
    {id:"m1tc", w:"IKOEH", t:"<b>Posts de leilão fora da categoria Leilões.</b> A categoria tem 8 posts, mas o site tem 24 sobre leilão (Correios, Caixa, Santander, Bradesco). Mover os 16 que faltam e fazer a página de leiloeiros ligar para os melhores deles no texto.", g:"24 posts na categoria e links na página de leiloeiros"},
    {id:"m1td", w:"IKOEH", t:"<b>Mais links internos para os guias e para Quanto custa.</b> Cada guia recebe só 4 links e Quanto custa recebe 9. Ligar a partir de media training, gestão de crise, assessor de imprensa, das cidades e de posts próximos do tema.", g:"cada guia com pelo menos 10 links internos"},
    {id:"m1te", w:"IKOEH", t:"<b>Logo quebrado e links externos quebrados.</b> O logo da EximiaCo no case Elemar Jr. aponta para um arquivo que não existe (<code>.png</code> em vez de <code>.webp</code>). Seis links externos dentro de posts estão quebrados (links \"safelinks\" do Outlook num post da Lambda3, arena22, vagas.lambda3 e um do Jovem Nerd).", g:"nenhum link ou imagem quebrada no rastreamento"},
    {id:"m1tf", w:"IKOEH", t:"<b>Posts repetidos.</b> Dois pares de posts têm o mesmo título (Correios e Guiando) e um terceiro par é quase igual (Zé Conecta). Apontar a versão menor para a mais completa, para as duas não disputarem a mesma busca.", g:"um post por assunto no Google"},
    {id:"m1tg", w:"IKOEH", t:"<b>Datas das páginas fixas no sitemap.</b> Só os posts levam data de atualização. As 39 páginas fixas (home, serviços, cidades, guias, Kelly, cases) saem sem data, e o índice do sitemap mostra 21/09.", g:"todas as páginas com data no sitemap"},
    {id:"m1th", w:"IKOEH", t:"<b>Cinco páginas de serviço antigas curtas.</b> Marketing de autoridade, branding, marketing digital, consultoria e mentorias têm cerca de 500 palavras e nenhuma pergunta frequente, contra 1.500 a 2.200 das páginas novas. Reescrever no mesmo padrão e mandar para a revisão da Kelly.", g:"as cinco com FAQ e texto completo"},
    {id:"m1ti", w:"IKOEH", t:"<b>Fotos da equipe no Perfil do Google.</b> Ficou de fora da atualização de 16/10. Subir as fotos da Kelly que o site já usa.", g:"fotos novas no Perfil"},
    {id:"m1pv", w:"Mclair", e:"aprovar o texto", t:"<b>Política de privacidade e aviso de cookies.</b> O site não tem página de privacidade (o endereço dá erro) e o Analytics e o pixel da Meta disparam antes de qualquer aceite. Desde 16/10 os formulários coletam nome, e-mail e telefone, o que a LGPD exige explicar. A IKOEH escreve a política e monta o aviso de cookies; <b>falta</b> a Mclair aprovar o texto e informar o CNPJ e o responsável pelos dados.", g:"página publicada e aviso de cookies no ar"},
    {id:"m1i", w:"IKOEH", s:"done", t:"<b>www redireciona para o endereço sem www (16/10).</b> Antes o <code>www.mclair.com.br</code> abria normalmente e o Bing mostrava a home com o título do site antigo. Agora ele leva direto a <code>https://mclair.com.br/</code>.", g:"<code>curl -I https://www.mclair.com.br/</code> devolve 301"},
    {id:"m1j", w:"IKOEH", s:"done", t:"<b>IndexNow ligado (16/10).</b> Chave publicada na raiz do site; o Bing aceitou as 406 páginas e recebe cada página nova ou alterada a cada publicação.", g:"o Bing responde 200 ao aviso"},
    {id:"m1k", w:"IKOEH", s:"done", t:"<b>Títulos sem palavras coladas (16/10).</b> Os títulos de 8 páginas (home, leiloeiros, mentorias, cases, clientes, contato, sobre e serviços) eram lidos como \"AgênciadeComunicaçãoEstratégica\" por quem lê o texto puro, como os sistemas que alimentam as IAs. Agora saem com espaço.", g:"títulos lidos com espaço em texto puro"},
    {id:"m1l", w:"IKOEH", s:"done", t:"<b>Título da home com \"Assessoria de Imprensa\" (16/10).</b> Agora é \"Assessoria de Imprensa e Comunicação Estratégica em SP | Mclair\", com descrição nova no mesmo tom.", g:"título novo no ar"},
    {id:"m1m", w:"Mclair", e:"decisão da Kelly", t:"<b>Leilões: falta decidir a chamada principal da página.</b> O título no Google já mudou em 16/10 para \"Assessoria de Imprensa para Leiloeiros e Marketing de Leilões\". A chamada principal da página continua o slogan \"Seu leilão merece mais que curtidas\". A sugestão é incluir \"assessoria de imprensa para leiloeiros\" nela, mas é texto de marca, então a decisão é da Kelly. O endereço <code>/marketing-para-leiloeiros/</code> não muda: é a página que ChatGPT e Perplexity citam em primeiro lugar.", g:"chamada decidida pela Kelly"},
    {id:"m1n", w:"IKOEH", s:"done", t:"<b>Página de assessoria de imprensa reescrita para quem quer contratar (16/10).</b> Título \"Agência de Assessoria de Imprensa | Mclair\" e cerca de 1.800 palavras: o que entra no trabalho, como funciona do diagnóstico à primeira matéria, onde os clientes já saíram (Fidalgo, VIP Leilões, Globo Leilões, Lambda3, Diego Nogare, Claudia Elisa), assessoria internacional com o case BMS/ABIMAQ, quem cuida da conta (a Kelly), para quem funciona e para quem não funciona, guias para ler antes de contratar e 10 perguntas frequentes.", g:"página no ar e indexação pedida"},
    {id:"m1o", w:"IKOEH", s:"done", t:"<b>Dados para o Google na página de assessoria (16/10).</b> Perguntas frequentes marcadas para o Google e as 9 cidades atendidas informadas, sem endereço, sem nota e sem preço inventados.", g:"dados válidos no teste do Google"},
    {id:"m1p", w:"IKOEH", s:"done", t:"<b>WhatsApp com a página de origem (16/10).</b> Toda mensagem que sai pela janela do WhatsApp termina com a página de onde a pessoa veio, e cada página de serviço abre o WhatsApp com o próprio assunto.", g:"cada contato mostra a página de origem"},
    {id:"m1q", w:"IKOEH", s:"done", t:"<b>Perfil da Empresa no Google: categoria, área, descrição e serviços (16/10).</b> Categoria principal passou de \"Agência de Marketing\" para \"Assessoria de imprensa\", com \"Agência de Marketing\" e \"Empresa de relações públicas\" como adicionais. A área de atendimento deixou de ser \"Brasil\" (o que punha o marcador no centro do país) e virou as 9 cidades do plano: São Paulo, Rio de Janeiro, Belo Horizonte, Porto Alegre, Curitiba, Florianópolis, Joinville, Distrito Federal e Goiânia, sem endereço. Descrição nova focada em assessoria de imprensa e 7 serviços cadastrados (assessoria para empresas, media training, gestão de crise, internacional, releases e pautas, clipping, consultoria). O Google revisa cada edição antes de publicar, em até um dia.", g:"categoria e área novas visíveis na ficha pública"},
    {id:"m1r", w:"Mclair", e:"precisa de login", t:"<b>Ficha no Bing Places.</b> Hoje não existe nenhuma, e ela é gratuita. O Bing importa os dados do Perfil do Google. <b>Falta:</b> entrar em bingplaces.com com uma conta Microsoft (pode ser com kelly@mclair.com.br) e escolher \"importar do Google\"; a IKOEH confere os dados depois. Alimenta o Bing local e o Copilot.", g:"\"Mclair Comunicação\" aparece no Bing Maps"},
    {id:"m1s", w:"Mclair", e:"precisa de login", t:"<b>Perfil no oHub, sem ativar o recurso de receber orçamentos.</b> E-mail de cadastro: kelly@mclair.com.br. <b>Falta:</b> criar a conta no oHub (a IKOEH não cria conta em nome do cliente); depois disso a IKOEH preenche área, ano de fundação, serviços e cases. O oHub foi a única fonte que a Visão Geral do Google usou para responder sobre agências em SP.", g:"perfil publicado e opção de orçamentos desligada"},
    {id:"m1t", w:"IKOEH", s:"done", desc:"Cadastros em hubs atraem sobretudo fornecedores oferecendo serviço, não clientes. O GetNinjas é um mercado de orçamentos: o perfil viraria caixa de pedidos de preço baixo e de propostas de terceiros. A decisão de 15/10 foi ficar só com Perfil do Google, Bing Places, oHub e Clutch (se for grátis).", t:"<b>GetNinjas: descartado.</b>"},
    {id:"m1u", w:"IKOEH", s:"done", desc:"Base de dados de CNPJ que vende listas de empresas. Estar lá não traz cliente e expõe o contato da Mclair a quem compra listas para prospectar. O mesmo vale para o b2bfy, citado no dossiê.", t:"<b>Econodata: descartado.</b>"},
    {id:"m1v", w:"IKOEH", t:"<b>Montar a rotina de medição do dia 15.</b> Search Console (cliques, impressões e consultas com \"assessoria de imprensa\"), Bing Webmaster com o relatório de IA, Semrush, e as mesmas perguntas feitas às IAs todo mês, sem login: \"Quais as melhores agências de assessoria de imprensa em São Paulo?\", \"Quem faz assessoria de imprensa para leiloeiros no Brasil?\", \"Qual agência de assessoria de imprensa contratar em Brasília?\" e \"Quanto custa assessoria de imprensa?\". Registra quem foi citado e por qual fonte.", g:"planilha de perguntas pronta e primeira rodada sem login registrada"},
    {id:"m1w", w:"Mclair", e:"decisão da Kelly", t:"<b>Nome do Facebook.</b> O acesso ao Perfil do Google e ao Facebook já existe (resposta de 16/10). <b>Falta:</b> a Kelly decidir se troca o nome da página do Facebook de \"M. Clair Comunicação\" para \"Mclair Comunicação\", para os cadastros dizerem a mesma coisa. O Facebook pode pedir revisão do nome.", g:"página do Facebook com o nome Mclair Comunicação"},
    {id:"m1x", w:"Mclair", s:"done", t:"<b>Endereço ou só área de atendimento.</b> Decidido: a Mclair aparece por área de atendimento, sem endereço, nas 9 cidades do plano.", g:"resposta por escrito"},
    {id:"m1y", w:"Mclair", s:"done", t:"<b>E-mail para cadastros.</b> Respondido em 16/10: os cadastros usam <code>kelly@mclair.com.br</code>, sempre com os recursos de receber orçamentos desligados.", g:"e-mail definido"},
    {id:"m1z", w:"Mclair", s:"done", t:"<b>LinkedIn pessoal da Kelly.</b> Recebido em 16/10: <a href='https://www.linkedin.com/in/kelly-pinheiro-30900123/'>linkedin.com/in/kelly-pinheiro-30900123</a>. Entrou no botão e no <code>sameAs</code> da página <a href='/kelly-pinheiro/'>/kelly-pinheiro/</a>.", g:"link conferido e publicado"},
    {id:"m1ma", w:"Mclair", e:"pedido na sexta 23/10", t:"<b>Clientes que topam avaliar no Google.</b> <b>Falta:</b> uma lista de clientes atuais ou recentes que aceitam deixar avaliação (nome e WhatsApp ou e-mail). A IKOEH prepara a mensagem e o link direto; quem pede é a Mclair. O ChatGPT ordenou as agências por nota, de 4,2 a 5,0, e a Mclair não tem nenhuma.", g:"lista recebida"},
    {id:"m1mb", w:"Mclair", s:"done", t:"<b>Matérias conquistadas com link.</b> Respondido em 16/10: as matérias já estão nos <a href='/cases/'>cases do site</a>, com veículo e link. Elas são a base da página \"Mclair na mídia\".", g:"fonte das matérias definida"}
  ]},
  {n:2, d:"novembro de 2026", t:"As três primeiras cidades e o silo no lugar", gate:"15 de dezembro: 3 páginas de cidade indexadas (São Paulo, Brasília e Porto Alegre) e as primeiras impressões com \"assessoria de imprensa\" no Search Console.", tasks:[
    {id:"m2a", w:"IKOEH", s:"done", t:"<b>Estrutura do silo de cidades.</b> As cidades ficam em <code>/servicos/assessoria-de-imprensa/{cidade}/</code>, com breadcrumb Início, Serviços, Assessoria de Imprensa, Cidade. O hub ganha a seção \"Assessoria de imprensa nas capitais\" com links HTML de verdade, e o rodapé ganha um bloco discreto \"Onde atendemos\". <b>Nada de cidade no menu</b> e nada de lista de dezenas de cidades.", g:"links rastreáveis no HTML do hub e do rodapé"},
    {id:"m2b", w:"IKOEH", s:"done", t:"<b>Dados estruturados de cidade, sem endereço inventado.</b> Em cada página: <code>Service</code> com <code>serviceType</code> \"Assessoria de imprensa\", <code>provider</code> apontando para a <code>Organization</code> da Mclair em São Paulo e <code>areaServed</code> do tipo <code>City</code> com o estado; <code>FAQPage</code> com perguntas da cidade; <code>BreadcrumbList</code>. Não entra <code>LocalBusiness</code> com endereço na cidade nem <code>aggregateRating</code>.", g:"Teste de pesquisa aprovada sem erros nas páginas novas"},
    {id:"m2c", w:"IKOEH", s:"done", t:"<b>Medidor de semelhança entre páginas de cidade (pronto em 17/10: São Paulo, Brasília e Porto Alegre entre 0,010 e 0,016).</b> Script que compara trechos de 5 palavras com o nome da cidade neutralizado. Meta: menos de 0,25 entre qualquer par. É o que separa a MGA Press (0,23 a 0,34, no topo) da F&B (0,90, posição 94).", g:"o script roda antes de cada publicação e o par mais parecido fica abaixo de 0,25"},
    {id:"m2d", w:"IKOEH", s:"done", t:"<b>Página de São Paulo</b> (<a href='/servicos/assessoria-de-imprensa/sao-paulo/'>no ar desde 16/10</a>). Recebe as variações com cidade: \"assessoria de imprensa sp\" (110/mês), \"em sp\" (90), \"são paulo\" (50) e \"em são paulo\" (50). No topo hoje estão Instagram, governo, oHub e vagas; a primeira agência real aparece na oitava posição. Texto próprio, com setores, imprensa paulistana e perguntas da cidade confirmados com a Mclair.", g:"publicada, no sitemap e inspecionada no Search Console"},
    {id:"m2e", w:"IKOEH", s:"done", t:"<b>Página de Brasília e DF</b> (<a href='/servicos/assessoria-de-imprensa/brasilia/'>no ar desde 17/10</a>). 100 buscas por mês somando as cinco variações, e o topo é fraco no on-page (Frisson sem H1 nem description, Agência 360 sem H1 nem dados estruturados). Brasília como sede da imprensa política e econômica, associações e entidades de classe (case BMS/ABIMAQ, se confirmado), media training para porta-vozes, crise e FAQ. Veículos citados sem prometer publicação: Correio Braziliense, Metrópoles, Poder360.", g:"publicada e semelhança abaixo de 0,25 contra SP"},
    {id:"m2f", w:"IKOEH", s:"done", t:"<b>Página de Porto Alegre e RS</b> (<a href='/servicos/assessoria-de-imprensa/porto-alegre/'>no ar desde 17/10</a>). 120 buscas por mês, a maior soma fora de SP. Title cobre \"rs\" e \"rio grande do sul\". Imprensa gaúcha (GZH/Zero Hora, Correio do Povo, Jornal do Comércio, RBS TV), indústria da Serra, agro e tecnologia, e Caxias do Sul como seção, não como página. Os primeiros colocados (FTcom e Acesso) não têm H1 válido nem FAQ.", g:"publicada e semelhança abaixo de 0,25 contra SP e Brasília"},
    {id:"m2g", w:"IKOEH", s:"done", t:"<b>Página \"Quanto custa assessoria de imprensa\"</b> (<a href='/servicos/assessoria-de-imprensa/quanto-custa/'>no ar desde 16/10</a>, com formulário que vai para o e-mail da Kelly e para o WhatsApp). Nenhuma das 15 agências analisadas publica faixa; quem pergunta cai no Cronoshare e no GetNinjas. Explica os modelos de cobrança (fee mensal, projeto, release avulso), o que muda o preço e o que está incluso. <b>Sem faixa de valor até a Mclair aprovar.</b>", g:"publicada, ligada pelo FAQ do hub"},
    {id:"m2h", w:"IKOEH", t:"<b>Semrush Position Tracking com localização por cidade.</b> O Semrush não tem SERP para as consultas de cidade na base geral; o rastreamento local é a única forma de medir o Google de verdade em cada praça.", g:"projeto criado com as consultas do painel abaixo"},
    {id:"m2i", w:"IKOEH", t:"<b>Clutch, só se o cadastro for grátis.</b> Apareceu entre as fontes da Perplexity. Mesmo e-mail de cadastros, sem recurso pago e sem receber orçamentos. Se exigir pagamento, a tarefa vira descartada com o motivo.", g:"perfil publicado ou decisão registrada"},
    {id:"m2j", w:"IKOEH", t:"<b>Pedido de avaliações no Google.</b> Mensagem curta e link direto preparados para a Mclair enviar à lista de clientes. Nenhuma avaliação comprada ou escrita por nós.", g:"mensagem e link entregues à Mclair"},
    {id:"m2k", w:"Mclair", e:"pedido na sexta 06/11", t:"<b>Faixa de preço.</b> <b>Falta:</b> aprovar a faixa que pode aparecer na página \"Quanto custa\" e o que entra em cada faixa, ou dizer por escrito que a Mclair prefere não publicar número. Sem aprovação, a página fica só com os modelos de cobrança.", g:"faixa aprovada ou recusa registrada"},
    {id:"m2l", w:"Mclair", s:"done", t:"<b>Cases por cidade e por segmento.</b> Respondido em 16/10: todos os cases podem ser citados. O atendimento fora de São Paulo é remoto, como diz a página de serviço.", g:"lista confirmada por escrito"},
    {id:"m2m", w:"Mclair", e:"envio de 16/11 a 30/11", t:"<b>Pedir as avaliações aos clientes.</b> <b>Falta:</b> enviar a mensagem preparada pela IKOEH aos clientes da lista. Quem pede é a Mclair, porque a relação é dela.", g:"primeiras avaliações visíveis no Perfil"},
    {id:"m2z", w:"IKOEH", t:"<b>Medição de 15/11.</b> Search Console, Bing, as perguntas fixas às IAs sem login e Semrush. Uma coluna nova nos painéis de desempenho e de palavras.", g:"coluna de 15/11 nos dois painéis"}
  ]},
  {n:3, d:"dezembro de 2026", t:"Prova: Mclair na mídia, o guia de SP e mais três cidades", gate:"15 de janeiro: 6 páginas de cidade indexadas, \"Mclair na mídia\" no ar com as matérias recebidas, e meta de 5 avaliações reais no Perfil do Google.", tasks:[
    {id:"m3a", w:"IKOEH", t:"<b>Página \"Mclair na mídia\"</b> (<code>/mclair-na-midia/</code>). Matérias organizadas por veículo e por cliente, com link para cada uma. É a prova que só uma assessoria pode ter, e o tipo de página que jornalistas e IAs reconhecem como evidência. A fonte são as matérias que já estão nos cases do site.", g:"publicada com link em cada matéria, ligada pelo hub"},
    {id:"m3b", w:"IKOEH", s:"done", t:"<b>Veículos e cases na página de assessoria (16/10).</b> O bloco \"Onde nossos clientes já saíram\" cita Folha de S.Paulo, Exame, Valor Investe, InfoMoney, IstoÉ Dinheiro, CNN Brasil, SBT, Record, Band e outros, com link para o case de cada cliente.", g:"veículos citados com link no hub"},
    {id:"m3c", w:"IKOEH", t:"<b>Guia próprio das melhores agências de assessoria de imprensa em São Paulo.</b> A MGA Press publicou o próprio ranking e está em sétimo na busca por \"melhores agências\", no formato que as IAs citam. O guia da Mclair diz quem escreve, explica os critérios e lista agências reais. Só para São Paulo, a cidade da Mclair: nas outras praças o formato pareceria oportunista.", g:"publicado, com critérios visíveis e a autoria da Mclair declarada"},
    {id:"m3d", w:"IKOEH", t:"<b>Página de Curitiba</b> (<code>/curitiba/</code>). 90 buscas por mês, a cidade mais disputada do Sul (MGA Press, NQM, Savannah e oHub). Indústria, tecnologia e saúde; Gazeta do Povo, Bem Paraná, Tribuna e RPC sem prometer publicação; \"Quanto custa assessoria de imprensa em Curitiba\"; FAQ maior que as 6 perguntas da NQM. Cobre também \"assessoria de comunicação curitiba\".", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m3e", w:"IKOEH", t:"<b>Página do Rio de Janeiro</b> (<code>/rio-de-janeiro/</code>). Cerca de 100 buscas por mês. A melhor página de cidade da MGA é a do Rio, mas não mostra nenhum cliente nem clipping carioca. A da Mclair entra com prova local só se existir.", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m3f", w:"IKOEH", t:"<b>Página de Belo Horizonte</b> (<code>/belo-horizonte/</code>). Cerca de 60 buscas por mês. A F&B, de BH, é o exemplo do que não fazer: 535 páginas iguais e posição 94.", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m3g", w:"Mclair", e:"pedido na sexta 04/12", t:"<b>Revisar as páginas de cidade antes de subir.</b> <b>Falta:</b> ler os textos de Curitiba, Rio e BH e marcar qualquer frase que não seja verdade para a Mclair. Combinado em 16/10: cada página de cidade entra nesta tarefa com o link, para a revisão ser feita direto na página.", g:"textos aprovados por escrito"},
    {id:"m3z", w:"IKOEH", t:"<b>Medição de 15/12.</b> Primeira leitura das três primeiras cidades no Position Tracking.", g:"coluna de 15/12 nos dois painéis"}
  ]},
  {n:4, d:"janeiro de 2027", t:"Sul e Centro-Oeste: Florianópolis, Joinville e Goiânia", gate:"15 de fevereiro: 9 páginas de cidade indexadas, o par mais parecido abaixo de 0,25, e meta de pelo menos uma cidade no top 20 do Position Tracking.", tasks:[
    {id:"m4a", w:"IKOEH", t:"<b>Página de Florianópolis e SC</b> (<code>/florianopolis/</code>). 60 buscas por mês, title cobre \"santa catarina\". Imprensa catarinense (NSC, ND+), startups e tecnologia da Ilha, turismo e eventos, interior de SC com link para Joinville. Concorrência média: Atré e Paes e Lima são locais e têm clipping.", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m4b", w:"IKOEH", t:"<b>Página de Joinville</b> (<code>/joinville/</code>). 40 buscas por mês e nenhum site de agência entre os 12 primeiros: o topo é só diretório e órgão público. A indústria de Joinville na imprensa nacional e setorial, A Notícia e NSC Joinville, e como funciona o atendimento a partir de SP. É a página de teste mais barata do plano.", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m4c", w:"IKOEH", t:"<b>Página de Goiânia</b> (<code>/goiania/</code>). 40 buscas por mês; o terceiro e o quinto lugar são da Idpress com uma página de 144 palavras. Agronegócio goiano na imprensa nacional, saúde e varejo, O Popular, Jornal Opção e TV Anhanguera.", g:"publicada e semelhança abaixo de 0,25"},
    {id:"m4d", w:"IKOEH", t:"<b>Links entre cidades vizinhas</b>, não entre todas: SP, Rio e BH; Curitiba, Joinville, Florianópolis e Porto Alegre; Brasília, Goiânia e Cuiabá.", g:"cada página de cidade liga só às vizinhas e ao hub"},
    {id:"m4e", w:"IKOEH", t:"<b>Revisão de 60 dias de SP, Brasília e Porto Alegre.</b> Onde a página estiver entre a 5ª e a 15ª posição, reforçar com um link de veículo local (release regional de cliente) ou com o perfil do oHub da cidade.", g:"decisão anotada para cada uma das três"},
    {id:"m4z", w:"IKOEH", t:"<b>Medição de 15/01.</b>", g:"coluna de 15/01 nos dois painéis"}
  ]},
  {n:5, d:"fevereiro de 2027", t:"Segmentos com case: tecnologia, internacional e leilões", gate:"15 de março: as duas páginas de segmento publicadas, e meta de \"assessoria de imprensa\" (ou uma variação com cidade) com pelo menos 10 cliques no mês no Search Console.", tasks:[
    {id:"m5a", w:"IKOEH", t:"<b>Medir o volume dos segmentos antes de escrever.</b> Médicos, startups, restaurantes, eventos e artistas têm 20 buscas cada; advogados, 0. A Mclair só faz página onde já tem case.", g:"volumes anotados no painel de palavras"},
    {id:"m5b", w:"IKOEH", t:"<b>Página de assessoria de imprensa para tecnologia</b> (<code>/servicos/assessoria-de-imprensa/tecnologia/</code>), com os cases Lambda3, Diego Nogare e Zbra, se confirmados.", g:"publicada com os cases ligados"},
    {id:"m5c", w:"IKOEH", t:"<b>Página de assessoria de imprensa internacional e comércio exterior</b> (<code>/servicos/assessoria-de-imprensa/internacional/</code>), com o case BMS/ABIMAQ e ApexBrasil. Nenhum concorrente analisado vende isso numa página.", g:"publicada com o case ligado"},
    {id:"m5d", w:"IKOEH", t:"<b>Mais um ou dois cases de leilão, com o nome do cliente no título.</b> Reforça a única liderança real que a Mclair já tem nas IAs. A página <code>/marketing-para-leiloeiros/</code> continua no mesmo endereço.", g:"cases publicados e ligados pela página de leilões"},
    {id:"m5e", w:"Mclair", e:"pedido na sexta 05/02", t:"<b>Cuiabá: existe cliente ou case em Mato Grosso?</b> <b>Falta:</b> confirmar se o case Abimaq/Agrishow é da Mclair ou se há cliente de agro em MT. A busca é de só 10 por mês; sem case, a página não é feita.", g:"resposta por escrito"},
    {id:"m5f", w:"Mclair", e:"pedido na sexta 05/02", t:"<b>Material dos cases de leilão novos.</b> <b>Falta:</b> autorização do cliente, números que podem aparecer e as matérias com link.", g:"material recebido"},
    {id:"m5z", w:"IKOEH", t:"<b>Medição de 15/02.</b>", g:"coluna de 15/02 nos dois painéis"}
  ]},
  {n:6, d:"março de 2027", t:"Meio do caminho: decidir com números", gate:"15 de abril: meta de 10 das consultas do painel no top 20 e a Mclair citada por pelo menos uma IA na pergunta sobre São Paulo.", tasks:[
    {id:"m6a", w:"IKOEH", t:"<b>Balanço de seis meses.</b> Quais cidades entraram, quais não saíram do lugar, de onde vieram os contatos (pela mensagem de origem do WhatsApp) e o que as IAs passaram a citar. O que não andou é corrigido ou cortado.", g:"relatório entregue à Mclair"},
    {id:"m6b", w:"IKOEH", t:"<b>Página de Cuiabá e MT</b> (<code>/cuiaba/</code>), só se o case ou cliente foi confirmado em fevereiro. Agro, máquinas agrícolas e eventos do setor; Gazeta Digital, Olhar Direto e Só Notícias.", g:"publicada, ou tarefa descartada com o motivo"},
    {id:"m6c", w:"IKOEH", t:"<b>FAQ com as perguntas reais dos contatos.</b> As dúvidas que chegaram pelo WhatsApp nesses meses entram nas perguntas do hub e das cidades.", g:"perguntas novas no FAQPage"},
    {id:"m6d", w:"IKOEH", t:"<b>Conferir que os cadastros dizem o mesmo.</b> Perfil do Google, Bing Places, oHub, Clutch, Facebook e LinkedIn com o mesmo nome (Mclair Comunicação), o mesmo telefone e a mesma área.", g:"nenhuma divergência entre os perfis"},
    {id:"m6z", w:"IKOEH", t:"<b>Medição de 15/03.</b>", g:"coluna de 15/03 nos dois painéis"}
  ]},
  {n:7, d:"abril de 2027", t:"Guias permanentes e a Mclair como notícia", gate:"15 de maio: 4 guias publicados e meta de uma matéria sobre a própria Mclair em veículo do setor.", tasks:[
    {id:"m7a", w:"IKOEH", t:"<b>Guias permanentes, um por semana</b>, assinados pela Kelly ou pela equipe: o que é release, media training, gestão de crise e como virar fonte de jornalistas. Cada um liga para o hub.", g:"4 guias publicados com autoria e FAQ"},
    {id:"m7b", w:"Mclair", e:"pedido na sexta 02/04", t:"<b>Pautas sobre a própria Mclair.</b> <b>Falta:</b> escolher duas pautas da agência (um número do trabalho, uma opinião da Kelly) para oferecer à imprensa do setor. A Mclair faz com ela mesma o que faz pelos clientes; cada matéria vira mais uma fonte para o Google e as IAs.", g:"pautas enviadas aos veículos"},
    {id:"m7c", w:"Mclair", e:"pedido na sexta 02/04", t:"<b>Prêmios e anuários do setor.</b> <b>Falta:</b> decidir em quais a Mclair se inscreve neste ano. O ChatGPT usou o Anuário da Comunicação Corporativa para responder.", g:"inscrições feitas"},
    {id:"m7z", w:"IKOEH", t:"<b>Medição de 15/04.</b>", g:"coluna de 15/04 nos dois painéis"}
  ]},
  {n:8, d:"maio de 2027", t:"Reforçar as cidades que pararam no meio da página", gate:"15 de junho: meta de 3 cidades no top 10 do Position Tracking.", tasks:[
    {id:"m8a", w:"IKOEH", t:"<b>Reforço das cidades entre a 5ª e a 15ª posição.</b> Link de veículo local a partir de release regional de cliente, perfil do oHub da cidade, e texto ampliado onde ficou curto.", g:"cada cidade com a ação anotada"},
    {id:"m8b", w:"IKOEH", t:"<b>Caxias do Sul e Londrina: página própria ou não.</b> Cada uma tem 20 buscas por mês. Só entram depois das capitais, e só se a seção dentro de Porto Alegre ou Curitiba não bastar. Maringá e Campo Grande têm volume 0: ficam de fora.", g:"decisão registrada"},
    {id:"m8c", w:"Mclair", e:"pedido na sexta 07/05", t:"<b>Releases regionais de clientes.</b> <b>Falta:</b> indicar clientes com pauta nas cidades do plano, para gerar matéria local que reforce a página da cidade.", g:"pautas indicadas"},
    {id:"m8z", w:"IKOEH", t:"<b>Medição de 15/05.</b>", g:"coluna de 15/05 nos dois painéis"}
  ]},
  {n:9, d:"junho de 2027", t:"Prova social atualizada", gate:"15 de julho: \"Mclair na mídia\" atualizada e meta de 10 avaliações reais no Perfil do Google.", tasks:[
    {id:"m9a", w:"IKOEH", t:"<b>Atualizar \"Mclair na mídia\"</b> com as matérias do semestre.", g:"matérias novas publicadas com link"},
    {id:"m9b", w:"Mclair", e:"pedido na sexta 04/06", t:"<b>Segunda rodada de avaliações e matérias novas.</b> <b>Falta:</b> pedir avaliação a clientes novos e mandar as matérias do semestre com link.", g:"avaliações novas e lista recebida"},
    {id:"m9c", w:"IKOEH", t:"<b>Responder às avaliações</b> no Perfil do Google, uma a uma, com texto aprovado pela Mclair.", g:"todas as avaliações respondidas"},
    {id:"m9z", w:"IKOEH", t:"<b>Medição de 15/06.</b>", g:"coluna de 15/06 nos dois painéis"}
  ]},
  {n:10, d:"julho de 2027", t:"Conversão: mais contatos do mesmo tráfego", gate:"15 de agosto: origem de cada contato registrada por página.", tasks:[
    {id:"m10a", w:"IKOEH", t:"<b>Formulário curto no topo das páginas de cidade</b>, com a cidade já preenchida, mais o WhatsApp. É o padrão de quem está no topo.", g:"formulário no ar nas páginas de cidade"},
    {id:"m10b", w:"IKOEH", t:"<b>Quais páginas trazem contato.</b> Contagem pela mensagem de origem do WhatsApp e pelo formulário; as páginas que trazem visita e não trazem contato ganham revisão de chamada.", g:"tabela de contatos por página"},
    {id:"m10z", w:"IKOEH", t:"<b>Medição de 15/07.</b>", g:"coluna de 15/07 nos dois painéis"}
  ]},
  {n:11, d:"agosto de 2027", t:"Revisão anual dos dados", gate:"15 de setembro: nenhum número desatualizado nas páginas de serviço, cidade e segmento.", tasks:[
    {id:"m11a", w:"IKOEH", t:"<b>Atualizar números e cases.</b> Total de inserções (hoje \"11.600+\"), cases novos, faixa de preço se publicada, e a data de revisão em cada página.", g:"todas as páginas com data de revisão de agosto"},
    {id:"m11b", w:"IKOEH", t:"<b>Medir de novo a semelhança</b> entre todas as páginas de cidade, depois de um ano de ajustes.", g:"par mais parecido abaixo de 0,25"},
    {id:"m11c", w:"Mclair", e:"pedido na sexta 06/08", t:"<b>Números do ano.</b> <b>Falta:</b> total atualizado de inserções e cases novos que podem ser citados.", g:"números recebidos"},
    {id:"m11z", w:"IKOEH", t:"<b>Medição de 15/08.</b>", g:"coluna de 15/08 nos dois painéis"}
  ]},
  {n:12, d:"setembro de 2027", t:"Fechamento do ano e plano do segundo", gate:"15 de outubro de 2027, meta do ano: a Mclair citada pelas IAs na pergunta sobre São Paulo, todas as cidades do plano no top 20 local e \"assessoria de imprensa\" entre as consultas que mais trazem cliques no Search Console.", tasks:[
    {id:"m12a", w:"IKOEH", t:"<b>Balanço do ano</b> contra a linha de partida de 23/09/2026: páginas, consultas, cidades, citações em IA e contatos.", g:"relatório entregue"},
    {id:"m12b", w:"Mclair", e:"pedido na sexta 03/09", t:"<b>Plano do ano 2 com a IKOEH.</b> <b>Falta:</b> aprovar as cidades e segmentos do segundo ano e o que continua.", g:"plano aprovado por escrito"},
    {id:"m12z", w:"IKOEH", t:"<b>Medição de 15/09.</b>", g:"coluna de 15/09 nos dois painéis"}
  ]}
];

/* ---- O que vem do servidor ----
   index.php grava um bloco JSON (não executável) com o estado das tarefas,
   as respostas, os painéis e, só para quem abriu com a chave certa, a
   própria chave. Tudo que veio de visitante é escapado antes de virar HTML. */
const DADOS = (() => {
  try { return JSON.parse(document.getElementById("plano-dados").textContent || "{}"); }
  catch { return {}; }
})();
const CHAVE = typeof DADOS.chave === "string" ? DADOS.chave : "";
const PUEDE_EDITAR = DADOS.editar === true && CHAVE !== "";
const BASE = "/dossie-seo-2026/";

const ORDER = ["todo","doing","done","blocked"];
const LABEL = {todo:"Pendente", doing:"Em andamento", done:"Feito", blocked:"Bloqueado"};
const ICON = {
  todo:'',
  doing:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M12 6v6l4 2"/></svg>',
  done:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg>',
  blocked:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>'
};

const state = {};
const stamps = {};
const isos = {};
WEEKS.forEach(w => w.tasks.forEach(t => { state[t.id] = t.s || "todo"; }));
const DESC = new Set();
WEEKS.forEach(w => w.tasks.forEach(t => { if (t.desc) DESC.add(t.id); }));
let filter = "all";

const el = id => document.getElementById(id);

function esc(s){ return String(s).replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }

/* ---- Palavras pelas quais a Mclair disputa ----
   Os dados ficam em dados/posicoes.json, lido pelo PHP, para a medição
   mensal acrescentar uma coluna sem mexer aqui. Uma consulta principal por
   página: duas páginas atrás da mesma busca atrapalham uma à outra. */
const POS = DADOS.posicoes || null;

const MES = ["janeiro","fevereiro","março","abril","maio","junho","julho","agosto","setembro","outubro","novembro","dezembro"];
function fecha(iso){ const [, m, d] = String(iso).split("-").map(Number); return `${d} de ${MES[m-1]}`; }
function fechaAnio(iso){ const [a, m, d] = String(iso).split("-").map(Number); return `${d} de ${MES[m-1]} de ${a}`; }

function buildPalabras(){
  const cont = el("kw");
  if (!cont || !POS) { if (cont) cont.hidden = true; return; }

  const fechas = (POS.medidas || []).map(m => m.fecha);
  const posDe = (kw) => fechas.map(f => {
    const m = POS.medidas.find(x => x.fecha === f);
    const v = m && m.posiciones ? m.posiciones[kw] : undefined;
    return (v === undefined || v === null) ? null : v;
  });

  const filas = (POS.objetivo || []).map(o => {
    const p = posDe(o.kw);
    const celdas = p.map(v => v === null
      ? '<td class="num fuera">fora</td>'
      : `<td class="num">${esc(v)}</td>`).join("");
    return `<tr>
      <td class="p ${esc(String(o.p).toLowerCase())}">${esc(o.p)}</td>
      <td class="q">${esc(o.kw)}</td>
      <td class="num">${o.vol ? Number(o.vol).toLocaleString("pt-BR") : "0"}</td>
      <td class="num">${o.kd === null ? "n/c" : esc(o.kd)}</td>
      <td class="dest">${esc(o.url)}${o.nova ? ' <span class="fuera">(a criar)</span>' : ""}</td>
      ${celdas}
    </tr>`;
  }).join("");

  const cabFechas = fechas.map(f => `<th class="num">${fecha(f)}</th>`).join("");
  const total = (POS.objetivo || []).reduce((t, o) => t + (o.vol || 0), 0);
  const cuenta = p => (POS.objetivo || []).filter(o => o.p === p).length;
  const vol = p => (POS.objetivo || []).filter(o => o.p === p)
    .reduce((t, o) => t + (o.vol || 0), 0).toLocaleString("pt-BR");
  const enTop100 = (POS.objetivo || []).filter(o => posDe(o.kw).some(v => v !== null)).length;

  const hoy = new Date().toISOString().slice(0,10);
  const cal = (POS.calendario || []).map(f => {
    const hecha = (POS.medidas || []).some(m => m.fecha === f);
    return hecha ? `<span class="hecha">${fecha(f)}</span>` : (f < hoy ? `<b>${fecha(f)}</b>` : fecha(f));
  }).join(" · ");

  cont.innerHTML = `
    <h3>As buscas pelas quais a Mclair disputa</h3>
    <div class="intro">
      <b>${(POS.objetivo || []).length} consultas</b> e <b>${total.toLocaleString("pt-BR")} buscas por mês</b> somadas, medidas em ${esc(POS.fuente || "")}.
      Uma consulta principal por página, para duas páginas da Mclair não disputarem a mesma busca.
      Cada coluna de data é a posição no Google naquele dia; <b>fora</b> quer dizer que a Mclair não estava nem entre os cem primeiros.
      Hoje são <b>${enTop100} de ${(POS.objetivo || []).length}</b> no top 100. Na coluna de dificuldade, <b>n/c</b> quer dizer que o Semrush não calculou por falta de dados, não que a busca é fácil.
      Mais da metade do total vem de uma busca só, "assessoria de imprensa", que mistura quem quer contratar com quem quer saber o que é a profissão.
    </div>
    <div class="leyenda">
      <div><span class="et p1">P1</span><span><b>Quer contratar uma agência.</b> "agência de assessoria de imprensa", "contratar assessoria de imprensa". ${cuenta("P1")} consultas e ${vol("P1")} buscas por mês, todas para a página de serviço.</span></div>
      <div><span class="et p2">P2</span><span><b>Quer uma agência que atenda a sua cidade.</b> ${cuenta("P2")} consultas e ${vol("P2")} buscas por mês, cada uma para a página da sua cidade. Pouco volume, mas o custo por clique no Google Ads (até R$ 5,66 em SP) mostra que quem compra mídia paga para estar ali.</span></div>
      <div><span class="et p3">P3</span><span><b>Ainda está pesquisando preço.</b> "quanto custa", "preço", "valor". ${cuenta("P3")} consultas e ${vol("P3")} buscas por mês. Nenhuma agência publica faixa de valor, então a página que responder não tem concorrente direto.</span></div>
    </div>
    <div class="scroll"><table>
      <thead><tr><th>Prio.</th><th>Consulta</th><th class="num">Por mês</th><th class="num">Dific.</th><th class="dest">Página</th>${cabFechas}</tr></thead>
      <tbody>${filas}</tbody>
    </table></div>
    <div class="cal"><b>Medição no dia 15 de cada mês:</b> ${cal}. As feitas ficam em verde.
      Cada medição acrescenta uma coluna a esta tabela, para o avanço (ou a falta dele) aparecer de relance.</div>`;
}


/* ---- Painel de desempenho ----
   Uma coluna por medição, uma linha por métrica. O que importa é ler a
   linha da esquerda para a direita. Os dados ficam em dados/desempenho.json. */
const DES = DADOS.desempenho || null;

function numero(v){
  if (v === null || v === undefined) return "sem dado";
  return Number.isInteger(v) ? v.toLocaleString("pt-BR") : esc(String(v).replace(".", ","));
}

function buildDesempeno(){
  const cont = el("perf");
  if (!cont || !DES) { if (cont) cont.hidden = true; return; }

  const medidas = DES.medidas || [];
  const ultima = medidas[medidas.length - 1] || {};
  const previa = medidas.length > 1 ? medidas[medidas.length - 2] : null;

  const flecha = (m) => {
    if (!previa) return "";
    const a = previa[m.id], b = ultima[m.id];
    if (a === null || a === undefined || b === null || b === undefined) return "";
    if (a === b) return ' <span class="igual">=</span>';
    const sube = b > a;
    const bien = (m.mejor === "arriba") ? sube : !sube;
    return ` <span class="${bien ? "sube" : "baja"}">${sube ? "▲" : "▼"} ${numero(Math.abs(Math.round((b - a) * 10) / 10))}</span>`;
  };

  const filas = (DES.metricas || []).map(m => {
    const celdas = medidas.map(me => `<td class="num">${numero(me[m.id])}</td>`).join("");
    return `<tr><td class="met">${esc(m.nombre)}${m.nota ? `<span class="mnota">${esc(m.nota)}</span>` : ""}</td>${celdas}<td class="num">${flecha(m)}</td></tr>`;
  }).join("");

  const cabeceras = medidas.map(me => `<th class="num">${fecha(me.fecha)}</th>`).join("");
  const notas = medidas.map(me => `<li><b>${fechaAnio(me.fecha)}:</b> ${esc(me.nota || "")}</li>`).join("");

  const hoy = new Date().toISOString().slice(0,10);
  const cal = (DES.calendario || []).map(f => {
    const hecha = medidas.some(me => me.fecha === f);
    return hecha ? `<span class="hecha">${fecha(f)}</span>` : (f < hoy ? `<b>${fecha(f)}</b>` : fecha(f));
  }).join(" · ");

  cont.innerHTML = `
    <h3>Como o site está indo, medido</h3>
    <div class="intro">
      Cada coluna é uma medição; a seta do fim compara com a anterior. Fontes: ${esc(DES.fuentes || "")}.
      Estar <b>indexado</b> é a página existir no buscador; <b>impressão</b> é alguém ter visto a página numa busca;
      <b>clique</b> é ter entrado. São três coisas diferentes e se ganham nessa ordem.
      A primeira coluna é o ponto de partida do dossiê de 23 de setembro de 2026.
    </div>
    <div class="scroll"><table>
      <thead><tr><th>O que se mede</th>${cabeceras}<th class="num">Mudança</th></tr></thead>
      <tbody>${filas}</tbody>
    </table></div>
    <div class="intro"><b>O que aconteceu em cada medição</b><ul class="notas">${notas}</ul></div>
    <div class="cal"><b>Medição no dia 15 de cada mês:</b> ${cal}. As feitas ficam em verde.</div>`;
}

function buildWeeks(){
  const host = el("weeks");
  host.innerHTML = WEEKS.map(w => `
    <section class="week" id="s${w.n}" data-week="${w.n}">
      <header class="whead">
        <h3>Mês ${w.n}: ${esc(w.t)}</h3>${w.n === mesAtual() ? `<span class="wagora">mês atual</span>` : ``}
        <span class="wdate">${esc(w.d)}</span>
      </header>
      <ul class="tasks">
        ${w.tasks.map(t => `
          <li class="task${t.desc ? " is-desc" : ""}" id="t-${t.id}" data-id="${t.id}" data-who="${t.w}"${t.desc ? ` data-desc="1"` : ""}>
            ${t.desc ? `<span class="cyc-off" aria-hidden="true"></span>` : `<button class="cyc" type="button" data-id="${t.id}" data-s="todo" aria-label="Estado da tarefa"></button>`}
            <div class="body">
              <div class="txt">${t.t}</div>
              ${t.desc ? `<p class="desc"><b>Por quê:</b> ${t.desc}</p>` : ``}
              ${t.img ? `<div class="shots">${t.img.map(i => `<figure><a href="${i.s}" target="_blank" rel="noopener"><img src="${i.s}" alt="${esc(i.c)}" loading="lazy"></a><figcaption>${i.c}</figcaption></figure>`).join("")}</div>` : ``}
              <div class="meta">
                <span class="who">${t.desc ? "Descartada" : "Responsável: " + t.w}</span>
                ${t.e ? `<span class="envio">Prazo: ${t.e}</span>` : ``}
                ${t.g ? `<span class="gate"><b>Pronto quando:</b> ${t.g}</span>` : ``}
                <span class="stamp" data-stamp="${t.id}"></span>
              </div>
              <div class="resp" data-resp="${t.id}"></div>
            </div>
          </li>`).join("")}
      </ul>
      ${w.gate ? `<div class="gatebar"><span class="mark">Meta do mês:</span><span>${esc(w.gate)}</span></div>` : ``}
    </section>`).join("");
}

/* ---- Resumo, cronograma e pedidos à Mclair ----
   Tudo calculado a partir de WEEKS e do estado. O mês 1 é outubro de 2026. */
const MES_CURTO = ["jan","fev","mar","abr","mai","jun","jul","ago","set","out","nov","dez"];

function mesAtual(){
  let a = 2026, m = 10;
  try {
    const p = new Intl.DateTimeFormat("en-CA", {timeZone: "America/Sao_Paulo", year: "numeric", month: "2-digit"}).format(new Date());
    [a, m] = p.split("-").map(Number);
  } catch { const d = new Date(); a = d.getFullYear(); m = d.getMonth() + 1; }
  return Math.min(12, Math.max(1, (a - 2026) * 12 + (m - 10) + 1));
}
function nomeMes(w){ return w.d.charAt(0).toUpperCase() + w.d.slice(1); }

/* Título (o primeiro negrito), o primeiro link e o que falta (o texto depois de "Falta:"). */
const PARTES = {};
function partes(t){
  if (PARTES[t.id]) return PARTES[t.id];
  const tp = document.createElement("template");
  tp.innerHTML = t.t;
  const b = tp.content.querySelector("b");
  const a = tp.content.querySelector("a[href]");
  const titulo = b ? b.textContent.trim().replace(/[.:]$/, "") : tp.content.textContent.trim();
  let falta = "";
  const i = t.t.indexOf("<b>Falta:</b>");
  if (i >= 0) falta = t.t.slice(i).trim();
  else { if (b) b.remove(); falta = tp.innerHTML.trim(); }
  return PARTES[t.id] = {titulo, href: a ? a.getAttribute("href") : "", falta};
}

function plural(n, um, varios){ return n + " " + (n === 1 ? um : varios); }
function listaPt(xs){ return xs.length < 2 ? xs.join("") : xs.slice(0, -1).join(", ") + " e " + xs[xs.length - 1]; }
function dataCurta(iso){
  try {
    const d = new Date(iso);
    if (isNaN(d)) return "";
    return d.toLocaleDateString("pt-BR", {day: "numeric", month: "short", timeZone: "America/Sao_Paulo"}).replace(" de ", " ").replace(".", "");
  } catch { return ""; }
}
function dataLonga(iso){
  try {
    const d = new Date(iso);
    if (isNaN(d)) return "";
    return d.toLocaleDateString("pt-BR", {day: "numeric", month: "long", year: "numeric", timeZone: "America/Sao_Paulo"});
  } catch { return ""; }
}

/* Os pedidos à Mclair são desenhados uma vez (têm formulário dentro); render() só esconde os que ficaram feitos. */
function buildDepende(){
  const atual = mesAtual();
  const item = (w, t) => {
    const p = partes(t);
    return `<li class="dep-item" id="d-${t.id}" data-id="${t.id}">
      <p class="dep-tit"><b>${esc(p.titulo)}</b></p>
      <p class="dep-meta">${t.e ? `<span class="envio">${esc(t.e)}</span>` : ``}<a href="#t-${t.id}">${esc(nomeMes(w))}, no plano</a><span class="dep-estado" data-dep-estado="${t.id}"></span></p>
      <div class="dep-falta">${p.falta}</div>
      <div class="resp" data-resp="${t.id}" data-pref="d"></div>
    </li>`;
  };
  const agora = [], depois = [];
  WEEKS.forEach(w => w.tasks.forEach(t => {
    if (t.w !== "Mclair" || t.desc) return;
    (w.n <= atual ? agora : depois).push(item(w, t));
  }));
  el("dep-agora").innerHTML = agora.join("");
  el("dep-depois").innerHTML = depois.join("");
}

function renderResumo(counts){
  const atual = mesAtual();
  const total = counts.todo + counts.doing + counts.done + counts.blocked;

  /* Onde estamos */
  const partesConta = [
    plural(counts.done, "feita", "feitas"),
    plural(counts.doing, "em andamento", "em andamento"),
    plural(counts.blocked, "bloqueada", "bloqueadas"),
    plural(counts.todo, "pendente", "pendentes")
  ];
  const mclair = WEEKS.flatMap(w => w.tasks).filter(t => t.w === "Mclair" && !t.desc && state[t.id] !== "done").length;
  el("onde-conta").innerHTML = `O plano tem ${total} tarefas: ${listaPt(partesConta)}. `
    + (mclair ? `<a href="#depende">${plural(mclair, "pedido espera", "pedidos esperam")} a Mclair</a>.` : `Nenhum pedido espera a Mclair agora.`);
  const pc = n => total ? (n / total * 100) : 0;
  el("track").innerHTML =
    `<i class="d" style="width:${pc(counts.done)}%"></i>` +
    `<i class="g" style="width:${pc(counts.doing)}%"></i>` +
    `<i class="b" style="width:${pc(counts.blocked)}%"></i>`;
  el("track").setAttribute("aria-label", `${Math.round(pc(counts.done))}% das tarefas feitas`);

  const ordem = {};
  let k = 0;
  WEEKS.forEach(w => w.tasks.forEach(t => { ordem[t.id] = k++; }));
  const feitas = WEEKS.flatMap(w => w.tasks)
    .filter(t => !t.desc && state[t.id] === "done" && isos[t.id])
    .sort((a, b) => (isos[b.id] > isos[a.id] ? 1 : isos[b.id] < isos[a.id] ? -1 : ordem[a.id] - ordem[b.id]))
    .slice(0, 6);
  el("entregas").innerHTML = feitas.length ? feitas.map(t => {
    const p = partes(t);
    const nome = p.titulo.replace(/\s*\(\d{2}\/\d{2}\)$/, "");
    const tit = p.href ? `<a href="${esc(p.href)}">${esc(nome)}</a>` : esc(nome);
    return `<li><time datetime="${esc(isos[t.id])}">${esc(dataCurta(isos[t.id]))}</time><span>${tit} <a class="ver" href="#t-${t.id}">ver tarefa</a></span></li>`;
  }).join("") : `<li><span>Nenhuma entrega marcada ainda.</span></li>`;

  const paradas = WEEKS.flatMap(w => w.tasks).filter(t => !t.desc && state[t.id] === "blocked");
  el("onde-parado").hidden = !paradas.length;
  el("onde-parado").innerHTML = paradas.length ? `<b>Parado:</b> ` + paradas.map(t =>
    `<a href="#t-${t.id}">${esc(partes(t).titulo)}</a>${t.e && !partes(t).titulo.includes(t.e) ? ` (${esc(t.e)})` : ``}`).join("; ") + "." : "";

  const w = WEEKS.find(x => x.n === atual);
  if (w) {
    const abertas = w.tasks.filter(t => !t.desc && state[t.id] !== "done");
    const daMclair = abertas.filter(t => t.w === "Mclair").length;
    const corte = w.gate.indexOf(": ");
    const quando = corte > 0 ? w.gate.slice(0, corte) : "";
    const oque = corte > 0 ? w.gate.slice(corte + 2) : w.gate;
    el("marco").innerHTML = `<b>Meta de ${esc(w.d)}, conferida em ${esc(quando)}:</b> ${esc(oque)} `
      + (abertas.length ? `Faltam ${plural(abertas.length, "tarefa", "tarefas")} deste mês${daMclair ? `, ${daMclair} da Mclair` : ``}. ` : `Todas as tarefas do mês estão feitas. `)
      + `<a href="#s${w.n}">Ver o mês</a>.`;
  }

  /* Cronograma */
  el("crono").innerHTML = WEEKS.map(w => {
    const ts = w.tasks.filter(t => !t.desc);
    const feitas = ts.filter(t => state[t.id] === "done").length;
    const pct = ts.length ? Math.round(feitas / ts.length * 100) : 0;
    const fase = w.n < atual ? "passado" : w.n === atual ? "atual" : "futuro";
    const [mes, ano] = [w.d.split(" de ")[0], w.d.split(" de ")[1]];
    const corte = w.gate.indexOf(": ");
    const quando = corte > 0 ? w.gate.slice(0, corte) : "";
    const oque = corte > 0 ? w.gate.slice(corte + 2) : w.gate;
    return `<li class="cr cr-${fase}${pct === 100 ? " cr-cheio" : ""}"${fase === "atual" ? ` aria-current="date"` : ``}>
      <a href="#s${w.n}" data-mes="${w.n}">
        <span class="cr-ponto" aria-hidden="true"></span>
        <span class="cr-mes">${esc(mes)} ${esc(ano)}${fase === "atual" ? ` <span class="cr-agora">mês atual</span>` : ``}</span>
        <span class="cr-tema">${esc(w.t)}</span>
        <span class="cr-conta">${plural(ts.length, "tarefa", "tarefas")}, ${pct}% feito</span>
        <span class="cr-barra" aria-hidden="true"><em style="width:${pct}%"></em></span>
        <span class="cr-meta"><b>Meta em ${esc(quando)}:</b> ${esc(oque)}</span>
      </a>
    </li>`;
  }).join("");

  /* O que depende da Mclair */
  let nAgora = 0, nDepois = 0;
  document.querySelectorAll(".dep-item").forEach(li => {
    const s = state[li.dataset.id] || "todo";
    li.hidden = s === "done";
    if (li.hidden) return;
    if (li.closest("#dep-depois")) nDepois++; else nAgora++;
    const est = li.querySelector("[data-dep-estado]");
    if (est) est.textContent = s === "todo" ? "" : LABEL[s].toLowerCase();
  });
  el("depende-conta").textContent = nAgora
    ? `${plural(nAgora, "pedido está aberto", "pedidos estão abertos")} agora. É o que trava as próximas tarefas da IKOEH, então vale começar por aqui.`
    : `Nenhum pedido aberto para agora.`;
  el("dep-depois-box").hidden = !nDepois;
  el("dep-depois-tit").textContent = `Mais adiante: ${plural(nDepois, "pedido já previsto", "pedidos já previstos")}, cada um com a data do pedido`;

  /* Data do topo */
  const ult = Object.values(isos).sort().pop();
  if (ult && el("atualizado")) el("atualizado").textContent = dataLonga(ult);
}

/* ---- Respostas dentro de cada tarefa ----
   O formulário é um envio normal (multipart) para ?plano=responder:
   funciona igual em qualquer celular e volta para a mesma tarefa. */
const RESP = Array.isArray(DADOS.respostas) ? DADOS.respostas : [];
const LIM = DADOS.limites || {arquivos: 3, mb: 10, texto: 5000, tipos: ".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.txt"};
const TIPOS_TXT = "PDF, JPG, PNG, WEBP, Word (docx), Excel (xlsx) e texto (txt)";
const RESP_ERR = {
  vazio: "Escreva uma resposta ou anexe pelo menos um arquivo.",
  tipo: `Nenhum arquivo foi aceito. Formatos permitidos: ${TIPOS_TXT}, até ${LIM.mb} MB cada um.`,
  grande: `O envio ficou grande demais. Máximo de ${LIM.mb} MB por arquivo.`,
  muitos: `Máximo de ${LIM.arquivos} arquivos por resposta.`,
  limite: "Envios demais em uma hora a partir desta conexão. Tente mais tarde.",
  token: "O formulário expirou. Recarregue a página e tente de novo.",
  tarefa: "Tarefa não encontrada. Recarregue a página.",
  falha: "Não foi possível gravar agora. Tente de novo em instantes.",
  cheio: "Esta tarefa não recebe mais respostas por aqui. Fale com a IKOEH."
};
function respItem(m){
  const arq = m.a && m.a.length ? `<ul class="resp-arquivos">${m.a.map(a =>
    `<li><a href="${BASE}?plano=baixar&amp;id=${encodeURIComponent(a.id)}" rel="nofollow">${esc(a.n)}</a> <span>${esc(a.p)}</span></li>`).join("")}</ul>` : ``;
  const apagar = PUEDE_EDITAR ? `<form method="post" action="${BASE}?plano=apagar" class="resp-apagar">
      <input type="hidden" name="k" value="${esc(CHAVE)}"><input type="hidden" name="id" value="${esc(m.id)}">
      <input type="hidden" name="tarefa" value="${esc(m.tarefa)}">
      <button type="submit">Apagar</button></form>` : ``;
  return `<li><p class="resp-meta"><b>${esc(m.q || "Sem nome")}</b> · ${esc(m.f)}</p>
    ${m.x ? `<p class="resp-texto">${esc(m.x).replace(/\n/g, "<br>")}</p>` : ``}${arq}${apagar}</li>`;
}
function buildResp(){
  const porTarefa = {};
  RESP.forEach(m => { if (m.tarefa) (porTarefa[m.tarefa] = porTarefa[m.tarefa] || []).push(m); });
  const q = new URLSearchParams(location.search);
  const volta = q.get("t") || "";
  /* A volta do envio abre o formulário num lugar só: no pedido à Mclair, se a tarefa estiver lá, ou na tarefa. */
  const voltaNoPedido = volta !== "" && !!document.getElementById("d-" + volta) && (state[volta] || "todo") !== "done";
  document.querySelectorAll(".resp").forEach(box => {
    const id = box.dataset.resp, pref = box.dataset.pref || "", lista = porTarefa[id] || [], n = lista.length;
    const aqui = volta === id && (pref === "d") === voltaNoPedido;
    let aviso = "", erro = false;
    if (aqui) {
      if (q.has("ok")) { const r = Number(q.get("r") || 0); aviso = r > 0 ? `Recebido. ${r} arquivo(s) não foram aceitos por tipo ou tamanho.` : "Recebido. Obrigado, já vamos ver."; }
      else if (q.has("apagado")) aviso = "Resposta apagada.";
      else if (q.has("e")) { const e = q.get("e"); aviso = Object.hasOwn(RESP_ERR, e) ? RESP_ERR[e] : "Não foi possível enviar. Tente de novo."; erro = true; }
    }
    const aberto = aqui;
    box.innerHTML = `
      <button type="button" class="resp-btn" aria-expanded="${aberto}" aria-controls="rp-${pref}${id}">Responder${n ? ` <span class="resp-n">${n} ${n === 1 ? "resposta" : "respostas"}</span>` : ``}</button>
      ${n ? `<ol class="resp-lista">${lista.map(respItem).join("")}</ol>` : ``}
      <div class="resp-panel" id="rp-${pref}${id}"${aberto ? `` : ` hidden`}>
        ${aviso ? `<p class="resp-aviso${erro ? " resp-aviso-erro" : ""}" role="status">${esc(aviso)}</p>` : ``}
        <form class="resp-form" method="post" action="${BASE}?plano=responder&amp;t=${encodeURIComponent(id)}" enctype="multipart/form-data">
          <input type="hidden" name="token" value="${esc(DADOS.token || "")}">
          <input type="hidden" name="tarefa" value="${esc(id)}">
          ${PUEDE_EDITAR ? `<input type="hidden" name="k" value="${esc(CHAVE)}">` : ``}
          <div class="resp-armadilha" aria-hidden="true"><label>Site <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
          <label>Seu nome<input type="text" name="nome" maxlength="80" autocomplete="name"></label>
          <label>Resposta<textarea name="texto" rows="4" maxlength="${LIM.texto}"></textarea></label>
          <label>Arquivos <span class="resp-opc">(até ${LIM.arquivos}, máximo de ${LIM.mb} MB cada um: ${TIPOS_TXT})</span><input type="file" name="arquivos[]" multiple accept="${esc(LIM.tipos)}"></label>
          <p class="resp-opc">O que for enviado aqui fica visível, com os arquivos, para quem tiver o link desta página.</p>
          <button type="submit" class="resp-enviar">Enviar resposta</button>
        </form>
      </div>`;
    box.querySelector(".resp-btn").addEventListener("click", ev => {
      const b = ev.currentTarget, p = box.querySelector(".resp-panel"), abrir = p.hidden;
      p.hidden = !abrir; b.setAttribute("aria-expanded", String(abrir));
    });
    box.querySelector(".resp-form").addEventListener("submit", ev => {
      const f = ev.currentTarget, input = f.querySelector('input[type="file"]');
      if (input && input.files && input.files.length > LIM.arquivos) {
        ev.preventDefault(); alert(RESP_ERR.muitos); return;
      }
      if (input && input.files && [...input.files].some(x => x.size > LIM.mb * 1024 * 1024)) {
        ev.preventDefault(); alert(RESP_ERR.grande); return;
      }
      const btn = f.querySelector(".resp-enviar"); btn.disabled = true; btn.textContent = "Enviando";
    });
  });
  if (volta) ALVO_INICIAL = (voltaNoPedido ? "d-" : "t-") + volta;
}
let ALVO_INICIAL = "";

function render(){
  const counts = {todo:0, doing:0, done:0, blocked:0};
  document.querySelectorAll(".task").forEach(li => {
    const id = li.dataset.id, s = state[id] || "todo", desc = DESC.has(id);
    if (!desc) counts[s]++;
    const btn = li.querySelector(".cyc");
    if (btn) {
      btn.dataset.s = s;
      btn.innerHTML = ICON[s];
      btn.setAttribute("aria-label", "Estado: " + LABEL[s] + (PUEDE_EDITAR ? ". Clique para mudar" : ""));
    }
    li.classList.toggle("is-done", s === "done" && !desc);
    const st = li.querySelector("[data-stamp]");
    st.textContent = stamps[id] ? (desc ? "decidido em " : "atualizado em ") + stamps[id] : "";
    const show = filter === "all" ? true
      : filter === "open" ? s !== "done" && !desc
      : filter === "Mclair" ? li.dataset.who === "Mclair" && s !== "done"
      : li.dataset.who === filter;
    li.hidden = !show;
  });

  WEEKS.forEach(w => {
    const total = w.tasks.filter(t => !t.desc).length;
    const done = w.tasks.filter(t => !t.desc && state[t.id] === "done").length;
    const sec = el("s" + w.n);
    if (sec) {
      sec.classList.toggle("done", total > 0 && done === total);
      const vis = w.tasks.some(t => {
        const s = state[t.id] || "todo";
        return filter === "all" ? true : filter === "open" ? s !== "done" && !t.desc
          : filter === "Mclair" ? t.w === "Mclair" && s !== "done" : t.w === filter;
      });
      sec.hidden = !vis;
    }
  });

  const faltaAg = [...document.querySelectorAll('.task[data-who="Mclair"]')].filter(li => (state[li.dataset.id] || "todo") !== "done").length;
  el("f-ag-n").textContent = faltaAg;
  el("filtro-aviso").textContent = filter === "Mclair" ? `Mostrando só as ${faltaAg} tarefas que faltam da Mclair. O que já foi feito fica escondido; "Tudo" mostra de novo.` : "";
  renderResumo(counts);
}

function fmt(iso){
  try {
    const d = new Date(iso);
    if (isNaN(d)) return "";
    return d.toLocaleDateString("pt-BR", {day:"numeric", month:"short", timeZone:"America/Sao_Paulo"}) + " " +
           d.toLocaleTimeString("pt-BR", {hour:"2-digit", minute:"2-digit", timeZone:"America/Sao_Paulo"});
  } catch { return ""; }
}

function aplicarEstado(dados){
  let mudou = false;
  Object.keys(dados || {}).forEach(id => {
    if (!(id in state)) return;
    const d = dados[id];
    if (d && ORDER.includes(d.status) && state[id] !== d.status) { state[id] = d.status; mudou = true; }
    if (d && d.updatedAt) { stamps[id] = fmt(d.updatedAt); if (isos[id] !== d.updatedAt) { isos[id] = d.updatedAt; mudou = true; } }
  });
  return mudou;
}

function aplicarFiltro(f, rolar = true){
  filter = f;
  document.querySelectorAll(".filter").forEach(b =>
    b.setAttribute("aria-pressed", String(b.dataset.f === filter)));
  render();
  if (rolar) document.querySelector(".controls").scrollIntoView({block: "start", behavior: "smooth"});
}

/* Link para uma tarefa ou mês escondido pelo filtro volta para "Tudo"; link para o anexo abre o anexo. */
function irPara(id, suave){
  const alvo = id && document.getElementById(id);
  if (!alvo) return false;
  if (id === "dossie" && alvo.tagName === "DETAILS") alvo.open = true;
  if (alvo.closest("[hidden]") && alvo.closest("#plano")) aplicarFiltro("all", false);
  alvo.scrollIntoView({block: "start", behavior: suave ? "smooth" : "auto"});
  return true;
}

function guardar(id, status){
  if (!PUEDE_EDITAR) return;
  fetch(BASE + "?plano=salvar", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({k: CHAVE, id, status})
  }).then(r => r.json()).then(r => {
    if (!r.ok) el("offline").hidden = false;
    else if (r.updatedAt) { stamps[id] = fmt(r.updatedAt); isos[id] = r.updatedAt; render(); }
  }).catch(() => { el("offline").hidden = false; });
}

/* ---- Início ---- */
aplicarEstado(DADOS.estado);

el("aviso").textContent = PUEDE_EDITAR
  ? "Modo de edição. Um clique no quadradinho de cada tarefa muda o estado e fica salvo para todos."
  : "Acompanhamento ao vivo do plano. A página se atualiza sozinha a cada minuto conforme a equipe avança. Para mandar um material ou tirar uma dúvida, use o Responder da tarefa.";

buildWeeks();
buildDepende();
buildResp();
buildDesempeno();
buildPalabras();
render();
if (!PUEDE_EDITAR) document.body.classList.add("solo-leitura");
{
  const inicial = ALVO_INICIAL || decodeURIComponent(location.hash.slice(1));
  if (inicial && irPara(inicial, false)) {
    // O navegador tenta de novo a âncora no load, quando as imagens chegam: mantém o mesmo alvo.
    window.addEventListener("load", () => irPara(inicial, false), {once: true});
  }
}

document.addEventListener("click", ev => {
  const btn = ev.target.closest(".cyc");
  if (btn) {
    if (!PUEDE_EDITAR) return;
    const id = btn.dataset.id;
    const next = ORDER[(ORDER.indexOf(state[id] || "todo") + 1) % ORDER.length];
    state[id] = next;
    stamps[id] = fmt(new Date().toISOString());
    isos[id] = new Date().toISOString().slice(0, 19) + "Z";
    render();
    guardar(id, next);
    return;
  }
  const f = ev.target.closest(".filter");
  if (f) { aplicarFiltro(f.dataset.f); return; }
  const a = ev.target.closest('a[href^="#"]');
  if (a && !ev.metaKey && !ev.ctrlKey && !ev.shiftKey) {
    const id = decodeURIComponent(a.getAttribute("href").slice(1));
    if (irPara(id, true)) { ev.preventDefault(); history.replaceState(null, "", "#" + id); }
  }
});
el("falta-ver").addEventListener("click", () => aplicarFiltro("Mclair"));

// Atualização periódica para o cliente ver as mudanças sem recarregar.
if (!PUEDE_EDITAR) {
  setInterval(() => {
    if (document.hidden) return;
    fetch(BASE + "?plano=estado", {cache: "no-store"})
      .then(r => r.json())
      .then(dados => { if (aplicarEstado(dados)) render(); })
      .catch(() => {});
  }, 60000);
}
