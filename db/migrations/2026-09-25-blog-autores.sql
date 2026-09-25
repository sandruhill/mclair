-- Autores do blog: tabela própria + referência no post.
--
-- Rodar UMA vez no banco de produção (u229450165_acesso), por exemplo:
--   mysql -h HOST -u USUARIO -p BANCO < db/migrations/2026-09-25-blog-autores.sql
--
-- Pode rodar antes ou depois do deploy do código: o painel (/acesso/autores,
-- editor de post) e o build do site detectam a ausência da tabela/coluna e
-- seguem funcionando sem a assinatura até a migração existir.
--
-- Idempotente: IF NOT EXISTS em tudo, pode rodar de novo sem efeito.

CREATE TABLE IF NOT EXISTS cmstest_blog_authors (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(120) NOT NULL,                 -- URL do perfil: /blog/autores/<slug>/ (não muda depois de criado)
  name          VARCHAR(190) NOT NULL,
  job_title     VARCHAR(190) NULL,                     -- cargo/função (schema Person.jobTitle)
  photo_url     VARCHAR(255) NULL,                     -- /uploads/xxxx.jpg (mesmo upload.php das capas)
  bio_short     VARCHAR(500) NULL,                     -- assinatura embaixo do post
  bio_long      TEXT NULL,                             -- página de perfil
  topics        LONGTEXT NULL CHECK (json_valid(topics)),        -- ["ESG","Governança"] (schema Person.knowsAbout)
  social_links  LONGTEXT NULL CHECK (json_valid(social_links)),  -- [{"type":"linkedin","url":"https://..."}] (schema Person.sameAs)
  created_by    INT NULL,
  created_at    TIMESTAMP NULL DEFAULT current_timestamp(),
  updated_at    TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY uq_blog_authors_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- author_id (FK para cmstest_users) fica como está: nunca foi usado (0 linhas)
-- e continua NULL. A nova coluna aponta para a tabela de autores acima. A
-- coluna de texto `author` continua sendo o nome exibido nas listas e no JSON
-- do site, e passa a ser preenchida automaticamente com o nome do autor
-- escolhido.
ALTER TABLE cmstest_blog_posts
  ADD COLUMN IF NOT EXISTS blog_author_id INT UNSIGNED NULL AFTER author_id;

ALTER TABLE cmstest_blog_posts
  ADD INDEX IF NOT EXISTS idx_blog_author (blog_author_id);

ALTER TABLE cmstest_blog_posts
  ADD FOREIGN KEY IF NOT EXISTS fk_blog_posts_blog_author (blog_author_id) REFERENCES cmstest_blog_authors (id) ON DELETE SET NULL;
