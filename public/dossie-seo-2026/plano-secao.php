<?php
/**
 * HTML do plano: do título até os painéis. Só é usado incluído por
 * planoSecao() (plano.php), que define $json, $versao e $atualizado.
 * O anexo com o dossiê de setembro vem depois, desenhado pelo index.php.
 *
 * "Onde estamos", o cronograma, "O que depende da Mclair" e as tarefas são
 * montados pelo plano.js a partir de WEEKS e do estado gravado no banco,
 * para os números nunca ficarem velhos no texto.
 */
if (!defined('DOSSIE_PLANO') || !isset($json, $versao, $atualizado)) {
    http_response_code(404);
    exit;
}
?>
  <div class="plano">

    <section class="abre" aria-labelledby="titulo">
      <h1 id="titulo">SEO da Mclair: plano e acompanhamento</h1>
      <p class="quem">Plano e execução da IKOEH. Atualizado em <span id="atualizado"><?= planoH($atualizado) ?></span>.</p>
      <p class="sub">Doze meses, de outubro de 2026 a setembro de 2027, para a <b>Mclair</b> virar referência em assessoria de imprensa no Google, no Bing e nas respostas das IAs, em São Paulo e em mais nove capitais.</p>
      <p class="aviso" id="aviso"></p>
      <nav class="indice" aria-label="Partes desta página">
        <a href="#onde">Onde estamos</a>
        <a href="#cronograma">Cronograma</a>
        <a href="#depende">O que depende da Mclair</a>
        <a href="#estrategia">Estratégia</a>
        <a href="#plano">Plano de 12 meses</a>
        <a href="#paineis">Painéis</a>
        <a href="#dossie">Diagnóstico de setembro</a>
      </nav>
    </section>

    <section class="sec" id="onde" aria-labelledby="onde-h">
      <h2 id="onde-h">Onde estamos</h2>
      <p class="onde-conta" id="onde-conta"></p>
      <div class="track" id="track" role="img" aria-label="Avanço do plano"></div>
      <p class="onde-legenda">
        <span><i class="dot dot-done"></i> Feito</span>
        <span><i class="dot dot-doing"></i> Em andamento</span>
        <span><i class="dot dot-blocked"></i> Bloqueado</span>
        <span><i class="dot dot-todo"></i> Pendente</span>
      </p>
      <h3>Entregas mais recentes</h3>
      <ol class="entregas" id="entregas"></ol>
      <p class="onde-parado" id="onde-parado" hidden></p>
      <h3>Próximo marco</h3>
      <p class="marco" id="marco"></p>
      <noscript><p class="regla">O resumo, o cronograma e a lista de tarefas precisam de JavaScript ligado no navegador.</p></noscript>
    </section>

    <section class="sec" id="cronograma" aria-labelledby="crono-h">
      <h2 id="crono-h">Cronograma</h2>
      <p class="sec-intro">Um tema por mês e uma meta que se confere no dia 15 do mês seguinte. Clique num mês para ver as tarefas dele.</p>
      <ol class="crono" id="crono"></ol>
    </section>

    <section class="sec" id="depende" aria-labelledby="depende-h">
      <h2 id="depende-h">O que depende da Mclair</h2>
      <p class="sec-intro" id="depende-conta"></p>
      <ol class="dep" id="dep-agora"></ol>
      <details class="dep-depois" id="dep-depois-box">
        <summary id="dep-depois-tit">Mais adiante</summary>
        <ol class="dep" id="dep-depois"></ol>
      </details>
      <p class="regla"><b>Como pedimos as coisas.</b> Tudo que depende da Mclair é pedido em bloco numa sexta-feira, nunca no meio da semana e nunca solto, para dar tempo de juntar o que falta sem pressa. Se algo não puder esperar a sexta, sai numa segunda. Para responder, use o botão Responder de cada pedido: dá para escrever e anexar até 3 arquivos.</p>
      <button type="button" class="btn-linha" id="falta-ver">Ver esses pedidos dentro do plano</button>
    </section>

    <section class="sec ilustrado" id="estrategia" aria-labelledby="estrategia-h">
      <h2 id="estrategia-h">Estratégia</h2>
      <p class="resumen"><b>A Mclair ainda não aparece onde as pessoas procuram uma agência</b>, apesar de mais de 11.600 inserções na mídia para clientes e nove anos de mercado. Quando a pergunta é sobre assessoria para leiloeiros, ChatGPT e Perplexity já colocam a Mclair em primeiro lugar. O objetivo é repetir isso em assessoria de imprensa, de forma ampla.</p>
      <p>O plano saiu do <a href="#dossie">diagnóstico de concorrência</a> de 23 de setembro, anexado no fim desta página, e da pesquisa de busca comercial em dez cidades feita em 15 de outubro. Ele trabalha em três frentes: estar nas fontes que as IAs consultam, ter no site páginas que confirmem o que essas fontes dizem e mostrar a prova que só uma assessoria tem.</p>

      <h3>Como uma IA decide quem citar</h3>
      <p>As IAs não leem o site de cada agência para montar a resposta. Elas consultam fontes que já organizam o mercado: diretórios de agências, o Perfil da Empresa no Google com as notas de avaliação, rankings do setor e matérias de imprensa. O site entra como confirmação.</p>

