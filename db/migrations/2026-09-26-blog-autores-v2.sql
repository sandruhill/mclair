-- Autores, segunda etapa: tipo (pessoa ou organização), tratamento
-- gramatical (autor/autora) e mais de um autor por post.
--
-- Pré-requisito: db/migrations/2026-09-25-blog-autores.sql já aplicada.
-- Rodar UMA vez:
--   mysql -h HOST -u USUARIO -p BANCO < db/migrations/2026-09-26-blog-autores-v2.sql
--
-- Pode rodar antes ou depois do deploy do código: painel e build detectam a
-- ausência das colunas/tabela e seguem no modo de um autor por post.
-- Idempotente (IF NOT EXISTS e INSERT IGNORE), pode rodar de novo sem efeito.

-- Tipo do autor: pessoa (Person no JSON-LD) ou organização (a própria Mclair,
-- que vira referência à Organization já declarada em todas as páginas).
ALTER TABLE cmstest_blog_authors
  ADD COLUMN IF NOT EXISTS kind ENUM('pessoa','organizacao') NOT NULL DEFAULT 'pessoa' AFTER name;

-- Tratamento gramatical: 'f' => "Autora no blog Mclair", 'm' => "Autor no
-- blog Mclair". NULL => o site usa formas neutras ("Perfil no blog Mclair",
-- "Sobre Fulano") em vez de chutar pelo nome.
ALTER TABLE cmstest_blog_authors
  ADD COLUMN IF NOT EXISTS gender ENUM('m','f') NULL AFTER kind;

-- Vários autores por post, com ordem. cmstest_blog_posts.blog_author_id
-- continua existindo e sempre igual ao PRIMEIRO autor (position 0): é o que
-- o painel e o build usam quando esta tabela ainda não existe, e evita
-- reescrever as consultas de contagem que já estão em produção. O painel
-- mantém os dois em sincronia a cada salvamento.
CREATE TABLE IF NOT EXISTS cmstest_blog_post_authors (
  post_slug  VARCHAR(255) NOT NULL,
  author_id  INT UNSIGNED NOT NULL,
  position   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (post_slug, author_id),
  KEY idx_post_authors_author (author_id, post_slug),
  CONSTRAINT fk_post_authors_post   FOREIGN KEY (post_slug) REFERENCES cmstest_blog_posts (slug)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_post_authors_author FOREIGN KEY (author_id) REFERENCES cmstest_blog_authors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leva para a tabela nova o que já está ligado pela coluna antiga.
INSERT IGNORE INTO cmstest_blog_post_authors (post_slug, author_id, position)
SELECT slug, blog_author_id, 0 FROM cmstest_blog_posts WHERE blog_author_id IS NOT NULL;
