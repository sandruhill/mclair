-- Autores, segunda rodada de dados. NÃO foi executado; revise e rode na ordem.
-- Pré-requisitos: as duas migrações (2026-09-25 e 2026-09-26) e o seed de
-- 2026-09-25 (34 autores, bloco A já aplicado em produção em 25/09/2026).
--
-- Blocos:
--   1. Bloco B revisado, post a post ("quem escreveu, assina")
--   2. Co-autoria (bloco C): Denis Zanini Lima + Kelly Pinheiro
--   3. Autor-organização "Mclair Comunicação" e os posts de pauta própria
--   4. Tratamento gramatical (autor/autora) só onde o texto dá a evidência
--   5. Sincroniza a tabela de vários autores e os nomes de exibição
--   6. Conferências
-- Depois de tudo: touch /home/u229450165/mclair-build/queue/pending

-- ===========================================================================
-- 1. BLOCO B, revisado post a post. Critério: só assina quem escreveu
--    (abre com "Por Fulano", fala em primeira pessoa, ou traz a caixa
--    "Sobre Fulano" como assinatura de artigo de opinião). Matéria que só
--    CITA a pessoa ("segundo Fulano", "diz especialista") fica com a Mclair.
-- ===========================================================================

-- quando-a-facilidade-de-usar-ia-vira-um-problema-de-governanca
--   ATRIBUIR a Carlos Pisani. Evidência: 1ª linha "*Por Carlos Pisani*";
--   primeira pessoa ("Ao longo de mais de três décadas de carreira na área,
--   observo que..."); fecha com "Sobre Carlos Pisani: ... é CEO e fundador da ArcH".
-- por-que-projetos-de-ia-agentica-comecam-a-fracassar-antes-mesmo-de-chegar-a
--   ATRIBUIR a Carlos Pisani. Evidência: 1ª linha "*Por Carlos Pisani*";
--   artigo de opinião; fecha com "Foto/Divulgação | Carlos Pisani" e "Sobre Carlos Pisani".
UPDATE cmstest_blog_posts
SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'carlos-pisani'), author = 'Carlos Pisani'
WHERE slug IN (
  'quando-a-facilidade-de-usar-ia-vira-um-problema-de-governanca',
  'por-que-projetos-de-ia-agentica-comecam-a-fracassar-antes-mesmo-de-chegar-a'
);

-- empresas-avancam-com-agentes-de-ia-sem-estrutura-de-governanca-alerta
--   NÃO ATRIBUIR (fica Mclair). Evidência: abre "Empresas têm colocado agentes...
--   A análise é de Carlos Pisani, CEO da ArcH IA"; terceira pessoa o tempo todo
--   ("afirma", "explica", "Para Pisani"); a caixa "Sobre Carlos Pisani" é o
--   boilerplate de release, não assinatura.

-- do-zero-a-adocao-tech-o-que-o-ciclo-completo-de-um-produto-ensina-sobre
--   ATRIBUIR a Marcus Garcia. Evidência: 1ª linha "*Por Marcus Garcia*";
--   ensaio na voz do autor; fecha com "Foto/Divulgação: Marcus Garcia" e "Sobre Marcus Garcia".
-- o-profissional-de-tecnologia-que-o-mercado-procura-vai-alem-do-conhecimento
--   ATRIBUIR a Marcus Garcia. Evidência: 1ª linha "*Por Marcus Garcia*";
--   primeira pessoa ("Venho de uma trajetória técnica...", "Publiquei livros,
--   escrevi artigos", "Tenho visto profissionais..."); fecha com "Sobre Marcus Garcia".
UPDATE cmstest_blog_posts
SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'marcus-garcia'), author = 'Marcus Garcia'
WHERE slug IN (
  'do-zero-a-adocao-tech-o-que-o-ciclo-completo-de-um-produto-ensina-sobre',
  'o-profissional-de-tecnologia-que-o-mercado-procura-vai-alem-do-conhecimento'
);

