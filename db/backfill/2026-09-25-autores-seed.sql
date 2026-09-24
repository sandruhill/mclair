-- Autores propostos a partir das evidências nos 278 posts (coluna `author`,
-- byline "Por Fulano" no início do texto, assinatura/caixa "Sobre" no fim).
-- Gerado em 24/09/2026 por leitura (SELECT) do banco de produção; NÃO foi
-- executado. Revise a lista antes de rodar.
--
-- Só NOME e SLUG: foto, bio e links ficam para preencher no painel
-- (/acesso/autores, botão "editar"). Nada aqui foi inventado.
-- Empresas que a coluna `author` guardava (Lambda3, BMS, Abrolhos Filmes,
-- Guiando, Fidalgo Leilões, "Mclair Comunicação" etc.) NÃO entram: eram o
-- cliente da pauta, não uma pessoa. Esses posts seguem só com o nome de
-- texto, como hoje.
--
-- Pré-requisito: db/migrations/2026-09-25-blog-autores.sql.
-- Depois: db/backfill/2026-09-25-autores-atribuicao.sql (liga os posts).

INSERT IGNORE INTO cmstest_blog_authors (slug, name) VALUES
  ('claudia-elisa-soares', 'Claudia Elisa Soares'),
  ('elemar-junior', 'Elemar Júnior'),
  ('carolina-fernandes', 'Carolina Fernandes'),
  ('eduardo-camargo', 'Eduardo Camargo'),
  ('rubiana-cruz', 'Rubiana Cruz'),
  ('giovanni-bassi', 'Giovanni Bassi'),
  ('kelly-pinheiro', 'Kelly Pinheiro'),
  ('patricia-kost', 'Patrícia Kost'),
  ('carlos-pisani', 'Carlos Pisani'),
  ('marcus-garcia', 'Marcus Garcia'),
  ('rafael-nanis', 'Rafael Nanis'),
  ('victor-hugo-germano', 'Victor Hugo Germano'),
  ('ahirton-lopes', 'Ahirton Lopes'),
  ('diego-nogare', 'Diego Nogare'),
  ('rafael-lage', 'Rafael Lage'),
  ('alexandre-magno', 'Alexandre Magno'),
  ('andre-sobral', 'André Sobral'),
  ('bruna-coelho', 'Bruna Coelho'),
  ('cristiano-zanetta', 'Cristiano Zanetta'),
  ('davi-filgueiras', 'Davi Filgueiras'),
  ('elaine-couri', 'Elaine Couri'),
  ('fernando-neiva-paiva', 'Fernando Neiva Paiva'),
  ('guilherme-tollmeiner', 'Guilherme Tollmeiner'),
  ('gustavo-de-paula', 'Gustavo de Paula'),
  ('gustavo-de-ribeiro-silva', 'Gustavo de Ribeiro Silva'),
  ('leticia-kawakami', 'Leticia Kawakami'),
  ('lucio-agberto', 'Lúcio Agberto'),
  ('marival-silva', 'Marival Silva'),
  ('matheus-vidal', 'Matheus Vidal'),
  ('rodrigo-schittini', 'Rodrigo Schittini'),
  ('rogerio-carvalho', 'Rogério Carvalho'),
  ('thiago-padilha', 'Thiago Padilha'),
  ('vanda-lohn', 'Vanda Lohn'),
  ('victor-cavalcante', 'Victor Cavalcante');

-- ---------------------------------------------------------------------------
-- OPCIONAL: cargo/função tirado da assinatura que o próprio texto traz
-- ("Fulano é CEO da X"). São textos de 2020 a 2026 e podem estar
-- desatualizados; confira um a um antes de descomentar.
-- ---------------------------------------------------------------------------
-- UPDATE cmstest_blog_authors SET job_title = 'especialista em ESG e transformação de negócios e líderes e conselheira em companhias abertas e familiares' WHERE slug = 'claudia-elisa-soares' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CEO da Cubo Comunicação, palestrante, mentora do curso Marketing para Empreendedores e especialista em Marketing & Comunicação com mais de 20 anos de atuação' WHERE slug = 'carolina-fernandes' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CMO da Guiando' WHERE slug = 'eduardo-camargo' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'formada em Psicologia com MBA em Gestão Estratégica de Pessoas e Coach pela SBC' WHERE slug = 'rubiana-cruz' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'Chief Software Architect (CSA) da Lambda3' WHERE slug = 'giovanni-bassi' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'jornalista, com especialização em Marketing Estratégico - realizada em Lisboa (PT) - e mais de 20 anos de experiência na área' WHERE slug = 'kelly-pinheiro' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'People Business Partner Lambda3' WHERE slug = 'patricia-kost' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CEO e fundador da ArcH, consultoria especializada em Arquitetura de Sistemas e Soluções de Software' WHERE slug = 'carlos-pisani' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CEO da Lambda3' WHERE slug = 'victor-hugo-germano' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'Chief Data Officer (CDO) da Lambda3 e professor na FIAP, onde ministra aulas nos cursos de MBA em Artificial Intelligence & Machine Learning, Digital Data Marketing, Cyber Security e Health ' WHERE slug = 'ahirton-lopes' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CEO da Trilha 91' WHERE slug = 'rafael-lage' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'CEO da Emergee e foi o primeiro Certified Scrum Trainer da América Latina (2008), além de ter liderado as primeiras iniciativas com Agile no Brasil' WHERE slug = 'alexandre-magno' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'Head de Transformação Digital da Guiando' WHERE slug = 'davi-filgueiras' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'formada em Psicologia com pós-graduação em psicanálise multidisciplinar e em gestão estratégica de pessoas' WHERE slug = 'elaine-couri' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'consultor de Negócios e Tecnologia da EximiaCo' WHERE slug = 'fernando-neiva-paiva' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'sócio-fundador da Vissla' WHERE slug = 'guilherme-tollmeiner' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'consultor jurídico da EximiaCo, com mais de 10 anos de experiência na área, ajudando empresas de todos os portes a ajustar e acelerar seus negócios de acordo com as demandas legais' WHERE slug = 'gustavo-de-paula' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'especialista em life & business coaching, com formação em Comunicação Social pela London University e ampla experiência em International Business Development' WHERE slug = 'marival-silva' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'formado em Análise de Desenvolvimento de Sistemas pela Universidade de Vila Velha e Engenheiro de Dados da Lambda3' WHERE slug = 'matheus-vidal' AND job_title IS NULL;
-- UPDATE cmstest_blog_authors SET job_title = 'Owner da Lambda3' WHERE slug = 'victor-cavalcante' AND job_title IS NULL;
