# Checklist Search Console, Mclair

Sem acesso à conta do Google Search Console nesta sessão. Este arquivo é um roteiro pronto para quem tiver acesso rodar, nenhum dado de impressão/clique/posição foi inventado.

## 1. Inspecionar e solicitar indexação (URLs prioritárias, 27 no total)

Home, institucionais, todos os 6 serviços e os 13 cases, usar a ferramenta de Inspeção de URL do GSC em cada uma, uma por uma, e clicar em "Solicitar indexação" se aparecer como "URL não está no Google" ou "Rastreada, sem indexação no momento":

- https://mclair.com.br/
- https://mclair.com.br/sobre/
- https://mclair.com.br/servicos/
- https://mclair.com.br/servicos/marketing-de-autoridade/
- https://mclair.com.br/servicos/assessoria-de-imprensa/
- https://mclair.com.br/servicos/branding-estrategico/
- https://mclair.com.br/servicos/marketing-digital/
- https://mclair.com.br/servicos/consultoria-em-comunicacao/
- https://mclair.com.br/servicos/mentorias-exclusivas/
- https://mclair.com.br/cases/
- https://mclair.com.br/cases/alexandre-magno/
- https://mclair.com.br/cases/bms-abimaq/
- https://mclair.com.br/cases/claudia-elisa/
- https://mclair.com.br/cases/diego-nogare/
- https://mclair.com.br/cases/elemar-jr/
- https://mclair.com.br/cases/fidalgo/
- https://mclair.com.br/cases/globo-leiloes/
- https://mclair.com.br/cases/insight-rh/
- https://mclair.com.br/cases/lambda3/
- https://mclair.com.br/cases/nexinvoice/
- https://mclair.com.br/cases/silene-chiconini/
- https://mclair.com.br/cases/vip-leiloes/
- https://mclair.com.br/cases/zbra/
- https://mclair.com.br/clientes/
- https://mclair.com.br/contato/
- https://mclair.com.br/mentorias/
- https://mclair.com.br/marketing-para-leiloeiros/

## 2. Reenviar sitemap

`https://mclair.com.br/sitemap-index.xml`, reenviar em Sitemaps depois do deploy desta rodada (título/canonical/trailing-slash/schema mudaram em várias URLs, vale forçar um recrawl).

## 3. URLs antigas que precisam sumir do índice (era pré-Astro)

Essas retornam 301 desde a primeira rodada de auditoria, se ainda aparecerem no relatório de Páginas do GSC como indexadas ou com erro, usar a ferramenta de Remoção (remoção temporária, 6 meses) enquanto o Google reprocessa o redirect:

- `/quem-somos.php`
- `/contato.php`
- `/servicos.php`
- `/blog.php`
- `/index.php`
- `/blog-detalhe.php?id=N` (34 ids mapeados, ver `AUDITORIA-MCLAIR.md` seção de redirects da 1ª rodada)

## 4. Verificar no relatório de Cobertura/Páginas

- Quantas URLs aparecem como "Rastreada, atualmente sem indexação", se o número for alto, é sinal de conteúdo fino ou duplicado que o Google decidiu não indexar (cruzar com `AUDITORIA-CRAWLER.csv`, coluna WordCount, e `CANIBALIZACAO-SEO.csv`).
- Erros 404 reportados pelo Google que NÃO aparecem em `LINKS-QUEBRADOS.csv`, indicam backlinks externos antigos apontando pra URLs que nunca existiram nesta versão do site (candidatos a redirect novo).
- Página "Redirecionamento de página", confirmar que caiu a zero depois do deploy da barra final (antes desta rodada, todo link interno do menu/cards pagava um 301, o que o GSC provavelmente já vinha registrando).

## 5. Core Web Vitals (relatório de Experiência)

Comparar com os números medidos localmente via Lighthouse nesta rodada (ver seção de Performance em `AUDITORIA-MCLAIR.md`), o CWV do GSC usa dados reais de campo (CrUX, usuários de verdade), o Lighthouse daqui é laboratório. Divergência grande entre os dois é normal nas primeiras semanas após uma mudança; usar o CWV do GSC como fonte de verdade depois de ~28 dias de dados acumulados.

## 6. Palavras-chave (relatório de Desempenho)

Sem dado real disponível nesta sessão. Ao acessar, cruzar as queries reais com os clusters de `TOPICAL-MAP-MCLAIR.md` para achar lacunas (queries com impressão alta e CTR baixo, ou posição 8-15 que um ajuste de title/meta pode empurrar pra primeira página).
