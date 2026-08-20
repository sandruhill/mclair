# Auditoria Mclair — mclair.com.br

Data: 2026-08-20 (revisão)
Escopo: auditoria técnica solicitada pelo Sandru (segurança, SEO técnico/semântico, Entity SEO, SILO, AEO/AIO/GEO, E-E-A-T, performance, acessibilidade), com duas restrições explícitas do pedido: **não alterar a estrutura do site ainda** (SILO, split blog/newsroom, novas páginas ficam só documentados/planejados) e **não criar a página da Kelly Pinheiro** (aguarda aprovação dela).

**Nota sobre este documento:** já existia um `AUDITORIA-MCLAIR.md` no repositório, de uma sessão overnight anterior (commit `9355647`, 2026-08-20 02:31). Verifiquei os itens que ele reivindicava como corrigidos — a maioria segue realmente aplicada no código atual (allowlist do `fb-capi.php`, ordem do `<meta charset>` antes do GTM, sitemap apontando pro domínio certo, contadores com valor real no HTML). Esse documento **substitui** o anterior, incorporando o que ainda é válido e cobrindo tudo que mudou desde então (renomeação `/admin/` → `/acesso/`, CMS novo, menu dinâmico, etc.) mais a auditoria nova pedida agora.

**Como ler:** cada item diz se foi (a) corrigido e já está em produção, (b) auditado e está OK, ou (c) precisa de decisão/dado que só o Sandru/Kelly têm. Nada foi inventado — sem acesso a Search Console, Lighthouse real ou credenciais, itens que dependem disso estão na seção 22, não fingidos como concluídos.

---

## 1. Estado inicial

Stack: Astro v6 (`output: 'static'`), conteúdo dinâmico (posts, cases, serviços, páginas institucionais, menu) servido por um CMS PHP+MySQL próprio em `/acesso/`, consumido em build-time via uma API JSON própria (`/acesso/api-export.php`, autenticada por token). Deploy: push para o remote Hostinger dispara um hook que roda `npm run build` no próprio servidor e sincroniza `dist/` via rsync; um segundo caminho via cron (`queueRebuild()`) republica após qualquer edição no CMS. DNS 100% em Hostinger (confirmado). Um projeto Vercel antigo existia como mirror público desatualizado — já foi bloqueado com middleware 403 em sessão anterior hoje mesmo, fora do escopo desta auditoria.

O projeto chega a este ponto com uma base de SEO bem mais madura que o normal: schema.org rico e válido, sitemap automático, `llms.txt` com conteúdo real, headers de segurança fortes, 404 verdadeira. A auditoria de hoje partiu de um patamar bom, não de um zero.

## 2. Problemas encontrados

