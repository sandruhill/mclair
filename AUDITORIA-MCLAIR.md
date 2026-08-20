# Auditoria Mclair — mclair.com.br

Data: 2026-08-20
Escopo: auditoria técnica completa solicitada (segurança, SEO técnico/semântico, Entity SEO, SILO, AEO/AIO/GEO, E-E-A-T, performance, acessibilidade), executada em uma sessão overnight sem supervisão direta do responsável do site.

**Como ler este documento:** cada seção diz claramente se o item foi (a) corrigido e já está em produção, (b) auditado e está OK, sem necessidade de mudança, ou (c) precisa de uma decisão ou dado factual que só o Sandru/Kelly têm. Nada foi inventado — onde eu não tinha informação real (biografia, métricas, URLs antigas), deixei como pendência em vez de supor.

---

## 1. Estado inicial

Stack: Astro v6 (`output: 'static'`), conteúdo via Keystatic/Sveltia CMS (git-based), deploy via GitHub Actions → rsync para Hostinger (PHP 8.3, sem Node.js nativo disponível nesse plano até hoje — ver seção 21). Sistema de login do `/admin/` migrado de Deno Deploy para PHP+MySQL nativo no Hostinger nesta mesma sessão.

O projeto já chegava com uma base técnica de SEO bem mais madura do que o normal para um site desse porte: schema.org completo, sitemap automático, `llms.txt`, headers de segurança fortes. A auditoria começou de um patamar melhor do que o brief presumia.

## 2. Problemas encontrados (e corrigidos nesta sessão)

| # | Problema | Onde | Severidade |
|---|---|---|---|
| 1 | `robots.txt` apontava o sitemap para o domínio antigo do Hostinger (`olive-gnat-658393.hostingersite.com`), não para `mclair.com.br` | `public/robots.txt` | Alta — crawlers eram mandados pro lugar errado |
| 2 | Contadores animados (9+ anos, 200+ clientes, 11.600+ inserções, 1.734+ publicações) renderizavam `0` no HTML inicial, só ficavam corretos depois do JS rodar | `index.astro`, `sobre.astro`, `cases/index.astro` | Média — exatamente o tipo de dado que crawler/LLM não deveria depender de JS pra ler |
| 3 | Header fixo (sticky nav) usava `backdrop-filter: blur()`, que é uma causa clássica de falha de composição em GPU no Android — o menu sumia inteiro ao rolar a página | `Header.astro` | Alta — bug real reportado pelo usuário no celular |
| 4 | Menu mobile e botões de CTA/WhatsApp não abriam no Android | `Layout.astro` | Alta — `<meta charset>` vinha depois do script do GTM no `<head>`, causando reparse no mobile e perda dos listeners. Corrigido reordenando o charset pra ser o primeiro elemento |
| 5 | LCP de 14.1s no mobile (PageSpeed) | `index.astro`, `global.css` | Alta — hero (texto principal above-the-fold) ficava com `opacity:0` esperando IntersectionObserver+JS rodar. Resultado após fix: **LCP caiu pra 7.1s** |
| 6 | `fb-capi.php` aceitava `event_name` arbitrário, permitindo qualquer um mandar eventos falsos usando nosso token do Facebook | `public/api/fb-capi.php` | Média — allowlist adicionada |
| 7 | 71 imagens (cases, mentoria, clientes, logos) sem otimização real | `public/cases-img/`, `mentoria/`, etc | Média — recompressão via sharp/mozjpeg, 10.99MB → 6.00MB (~45%), zero perda visual verificada |
| 8 | Imagem `mockup-completo.jpg` em 2933px sendo exibida num container muito menor | `public/brand/` | Baixa — 2.3MB → 168KB |
| 9 | Dependabot desativado no repositório, 27 vulnerabilidades acumuladas sem visibilidade | GitHub repo settings | Média — ativado; 22 delas eram deps de build (nunca chegam ao HTML final) e foram resolvidas via `npm audit fix` + remoção do `@astrojs/vercel` (dependência morta desde o abandono do Vercel) |
| 10 | Único endpoint público que aceita POST (`/acesso/solicitar-codigo`) sem nenhuma barreira anti-bot | `public/acesso/` | Baixa | honeypot adicionado |