<figure class="diagram" aria-label="Fluxo das fontes que as IAs consultam até a resposta">
  <svg viewBox="0 0 1000 430" role="img">
    <title>As fontes que alimentam a resposta de uma IA</title>
    <g font-size="15" fill="var(--fg)">
      <text x="20" y="34" font-weight="700" fill="var(--fg-2)" font-size="15">O que a IA consulta</text>
      <g>
        <rect x="20" y="56" width="300" height="60" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="40" y="82" font-weight="700">Diretórios de agências</text>
        <text x="40" y="104" fill="var(--fg-2)" font-size="13.5">oHub, Clutch, Econodata, GetNinjas</text>
      </g>
      <g>
        <rect x="20" y="132" width="300" height="60" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="40" y="158" font-weight="700">Perfil da Empresa no Google</text>
        <text x="40" y="180" fill="var(--fg-2)" font-size="13.5">categoria, endereço, notas</text>
      </g>
      <g>
        <rect x="20" y="208" width="300" height="60" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="40" y="234" font-weight="700">Imprensa e rankings do setor</text>
        <text x="40" y="256" fill="var(--fg-2)" font-size="13.5">matérias, colunas, anuários, prêmios</text>
      </g>
      <g>
        <rect x="20" y="284" width="300" height="60" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="40" y="310" font-weight="700">Pessoas conhecidas por trás da marca</text>
        <text x="40" y="332" fill="var(--fg-2)" font-size="13.5">LinkedIn, palestras, autoria, perfil</text>
      </g>
      <g>
        <rect x="20" y="360" width="300" height="60" rx="6" fill="none" stroke="var(--line)" stroke-dasharray="4 4"/>
        <text x="40" y="386" font-weight="700">O site da agência</text>
        <text x="40" y="408" fill="var(--fg-2)" font-size="13.5">confirma o que as fontes disseram</text>
      </g>
      <g stroke="var(--fg-3)" stroke-width="1.5" fill="none">
        <path d="M320 86 C460 86 480 210 600 214"/>
        <path d="M320 162 C450 162 480 214 600 214"/>
        <path d="M320 238 C450 238 480 214 600 214"/>
        <path d="M320 314 C450 314 480 214 600 214"/>
        <path d="M320 390 C460 390 480 214 600 214" stroke-dasharray="4 4"/>
      </g>
      <rect x="600" y="160" width="380" height="108" rx="8" fill="var(--red)"/>
      <text x="624" y="196" fill="var(--ink-on-red)" font-weight="700" font-size="17">"Quais as melhores agências de</text>
      <text x="624" y="220" fill="var(--ink-on-red)" font-weight="700" font-size="17">assessoria de imprensa em SP?"</text>
      <text x="624" y="248" fill="var(--ink-on-red)" font-size="14">A resposta é montada com o que as fontes dizem</text>
    </g>
  </svg>
  <figcaption>Medido em 23 de setembro de 2026: a Visão Geral do Google respondeu lendo só o oHub; a Perplexity usou oHub, Clutch, GetNinjas e Econodata; o ChatGPT usou o mapa do Google com as notas e o Anuário da Comunicação Corporativa.</figcaption>
