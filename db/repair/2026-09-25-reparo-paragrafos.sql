-- Reparo dos posts gravados pelo editor visual com parágrafos separados por
-- UMA quebra de linha ("a\nb"). Para o markdown isso é a mesma frase, então o
-- post inteiro saía num único <p> no site. O editor foi corrigido; este
-- script conserta o que já está no banco.
--
-- A regra é a mesma de src/utils/markdown.ts (build), de cmsNormalizarParagrafos
-- em public/acesso/db.php (ao salvar) e foi conferida linha a linha contra o
-- resultado do JS nos 13 posts afetados em 24/09/2026 (SELECT, sem gravar):
-- duas linhas de texto comum coladas viram dois parágrafos. Não mexe em item
-- de lista, linha de tabela, citação, título, HTML, quebra forçada (dois
-- espaços ou barra no fim da linha) nem em linha indentada. Posts com bloco
-- de código (```) ficam de fora por segurança (nenhum dos afetados tem).
--
-- Observação: o site já renderiza certo mesmo SEM este reparo, porque o build
-- aplica a mesma normalização antes do marked. O reparo deixa o banco limpo
-- para que o editor abra o texto com os parágrafos certos.
--
-- Como rodar (nesta ordem, uma etapa por vez):
--   1. Backup     (bloco 1)
--   2. Prévia     (bloco 2)  -> conferir a lista de slugs; são os posts que vão mudar
--   3. Reparo     (bloco 3)
--   4. Conferir   (bloco 4)  -> deve devolver 0 linhas
--   5. touch /home/u229450165/mclair-build/queue/pending  (republica o site)
--
-- Reverter, se precisar:
--   UPDATE cmstest_blog_posts p JOIN cmstest_blog_posts_bak_20260925 b USING (slug)
--   SET p.content_md = b.content_md;

-- ---------------------------------------------------------------------------
-- 1. BACKUP da tabela inteira (estrutura + dados) antes de qualquer UPDATE.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cmstest_blog_posts_bak_20260925 LIKE cmstest_blog_posts;
INSERT IGNORE INTO cmstest_blog_posts_bak_20260925 SELECT * FROM cmstest_blog_posts;
SELECT COUNT(*) AS linhas_no_backup FROM cmstest_blog_posts_bak_20260925;

-- ---------------------------------------------------------------------------
-- 2. PRÉVIA: quais posts serão alterados. Condição = editados pelo editor
--    visual (desde 23/09/2026) e com quebra simples entre linhas de texto.
--    Em 24/09/2026 eram 13 posts (lista no relatório da entrega).
-- ---------------------------------------------------------------------------
SELECT slug, updated_at, LENGTH(content_md) AS bytes
FROM cmstest_blog_posts
WHERE updated_at >= '2026-09-23'
  AND content_md REGEXP '[^\r\n]\r?\n[^\r\n]'
  AND content_md NOT LIKE '%```%'
ORDER BY updated_at;

-- ---------------------------------------------------------------------------
-- 3. REPARO. Três passos dentro da mesma expressão:
--    a) CRLF -> LF (o navegador manda \r\n no POST do formulário);
--    b) linha só de espaços/tabs vira linha vazia;
--    c) linha de texto comum seguida de outra linha de texto comum ganha uma
--       linha em branco entre as duas.
-- ---------------------------------------------------------------------------
UPDATE cmstest_blog_posts
SET content_md = REGEXP_REPLACE(
  REGEXP_REPLACE(
    REPLACE(content_md, CHAR(13,10), CHAR(10)),
    '(?m)^[ \\t]+$',
    ''
  ),
  '(?m)^(?![ \\t]*$)(?![-*+] |\\d+[.)] |\\||>|#|<|(?:=+|-+)[ \\t]*$)(.*)(?<!  )(?<!\\\\)\\n(?![ \\t]*$)(?![-*+] |\\d+[.)] |\\||>|#|<|(?:=+|-+)[ \\t]*$|[ \\t])',
  CONCAT('\\1', CHAR(10,10))
)
WHERE updated_at >= '2026-09-23'
  AND content_md REGEXP '[^\r\n]\r?\n[^\r\n]'
  AND content_md NOT LIKE '%```%';

-- ---------------------------------------------------------------------------
-- 4. CONFERÊNCIA: nenhum post recente pode ter sobrado com quebra simples
--    entre duas linhas de texto comum (lista, tabela etc. não contam).
-- ---------------------------------------------------------------------------
SELECT slug
FROM cmstest_blog_posts
WHERE updated_at >= '2026-09-23'
  AND content_md NOT LIKE '%```%'
  AND content_md REGEXP '(?m)^(?![ \\t]*$)(?![-*+] |\\d+[.)] |\\||>|#|<|(?:=+|-+)[ \\t]*$).*(?<!  )(?<!\\\\)\\n(?![ \\t]*$)(?![-*+] |\\d+[.)] |\\||>|#|<|(?:=+|-+)[ \\t]*$|[ \\t])';
