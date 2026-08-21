# Auditoria Mclair, mclair.com.br

Data: 2026-08-20 (revisão)
Escopo: auditoria técnica solicitada pelo Sandru (segurança, SEO técnico/semântico, Entity SEO, SILO, AEO/AIO/GEO, E-E-A-T, performance, acessibilidade), com duas restrições explícitas do pedido: **não alterar a estrutura do site ainda** (SILO, split blog/newsroom, novas páginas ficam só documentados/planejados) e **não criar a página da Kelly Pinheiro** (aguarda aprovação dela).

**Nota sobre este documento:** já existia um `AUDITORIA-MCLAIR.md` no repositório, de uma sessão overnight anterior (commit `9355647`, 2026-08-20 02:31). Verifiquei os itens que ele reivindicava como corrigidos, a maioria segue realmente aplicada no código atual (allowlist do `fb-capi.php`, ordem do `<meta charset>` antes do GTM, sitemap apontando pro domínio certo, contadores com valor real no HTML). Esse documento **substitui** o anterior, incorporando o que ainda é válido e cobrindo tudo que mudou desde então (renomeação `/admin/` → `/acesso/`, CMS novo, menu dinâmico, etc.) mais a auditoria nova pedida agora.

**Como ler:** cada item diz se foi (a) corrigido e já está em produção, (b) auditado e está OK, ou (c) precisa de decisão/dado que só o Sandru/Kelly têm. Nada foi inventado, sem acesso a Search Console, Lighthouse real ou credenciais, itens que dependem disso estão na seção 22, não fingidos como concluídos.

---

## 1. Estado inicial

Stack: Astro v6 (`output: 'static'`), conteúdo dinâmico (posts, cases, serviços, páginas institucionais, menu) servido por um CMS PHP+MySQL próprio em `/acesso/`, consumido em build-time via uma API JSON própria (`/acesso/api-export.php`, autenticada por token). Deploy: push para o remote Hostinger dispara um hook que roda `npm run build` no próprio servidor e sincroniza `dist/` via rsync; um segundo caminho via cron (`queueRebuild()`) republica após qualquer edição no CMS. DNS 100% em Hostinger (confirmado). Um projeto Vercel antigo existia como mirror público desatualizado, já foi bloqueado com middleware 403 em sessão anterior hoje mesmo, fora do escopo desta auditoria.

O projeto chega a este ponto com uma base de SEO bem mais madura que o normal: schema.org rico e válido, sitemap automático, `llms.txt` com conteúdo real, headers de segurança fortes, 404 verdadeira. A auditoria de hoje partiu de um patamar bom, não de um zero.

## 2. Problemas encontrados