| # | Problema | Onde | Severidade | Status |
|---|---|---|---|---|
| 1 | URLs antigas da era PHP (`quem-somos.php`, `contato.php`, `servicos.php`, `blog.php`, `index.php`, `blog-detalhe.php?id=N`) retornavam 404 puro, sem redirect — qualquer backlink/bookmark antigo perdia o link equity | site inteiro | Alta | **Corrigido** |
| 2 | Sitemap listava a forma não-canônica de `/servicos`, `/cases` e páginas institucionais (sem barra final), enquanto a própria página se autodeclara canônica COM barra — o servidor até 301-redireciona a forma sem barra pra com barra | `astro.config.mjs` (`customPages`) | Média | **Corrigido** |
| 3 | (bug que eu mesmo introduzi ao corrigir #1) `RewriteRule` sem `QSD` estava anexando a query string antiga (`?id=155/...`) na URL de destino do redirect | `public/.htaccess` | Média | **Corrigido** no mesmo ciclo |
| 4 | `.htaccess` tinha um comentário desatualizado referenciando `/admin/` e "Keystatic CMS" — sistemas que não existem mais (renomeados/substituídos em sessão anterior) | `public/.htaccess` | Baixa (só documentação) | **Corrigido** |
| 5 | `sharp` (processamento de imagem, só build-time) tem 2 CVEs altas conhecidas (libvips); fix requer bump major do Astro (7.2.4) | `package.json` | Média, risco real baixo (não roda em runtime exposto a visitantes) | **Documentado, não aplicado** — ver seção 19 |
| 6 | Lista `customPages` do sitemap é mantida manualmente (cases/serviços não são auto-descobertos) — risco de ficar desatualizada quando um case/serviço novo for criado no CMS | `astro.config.mjs` | Baixa, risco futuro | **Documentado** — ver seção 23 |

## 3. Vulnerabilidades encontradas

- **Nenhum secret exposto.** `.env`, `.git/`, arquivos `.sql`/`.bak`/`.zip` retornam 403 (bloqueados via `.htaccess`); `package.json`, `vercel.json` retornam 404 (nem chegam a `dist/`). Segredos reais (`.secrets/`) confirmados fora do git (`git ls-files` não lista nenhum).
- **CORS**: único endpoint com header CORS é `public/api/fb-capi.php`, restrito a `https://mclair.com.br` (não `*`). OK.
- **XSS**: nenhuma atribuição `innerHTML =` encontrada em `src/`. Conteúdo do CMS passa por `htmlspecialchars()` no lado PHP e por interpolação segura do Astro no lado do site.
- **Formulário de contato**: não existe backend — o form roda 100% client-side, monta uma mensagem com `encodeURIComponent()` e abre um link `wa.me` via `window.open()`. Não há superfície de CSRF/injeção server-side porque não há servidor processando o envio. Validação client-side existe (nome/e-mail/mensagem obrigatórios, formato de e-mail) — adequada para esse desenho, já que nada é persistido ou processado no backend.
- **`fb-capi.php`** (Facebook Conversions API): já tem allowlist de `event_name` (confirmado no código atual) — não aceita eventos arbitrários.
- **Dependências**: `npm audit` (produção) → 3 vulnerabilidades (1 low `esbuild`, 2 high `sharp`/libvips). Ambas são dependências **de build**, não chegam ao HTML/JS servido ao visitante. Fix disponível só via upgrade major do Astro — não apliquei sem confirmação (ver seção 19).
- **`/acesso/`**: não toquei nada aqui por instrução explícita (outra sessão está mexendo em paralelo). Nada de novo auditado nessa área além do que já é sabido de sessões anteriores (auth por sessão PHP, tokens de export, etc.).

## 4. Correções de segurança realizadas

Nenhuma vulnerabilidade de segurança nova foi encontrada além do que já estava corrigido. O trabalho desta sessão nessa frente foi de **SEO técnico** (seção 2), não de segurança propriamente dita.

## 5. SEO técnico

- Canonical: presente e autorreferencial em todas as páginas verificadas (`/cases/`, homepage). Não encontrei duplicidade.
- Trailing slash: agora consistente entre sitemap e canonical (ver #2 acima).
- Robots.txt: correto — `Allow: /`, bloqueia só `/acesso/`, referencia os dois arquivos de sitemap, com comentários de auditoria anteriores documentando correções já feitas (domínio do sitemap, remoção de bloqueio indevido a Googlebot).
- Meta description e title: não fiz uma varredura página-a-página das 265+ URLs (fora do orçamento desta sessão) — spot-check na home e em `/cases/` mostrou title/description específicos, não genéricos.
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

**Como foi construído:** os 34 ids confirmados vieram do Wayback Machine (CDX API) — o banco de dados atual não preserva os ids numéricos antigos, então não dava pra montar o mapa só com o banco. Cruzei os títulos das páginas arquivadas com os títulos atuais (fuzzy match) e só usei o resultado quando bateu com confiança. Ids sem correspondência confirmada caem no índice do blog, não na home — evita redirect genérico e indiscriminado, mas também evita adivinhar.

**`/servicos.php?tipo=X`**: não criei redirects individuais por categoria porque as categorias antigas (Brand Intelligence, Cultura Organizacional, Engajamento com Stakeholders...) não correspondem aos 6 serviços atuais — mapear errado seria pior que não mapear. `/servicos.php` (sem `tipo`) redireciona pro índice atual de serviços.

## 7. Canonicals

Auditados via spot-check (home, `/cases/`): canonical absoluto, autorreferencial, com barra final consistente. Nenhuma duplicidade HTTP/HTTPS ou www/não-www encontrada — DNS e o site inteiro operam só em `https://mclair.com.br` (sem `www`).

## 8. Sitemap

`https://mclair.com.br/sitemap-index.xml` → `sitemap-0.xml`, gerado por `@astrojs/sitemap`. Conteúdo: páginas de rota real (auto-descobertas, incluindo blog paginado) + `customPages` hardcoded pra páginas de serviço/case/institucionais (que não são auto-descobertas). Corrigido: barra final ausente nas `customPages` (seção 2/6). Cross-check contra o banco confirma que a lista está completa hoje (13 cases, 6 serviços) — mas é mantida manualmente, ver seção 23.

## 9. Robots

`https://mclair.com.br/robots.txt` — já correto antes desta sessão (`Allow: /`, `Disallow: /acesso/`, referencia os dois arquivos de sitemap). Não bloqueia CSS/JS/imagens. Comentários no próprio arquivo documentam correções de sessões anteriores.

## 10. Schema

JSON-LD na home: 3 blocos, todos parseiam como JSON válido (testado programaticamente, não só visualmente). Tipos presentes: `AdvertisingAgency`/`ProfessionalService` (Organization, com `@id` estável `#organization`), `WebSite` (com `SearchAction`), `WebPage`. Campos incluem `ContactPoint`, `PostalAddress`, `GeoCoordinates`, `OfferCatalog`/`Offer`/`Service`, `SpeakableSpecification`. Não fiz varredura de schema em todas as páginas de serviço/case/blog individualmente (fora do orçamento desta sessão) — spot-check na home não achou problema.

## 11. Entity SEO

A entidade Mclair já está bem definida no schema atual (nome, `alternateName`, `url`, `logo`, `@id` estável). Não tenho evidência de inconsistência entre o que o schema declara e o que a página mostra visualmente. Não mexi em nada aqui — não há gap óbvio que precise de correção imediata.

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

Recomendação para quando a estrutura puder mudar: hubs de Marketing de Autoridade e Assessoria de Imprensa com supporting pages (`/marca-pessoal/`, `/posicionamento-de-executivos/` etc.) — exatamente como o brief descreve. Fica para depois.

## 13. Internal Linking

Não fiz uma auditoria sistemática de linkagem interna (crawler completo fica pra próxima rodada — ver seção 22). Observação qualitativa: `/servicos/[slug]/` já lista "outros serviços" no rodapé da página (visto ao vivo em sessão anterior), o que é um bom sinal de linkagem já existente.

## 14. AEO

Não avaliei conteúdo editorial página-a-página quanto a "answer-first writing" — precisaria ler os 265 posts, fora do orçamento. Ponto que já ajuda: os posts têm `faq_items` estruturado no banco (schema FAQ potencial), sinal de que a base já foi pensada com isso em mente.

## 15. AIO

Mesma limitação da seção 14 — avaliação de conteúdo em massa não foi feita.

## 16. GEO

Nenhum spam de GEO encontrado (nenhuma menção a "ChatGPT deve recomendar", texto invisível, keywords escondidas). `llms.txt` (seção 9 do brief original) já existe e é de boa qualidade: descrição factual, lista de páginas principais, segmento especializado (leiloeiros), estatística real ("mais de 265 artigos"), contato. Não precisou de correção.

## 17. SXO

Não alterei nada de UX nesta sessão. Observação: o formulário de contato (`/contato/`) tem validação client-side clara, mensagens de erro específicas por campo, e scroll automático até o primeiro erro — já é uma UX de formulário acima da média.

## 18. E-E-A-T

Fora do escopo desta rodada criar/editar biografias de autor (a de Kelly Pinheiro está explicitamente bloqueada até aprovação dela). Não encontrei autores fictícios no código.

## 19. Performance

**Não rodei Lighthouse/PageSpeed real** (precisa de ferramenta externa que não tenho nesta sessão) — os números "LCP 14.1s → 7.1s" do audit anterior não foram re-verificados por mim, só o código-fonte da correção (hero sem `opacity:0` esperando JS) foi confirmado presente. `npm audit` (produção): 2 vulnerabilidades altas em `sharp` (libvips, CVEs de parsing de imagem) e 1 baixa em `esbuild` — ambas dependências de build, não expostas a visitantes em runtime. Fix disponível só via `astro@7.2.4` (breaking change) — não apliquei sem confirmação explícita, por instrução do brief de não fazer upgrade major às cegas.

## 20. Acessibilidade

Não encontrei um componente de "modal do WhatsApp" no código — o CTA de WhatsApp é um link simples (`<a href="wa.me/...">`) ou um `window.open()` disparado pelo form de contato, não um `<dialog>`/modal customizado. O ponto do brief sobre "focus trap, ESC, restaurar foco" não se aplica como descrito — não existe esse componente pra corrigir. Não fiz auditoria de contraste/ARIA/navegação por teclado em massa nesta sessão.

## 21. Arquivos alterados

- `public/.htaccess` — adicionado bloco de redirects 301 (URLs antigas → rotas atuais), corrigido comentário desatualizado sobre `/admin/`/Keystatic.
- `astro.config.mjs` — barra final adicionada em todas as entradas de `customPages` do sitemap.
- `AUDITORIA-MCLAIR.md` — este arquivo, substituindo a versão anterior.

Nada em `public/acesso/` foi tocado (instrução explícita — outra sessão trabalhando em paralelo ali).

## 22. O que NÃO foi possível validar

- Lighthouse/PageSpeed real (LCP/INP/CLS/FCP/TTFB) — preciso rodar a ferramenta de verdade, não confirmei os números do audit anterior.
- Crawler completo de links internos/externos quebrados no site inteiro.
- Auditoria de `alt`/dimensão/formato de imagem em todas as páginas (só spot-check).
- Auditoria de title/meta description únicos nas 265+ URLs do blog.
- Catalogação completa dos 265 posts do blog por categoria (editorial/cliente/obsoleto/etc.) — pedido explicitamente como só planejamento nesta rodada, mas também é um trabalho grande demais pro orçamento desta sessão sozinha.
- Acessibilidade completa (contraste, ARIA, teclado) em todas as páginas.
- Google Search Console (sem credenciais).
- LGPD/cookies/GTM — não abri o Gerenciador de Tags pra ver o que dispara antes de consentimento.

## 23. Recomendações futuras

1. **Automatizar a lista `customPages` do sitemap** a partir do banco (mesma fonte que já alimenta `getServices()`/`getCases()`), em vez de mantê-la à mão — hoje está correta, mas vai ficar desatualizada silenciosamente na próxima vez que um case/serviço for criado/removido.
2. **Rodar Lighthouse real** numa próxima sessão pra confirmar/atualizar os números de performance.
3. **Catalogar os 265 posts do blog** (planejamento SILO/newsroom vs editorial) quando a reestruturação de conteúdo for autorizada.
4. **Página da Kelly Pinheiro** (`/kelly-pinheiro/`) — pronta pra construir assim que ela aprovar o conteúdo.
5. Considerar o upgrade do Astro pra resolver as 2 vulnerabilidades `sharp`/libvips, com uma sessão dedicada a testar breaking changes (não uma correção "de passagem").