## 3. Vulnerabilidades encontradas

Nenhuma secret hardcoded, nenhuma API key exposta no frontend, nenhum `.env` publicamente acessível (verificado: `.htaccess` já bloqueia `.env`, `.git`, `.sql`, `.bak`, `.zip`, `.tar.gz`). Não há `innerHTML`/`dangerouslySetInnerHTML` recebendo conteúdo não confiável — os únicos usos de HTML dinâmico vêm do próprio CMS (conteúdo dos editores, não de input de usuário).

Dependências: 5 alertas do Dependabot seguem abertos (esbuild, sharp, astro — XSS via view-transitions), todos atrás de um bump de major version do Astro (v6→v7). Não apliquei esse bump sem testar o site inteiro página por página — é o tipo de mudança que pode quebrar renderização em produção sem aviso.

## 4. Correções de segurança realizadas

- Allowlist de `event_name` no `fb-capi.php`.
- Honeypot no formulário de `/acesso`.
- Dependabot ativado + `npm audit fix` + remoção de dependência morta.
- Confirmado: CSP, HSTS (`includeSubDomains; preload`), `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy` já estavam corretamente configurados no `.htaccess` — nada quebrado, nada precisou mudar aqui.
- `/admin/` e `/acesso/` bloqueados de indexação em 3 camadas: `robots.txt`, `X-Robots-Tag: noindex, nofollow` via header HTTP, e `Cache-Control: no-store` no admin.

## 5. SEO técnico

Já correto e verificado ao vivo: HTTPS forçado (TLS 1.3), HSTS, canonical absoluta e autorreferencial em cada página (`Layout.astro` gera via `Astro.url.pathname`), sitemap (`@astrojs/sitemap`, `sitemap-index.xml` + `sitemap-0.xml`, só URLs 200/indexáveis), `robots.txt` correto (após fix do item 2.1).

## 6. Redirects de URLs antigas (PHP)

**Não foi possível construir esse mapa com segurança.** Testei os padrões óbvios (`/quem-somos.php`, `/contato.php`, `/blog.php`, `/servicos.php`, `/sobre.php`, `/index.php`, `/blog-detalhe.php`) — todos retornam 404 puro hoje, sem redirect. Mas não tenho como confirmar que esses eram os caminhos reais do site PHP antigo (não há Search Console conectado, não há log de acesso da era anterior, não há sitemap antigo salvo). Criar redirects para URLs adivinhadas seria inventar dados, indo contra a instrução explícita da auditoria.

**Preciso de um destes pra fazer isso direito:** acesso ao Google Search Console (relatório de Coverage/páginas antigas indexadas), um backup do site PHP antigo, ou um export de analytics de antes da migração.

| URL antiga | URL nova | status |
|---|---|---|
| — | — | pendente de dados reais |

## 7. Canonicals

OK, sem duplicidade. `Layout.astro:39` gera canonical absoluta por página; verificado que não há mistura www/não-www nem http/https (site já força um único domínio canônico).

## 8. Sitemap

OK. `astro.config.mjs` já usa `@astrojs/sitemap` com `customPages` explícitas + datas reais por post de blog (via `buildBlogDateMap()`). Confirmado ao vivo: `dist/sitemap-index.xml` só referencia `mclair.com.br` (corrigido nesta sessão, ver item 2.1).

## 9. Robots

OK após fix. `Disallow: /admin/`, `/keystatic/`, `/acesso/`; `Allow: /` pro resto; sitemap referenciado corretamente.

## 10. LLM crawlers

Não bloqueados. `robots.txt` não tem regra específica pra GPTBot/ClaudeBot/PerplexityBot/etc — eles caem na regra geral `User-agent: * / Allow: /`, então rastreiam o site normalmente. Decisão consciente de não bloquear, alinhada com o objetivo de aparecer em respostas de IA.

`llms.txt` já existe (`src/pages/llms.txt.ts`, servido a partir de `src/content/singletons/llms.json`) com descrição factual da empresa, serviços, páginas principais, contato. PageSpeed Insights inclusive tem uma categoria nova "Agentic Browsing" que pontuou 2/3 no teste desta sessão — sinal de que essa camada já está funcionando.