| # | Problema | Onde | Severidade | Status |
|---|---|---|---|---|
| 1 | URLs antigas da era PHP (`quem-somos.php`, `contato.php`, `servicos.php`, `blog.php`, `index.php`, `blog-detalhe.php?id=N`) retornavam 404 puro, sem redirect, qualquer backlink/bookmark antigo perdia o link equity | site inteiro | Alta | **Corrigido** |
| 2 | Sitemap listava a forma não-canônica de `/servicos`, `/cases` e páginas institucionais (sem barra final), enquanto a própria página se autodeclara canônica COM barra, o servidor até 301-redireciona a forma sem barra pra com barra | `astro.config.mjs` (`customPages`) | Média | **Corrigido** |
| 3 | (bug que eu mesmo introduzi ao corrigir #1) `RewriteRule` sem `QSD` estava anexando a query string antiga (`?id=155/...`) na URL de destino do redirect | `public/.htaccess` | Média | **Corrigido** no mesmo ciclo |
| 4 | `.htaccess` tinha um comentário desatualizado referenciando `/admin/` e "Keystatic CMS", sistemas que não existem mais (renomeados/substituídos em sessão anterior) | `public/.htaccess` | Baixa (só documentação) | **Corrigido** |
| 5 | `sharp` (processamento de imagem, só build-time) tem 2 CVEs altas conhecidas (libvips); fix requer bump major do Astro (7.2.4) | `package.json` | Média, risco real baixo (não roda em runtime exposto a visitantes) | **Documentado, não aplicado**, ver seção 19 |
| 6 | Lista `customPages` do sitemap é mantida manualmente (cases/serviços não são auto-descobertos), risco de ficar desatualizada quando um case/serviço novo for criado no CMS | `astro.config.mjs` | Baixa, risco futuro | **Documentado**, ver seção 23 |

## 3. Vulnerabilidades encontradas

- **Nenhum secret exposto.** `.env`, `.git/`, arquivos `.sql`/`.bak`/`.zip` retornam 403 (bloqueados via `.htaccess`); `package.json`, `vercel.json` retornam 404 (nem chegam a `dist/`). Segredos reais (`.secrets/`) confirmados fora do git (`git ls-files` não lista nenhum).
- **CORS**: único endpoint com header CORS é `public/api/fb-capi.php`, restrito a `https://mclair.com.br` (não `*`). OK.
- **XSS**: nenhuma atribuição `innerHTML =` encontrada em `src/`. Conteúdo do CMS passa por `htmlspecialchars()` no lado PHP e por interpolação segura do Astro no lado do site.
- **Formulário de contato**: não existe backend, o form roda 100% client-side, monta uma mensagem com `encodeURIComponent()` e abre um link `wa.me` via `window.open()`. Não há superfície de CSRF/injeção server-side porque não há servidor processando o envio. Validação client-side existe (nome/e-mail/mensagem obrigatórios, formato de e-mail), adequada para esse desenho, já que nada é persistido ou processado no backend.
- **`fb-capi.php`** (Facebook Conversions API): já tem allowlist de `event_name` (confirmado no código atual), não aceita eventos arbitrários.
- **Dependências**: `npm audit` (produção) → 3 vulnerabilidades (1 low `esbuild`, 2 high `sharp`/libvips). Ambas são dependências **de build**, não chegam ao HTML/JS servido ao visitante. Fix disponível só via upgrade major do Astro, não apliquei sem confirmação (ver seção 19).
- **`/acesso/`**: não toquei nada aqui por instrução explícita (outra sessão está mexendo em paralelo). Nada de novo auditado nessa área além do que já é sabido de sessões anteriores (auth por sessão PHP, tokens de export, etc.).

## 4. Correções de segurança realizadas

Nenhuma vulnerabilidade de segurança nova foi encontrada além do que já estava corrigido. O trabalho desta sessão nessa frente foi de **SEO técnico** (seção 2), não de segurança propriamente dita.

## 5. SEO técnico

- Canonical: presente e autorreferencial em todas as páginas verificadas (`/cases/`, homepage). Não encontrei duplicidade.
- Trailing slash: agora consistente entre sitemap e canonical (ver #2 acima).
- Robots.txt: correto, `Allow: /`, bloqueia só `/acesso/`, referencia os dois arquivos de sitemap, com comentários de auditoria anteriores documentando correções já feitas (domínio do sitemap, remoção de bloqueio indevido a Googlebot).
- Meta description e title: não fiz uma varredura página-a-página das 265+ URLs (fora do orçamento desta sessão), spot-check na home e em `/cases/` mostrou title/description específicos, não genéricos.
- Imagens: não rodei um crawler de `alt` em massa; grep rápido não indicou `alt="Image"` genérico no logo.

## 6. Redirects antigos

| URL antiga | URL nova | Status |
|---|---|---|
| `/quem-somos.php` | `/sobre/` | ✅ 301 (QSD) |
| `/contato.php` | `/contato/` | ✅ 301 (QSD) |
| `/servicos.php` | `/servicos/` | ✅ 301 (QSD) |
| `/blog.php` | `/blog/` | ✅ 301 (QSD) |
| `/index.php` | `/` | ✅ 301 (QSD) |
| `/blog-detalhe.php?id=12` | `/blog/em-tempos-de-pandemia-tecnologia-cria-oportunidades/` | ✅ 301 |
| `/blog-detalhe.php?id=13` | `/blog/o-lado-b-da-produtividade-em-trabalho-remoto/` | ✅ 301 |
| `/blog-detalhe.php?id=96` | `/blog/guiando-tem-20-vagas-abertas-100-remoto-1/` | ✅ 301 |
| `/blog-detalhe.php?id=99` | `/blog/o-que-a-vida-corporativa-tem-a-ver-com-um-jogo-de-squash/` | ✅ 301 |
| `/blog-detalhe.php?id=101` | `/blog/5-perguntas-cruciais-antes-de-formar-um-conselho-administrativo-ou-con/` | ✅ 301 |
| `/blog-detalhe.php?id=108` | `/blog/perceber-nuances-o-superpoder-que-todo-lider-precisa-ter/` | ✅ 301 |
| `/blog-detalhe.php?id=124` | `/blog/cotas-para-streaming-na-franca-realca-atraso-do-brasil-na-regulamentac/` | ✅ 301 |
| `/blog-detalhe.php?id=125` | `/blog/projeto-ze-conecta-amplia-inclusao-digital-a-alunos-em-situacao-vulner/` | ✅ 301 |
| `/blog-detalhe.php?id=126` | `/blog/3-setores-beneficiados-pelo-audiovisual-em-2021/` | ✅ 301 |
| `/blog-detalhe.php?id=127` | `/blog/guiando-abre-novas-vagas-para-ti-e-outras-areas/` | ✅ 301 |
| `/blog-detalhe.php?id=128` | `/blog/comunicacao-nao-tem-receita-infalivel/` | ✅ 301 |
| `/blog-detalhe.php?id=129` | `/blog/entre-perfis-e-curtidas-por-que-alguem-seria-fiel-a-sua-marca/` | ✅ 301 |
| `/blog-detalhe.php?id=131` | `/blog/o-growth-e-eu-acelerando/` | ✅ 301 |
| `/blog-detalhe.php?id=145` | `/blog/valuation-quanto-vale-um-canal-no-youtube/` | ✅ 301 |
| `/blog-detalhe.php?id=150` | `/blog/a-quem-interessa-a-desigualdade-social/` | ✅ 301 |
| `/blog-detalhe.php?id=155` | `/blog/dona-coruja-e-sua-marca-propria/` | ✅ 301 |
| `/blog-detalhe.php?id=169` | `/blog/a-maternidade-e-os-desafios-para-a-carreira-da-mulher/` | ✅ 301 |
| `/blog-detalhe.php?id=171` | `/blog/caixa-realiza-leilao-de-imoveis-online-no-centro-oeste-com-descontos-d/` | ✅ 301 |
| `/blog-detalhe.php?id=172` | `/blog/o-que-mudou-na-busca-pelo-lider-de-rh/` | ✅ 301 |
| `/blog-detalhe.php?id=173` | `/blog/lambda3-abre-vagas-para-times-de-tecnologia/` | ✅ 301 |
| `/blog-detalhe.php?id=174` | `/blog/como-a-robotizacao-deve-ganhar-protagonismo-para-os-processos-empresar/` | ✅ 301 |
| `/blog-detalhe.php?id=247` | `/blog/como-evitar-o-transtorno-com-roubo-de-smartphones-e-dados/` | ✅ 301 |
| `/blog-detalhe.php?id=261` | `/blog/nao-caia-na-armadilha-da-massificacao-da-inteligencia-artificial/` | ✅ 301 |
| `/blog-detalhe.php?id=262` | `/blog/ted-lasso-por-que-a-serie-e-um-case-de-branded-content/` | ✅ 301 |
| `/blog-detalhe.php?id=278` | `/blog/racismo-algoritmico-especialistas-apontam-caminhos-para-um-futuro-incl/` | ✅ 301 |
| `/blog-detalhe.php?id=279` | `/blog/5-filmes-e-series-para-inspirar-os-amantes-da-comunicacao/` | ✅ 301 |
| `/blog-detalhe.php?id=280` | `/blog/artista-catarinense-vence-a-3a-edicao-da-galeria-consigaz/` | ✅ 301 |
| `/blog-detalhe.php?id=281` | `/blog/referencia-mundial-em-tecnologia-para-o-campo-brasil-apresenta-maquina/` | ✅ 301 |
| `/blog-detalhe.php?id=282` | `/blog/diego-nogare-deixa-o-itau-apos-consolidar-inovacao-em-machine-learning/` | ✅ 301 |
| `/blog-detalhe.php?id=283` | `/blog/de-imoveis-a-veiculos-o-leilao-da-justica-do-trabalho-de-sp-oferece-va/` | ✅ 301 |
| `/blog-detalhe.php?id=284` | `/blog/esg-e-felicidade-corporativa-a-nova-fronteira-da-gestao/` | ✅ 301 |
| `/blog-detalhe.php?id=285` | `/blog/leilao-do-bradesco-traz-52-imoveis-com-precos-a-partir-de-r-40-mil/` | ✅ 301 |
| `/blog-detalhe.php?id=287` | `/blog/do-glp-ao-coracao-dos-corinthianos-como-o-patrocinio-do-appgas-ao-tima/` | ✅ 301 |
| `/blog-detalhe.php?id=295` | `/blog/justica-federal-leilao-com-descontos-de-ate-50-oferece-imoveis-e-veicu/` | ✅ 301 |
| `/blog-detalhe.php?id=` (qualquer outro não listado acima) | `/blog/` | ✅ 301 (fallback, não pra home) |

**Como foi construído:** os 34 ids confirmados vieram do Wayback Machine (CDX API), o banco de dados atual não preserva os ids numéricos antigos, então não dava pra montar o mapa só com o banco. Cruzei os títulos das páginas arquivadas com os títulos atuais (fuzzy match) e só usei o resultado quando bateu com confiança. Ids sem correspondência confirmada caem no índice do blog, não na home, evita redirect genérico e indiscriminado, mas também evita adivinhar.

**`/servicos.php?tipo=X`**: não criei redirects individuais por categoria porque as categorias antigas (Brand Intelligence, Cultura Organizacional, Engajamento com Stakeholders...) não correspondem aos 6 serviços atuais, mapear errado seria pior que não mapear. `/servicos.php` (sem `tipo`) redireciona pro índice atual de serviços.

## 7. Canonicals

Auditados via spot-check (home, `/cases/`): canonical absoluto, autorreferencial, com barra final consistente. Nenhuma duplicidade HTTP/HTTPS ou www/não-www encontrada, DNS e o site inteiro operam só em `https://mclair.com.br` (sem `www`).

## 8. Sitemap

`https://mclair.com.br/sitemap-index.xml` → `sitemap-0.xml`, gerado por `@astrojs/sitemap`. Conteúdo: páginas de rota real (auto-descobertas, incluindo blog paginado) + `customPages` hardcoded pra páginas de serviço/case/institucionais (que não são auto-descobertas). Corrigido: barra final ausente nas `customPages` (seção 2/6). Cross-check contra o banco confirma que a lista está completa hoje (13 cases, 6 serviços), mas é mantida manualmente, ver seção 23.

## 9. Robots

`https://mclair.com.br/robots.txt`, já correto antes desta sessão (`Allow: /`, `Disallow: /acesso/`, referencia os dois arquivos de sitemap). Não bloqueia CSS/JS/imagens. Comentários no próprio arquivo documentam correções de sessões anteriores.

## 10. Schema

JSON-LD na home: 3 blocos, todos parseiam como JSON válido (testado programaticamente, não só visualmente). Tipos presentes: `AdvertisingAgency`/`ProfessionalService` (Organization, com `@id` estável `#organization`), `WebSite` (com `SearchAction`), `WebPage`. Campos incluem `ContactPoint`, `PostalAddress`, `GeoCoordinates`, `OfferCatalog`/`Offer`/`Service`, `SpeakableSpecification`. Não fiz varredura de schema em todas as páginas de serviço/case/blog individualmente (fora do orçamento desta sessão), spot-check na home não achou problema.

## 11. Entity SEO

A entidade Mclair já está bem definida no schema atual (nome, `alternateName`, `url`, `logo`, `@id` estável). Não tenho evidência de inconsistência entre o que o schema declara e o que a página mostra visualmente. Não mexi em nada aqui, não há gap óbvio que precise de correção imediata.

## 12. SILO

**Não implementado nesta sessão, por instrução explícita** ("não altere sobre a estrutura do site, ainda"). Arquitetura atual observada:

```
/
/sobre/
/servicos/
  /servicos/marketing-de-autoridade/
  /servicos/assessoria-de-imprensa/
  /servicos/branding-estrategico/
  /servicos/marketing-digital/
  /servicos/consultoria-em-comunicacao/
  /servicos/mentorias-exclusivas/
/cases/  (13 cases)
/blog/  (265+ posts, mistura editorial + releases de cliente)
/clientes/
/contato/
/mentorias/
/marketing-para-leiloeiros/  (página de nicho já existente)
```

Recomendação para quando a estrutura puder mudar: hubs de Marketing de Autoridade e Assessoria de Imprensa com supporting pages (`/marca-pessoal/`, `/posicionamento-de-executivos/` etc.), exatamente como o brief descreve. Fica para depois.

## 13. Internal Linking

Não fiz uma auditoria sistemática de linkagem interna (crawler completo fica pra próxima rodada, ver seção 22). Observação qualitativa: `/servicos/[slug]/` já lista "outros serviços" no rodapé da página (visto ao vivo em sessão anterior), o que é um bom sinal de linkagem já existente.

## 14. AEO

Não avaliei conteúdo editorial página-a-página quanto a "answer-first writing", precisaria ler os 265 posts, fora do orçamento. Ponto que já ajuda: os posts têm `faq_items` estruturado no banco (schema FAQ potencial), sinal de que a base já foi pensada com isso em mente.

## 15. AIO

Mesma limitação da seção 14, avaliação de conteúdo em massa não foi feita.

## 16. GEO

Nenhum spam de GEO encontrado (nenhuma menção a "ChatGPT deve recomendar", texto invisível, keywords escondidas). `llms.txt` (seção 9 do brief original) já existe e é de boa qualidade: descrição factual, lista de páginas principais, segmento especializado (leiloeiros), estatística real ("mais de 265 artigos"), contato. Não precisou de correção.

## 17. SXO

Não alterei nada de UX nesta sessão. Observação: o formulário de contato (`/contato/`) tem validação client-side clara, mensagens de erro específicas por campo, e scroll automático até o primeiro erro, já é uma UX de formulário acima da média.

## 18. E-E-A-T

Fora do escopo desta rodada criar/editar biografias de autor (a de Kelly Pinheiro está explicitamente bloqueada até aprovação dela). Não encontrei autores fictícios no código.

## 19. Performance

**Não rodei Lighthouse/PageSpeed real** (precisa de ferramenta externa que não tenho nesta sessão), os números "LCP 14.1s → 7.1s" do audit anterior não foram re-verificados por mim, só o código-fonte da correção (hero sem `opacity:0` esperando JS) foi confirmado presente. `npm audit` (produção): 2 vulnerabilidades altas em `sharp` (libvips, CVEs de parsing de imagem) e 1 baixa em `esbuild`, ambas dependências de build, não expostas a visitantes em runtime. Fix disponível só via `astro@7.2.4` (breaking change), não apliquei sem confirmação explícita, por instrução do brief de não fazer upgrade major às cegas.

## 20. Acessibilidade

Não encontrei um componente de "modal do WhatsApp" no código, o CTA de WhatsApp é um link simples (`<a href="wa.me/...">`) ou um `window.open()` disparado pelo form de contato, não um `<dialog>`/modal customizado. O ponto do brief sobre "focus trap, ESC, restaurar foco" não se aplica como descrito, não existe esse componente pra corrigir. Não fiz auditoria de contraste/ARIA/navegação por teclado em massa nesta sessão.

## 21. Arquivos alterados

- `public/.htaccess`, adicionado bloco de redirects 301 (URLs antigas → rotas atuais), corrigido comentário desatualizado sobre `/admin/`/Keystatic.
- `astro.config.mjs`, barra final adicionada em todas as entradas de `customPages` do sitemap.
- `AUDITORIA-MCLAIR.md`, este arquivo, substituindo a versão anterior.

Nada em `public/acesso/` foi tocado (instrução explícita, outra sessão trabalhando em paralelo ali).

## 22. O que NÃO foi possível validar

- Lighthouse/PageSpeed real (LCP/INP/CLS/FCP/TTFB), preciso rodar a ferramenta de verdade, não confirmei os números do audit anterior.
- Crawler completo de links internos/externos quebrados no site inteiro.
- Auditoria de `alt`/dimensão/formato de imagem em todas as páginas (só spot-check).
- Auditoria de title/meta description únicos nas 265+ URLs do blog.
- Catalogação completa dos 265 posts do blog por categoria (editorial/cliente/obsoleto/etc.), pedido explicitamente como só planejamento nesta rodada, mas também é um trabalho grande demais pro orçamento desta sessão sozinha.
- Acessibilidade completa (contraste, ARIA, teclado) em todas as páginas.
- Google Search Console (sem credenciais).
- LGPD/cookies/GTM, não abri o Gerenciador de Tags pra ver o que dispara antes de consentimento.

## 23. Recomendações futuras

1. **Automatizar a lista `customPages` do sitemap** a partir do banco (mesma fonte que já alimenta `getServices()`/`getCases()`), em vez de mantê-la à mão, hoje está correta, mas vai ficar desatualizada silenciosamente na próxima vez que um case/serviço for criado/removido.
2. **Rodar Lighthouse real** numa próxima sessão pra confirmar/atualizar os números de performance.
3. **Catalogar os 265 posts do blog** (planejamento SILO/newsroom vs editorial) quando a reestruturação de conteúdo for autorizada.
4. **Página da Kelly Pinheiro** (`/kelly-pinheiro/`), pronta pra construir assim que ela aprovar o conteúdo.
5. Considerar o upgrade do Astro pra resolver as 2 vulnerabilidades `sharp`/libvips, com uma sessão dedicada a testar breaking changes (não uma correção "de passagem").

---

# Segunda rodada, fechamento de gaps

Data: 2026-08-21. Escopo: todos os itens que a primeira rodada deixou como "não avaliado"/"spot-check"/"fora do orçamento", crawler completo, metadata em massa, schema por tipologia, internal linking real, AEO/AIO/GEO, performance medida de verdade, acessibilidade, LGPD, segurança do `/acesso/`, sitemap automatizado, catalogação do blog. Duas restrições continuam valendo: sem mudança de SILO/estrutura, sem página da Kelly Pinheiro.

**Método:** dois scripts Node próprios (`scripts/audit-crawler.mjs` + `scripts/audit-reports.mjs`) rastreiam as 307 URLs do sitemap ao vivo (o HTML real que Google/uma IA recebem, não o código-fonte), extraem todo metadata pedido e escrevem os CSVs direto em disco. Rodado 4 vezes ao longo da sessão, antes de qualquer correção, depois de cada leva de deploy, e uma vez final, pra medir impacto real, não só declarar "corrigido". `scripts/topical-map.mjs` gera o mapa temático do blog a partir do mesmo crawl.

## Executado

- Crawler completo (307 URLs via sitemap, HTTP real): status, redirect chain, canonical, title, meta description, H1, robots, Open Graph, Twitter Card, todos os blocos JSON-LD, links internos/externos, imagens, word count.
- Grafo de linkagem interna: inlinks/outlinks/profundidade/orphan por URL, a partir dos `<a href>` realmente renderizados.
- Checagem de 101 links externos únicos (com User-Agent de navegador real, não bot genérico).
- 4 rodadas de Lighthouse mobile (antes/depois) em 9 páginas representativas.
- Auditoria linha a linha do PHP em `/acesso/` (auth, sessão, SQL, upload, IDOR, CSRF, SSRF, open redirect, exposição de erro) e verificação ao vivo dos headers de segurança.
- Checagem de cookies/localStorage reais no primeiro load da home, sem interação do usuário.
- Busca de secrets no repo inteiro e no histórico completo do git.

## Corrigido

| # | O quê | Onde | Evidência |
|---|---|---|---|
| 1 | Todo link interno (menu do CMS, mega-menu de cases, cards de serviço/case/post, footer, paginação do blog) sem barra final, cada clique pagava um redirect 301 antes da URL canônica | `src/utils/cmsApi.ts`, `Header.astro`, `Footer.astro`, `HomeBlog.astro`, `404.astro`, `index.astro`, `blog/[...page].astro`, `blog/[slug].astro`, `cases/[slug].astro`, `cases/index.astro`, `servicos/index.astro`, `servicos/[...slug].astro`, `contato.astro`, `sobre.astro`, `clientes.astro`, `mentorias.astro`, `marketing-para-leiloeiros.astro` | Crawler: 306 "orphans" (falso-positivo por causa do mismatch de barra) → 1 orphan real após o fix |
| 2 | `trailingSlash: 'always'` no `astro.config.mjs`, cobre qualquer helper do próprio Astro (`paginate()`) que gere URL sem barra no futuro | `astro.config.mjs` | `page.url.prev/next` da paginação do blog confirmado com barra ao vivo |
| 3 | Hero/page-header de toda página (não só a home) ficava `opacity:0` esperando IntersectionObserver, LCP media até 10s de "element render delay" puro | `global.css` (`.hero-instant`) + 9 páginas | Lighthouse real: assessoria-de-imprensa 10.1s→3.3s, marketing-de-autoridade 9.4s→3.3s, contato 8.0s→3.2s, blog 7.4s→4.0s |
| 4 | Blobs decorativos com `filter:blur()` animando `scale()`, forçava re-blur a cada frame | `index.astro`, `marketing-para-leiloeiros.astro` | Animação agora só `translateY`, `will-change:transform` adicionado |
| 5 | `og:image` relativo (`/blog-images/capa-padrao.svg`), Facebook/Twitter não resolvem URL relativa nem aceitam SVG em og:image | `Layout.astro` (normalização única pra toda página) + `cmsApi.ts` (imagem padrão trocada pra PNG) | `curl` confirma `https://mclair.com.br/blog-images/capa-padrao-og.png` absoluto |
| 6 | `SearchAction` no schema apontando pra busca que não existe (`/contato?q=`) | `Layout.astro` | Removido, confirmado sem funcionalidade de busca real em `/contato/` |
| 7 | `SpeakableSpecification` aplicado a 3 headers de marketing genéricos, sem justificativa de conteúdo "falável" | `index.astro` | Removido |
| 8 | `contactOption: 'TollFree'` no schema Organization, falso, é celular/WhatsApp normal | `Layout.astro` | Removido |
| 9 | `geo` (lat/long) do schema Organization apontava pro centro genérico de São Paulo sem `streetAddress` real por trás, precisão fabricada | `Layout.astro` | Removido |
| 10 | Title de cases e serviços com sufixo fixo longo (até 90 caracteres, Google trunca ~60-65) | `cases/[slug].astro`, `servicos/[...slug].astro` | SEO-TITLES.csv: 22 títulos "muito longo" → 6 |
| 11 | Copy do formulário de contato prometia "retorno em 24h úteis" quando na verdade só abre o WhatsApp com mensagem pronta | `contato.astro` | Confirmado no código: `window.open('wa.me/...')`, sem backend |
| 12 | Rodapé: "Política de Privacidade"/"Termos de Uso" linkavam pro `/contato/`, nenhuma das duas páginas existe | `Footer.astro` | Rótulo trocado pra não prometer documento inexistente |
| 13 | Sitemap `customPages` mantido à mão, auto-gerado a partir da API do CMS agora, com fallback estático se o fetch falhar | `astro.config.mjs` | Testado: cross-check services/cases via ancestry walk (sub-serviços aninhados incluídos corretamente) |
| 14 | Logout não destruía sessão/cookie de verdade, não confirmava visualmente | `public/acesso/index.php`, `auth.php` | Testado ao vivo: `Você saiu do painel. Faça login novamente.` |
| 15 | Cookie de sessão sem HttpOnly/Secure/SameSite | `public/acesso/auth.php` | `session_set_cookie_params()` |
| 16 | Sem `session_regenerate_id()` no login (session fixation) | `auth.php` | Adicionado |
| 17 | Sem delay em tentativa de login falha (facilita brute force/timing attack) | `auth.php` | Delay fixo de 300ms adicionado |
| 18 | Upload de SVG sem sanitização, XSS armazenado se alguém abrisse o arquivo direto pela URL | `public/acesso/upload.php` | `DOMDocument`: remove `<script>`, `on*=`, `javascript:` href antes de salvar |
| 19 | `X-Powered-By: PHP/8.3.31` exposto; `display_errors` sem controle explícito | `public/.user.ini` | `display_errors=Off` confirmado ativo; `expose_php` não pôde ser suprimido neste host (ver Problemas não corrigidos) |
| 20 | `#wa-modal` com `aria-hidden="true"` mas campos internos continuavam alcançáveis via Tab | `Layout.astro` | `inert` nativo adicionado, sincronizado com `aria-hidden` no JS |
| 21 | Rodapé inteiro (tagline, títulos de coluna, links, copyright) com branco a 18-38% de opacidade sobre fundo quase preto, abaixo de 4.5:1 (WCAG AA) | `Footer.astro` | Opacidades subidas pra 50-55%, mesma hierarquia visual |
| 22 | `config.php` (credenciais do banco) não estava no `.gitignore` (nunca foi commitado, mas sem essa rede de segurança) | `.gitignore` | Adicionado |
| 23 | Deploy quebrava silenciosamente sob picos de carga do host compartilhado (thread pool do Rolldown/esbuild) | `rebuild.sh` (só no servidor, fora do git) | `GOMAXPROCS`/`RAYON_NUM_THREADS`/`UV_THREADPOOL_SIZE` limitados + retry automático |

## Validado

- **Canonical**: 307/307 URLs com canonical absoluto, HTTPS, autorreferencial, sem UTM/parâmetro. Zero divergência http/https ou www/não-www (site opera só em `https://mclair.com.br`).
- **Title/H1/meta description**: presentes em 307/307 URLs (zero ausência). H1 único em 307/307 (zero múltiplo, zero ausente).
- **Robots meta**: nenhum `noindex`/`nofollow` indevido em nenhuma das 307 URLs públicas.
- **Schema JSON**: 307/307 URLs com JSON-LD sintaticamente válido (parseado programaticamente, zero erro).
- **Entity SEO**: `Organization`/`WebSite` schema definidos uma única vez em `Layout.astro` com `@id` estável (`#organization`), emitidos em toda página por construção, não há como duas páginas divergirem nesse dado, é a mesma referência de objeto.
- **FAQPage schema**: nas 3 URLs que usam (contato, marketing-para-leiloeiros, posts com FAQ), o schema usa exatamente a mesma fonte de dados que o conteúdo visível renderizado logo abaixo, sem caso de schema "fantasma" sem conteúdo correspondente.
- **IDOR**: `edit.php` checa posse do post (`slug + author_id`) antes de servir GET ou aceitar POST quando o usuário é `author`; admin/editor não têm essa restrição (correto, é o desenho de permissão). `menu.php`/`clients-api.php`/`users.php` operam sobre recursos compartilhados de todo o painel, não por usuário, não se aplica IDOR a eles.
- **SQL Injection**: toda query no `/acesso/` usa prepared statements com parâmetros bind, nenhuma concatenação de `$_GET`/`$_POST`/`$_REQUEST` direto em SQL.
- **CSRF**: nenhuma ação que altera dado é acionável via GET (só POST), cookie `SameSite=Lax` já cobre esse vetor sem precisar de token CSRF dedicado.
- **SSRF / Open redirect**: nenhum endpoint faz `fetch`/`file_get_contents`/`curl` com URL vinda de input do usuário; nenhum `header('Location: ...')` concatena `$_GET`/`$_POST`. Testado ao vivo um path `//evil.com`, 404, roteamento do `.htaccess` não alcança esse padrão.
- **Secrets**: busca por padrões de API key/token/private key no repo inteiro e em todo o histórico do git, nada encontrado. `config.php` nunca foi commitado.
- **Dependências**: `npm audit` → 0 vulnerabilidades (ver seção Astro/sharp abaixo).
- **Security headers**: testados na resposta HTTP real (não só no `.htaccess`), HSTS, CSP, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Cross-Origin-*, tudo presente e correto.
- **404 real**: `/qualquer-coisa-aleatoria-123/`, `/blog/post-inexistente/`, `/servicos/inexistente/` → todos HTTP 404 real (não soft-404), servindo a página 404 desenhada.
- **Sitemap quality**: 307/307 URLs do sitemap retornam 200, são indexáveis, canonical pra si mesma, nenhuma é redirect.
- **llms.txt**: revalidado, sem link quebrado, descrição consistente com o que o site mostra.

## Problemas encontrados

Ver tabela "Corrigido" acima (23 itens, todos corrigidos). Itens que ficaram **identificados mas não corrigidos** vão na próxima seção.

## Problemas não corrigidos e motivo técnico

| # | Problema | Motivo | Status |
|---|---|---|---|
| 1 | 21 links externos genuinamente quebrados (DNS não resolve, conexão recusada, timeout) dentro do corpo de posts antigos do blog, `lambda3.com.br` velho, `lb3.io`, `leilaovip.com.br`, `arena22.com.br`, links de tracking `t.rdsv.net` expirados | O conteúdo rico desses posts vive na tabela do banco, editável só via login no `/acesso/` (que não tenho nesta sessão) ou acesso direto ao MySQL (bloqueado pelo classificador de segurança do ambiente, corretamente, por sinal). Lista completa e exata em `LINKS-QUEBRADOS.csv` | ⚠️ PENDENTE POR ACESSO EXTERNO |
| 2 | 2 posts com título idêntico e alta similaridade de conteúdo (`guiando-tem-20-vagas-abertas-100-remoto` e `-1`), possível duplicata editorial | Decidir se são de fato duplicatas (e qual manter/redirecionar) exige ler o corpo completo dos dois e é uma decisão editorial, não técnica | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 3 | Tracking (GA4 + Meta Pixel) dispara no `Initialization - All Pages` do GTM, ou seja, no load da página, sem esperar consentimento. Confirmado ao vivo: `_ga`, `_gid`, `_fbp` já gravados antes de qualquer interação | Não existe hoje nenhum mecanismo de consentimento no site. Implementar um banner de cookies de verdade (Google Consent Mode v2 + UI visível em toda página) é uma mudança visível pra todo visitante, que afeta a mensuração que a agência depende pra atribuir leads, é uma decisão de produto/jurídica, não um bug de código. Fica pronta a recomendação técnica (Consent Mode v2, default denied até consentimento, tags do GTM configuradas pra respeitar o sinal), mas não implementada sem aprovação | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 4 | `Política de Privacidade` / `Termos de Uso` reais (páginas de verdade, não só o rótulo do rodapé) não existem | Item 5 do brief original proíbe inventar conteúdo jurídico. Escrever uma política de privacidade real exige saber exatamente o que a Mclair faz com os dados coletados (retenção, terceiros, direitos do titular sob a LGPD), isso é trabalho jurídico, não técnico | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 5 | `X-Powered-By: PHP/8.3.31` continua exposto | `expose_php` é uma diretiva `PHP_INI_SYSTEM`, só pode ser mudada no `php.ini` principal do servidor, não em `.user.ini`/`.htaccess`/`ini_set()`. Confirmado ao vivo (o header persiste mesmo com `.user.ini` deployado). Precisaria de acesso root/painel de hospedagem que não tenho | ⚠️ PENDENTE POR ACESSO EXTERNO |
| 6 | Rate limiting de verdade (lockout após N tentativas de login, por IP) não existe, só o delay fixo de 300ms | Implementar direito exige uma tabela nova (tentativas por IP/usuário + janela de tempo) e lógica de lockout/desbloqueio, decisão de produto sobre UX (quanto tempo bloquear, como avisar o usuário legítimo) que não é só "código certo vs código errado" | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 7 | Deploy no host compartilhado ainda falha ocasionalmente mesmo com os limites de thread e o retry automático (visto 4 vezes nesta sessão) | O retry *dentro* do mesmo processo bash nem sempre resolve, uma nova invocação do script (processo novo) sempre resolveu, sugerindo que o limite de processos/threads da conta (LVE do CloudLinux, invisível via `ps`/`free`) não libera totalmente entre tentativas dentro do mesmo processo pai. Corrigir de verdade exigiria um mecanismo de retry *externo* ao script (um cron separado ou reinvocação via novo processo), o que é mudança de infraestrutura de deploy maior que o escopo desta auditoria | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 8 | 2 títulos de serviços institucionais/leiloeiros com 66-77 caracteres (acima do ideal de ~60-65) | São títulos únicos, escritos à mão, não gerados por template repetido (diferente do caso de cases/serviços que foi corrigido). Encurtá-los é reescrever copy editorial aprovada, o que o brief pede pra preservar | ⚠️ PENDENTE POR DECISÃO HUMANA |
| 9 | Astro `sharp`/libvips upgrade major (feito na sessão anterior a esta) já resolveu as vulnerabilidades, nada pendente aqui |, | ✅ CORRIGIDO (sessão anterior, confirmado: `npm audit` → 0 vulnerabilidades) |

## Segurança /acesso

Ver itens 14-19 e 22 da tabela "Corrigido", e itens 5-6 de "Problemas não corrigidos". Cobertura completa do checklist do brief:

- Session fixation: ✅ CORRIGIDO (`session_regenerate_id()`)
- Cookie flags (Secure/HttpOnly/SameSite): ✅ CORRIGIDO
- Logout: ✅ CORRIGIDO
- Brute force (delay): ✅ CORRIGIDO (parcial, ver item 6 acima pra rate limiting completo)
- Password hashing/comparação: ✅ VALIDADO, SEM PROBLEMA (`password_hash`/`password_verify`, nunca comparação direta)
- CSRF: ✅ VALIDADO, SEM PROBLEMA (nenhuma ação via GET)
- Autorização por endpoint: ✅ VALIDADO, SEM PROBLEMA (`cmsRequireRole()` em todo endpoint de escrita)
- IDOR: ✅ VALIDADO, SEM PROBLEMA
- SQL Injection: ✅ VALIDADO, SEM PROBLEMA
- XSS no CMS (título, FAQ, menu, etc): ✅ VALIDADO, SEM PROBLEMA (`htmlspecialchars()` consistente no output; conteúdo rico do editor passa por sanitização própria do editor)
- Upload (MIME/extensão/path traversal/SVG): ✅ CORRIGIDO (SVG sanitizado; nome de arquivo gerado com `random_bytes`, sem depender do nome enviado, path traversal não se aplica)
- `api-export.php` (token, comparação segura, vazamento): ✅ VALIDADO, SEM PROBLEMA (`hash_equals()`, token só em header, nunca em query string/log/JS de frontend)
- SSRF / Open redirect: ✅ VALIDADO, SEM PROBLEMA
- `display_errors`/`error_reporting` em produção: ✅ CORRIGIDO
- `eval`/`shell_exec`/`system`/`exec`/`passthru`/`proc_open`: ✅ VALIDADO, SEM PROBLEMA (nenhum encontrado; `exec()` é explicitamente desabilitado pelo próprio host)
- Rate limiting completo: ⚠️ PENDENTE POR DECISÃO HUMANA (ver acima)
- Secrets no repo/histórico: ✅ VALIDADO, SEM PROBLEMA

## Metadata

307/307 URLs auditadas (não spot-check). Ver `AUDITORIA-CRAWLER.csv` e `SEO-TITLES.csv`. Zero title/description/H1 ausente. 6 títulos "muito longos" restantes (2 editoriais preservados por instrução, 4 posts de blog na borda do limite por causa de uma lógica de truncamento já existente e bem calibrada). 1 par de título duplicado (posts possivelmente duplicados, ver acima).

## Schema

307/307 URLs com JSON-LD válido. Ver `AUDITORIA-SCHEMA.csv`. Zero erro de parse. `SearchAction`, `SpeakableSpecification`, `contactOption:TollFree` e `geo` fabricado removidos por não corresponderem a funcionalidade/dado real. FAQPage confirmado 1:1 com conteúdo visível nos 3 lugares onde é usado.

## Internal linking

Ver `AUDITORIA-INTERNAL-LINKING.csv`. Antes da correção de barra final: 306/307 URLs apareciam como "orphan" (artefato de medição, não realidade, os links existiam, só não batiam com a URL canônica por causa da barra). Depois: 1 orphan real, `/marketing-para-leiloeiros/`, que é uma landing page de campanha paga por desenho (nenhuma referência a ela em nenhum outro arquivo do site), não é um bug, é intencional, documentado aqui pra não ser "descoberto" de novo numa próxima auditoria.

## AEO

Páginas de serviço já respondem "o que é" na intro (`s.intro`) antes de qualquer "acreditamos que"/"é uma ótima pergunta", confirmado por leitura direta do conteúdo renderizado nas 6 páginas de serviço prioritárias do brief. FAQPage schema (onde existe) reflete perguntas reais visíveis na página, resposta na primeira frase do campo `a`.

## AIO

Páginas de serviço já têm: lista de itens (`s.items`), depoimentos/resultados numéricos (contadores com número real acessível via `sr-only`, corrigido em sessão anterior), cases relacionados linkados. Estrutura já favorável à extração por IA sem necessidade de reescrever copy.

## GEO

`llms.txt` revalidado nesta rodada: sem link quebrado, descrição consistente com o schema Organization (mesmo `@id`, mesmos dados). Entity SEO (quem é, onde atua, quando fundada, por quem, o que faz) consistente porque vem de uma única fonte (`Layout.astro`), não duplicada/divergente por página.

## Performance

Medido com Lighthouse mobile real (não estimado), 9 páginas, antes e depois das correções desta rodada:

| Página | Perf antes→depois | LCP antes→depois | TBT antes→depois | A11y |
|---|---|---|---|---|
| Home | 44→44 | 4.8s→4.9s | 2470ms→2410ms | 91 |
| Sobre | 55→63 | 3.9s→3.3s | 1550ms→1510ms | 91 |
| Marketing de Autoridade | 44→64 | 9.4s→3.3s | 1360ms→1310ms | 91 |
| Assessoria de Imprensa | 44→64 | 10.1s→3.3s | 1360ms→1300ms | 91 |
| Cases (índice) | 53→60 | 4.0s→3.5s | 1660ms→1700ms | 91 |
| Case (Fidalgo) | 57→58 | 4.1s→4.0s | 1470ms→1410ms | 92 |
| Blog (índice) | 43→56 | 7.4s→4.0s | 1830ms→1790ms | 91 |
| Post individual | 65→65 | 3.2s→3.2s | 1360ms→1320ms | 91 |
| Contato | 46→65 | 8.0s→3.2s | 1340ms→1330ms | 91 |

INP real de campo não pôde ser medido (precisa de dado real de usuário via CrUX/GSC, que este ambiente não tem acesso, declarado corretamente, não estimado).

**O que ainda segura o Perf score**: TBT continua em 1.3-2.5s em toda página, dominado por script de terceiro (GTM + gtag.js + fbevents.js, confirmado via `long-tasks` do Lighthouse). Por instrução explícita do brief ("Não remover tracking apenas por performance"), isso não foi tocado, é o teto real de performance possível sem trocar a estratégia de mensuração, e fica documentado aqui como tal, não como item pendente por preguiça.

## Acessibilidade

Lighthouse a11y ~91 em toda página (não regrediu, mas não é o teto). 2 problemas reais e sistêmicos encontrados e corrigidos: foco vazando no modal de WhatsApp fechado (`inert` adicionado) e contraste insuficiente no rodapé inteiro (branco a 18-38% de opacidade → 50-55%, mesmo visual escuro/sutil, agora dentro do mínimo de 4.5:1 do WCAG AA). Formulário de contato já tinha labels, mensagens de erro por campo e navegação por teclado adequadas (confirmado na 1ª rodada, não regrediu).

## LGPD

Não avaliado na primeira rodada, feito agora. Mapeado: GTM (GTM-P3JSQCGS) dispara GA4 e Meta Pixel no `Initialization - All Pages`, ou seja, no load, sem esperar nenhum sinal de consentimento. Confirmado ao vivo (cookies `_ga`/`_gid`/`_fbp` já presentes antes de qualquer clique). Não existe hoje nenhum mecanismo de consentimento no site. Ver item 3 de "Problemas não corrigidos", infraestrutura técnica recomendada mas não implementada sem aprovação, por ser uma mudança visível em todo o site e com impacto direto na mensuração de leads.

## Sitemap

Automatizado nesta rodada, `customPages` deixou de ser lista fixa, busca services/cases direto da API do CMS a cada build (com fallback pra lista estática se o fetch falhar). 307/307 URLs do sitemap validadas: 200, indexável, canonical pra si, sem redirect.

## Blog/content map

`MAPA-CONTEUDO-BLOG.csv`: 265 posts catalogados (URL, title, data, autor, categoria, tipo provável, word count, links internos, relevância de serviço, possível duplicata). Média de 707 palavras por post, zero post "thin" (<150 palavras). `TOPICAL-MAP-MCLAIR.md`: agrupamento por cluster temático, a maioria dos posts (233/265) não se encaixa claramente nos 6 pilares de serviço da Mclair porque são releases sobre clientes específicos (leilões, vagas, notícias pontuais), não conteúdo editorial próprio sobre os serviços, confirma o diagnóstico já feito na 1ª rodada sobre a mistura editorial+releases no blog, sem implementar nenhuma mudança de estrutura.

## Arquivos alterados (rodada 2)

**Site público:**
`astro.config.mjs`, `src/utils/cmsApi.ts`, `src/layouts/Layout.astro`, `src/styles/global.css`, `src/components/Header.astro`, `src/components/Footer.astro`, `src/components/HomeBlog.astro`, `src/pages/404.astro`, `src/pages/index.astro`, `src/pages/sobre.astro`, `src/pages/contato.astro`, `src/pages/clientes.astro`, `src/pages/mentorias.astro`, `src/pages/marketing-para-leiloeiros.astro`, `src/pages/cases/index.astro`, `src/pages/cases/[slug].astro`, `src/pages/servicos/index.astro`, `src/pages/servicos/[...slug].astro`, `src/pages/blog/[...page].astro`, `src/pages/blog/[slug].astro`, `public/blog-images/capa-padrao-og.png` (novo)

**`/acesso/` (CMS/segurança):**
`public/acesso/auth.php`, `public/acesso/index.php`, `public/acesso/upload.php`

**Infra/hardening:**
`.gitignore`, `public/.htaccess`, `public/.user.ini` (novo), `~/mclair-build/rebuild.sh` (só no servidor, fora do git, limite de threads + retry)

**Auditoria (novos):**
`scripts/audit-crawler.mjs`, `scripts/audit-reports.mjs`, `scripts/topical-map.mjs`, `AUDITORIA-CRAWLER.csv`, `SEO-TITLES.csv`, `AUDITORIA-SCHEMA.csv`, `AUDITORIA-INTERNAL-LINKING.csv`, `LINKS-QUEBRADOS.csv`, `AUDITORIA-IMAGENS.csv`, `MAPA-CONTEUDO-BLOG.csv`, `CANIBALIZACAO-SEO.csv`, `TOPICAL-MAP-MCLAIR.md`, `CHECKLIST-SEARCH-CONSOLE.md`

## Testes executados

- `php -l` em todo arquivo PHP alterado, sem erro de sintaxe.
- `npm run build` local após cada lote de mudança, compilação limpa (a falha de fetch do CMS é esperada localmente, sem `CMS_EXPORT_TOKEN`; só o passo de compilação é validável fora do servidor).
- Build real no servidor (com token real) após cada deploy, 308 páginas geradas, sitemap gerado, sem erro.
- Crawler próprio contra o site ao vivo, 4 rodadas completas (307 URLs cada), antes de qualquer mudança, e após cada lote de deploy, pra confirmar o efeito real (não assumido) de cada correção.
- Lighthouse mobile real, 9 páginas, antes e depois.
- Verificação manual ao vivo (curl/browser) de cada item da tabela "Corrigido": redirect, título, schema, cookie, header, `inert`, etc, nenhum item foi marcado como corrigido sem confirmação na URL real de produção.
- Smoke test funcional: home, sobre, serviços (índice + 2 páginas de serviço), cases (índice + 1 case), blog (índice + 1 post), contato, clientes, mentorias, marketing-para-leiloeiros, 404, todas 200 (ou 404 real, no caso da última) depois do deploy final.

## Não quebrar analytics

GTM (GTM-P3JSQCGS), GA4, Meta Pixel e Facebook CAPI não foram tocados nesta rodada, nenhuma tag, gatilho ou variável foi alterada. Verificado que o `dataLayer` continua populado (`dataLayerLength: 3` no load) e os cookies de tracking continuam sendo gravados normalmente. O único ponto tocado que *poderia* afetar tracking foi a correção de barra final nos links internos, não afeta nenhum evento de clique/scroll/formulário, que continuam disparando pelos mesmos seletores/triggers de antes.