-- a-pressa-para-adotar-ia-expoe-um-problema-anterior-a-tecnologia
--   NÃO ATRIBUIR (fica Mclair). Evidência: sem "Por"; terceira pessoa
--   ("Segundo Marcus Garcia, diretor...", "Na avaliação de Garcia", "O ponto
--   levantado por Marcus"); matéria que cita o especialista.
-- gargalo-da-ia-nas-empresas-vai-alem-da-tecnologia-diz-especialista
--   NÃO ATRIBUIR (fica Mclair). Evidência: título "diz especialista"; "A
--   avaliação é de Marcus Garcia"; aspas e "explica", "afirma".

-- o-que-assistir-depois-de-o-dilema-das-redes (coluna author = "Lambda3")
--   ATRIBUIR a Diego Nogare. Evidência: primeira pessoa ("a meu ver a obra
--   foca..."); fecha com "Sobre Diego Nogare / Diego Nogare é CDO na Lambda3".
--   A linha final "Autor: Lambda3" era o campo do CMS antigo (o cliente da pauta).
UPDATE cmstest_blog_posts
SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'diego-nogare'), author = 'Diego Nogare'
WHERE slug = 'o-que-assistir-depois-de-o-dilema-das-redes';

-- ===========================================================================
-- 2. CO-AUTORIA (bloco C): "ambos". Denis Zanini Lima entra só com o nome.
--    Evidência nos dois textos: "Denis Zanini Lima é diretor da Ynusitado
--    Marketing Digital Intelligence e Kelly Pinheiro é jornalista e fundadora
--    e sócio-diretora da Mclair Comunicação".
-- ===========================================================================
INSERT IGNORE INTO cmstest_blog_authors (slug, name, kind, gender) VALUES ('denis-zanini-lima', 'Denis Zanini Lima', 'pessoa', 'm');

UPDATE cmstest_blog_posts
SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'denis-zanini-lima'),
    author = 'Denis Zanini Lima e Kelly Pinheiro'
WHERE slug IN ('nao-caia-na-armadilha-da-massificacao-da-inteligencia-artificial', 'ted-lasso-por-que-a-serie-e-um-case-de-branded-content');

INSERT IGNORE INTO cmstest_blog_post_authors (post_slug, author_id, position)
SELECT p.slug, a.id, 0 FROM cmstest_blog_posts p JOIN cmstest_blog_authors a ON a.slug = 'denis-zanini-lima'
WHERE p.slug IN ('nao-caia-na-armadilha-da-massificacao-da-inteligencia-artificial', 'ted-lasso-por-que-a-serie-e-um-case-de-branded-content');
INSERT IGNORE INTO cmstest_blog_post_authors (post_slug, author_id, position)
SELECT p.slug, a.id, 1 FROM cmstest_blog_posts p JOIN cmstest_blog_authors a ON a.slug = 'kelly-pinheiro'
WHERE p.slug IN ('nao-caia-na-armadilha-da-massificacao-da-inteligencia-artificial', 'ted-lasso-por-que-a-serie-e-um-case-de-branded-content');

-- ===========================================================================
-- 3. AUTOR-ORGANIZAÇÃO "Mclair Comunicação". No JSON-LD vira a Organization
--    já declarada (@id https://mclair.com.br/#organization). Descrição e
--    links são os mesmos do schema da Organization em Layout.astro; a foto é
--    o ícone da marca que o painel já usa.
-- ===========================================================================
INSERT IGNORE INTO cmstest_blog_authors (slug, name, kind, gender, job_title, photo_url, bio_short, bio_long, topics, social_links)
VALUES (
  'mclair-comunicacao', 'Mclair Comunicação', 'organizacao', NULL,
  'Agência de comunicação estratégica e marketing de autoridade',
  '/logos/logo-icone-M.png',
  'Agência de Marketing especializada em Comunicação Estratégica e Marketing de Autoridade. Posicionamos marcas como referência reconhecida no mercado.',
  'Agência de Marketing especializada em Comunicação Estratégica e Marketing de Autoridade, em São Paulo desde 2017. Posicionamos marcas como referência reconhecida no mercado, com assessoria de imprensa, branding e conteúdo.',
  '["Comunicação estratégica","Assessoria de imprensa","Marketing de autoridade","Branding"]',
  '[{"type":"instagram","url":"https://instagram.com/mclair.com.br"},{"type":"linkedin","url":"https://linkedin.com/company/mclair-comunicacao"},{"type":"site","url":"https://mclair.com.br"}]'
);

-- Os posts de pauta própria (coluna author = "Mclair Comunicação" e ainda sem
-- autor cadastrado). Roda DEPOIS do bloco 1, para os quatro artigos
-- assinados por Pisani e Garcia já terem saído dessa lista.
UPDATE cmstest_blog_posts
SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'mclair-comunicacao')
WHERE author = 'Mclair Comunicação' AND blog_author_id IS NULL;

