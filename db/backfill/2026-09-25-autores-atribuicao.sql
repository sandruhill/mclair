-- Liga cada post ao autor cadastrado pelo seed. NÃO foi executado.
-- Pré-requisitos: migração + 2026-09-25-autores-seed.sql.
--
-- Bloco A: posts cuja coluna `author` já traz o nome da pessoa (casamento
--          exato, sem adivinhação). O nome de texto não muda.
-- Bloco B: posts em que a coluna `author` diz "Mclair Comunicação" mas o
--          texto abre com "Por Fulano" (artigos assinados por convidados).
--          Aqui o nome de texto também passa a ser o da pessoa, porque é o
--          que aparece em "por ..." no topo do artigo.
-- Bloco C: casos para decidir à mão (co-autoria), não aplicados.
--
-- Conferência antes: SELECT COUNT(*) FROM cmstest_blog_posts WHERE blog_author_id IS NOT NULL;
-- Depois de rodar: touch /home/u229450165/mclair-build/queue/pending

-- ---- Bloco A ----
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'claudia-elisa-soares') WHERE author = 'Claudia Elisa Soares' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'elemar-junior') WHERE author = 'Elemar Júnior' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'carolina-fernandes') WHERE author = 'Carolina Fernandes' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'eduardo-camargo') WHERE author = 'Eduardo Camargo' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'rubiana-cruz') WHERE author = 'Rubiana Cruz' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'giovanni-bassi') WHERE author = 'Giovanni Bassi' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'kelly-pinheiro') WHERE author = 'Kelly Pinheiro' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'patricia-kost') WHERE author = 'Patrícia Kost' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'rafael-nanis') WHERE author = 'Rafael Nanis' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'victor-hugo-germano') WHERE author = 'Victor Hugo Germano' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'ahirton-lopes') WHERE author = 'Ahirton Lopes' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'diego-nogare') WHERE author = 'Diego Nogare' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'rafael-lage') WHERE author = 'Rafael Lage' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'alexandre-magno') WHERE author = 'Alexandre Magno' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'andre-sobral') WHERE author = 'André Sobral' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'bruna-coelho') WHERE author = 'Bruna Coelho' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'cristiano-zanetta') WHERE author = 'Cristiano Zanetta' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'davi-filgueiras') WHERE author = 'Davi Filgueiras' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'elaine-couri') WHERE author = 'Elaine Couri' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'fernando-neiva-paiva') WHERE author = 'Fernando Neiva Paiva' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'guilherme-tollmeiner') WHERE author = 'Guilherme Tollmeiner' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'gustavo-de-paula') WHERE author = 'Gustavo de Paula' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'gustavo-de-ribeiro-silva') WHERE author = 'Gustavo de Ribeiro Silva' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'leticia-kawakami') WHERE author = 'Leticia Kawakami' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'lucio-agberto') WHERE author = 'Lúcio Agberto' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'marival-silva') WHERE author = 'Marival Silva' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'matheus-vidal') WHERE author = 'Matheus Vidal' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'rodrigo-schittini') WHERE author = 'Rodrigo Schittini' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'rogerio-carvalho') WHERE author = 'Rogério Carvalho' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'thiago-padilha') WHERE author = 'Thiago Padilha' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'vanda-lohn') WHERE author = 'Vanda Lohn' AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'victor-cavalcante') WHERE author = 'Victor Cavalcante' AND blog_author_id IS NULL;

-- ---- Bloco B ----
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'carlos-pisani'), author = 'Carlos Pisani' WHERE slug IN ('empresas-avancam-com-agentes-de-ia-sem-estrutura-de-governanca-alerta', 'por-que-projetos-de-ia-agentica-comecam-a-fracassar-antes-mesmo-de-chegar-a', 'quando-a-facilidade-de-usar-ia-vira-um-problema-de-governanca') AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'marcus-garcia'), author = 'Marcus Garcia' WHERE slug IN ('a-pressa-para-adotar-ia-expoe-um-problema-anterior-a-tecnologia', 'do-zero-a-adocao-tech-o-que-o-ciclo-completo-de-um-produto-ensina-sobre', 'o-profissional-de-tecnologia-que-o-mercado-procura-vai-alem-do-conhecimento') AND blog_author_id IS NULL;
UPDATE cmstest_blog_posts SET blog_author_id = (SELECT id FROM cmstest_blog_authors WHERE slug = 'diego-nogare'), author = 'Diego Nogare' WHERE slug IN ('o-que-assistir-depois-de-o-dilema-das-redes') AND blog_author_id IS NULL;

-- ---- Bloco C: decidir à mão ----
-- nao-caia-na-armadilha-da-massificacao-da-inteligencia-artificial: author = "Denis Zanini Lima & Kelly Pinheiro" (dois autores; o modelo tem um autor por post). Sugestão: Kelly Pinheiro como autora cadastrada e manter a assinatura dupla no texto.
-- ted-lasso-por-que-a-serie-e-um-case-de-branded-content: author = "Denis Zanini Lima & Kelly Pinheiro" (dois autores; o modelo tem um autor por post). Sugestão: Kelly Pinheiro como autora cadastrada e manter a assinatura dupla no texto.