</figure>

      <h3>O mercado: estar onde as IAs procuram</h3>
      <p>É a frente com maior retorno por hora de trabalho, e quase toda ela acontece fora do site.</p>
      <ul>
        <li><strong>Perfil da Empresa no Google.</strong> Ele já existe e teve 285 visualizações, mas estava sem área de atendimento, com o marcador caindo no centro do Brasil, e na categoria "Agência de Marketing". Passa para assessoria de imprensa, com área nas cidades do plano, fotos da equipe e um pedido de avaliação aos clientes atuais.</li>
        <li><strong>oHub.</strong> É a única fonte que a Visão Geral do Google usou para responder. Cadastro completo com bairro, ano de fundação, serviços e cases.</li>
        <li><strong>Bing Places e Clutch.</strong> Fichas gratuitas que alimentam o Copilot e a Perplexity. GetNinjas e Econodata ficam de fora: atraem mais fornecedor e pedido barato do que cliente, e pesam pouco nas respostas.</li>
        <li><strong>Sem virar ímã de fornecedor.</strong> Os cadastros usam o e-mail da Kelly e o WhatsApp comercial, com os recursos de "receber orçamentos" desligados. A ficha serve de referência para as IAs, não de canal de venda.</li>
        <li><strong>Um nome só.</strong> O Facebook está como "M. Clair Comunicação". Para quem cruza cadastros, isso é outra empresa. Tudo passa a ser "Mclair Comunicação".</li>
      </ul>

      <h3>O conteúdo: o site dizendo com clareza o que a Mclair vende</h3>
      <p>A página de assessoria de imprensa passa a ser a página da agência de assessoria de imprensa em São Paulo, e ganha à sua volta um conjunto de páginas que respondem as perguntas de quem está decidindo contratar.</p>

