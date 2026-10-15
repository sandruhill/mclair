<?php
/**
 * HTML da seção "Plano de 12 meses". Só é usado incluído por planoSecao()
 * (plano.php), que define $json e $versao. As tarefas são desenhadas pelo
 * plano.js a partir de WEEKS.
 */
if (!defined('DOSSIE_PLANO') || !isset($json, $versao)) {
    http_response_code(404);
    exit;
}
?>
  <section class="plano" id="plano" data-aba="plano" aria-labelledby="plano-titulo">
    <h1 id="plano-titulo">Plano de 12 meses</h1>
    <p class="sub">Doze meses para a <b>Mclair</b> virar referência em assessoria de imprensa no Google, no Bing e nas respostas das IAs, e ficar à frente em São Paulo, Rio de Janeiro, Belo Horizonte, Porto Alegre, Curitiba, Florianópolis, Joinville, Brasília, Goiânia e Cuiabá. De outubro de 2026 a setembro de 2027. Cada tarefa mostra o estado em que está, e em cada uma dá para responder e mandar arquivos.</p>
    <p class="sub">De onde vem o plano: o <a href="#dossie"><b>dossiê de concorrência</b></a> de 23 de setembro, nesta mesma página, e a pesquisa de busca comercial em dez cidades feita em 15 de outubro.</p>

    <section class="evo" aria-label="Ponto de partida">
      <h2>De onde a Mclair parte</h2>
      <p class="resumen"><b>O site é saudável, mas não aparece para o que a Mclair vende.</b> Em 23 de setembro o Google conhecia 351 páginas e nenhuma das 45 buscas que trouxeram o site tinha "assessoria de imprensa". Perguntadas sobre as melhores agências de São Paulo, três IAs não citaram a Mclair. No nicho de leilões é o contrário: ChatGPT e Perplexity colocam a Mclair em primeiro lugar, citando o próprio site. O plano repete em assessoria de imprensa o que já deu certo em leilões. A primeira medição mensal é em 15 de novembro.</p>
      <div class="scroll"><table>
        <tr><th>O que medimos</th><th>Partida<span class="nota">23/09</span></th><th>Fonte</th></tr>
        <tr><td>Páginas no Google</td><td class="num">351</td><td>Search Console</td></tr>
        <tr><td>Cliques e impressões no Google<span class="nota">3 meses</span></td><td class="num">11 / 465</td><td>Search Console</td></tr>
        <tr><td>Buscas com "assessoria de imprensa" que trouxeram o site</td><td class="num">0 de 45</td><td>Search Console</td></tr>
        <tr><td>Páginas no Bing</td><td class="num">50</td><td>Bing Webmaster</td></tr>
        <tr><td>Citações no Copilot</td><td class="num">0</td><td>relatório de IA do Bing, 3 meses</td></tr>
        <tr><td>Palavras-chave no top 100</td><td class="num">17</td><td>Semrush, base Brasil</td></tr>
        <tr><td>Visitas orgânicas por mês</td><td class="num">cerca de 10</td><td>Semrush</td></tr>
        <tr><td>IAs que citam a Mclair entre as melhores de SP</td><td class="num">0 de 3</td><td>Visão Geral do Google, Perplexity, ChatGPT</td></tr>
        <tr><td>IAs que citam a Mclair em 1º para leiloeiros</td><td class="num">2 de 2</td><td>ChatGPT, Perplexity</td></tr>
        <tr><td>Avaliações no Perfil do Google</td><td class="num">0</td><td>Perfil da Empresa, 285 visualizações</td></tr>
      </table></div>
      <h3 class="evo-sub">As cidades do plano</h3>
      <p class="evo-txt">Buscas por mês somando as variações de cada cidade (Semrush, base br, 15/10). São poucas, mas com intenção de contratar: no topo de quase todas estão diretórios, órgãos públicos e Instagram, e a autoridade do domínio da Mclair é igual à das agências locais que lideram. Cada cidade ganha página com texto próprio.</p>
      <div class="scroll"><table>
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
      <p class="meta-plan"><b>A lição das concorrentes:</b> a F&amp;B publicou 535 páginas de cidade com o mesmo texto e está na posição 94. A MGA Press reescreveu 26 cidades com setores e imprensa locais e aparece entre os primeiros. Aqui vale o segundo modelo, com o endereço real da Mclair em São Paulo e nenhum endereço inventado nas outras cidades.</p>
    </section>

    <section class="falta" aria-labelledby="falta-h">
      <h2 id="falta-h">O que falta da Mclair: <span id="falta-n">0</span> tarefas</h2>
      <ol>
        <li><a href="#t-m1w">Perfil do Google e Facebook</a>: convite de administrador e o nome "Mclair Comunicação" no Facebook.</li>
        <li><a href="#t-m1x">Endereço</a>: se o bairro pode aparecer ou se fica só a área de atendimento.</li>
        <li><a href="#t-m1y">E-mail de cadastros</a>: por exemplo cadastros@mclair.com.br.</li>
        <li><a href="#t-m1z">LinkedIn da Kelly</a>: o endereço certo do perfil pessoal.</li>
        <li><a href="#t-m1ma">Avaliações</a>: clientes que topam avaliar no Google.</li>
        <li><a href="#t-m1mb">Matérias conquistadas</a>: veículo, cliente, data e link.</li>
        <li><a href="#t-m2k">Faixa de preço</a>: aprovar ou recusar por escrito.</li>
        <li><a href="#t-m2l">Cases por cidade e segmento</a>: quais são da Mclair e podem ser citados.</li>
      </ol>
      <p class="falta-dica">Para responder, abra a tarefa e use o botão Responder: dá para escrever e anexar até 3 arquivos.</p>
      <button type="button" class="falta-ver" id="falta-ver">Ver só o que falta da Mclair</button>
    </section>

    <section class="resumo" aria-label="Avanço geral">
      <div class="bigrow">
        <span class="pct" id="pct">0%</span>
        <span class="pctlabel" id="pctlabel">do plano concluído</span>
      </div>
      <div class="track" id="track" role="img" aria-label="Avanço do plano"></div>
      <div class="leyenda-color" aria-label="Cores das tarefas">
        <span><i class="cor-feito"></i> Feito</span>
        <span><i class="cor-mclair"></i> Pendente da Mclair</span>
        <span><i class="cor-ikoeh"></i> Pendente da IKOEH</span>
      </div>
      <div class="legend">
        <span><i class="dot dot-done"></i> Feito <b id="c-done">0</b></span>
        <span><i class="dot dot-doing"></i> Em andamento <b id="c-doing">0</b></span>
        <span><i class="dot dot-blocked"></i> Bloqueado <b id="c-blocked">0</b></span>
        <span><i class="dot dot-todo"></i> Pendente <b id="c-todo">0</b></span>
      </div>
    </section>

    <p class="aviso" id="aviso"></p>

    <p class="regla"><b>Como pedimos as coisas.</b> Tudo que depende da Mclair é pedido em bloco numa sexta-feira, nunca no meio da semana e nunca solto, para dar tempo de juntar o que falta sem pressa. Se algo não puder esperar a sexta, sai numa segunda. As tarefas com prazo trazem a data ao lado. A medição de cada mês é feita no dia 15 e acrescenta uma coluna aos dois painéis do detalhe.</p>

    <details class="detalle">
      <summary>Ver todos os números e o detalhe técnico</summary>
      <div class="baseline">
        <div><div class="v">351 / 50</div><div class="k">páginas no Google e no Bing em 23/09; o sitemap tem 333 endereços</div></div>
        <div><div class="v">7</div><div class="k">Authority Score no Semrush em 23/09, com 487 links de 193 domínios</div></div>
        <div><div class="v">0</div><div class="k">posições da Mclair em buscas com "imprensa" no Semrush (15/10)</div></div>
        <div><div class="v">48</div><div class="k">consultas vigiadas no painel de palavras abaixo</div></div>
      </div>

      <div class="capas">
        <h3>As camadas do trabalho, e em qual estamos</h3>
        <div class="scroll"><table>
          <tr><td class="n">SEO técnico</td><td class="e curso"><i></i>Em andamento</td><td class="det">Saudável no Google: canonical certo, 301 das 69 URLs antigas em PHP e robots correto. Faltam o 301 do <code>www</code>, o IndexNow e os <code>h1</code> com palavras coladas. O sitemap estava com erro no Bing e foi reenviado em 23/09.</td></tr>
          <tr><td class="n">Página de serviço</td><td class="e nada"><i></i>A fazer</td><td class="det">Cerca de 181 palavras de conteúdo, sem FAQ, sem cases ligados no texto e sem nenhum veículo citado pelo nome. O schema já é o mais completo do mercado analisado.</td></tr>
          <tr><td class="n">Silo de cidades</td><td class="e nada"><i></i>A fazer</td><td class="det">Nenhuma página de cidade ou de segmento além de <code>/marketing-para-leiloeiros/</code>. Plano: dez cidades em <code>/servicos/assessoria-de-imprensa/{cidade}/</code>, ligadas pelo hub e pelo rodapé, fora do menu.</td></tr>
          <tr><td class="n">Entidade</td><td class="e curso"><i></i>Em andamento</td><td class="det"><code>AdvertisingAgency</code> com a fundadora e <code>knowsAbout</code>; <b>Person canônico da Kelly no ar desde 15/10</b>. Falta o LinkedIn pessoal no <code>sameAs</code> e um nome só, "Mclair Comunicação", no Facebook.</td></tr>
          <tr><td class="n">Fichas externas</td><td class="e nada"><i></i>A fazer</td><td class="det">Perfil do Google sem área, na categoria errada e sem avaliação; Bing Places inexistente; ausente do oHub. São as fontes que as IAs leram em 23/09.</td></tr>
          <tr><td class="n">AEO e GEO</td><td class="e curso"><i></i>Em andamento</td><td class="det">Em leilões a Mclair já é a resposta. Em assessoria de imprensa em geral, nenhuma IA a cita. Falta FAQPage na página de serviço e presença nas fontes que as IAs consultam.</td></tr>
          <tr><td class="n">Conversão</td><td class="e listo"><i></i>Bom</td><td class="det">Sete links de WhatsApp, o primeiro logo abaixo do H1. Falta a mensagem dizer de qual página o contato veio.</td></tr>
        </table></div>
      </div>

      <div class="kw" id="perf"></div>
      <div class="kw" id="kw"></div>
    </details>

    <nav class="rail" id="rail" aria-label="Meses"></nav>

    <div class="controls">
      <span class="lbl">Ver:</span>
      <button class="filter" type="button" data-f="all" aria-pressed="true">Tudo</button>
      <button class="filter" type="button" data-f="IKOEH" aria-pressed="false">IKOEH</button>
      <button class="filter" type="button" data-f="Mclair" aria-pressed="false">Falta da Mclair (<span id="f-ag-n">0</span>)</button>
      <button class="filter" type="button" data-f="open" aria-pressed="false">Sem terminar</button>
    </div>
    <p class="filtro-aviso" id="filtro-aviso" role="status"></p>

    <div id="weeks"><noscript><p class="regla">A lista de tarefas precisa de JavaScript ligado no navegador.</p></noscript></div>

    <p class="note"><b>Como se usa.</b> O quadradinho de cada tarefa passa por quatro estados: pendente, em andamento, feito e bloqueado. Quem marca é a equipe da IKOEH, e o que for marcado aparece para todos. A meta no pé de cada mês não é opinião: se confere com um comando ou com um número do Search Console, e se não passar, o que vem depois é adiado.</p>
    <p class="offline" id="offline" hidden>Sem conexão com o armazenamento compartilhado. Dá para ler o plano, mas as mudanças de estado não são salvas nesta tela.</p>
  </section>
  <script type="application/json" id="plano-dados"><?= $json ?></script>
  <script src="<?= PLANO_BASE ?>plano.js?v=<?= planoH($versao) ?>" defer></script>