-- ===========================================================================
-- 4. TRATAMENTO GRAMATICAL. Só com evidência no próprio texto (assinatura ou
--    caixa "Sobre"). A evidência está no comentário de cada linha. Quem não
--    tem evidência fica NULL (site usa forma neutra) e está na lista do fim,
--    para o usuário definir no painel.
-- ===========================================================================
-- "é Conselheira de Administração", "conselheira em companhias abertas"
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'claudia-elisa-soares' AND gender IS NULL;
-- "palestrante, mentora do curso Marketing para Empreendedores"
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'carolina-fernandes' AND gender IS NULL;
-- "é Engenheiro da Computação e CMO da Guiando", "é sócio e CMO da Guiando"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'eduardo-camargo' AND gender IS NULL;
-- "é formada em Psicologia", "Atua como Consultora"
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'rubiana-cruz' AND gender IS NULL;
-- "é fundador e Chief Software Architect da Lambda3", "consultor especialista"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'giovanni-bassi' AND gender IS NULL;
-- "É fundadora e sócio-diretora da Mclair" (co-autoria) e "fundadora e diretora-executiva da empresa" (mclair-anuncia-spin-off)
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'kelly-pinheiro' AND gender IS NULL;
-- "é psicóloga e Líder da Área de Pessoas da Lambda3"
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'patricia-kost' AND gender IS NULL;
-- "é CEO e fundador da ArcH"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'carlos-pisani' AND gender IS NULL;
-- "Executivo e especialista em inovação tecnológica"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'marcus-garcia' AND gender IS NULL;
-- "Segundo o sócio-fundador e CEO da Lambda3, Victor Hugo Germano"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'victor-hugo-germano' AND gender IS NULL;
-- "é Chief Data Officer (CDO) da Lambda3 e professor na FIAP"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'ahirton-lopes' AND gender IS NULL;
-- "Também é professor em programas de pós-graduação", "Foi nomeado como Microsoft MVP"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'diego-nogare' AND gender IS NULL;
-- "Segundo o sócio-fundador da Trilha 91, Rafael Lage"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'rafael-lage' AND gender IS NULL;
-- "foi o primeiro Certified Scrum Trainer da América Latina", "Segundo o fundador e CEO da Emergee"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'alexandre-magno' AND gender IS NULL;
-- "é formado em Direito", "É produtor da Abrolhos Filmes"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'andre-sobral' AND gender IS NULL;
-- "é formada em Psicologia", "Atua como Consultora"
UPDATE cmstest_blog_authors SET gender = 'f' WHERE slug = 'elaine-couri' AND gender IS NULL;
-- "é consultor de Negócios e Tecnologia da EximiaCo"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'fernando-neiva-paiva' AND gender IS NULL;
-- "é sócio-fundador da Vissla"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'guilherme-tollmeiner' AND gender IS NULL;
-- "Por Gustavo de Paula, consultor jurídico EximiaCo"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'gustavo-de-paula' AND gender IS NULL;
-- "é formado em Análise de Desenvolvimento de Sistemas", "Engenheiro de Dados"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'matheus-vidal' AND gender IS NULL;
-- "é formado em Administração", "É CEO e fundador da Guiando"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'rodrigo-schittini' AND gender IS NULL;
-- "é formado em Gestão de TI"
UPDATE cmstest_blog_authors SET gender = 'm' WHERE slug = 'thiago-padilha' AND gender IS NULL;

-- SEM EVIDÊNCIA NO TEXTO (ficam NULL; definir no painel, em Posts do blog >
-- Autores > editar > "Tratamento"): Elemar Júnior ("CEO da ExímiaCo", só
-- cargo neutro), Rafael Nanis, Rafael Lage já resolvido acima, Bruna Coelho,
-- Cristiano Zanetta ("o Batman do Brasil" é apelido, não trato como
-- evidência), Davi Filgueiras ("Head", neutro), Gustavo de Ribeiro Silva,
-- Leticia Kawakami ("Digital Business Executive", neutro), Lúcio Agberto,
-- Marival Silva ("especialista", "formação", neutros), Rogério Carvalho
-- ("CEO", neutro), Vanda Lohn, Victor Cavalcante ("Owner", neutro).

-- ===========================================================================
-- 5. SINCRONIZAÇÃO: todo post com blog_author_id entra na tabela de vários
--    autores (idempotente), e o nome de exibição acompanha o cadastro.
-- ===========================================================================
INSERT IGNORE INTO cmstest_blog_post_authors (post_slug, author_id, position)
SELECT slug, blog_author_id, 0 FROM cmstest_blog_posts WHERE blog_author_id IS NOT NULL;

-- ===========================================================================
-- 6. CONFERÊNCIAS (esperado: 0 posts "Mclair Comunicação" soltos; 2 posts com
--    2 autores; Pisani 2, Garcia 2, Nogare 2; Denis 2; Mclair 30)
-- ===========================================================================
SELECT COUNT(*) AS mclair_sem_autor FROM cmstest_blog_posts WHERE author = 'Mclair Comunicação' AND blog_author_id IS NULL;
SELECT post_slug, COUNT(*) n FROM cmstest_blog_post_authors GROUP BY post_slug HAVING n > 1;
SELECT a.slug, COUNT(pa.post_slug) posts FROM cmstest_blog_authors a LEFT JOIN cmstest_blog_post_authors pa ON pa.author_id = a.id
WHERE a.slug IN ('carlos-pisani','marcus-garcia','diego-nogare','denis-zanini-lima','kelly-pinheiro','mclair-comunicacao') GROUP BY a.slug;