<figure class="diagram" aria-label="Arquitetura de páginas de assessoria de imprensa">
  <svg viewBox="0 0 1000 340" role="img">
    <title>O conjunto de páginas em torno de assessoria de imprensa</title>
    <g font-size="14" fill="var(--fg)">
      <rect x="330" y="20" width="340" height="64" rx="8" fill="var(--red)"/>
      <text x="500" y="47" text-anchor="middle" fill="var(--ink-on-red)" font-weight="900" font-size="16">Agência de assessoria de imprensa</text>
      <text x="500" y="69" text-anchor="middle" fill="var(--ink-on-red)" font-size="13">em São Paulo, para todo o Brasil</text>
      <g stroke="var(--fg-3)" stroke-width="1.5" fill="none">
        <path d="M500 84 V120 H140 V150"/><path d="M500 120 H380 V150"/><path d="M500 120 H620 V150"/><path d="M500 120 H860 V150"/>
      </g>
      <g>
        <rect x="40" y="150" width="200" height="170" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="58" y="178" font-weight="700">Por segmento</text>
        <text x="58" y="206" fill="var(--fg-2)">Leilões (já citada)</text>
        <text x="58" y="230" fill="var(--fg-2)">Tecnologia e software</text>
        <text x="58" y="254" fill="var(--fg-2)">Indústria e associações</text>
        <text x="58" y="278" fill="var(--fg-2)">Saúde e profissionais</text>
        <text x="58" y="302" fill="var(--fg-2)">Executivos e marca pessoal</text>
      </g>
      <g>
        <rect x="280" y="150" width="200" height="170" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="298" y="178" font-weight="700">Decisão de compra</text>
        <text x="298" y="206" fill="var(--fg-2)">Quanto custa</text>
        <text x="298" y="230" fill="var(--fg-2)">Como escolher a agência</text>
        <text x="298" y="254" fill="var(--fg-2)">O que esperar no 1º mês</text>
        <text x="298" y="278" fill="var(--fg-2)">Assessoria x marketing</text>
      </g>
      <g>
        <rect x="520" y="150" width="200" height="170" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="538" y="178" font-weight="700">Guias permanentes</text>
        <text x="538" y="206" fill="var(--fg-2)">O que é release</text>
        <text x="538" y="230" fill="var(--fg-2)">Media training</text>
        <text x="538" y="254" fill="var(--fg-2)">Gestão de crise</text>
        <text x="538" y="278" fill="var(--fg-2)">Como virar fonte</text>
        <text x="538" y="302" fill="var(--fg-2)">de jornalistas</text>
      </g>
      <g>
        <rect x="760" y="150" width="200" height="170" rx="6" fill="var(--tint)" stroke="var(--line)"/>
        <text x="778" y="178" font-weight="700">Prova</text>
        <text x="778" y="206" fill="var(--fg-2)">Mclair na mídia:</text>
        <text x="778" y="230" fill="var(--fg-2)">matérias conquistadas</text>
        <text x="778" y="254" fill="var(--fg-2)">Cases com clipping</text>
        <text x="778" y="278" fill="var(--fg-2)">Avaliações no Google</text>
      </g>
    </g>
  </svg>
  <figcaption>Os termos com intenção de contratação estão praticamente livres: "agência de assessoria de imprensa", "empresa de assessoria de imprensa", "assessoria de imprensa São Paulo" e "quanto custa assessoria de imprensa" têm dificuldade zero no Semrush. A página de leilões mostra que uma página de nicho bem feita vira resposta de IA mesmo sem volume de busca.</figcaption>
