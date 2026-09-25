-- post_date passa de DATE para DATETIME (hora de Brasília, sem fuso no banco).
--
-- Rodar UMA vez:
--   mysql -h HOST -u USUARIO -p BANCO < db/migrations/2026-09-26-blog-post-date-datetime.sql
--
-- Idempotente: só altera se a coluna ainda for DATE. Os posts existentes
-- ficam no MESMO dia, com a hora 09:00 (um horário de publicação plausível,
-- em vez de meia-noite). Painel e build funcionam com a coluna nos dois
-- tipos: antes da migração só o dia é gravado e exibido.

SET @tipo = (SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cmstest_blog_posts' AND COLUMN_NAME = 'post_date');

SET @sql = IF(@tipo = 'date',
  'ALTER TABLE cmstest_blog_posts MODIFY post_date DATETIME NULL',
  'SELECT ''post_date já é DATETIME, nada a fazer'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(@tipo = 'date',
  'UPDATE cmstest_blog_posts SET post_date = post_date + INTERVAL 9 HOUR WHERE post_date IS NOT NULL',
  'SELECT ''horas já definidas'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Conferência: tipo da coluna e amostra.
SELECT COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cmstest_blog_posts' AND COLUMN_NAME = 'post_date';
SELECT slug, post_date FROM cmstest_blog_posts ORDER BY post_date DESC LIMIT 3;