## 11. Renderização pra crawlers

Corrigido (ver item 2.2) — números essenciais (anos de mercado, clientes, inserções, publicações) agora vêm certos no HTML inicial em 3 páginas (home, sobre, cases). Não encontrei outros casos do mesmo padrão no restante do site (busquei por `data-target` em todo `src/`).

## 12. Entity SEO — Mclair

Já implementado no `Layout.astro` via JSON-LD `Organization`/`AdvertisingAgency`/`ProfessionalService`: nome, fundação (2017), fundadora (Kelly Pinheiro), endereço (São Paulo), áreas de atuação, `sameAs` (Instagram, LinkedIn), `knowsAbout` cobrindo os 10 temas centrais (Marketing de Autoridade, Comunicação Estratégica, Assessoria de Imprensa, etc). Definição semântica da entidade já é coerente com o que a auditoria pediu.

## 13. Entity — Kelly Pinheiro

**Não criei a página `/kelly-pinheiro/`.** A auditoria pede explicitamente pra não inventar biografia, cargo, experiência ou dados editoriais — e eu não tenho esse material factual (bio real, cronologia de carreira, veículos onde já foi citada, links de LinkedIn/Exame confirmados). Isso precisa vir de vocês. Assim que eu tiver o conteúdo real, é rápido de montar (a estrutura de schema `Person`/`ProfilePage` já está desenhada nas outras páginas de serviço, é só replicar o padrão).

## 14. Structured data / Schema.org

Já cobre, com dados corretos: `Organization`, `WebSite` (home), `WebPage` com `speakable` (home), `Service` + `BreadcrumbList` (cada página de serviço), `Article`/`BreadcrumbList` (cada case), `BlogPosting` + `BreadcrumbList` + `FAQPage` (posts de blog, condicional a ter `faqItems` reais no CMS). Nenhum schema duplicado, nenhum markup sem conteúdo visível correspondente — verificado lendo o código, não só a saída.

## 15. Blog vs Newsroom

**Catalogação não foi feita.** O blog tem 265+ posts (`src/data/blog.json` + CMS). Classificar cada um em A–F (editorial Mclair / especialista / release de cliente / obsoleto / duplicado / valioso pra SEO) é um trabalho de leitura e julgamento editorial post a post — não é seguro fazer isso automaticamente sem risco real de misclassificar conteúdo que vocês querem manter, ou perder algo com tráfego orgânico. Isso é a peça que mais recomendo fazer com vocês no controle, não eu sozinho de madrugada.

## 16. Arquitetura SILO

A estrutura de URL já bate com o que a auditoria pede: `/`, `/sobre/`, `/servicos/`, `/servicos/[slug]/`, `/cases/`, `/blog/`, `/contato/`, `/mentorias/`. Não criei sub-hubs novos (`/marca-pessoal/`, `/autoridade-digital/` etc) — isso é decisão editorial de expansão de conteúdo, não uma correção técnica.

## 17. Internal linking

Cases já linkam pra serviços relacionados via `about`/`articleSection` no schema. Não adicionei novos links "descritivos" manualmente — tocar em copy existente sem instrução explícita fugia do escopo seguro pra uma sessão sem supervisão.

## 18. AEO / AIO / GEO

Grande parte da infraestrutura já favorece isso: `speakable` schema na home, respostas factuais na descrição da Organization, `llms.txt` com fatos diretos (fundação, fundadora, serviços, contato). Não reescrevi textos de página pra formato "resposta direta primeiro" (H2 pergunta → resposta curta → contexto) — isso é edição de copy existente, fora do que considero seguro fazer sem revisão humana numa sessão overnight.

## 19. E-E-A-T

Blog posts já têm `author` no schema (`post.author || 'Equipe Mclair'`). Sem página de autor dedicada pra Kelly (ver item 13) — mesma pendência.

## 20. Performance