</figure>

      <h3>A prova que só uma assessoria pode ter</h3>
      <p>Uma agência de assessoria de imprensa tem um ativo que nenhuma agência de marketing tem: matérias publicadas. Uma página "Mclair na mídia", organizada por veículo e por cliente, com link para cada matéria conquistada, é a prova mais direta do serviço e o tipo de página que jornalistas e IAs reconhecem como evidência. A Mclair também pratica com ela mesma o que entrega aos clientes: pautas próprias, artigos assinados, entrevistas e inscrição em prêmios e anuários do setor. Cada matéria sobre a Mclair vira uma fonte a mais para o Google e as IAs.</p>

      <h3>As cidades do plano</h3>
      <p>Buscas por mês somando as variações de cada cidade (Semrush, base br, 15/10). São poucas, mas com intenção de contratar: no topo de quase todas estão diretórios, órgãos públicos e Instagram, e a autoridade do domínio da Mclair é igual à das agências locais que lideram. Cada cidade ganha página com texto próprio.</p>
      <div class="scroll"><table class="tabela">
        <tr><th>Cidade</th><th>Buscas/mês</th><th>Disputa hoje</th><th>Mês</th></tr>
        <tr><td>São Paulo</td><td class="num">cerca de 300</td><td>primeira agência real só na 8ª posição</td><td>novembro</td></tr>
        <tr><td>Brasília e DF</td><td class="num">100</td><td>fraca no on-page</td><td>novembro</td></tr>
        <tr><td>Porto Alegre e RS</td><td class="num">120</td><td>locais sem H1 nem FAQ</td><td>novembro</td></tr>
        <tr><td>Curitiba</td><td class="num">90</td><td>a mais disputada do Sul</td><td>dezembro</td></tr>
        <tr><td>Rio de Janeiro</td><td class="num">cerca de 100</td><td>sem medição do Google</td><td>dezembro</td></tr>
        <tr><td>Belo Horizonte</td><td class="num">cerca de 60</td><td>sem medição do Google</td><td>dezembro</td></tr>
        <tr><td>Florianópolis e SC</td><td class="num">60</td><td>média</td><td>janeiro</td></tr>
        <tr><td>Joinville</td><td class="num">40</td><td>nenhuma agência no top 12</td><td>janeiro</td></tr>
        <tr><td>Goiânia</td><td class="num">40</td><td>muito fraca</td><td>janeiro</td></tr>
        <tr><td>Cuiabá</td><td class="num">10</td><td>muito fraca</td><td>março, só com case em MT</td></tr>
      </table></div>
      <p><b>A lição das concorrentes:</b> a F&amp;B publicou 535 páginas de cidade com o mesmo texto e está na posição 94. A MGA Press reescreveu 26 cidades com setores e imprensa locais e aparece entre os primeiros. Aqui vale o segundo modelo, com o endereço real da Mclair em São Paulo e nenhum endereço inventado nas outras cidades.</p>
    </section>

    <section class="sec" id="plano" aria-labelledby="plano-h">
      <h2 id="plano-h">Plano de 12 meses</h2>
      <p class="sec-intro">Todas as tarefas, mês a mês, com o estado de cada uma. Em cada tarefa dá para responder e mandar arquivos.</p>

      <div class="controls">
        <span class="lbl">Ver:</span>
        <button class="filter" type="button" data-f="all" aria-pressed="true">Tudo</button>
        <button class="filter" type="button" data-f="IKOEH" aria-pressed="false">IKOEH</button>
        <button class="filter" type="button" data-f="Mclair" aria-pressed="false">Falta da Mclair (<span id="f-ag-n">0</span>)</button>
        <button class="filter" type="button" data-f="open" aria-pressed="false">Sem terminar</button>
      </div>
      <p class="filtro-aviso" id="filtro-aviso" role="status"></p>

      <div id="weeks"><noscript><p class="regla">A lista de tarefas precisa de JavaScript ligado no navegador.</p></noscript></div>

      <p class="note"><b>Como se usa.</b> O quadradinho de cada tarefa passa por quatro estados: pendente, em andamento, feito e bloqueado. Quem marca é a equipe da IKOEH, e o que for marcado aparece para todos. A meta no pé de cada mês não é opinião: se confere com um comando ou com um número do Search Console, e se não passar, o que vem depois é adiado. As tarefas com prazo trazem a data ao lado.</p>
      <p class="offline" id="offline" hidden>Sem conexão com o armazenamento compartilhado. Dá para ler o plano, mas as mudanças de estado não são salvas nesta tela.</p>
    </section>

    <section class="sec" id="paineis" aria-labelledby="paineis-h">
      <h2 id="paineis-h">Painéis</h2>
      <p class="sec-intro">A medição é feita no dia 15 de cada mês e acrescenta uma coluna aos dois primeiros painéis.</p>

      <div class="kw" id="perf"></div>
      <div class="kw" id="kw"></div>

      <div class="kw">
        <h3>Ponto de partida, em 23 de setembro</h3>
        <div class="intro">O site é saudável, mas não aparecia para o que a Mclair vende. Perguntadas sobre as melhores agências de São Paulo, três IAs não citaram a Mclair; no nicho de leilões é o contrário, e ChatGPT e Perplexity a colocam em primeiro lugar, citando o próprio site.</div>
        <div class="scroll"><table>
          <thead><tr><th>O que medimos</th><th class="num">Partida</th><th>Fonte</th></tr></thead>
          <tbody>
          <tr><td>Páginas no Google</td><td class="num">351</td><td>Search Console</td></tr>
          <tr><td>Endereços no sitemap</td><td class="num">333</td><td>sitemap do site</td></tr>
          <tr><td>Cliques e impressões no Google<span class="mnota">3 meses</span></td><td class="num">11 / 465</td><td>Search Console</td></tr>
          <tr><td>Buscas com "assessoria de imprensa" que trouxeram o site</td><td class="num">0 de 45</td><td>Search Console</td></tr>
          <tr><td>Páginas no Bing</td><td class="num">50</td><td>Bing Webmaster</td></tr>
          <tr><td>Citações no Copilot</td><td class="num">0</td><td>relatório de IA do Bing, 3 meses</td></tr>
          <tr><td>Palavras-chave no top 100</td><td class="num">17</td><td>Semrush, base Brasil</td></tr>
          <tr><td>Posições em buscas com "imprensa"<span class="mnota">medido em 15/10</span></td><td class="num">0</td><td>Semrush</td></tr>
          <tr><td>Authority Score<span class="mnota">487 links de 193 domínios</span></td><td class="num">7</td><td>Semrush</td></tr>
          <tr><td>Visitas orgânicas por mês</td><td class="num">cerca de 10</td><td>Semrush</td></tr>
          <tr><td>IAs que citam a Mclair entre as melhores de SP</td><td class="num">0 de 3</td><td>Visão Geral do Google, Perplexity, ChatGPT</td></tr>
          <tr><td>IAs que citam a Mclair em 1º para leiloeiros</td><td class="num">2 de 2</td><td>ChatGPT, Perplexity</td></tr>
          <tr><td>Avaliações no Perfil do Google</td><td class="num">0</td><td>Perfil da Empresa, 285 visualizações</td></tr>
          </tbody>
        </table></div>
      </div>

      <div class="kw capas">
        <h3>Diagnóstico por camada, em 15 de outubro</h3>
        <div class="intro">Retrato da véspera da primeira rodada de correções. O que mudou depois disso está nas tarefas do plano.</div>
        <div class="scroll"><table>
          <tr><td class="n">SEO técnico</td><td class="e curso"><i></i>Em andamento</td><td class="det">Saudável no Google: canonical certo, 301 das 69 URLs antigas em PHP e robots correto. Faltavam o 301 do <code>www</code>, o IndexNow e os <code>h1</code> com palavras coladas. O sitemap estava com erro no Bing e foi reenviado em 23/09.</td></tr>
          <tr><td class="n">Página de serviço</td><td class="e nada"><i></i>A fazer</td><td class="det">Cerca de 181 palavras de conteúdo, sem FAQ, sem cases ligados no texto e sem nenhum veículo citado pelo nome. O schema já era o mais completo do mercado analisado.</td></tr>
          <tr><td class="n">Silo de cidades</td><td class="e nada"><i></i>A fazer</td><td class="det">Nenhuma página de cidade ou de segmento além de <code>/marketing-para-leiloeiros/</code>. Plano: dez cidades em <code>/servicos/assessoria-de-imprensa/{cidade}/</code>, ligadas pelo hub e pelo rodapé, fora do menu.</td></tr>
          <tr><td class="n">Entidade</td><td class="e curso"><i></i>Em andamento</td><td class="det"><code>AdvertisingAgency</code> com a fundadora e <code>knowsAbout</code>; <b>Person canônico da Kelly no ar desde 15/10</b>. Faltavam o LinkedIn pessoal no <code>sameAs</code> e um nome só, "Mclair Comunicação", no Facebook.</td></tr>
          <tr><td class="n">Fichas externas</td><td class="e nada"><i></i>A fazer</td><td class="det">Perfil do Google sem área, na categoria errada e sem avaliação; Bing Places inexistente; ausente do oHub. São as fontes que as IAs leram em 23/09.</td></tr>
          <tr><td class="n">AEO e GEO</td><td class="e curso"><i></i>Em andamento</td><td class="det">Em leilões a Mclair já é a resposta. Em assessoria de imprensa em geral, nenhuma IA a cita. Faltavam FAQPage na página de serviço e presença nas fontes que as IAs consultam.</td></tr>
          <tr><td class="n">Conversão</td><td class="e listo"><i></i>Bom</td><td class="det">Sete links de WhatsApp, o primeiro logo abaixo do H1. Faltava a mensagem dizer de qual página o contato veio.</td></tr>
        </table></div>
      </div>
    </section>

  </div>
  <script type="application/json" id="plano-dados"><?= $json ?></script>
  <script src="<?= PLANO_BASE ?>plano.js?v=<?= planoH($versao) ?>" defer></script>