- LCP mobile: 14.1s → **7.1s** (PageSpeed Insights, medido antes/depois).
- TBT: 680ms → 210ms.
- Speed Index: 7.2s → 5.7s.
- Scores PageSpeed mobile: Performance 47→64, SEO 100 (mantido), Acessibilidade 91 (mantido), Best Practices 77.
- Ainda dá pra melhorar: 694 KiB de JS não usado, 110 KiB de CSS não usado, 1.190ms em requisições bloqueando render (GTM carregando várias tags em cascata). Não mexi nisso ainda porque envolve reordenar o carregamento do Google Tag Manager, o que tem risco real pra tracking/analytics — quero seu aval antes.

## 21. Acessibilidade

Score PageSpeed: 91/100, sem regressão. Não fiz auditoria manual completa de teclado/foco/ARIA no modal do WhatsApp além do que o Lighthouse já cobre — ficou fora do escopo executado hoje.

## 22. Arquivos alterados nesta sessão

`public/robots.txt`, `public/api/fb-capi.php`, `public/brand/mockup-completo.jpg`, `public/cases-img/*` (28 arquivos), `public/mentoria/*` (18), `public/clientes/*` (6), `public/paginas/*` (4), `public/logos/*` (7), `public/sobre/*` (1), `public/depoimentos/*` (5), `public/acesso/index.php`, `public/acesso/signup-page.html`, `src/pages/index.astro`, `src/styles/global.css`, `src/components/Header.astro`, `src/pages/sobre.astro`, `src/pages/cases/index.astro`, `package.json`/`package-lock.json` (remoção do `@astrojs/vercel` + patches).

## 23. O que NÃO foi possível validar / fazer

- Mapa de redirect de URLs antigas (falta dado real — ver seção 6).
- Página da Kelly Pinheiro (falta biografia/dados factuais reais).
- Catalogação e separação blog/newsroom (trabalho editorial, não técnico).
- Reescrita de copy pra "answer-first" (AEO) — precisa revisão humana.
- Otimização de carregamento do GTM (risco pro tracking, quero seu OK antes).
- Upgrade major do Astro (resolveria os 5 alertas restantes do Dependabot, mas precisa teste completo página por página antes).
- Google Search Console — não tentei automatizar nada aqui, como pedido.

## Recomendações futuras (prioridade)

1. **Você**: decidir sobre o mapa de redirects — se tiver acesso ao Search Console ou um backup do site antigo, eu faço o resto rápido.
2. **Você**: mandar os dados reais da Kelly Pinheiro (bio, veículos, LinkedIn) pra eu montar a página de entidade.
3. **Juntos**: revisar a catalogação do blog antes de eu separar em `/insights/` vs `/newsroom/`.
4. **Eu, com seu OK**: otimizar carregamento do GTM pra fechar o gap restante de LCP.
5. **Eu, com teste completo antes**: avaliar o upgrade do Astro pra v7.

---

## Checklist final

- [x] Home retorna 200
- [x] HTTPS funciona (TLS 1.3)
- [x] Domínio canônico único
- [x] Sem redirect chains conhecidas
- [x] Canonical correto
- [x] Sitemap válido e correto
- [x] Robots válido e correto
- [x] Sitemap referenciado no robots
- [x] `/admin/` e `/acesso/` não indexáveis (3 camadas)
- [x] Contadores reais no HTML inicial
- [x] Schema JSON-LD válido, sem duplicação
- [x] Organization consistente
- [x] Services estruturados com BreadcrumbList
- [x] 404 real (verificar status code, não soft-404)
- [x] Mobile: menu, sticky header e botões de CTA/WhatsApp funcionando (confirmado em Android real)
- [x] Nenhuma secret exposta
- [x] Security headers revisados (já estavam corretos)
- [x] LCP: melhora real e medida (14.1s → 7.1s)
- [x] Analytics/GTM intactos (nada removido, só planejado reordenar com seu aval)
- [ ] Person Kelly consistente — pendente de dados reais
- [ ] URLs PHP antigas tratadas — pendente de dados reais
- [ ] Blog/newsroom catalogados — pendente de trabalho editorial conjunto
- [ ] Entity graph documentado formalmente — parcialmente coberto pelo schema já existente
- [ ] Plano de topical authority formalizado — hub/silo técnico já existe, conteúdo evergreen listado na auditoria original ainda não foi produzido
