import { config, collection, singleton, fields } from '@keystatic/core'

export default config({
  storage: import.meta.env.DEV
    ? { kind: 'local' as const }
    : {
        kind: 'github' as const,
        repo: { owner: 'sandruhill', name: 'mclair' },
      },

  ui: {
    brand: { name: 'Mclair CMS' },
    navigation: {
      'Páginas': ['homepage', 'sobre', 'clientes', 'contato', 'mentorias'],
      'Serviços': ['servicos'],
      'Cases': ['cases'],
      'Blog': ['blog'],
    },
  },

  singletons: {
    homepage: singleton({
      label: 'Homepage',
      path: 'src/content/singletons/homepage',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/',
      schema: {
        content: fields.object({
          heroVideoId: fields.text({
            label: 'YouTube — ID do vídeo',
            defaultValue: 'SB26MowGMDM',
            description: 'Apenas o ID do vídeo (ex: SB26MowGMDM)',
          }),
          stats: fields.array(
            fields.object({
              n: fields.text({ label: 'Número' }),
              s: fields.text({ label: 'Sufixo', defaultValue: '+' }),
              label: fields.text({ label: 'Rótulo' }),
            }, { layout: [3, 3, 6] }),
            { label: 'Estatísticas do Hero', itemLabel: (p) => p.fields.label.value }
          ),
          testimonials: fields.array(
            fields.object({
              quote: fields.text({ label: 'Depoimento', multiline: true }),
              name: fields.text({ label: 'Nome' }),
              role: fields.text({ label: 'Cargo / Empresa' }),
              photo: fields.url({ label: 'Foto (URL)' }),
            }, { layout: [12, 6, 6, 12] }),
            { label: 'Depoimentos', itemLabel: (p) => p.fields.name.value }
          ),
        }, { layout: [12, 6, 6] }),
      },
    }),

    sobre: singleton({
      label: 'Sobre',
      path: 'src/content/singletons/sobre',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/sobre',
      schema: {
        content: fields.object({
          valores: fields.array(
            fields.object({
              icon: fields.text({ label: 'Ícone / Símbolo' }),
              title: fields.text({ label: 'Título' }),
              desc: fields.text({ label: 'Descrição', multiline: true }),
            }, { layout: [2, 4, 6] }),
            { label: 'Valores', itemLabel: (p) => p.fields.title.value }
          ),
          equipe: fields.array(
            fields.object({
              nome: fields.text({ label: 'Nome' }),
              cargo: fields.text({ label: 'Cargo' }),
              initials: fields.text({ label: 'Iniciais (2 letras)' }),
              bio: fields.text({ label: 'Bio', multiline: true }),
            }, { layout: [4, 4, 4, 12] }),
            { label: 'Equipe', itemLabel: (p) => p.fields.nome.value }
          ),
          timeline: fields.array(
            fields.object({
              year: fields.text({ label: 'Ano' }),
              ev: fields.text({ label: 'Evento', multiline: true }),
            }, { layout: [3, 9] }),
            { label: 'Timeline', itemLabel: (p) => p.fields.year.value }
          ),
        }, { layout: [6, 6, 12] }),
      },
    }),

    clientes: singleton({
      label: 'Clientes',
      path: 'src/content/singletons/clientes',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/clientes',
      schema: {
        content: fields.object({
          clients: fields.array(
            fields.object({
              name: fields.text({ label: 'Nome do cliente' }),
              logo: fields.url({ label: 'Logo (URL)' }),
            }, { layout: [6, 6] }),
            { label: 'Clientes', itemLabel: (p) => p.fields.name.value || '(logo sem nome)' }
          ),
          stats: fields.array(
            fields.object({
              n: fields.text({ label: 'Número' }),
              label: fields.text({ label: 'Rótulo' }),
            }, { layout: [4, 8] }),
            { label: 'Estatísticas', itemLabel: (p) => p.fields.label.value }
          ),
        }, { layout: [8, 4] }),
      },
    }),

    contato: singleton({
      label: 'Contato',
      path: 'src/content/singletons/contato',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/contato',
      schema: {
        content: fields.object({
          contactInfo: fields.object({
            phone: fields.text({ label: 'Telefone / WhatsApp', defaultValue: '(11) 98860-0372' }),
            email: fields.text({ label: 'E-mail', defaultValue: 'kelly@mclair.com.br' }),
          }, { layout: [6, 6] }),
          faqs: fields.array(
            fields.object({
              q: fields.text({ label: 'Pergunta' }),
              a: fields.text({ label: 'Resposta', multiline: true }),
            }, { layout: [6, 6] }),
            { label: 'FAQs', itemLabel: (p) => p.fields.q.value }
          ),
        }, { layout: [4, 8] }),
      },
    }),

    mentorias: singleton({
      label: 'Mentorias',
      path: 'src/content/singletons/mentorias',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/mentorias',
      schema: {
        content: fields.object({
          bolderLevels: fields.array(
            fields.object({
              n: fields.text({ label: 'Número' }),
              title: fields.text({ label: 'Título do nível' }),
              desc: fields.text({ label: 'Descrição', multiline: true }),
            }, { layout: [2, 4, 6] }),
            { label: 'Níveis — Mentoria Bolder', itemLabel: (p) => p.fields.title.value }
          ),
          bolderPressLevels: fields.array(
            fields.object({
              n: fields.text({ label: 'Número' }),
              title: fields.text({ label: 'Título do módulo' }),
              desc: fields.text({ label: 'Descrição', multiline: true }),
            }, { layout: [2, 4, 6] }),
            { label: 'Módulos — Bolder Press', itemLabel: (p) => p.fields.title.value }
          ),
        }, { layout: [6, 6] }),
      },
    }),
  },

  collections: {
    blog: collection({
      label: 'Blog',
      slugField: 'title',
      path: 'src/content/blog/*',
      format: { contentField: 'content', data: 'yaml' },
      entryLayout: 'content',
      previewUrl: 'https://mclair.vercel.app/blog/{slug}',
      schema: {
        title: fields.slug({
          name: {
            label: 'Título',
            description: 'Ideal: 50–60 caracteres para o Google',
            validation: { length: { max: 70 } },
          },
        }),

        subtitle: fields.text({
          label: 'Subtítulo',
          multiline: true,
        }),

        metaDescription: fields.text({
          label: 'Meta Descrição (SEO)',
          description:
            '150–160 caracteres. Google exibe até ~160. Deixe em branco para usar o Subtítulo automaticamente.',
          multiline: true,
          validation: { length: { max: 160 } },
        }),

        date: fields.date({
          label: 'Data de publicação',
          defaultValue: { kind: 'today' },
        }),

        author: fields.text({
          label: 'Autor',
          defaultValue: 'Equipe Mclair',
        }),

        featuredImage: fields.image({
          label: 'Imagem de destaque',
          description: 'Arraste a foto aqui ou clique para selecionar. Recomendado: 1200 × 630 px.',
          directory: 'public/blog-images',
          publicPath: '/blog-images/',
        }),

        image: fields.url({
          label: 'Imagem (URL externa — somente posts antigos)',
          description: 'Deixe em branco para novos posts. Use o campo acima para enviar fotos.',
        }),

        heroVideo: fields.url({
          label: 'Vídeo do hero (substitui a imagem)',
          description: 'Cole a URL do YouTube ou Vimeo. Se preenchido, exibe o vídeo no lugar da imagem de destaque.',
        }),

        category: fields.select({
          label: 'Categoria',
          description: 'Categoria principal do post (usada para SEO e filtros)',
          options: [
            { label: 'Assessoria de Imprensa', value: 'assessoria-de-imprensa' },
            { label: 'Marketing de Autoridade', value: 'marketing-de-autoridade' },
            { label: 'Branding Estratégico', value: 'branding-estrategico' },
            { label: 'Marketing Digital', value: 'marketing-digital' },
            { label: 'Comunicação', value: 'comunicacao' },
            { label: 'Marca Pessoal', value: 'marca-pessoal' },
            { label: 'Negócios', value: 'negocios' },
            { label: 'Geral', value: 'geral' },
          ],
          defaultValue: 'geral',
        }),

        keywords: fields.array(
          fields.text({ label: 'Palavra-chave' }),
          {
            label: 'Keywords SEO',
            description: '6 a 8 palavras-chave relacionadas ao tema do post',
            itemLabel: (props) => props.value,
          }
        ),

        aboutTopics: fields.array(
          fields.text({ label: 'Tópico / Entidade' }),
          {
            label: 'Tópicos para IA (GEO)',
            description:
              'Entidades e temas abordados — melhora citações no ChatGPT, Perplexity e Google AI Overview',
            itemLabel: (props) => props.value,
          }
        ),

        faqItems: fields.array(
          fields.object({
            question: fields.text({ label: 'Pergunta' }),
            answer: fields.text({ label: 'Resposta', multiline: true }),
          }, { layout: [6, 6] }),
          {
            label: 'FAQ — Featured Snippets',
            description:
              'Perguntas e respostas — geram rich results no Google e aumentam citações em IA',
            itemLabel: (props) => props.fields.question.value,
          }
        ),

        content: fields.document({
          label: 'Conteúdo',
          formatting: true,
          dividers: true,
          links: true,
          images: {
            directory: 'public/blog-images',
            publicPath: '/blog-images/',
          },
        }),
      },
    }),

    servicos: collection({
      label: 'Serviços',
      slugField: 'title',
      path: 'src/content/servicos/*',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/servicos/{slug}',
      schema: {
        title: fields.slug({ name: { label: 'Título do Serviço' } }),
        num: fields.text({ label: 'Número (01, 02...)' }),
        color: fields.text({ label: 'Cor CSS', defaultValue: 'var(--red)' }),
        accent: fields.text({ label: 'Cor accent (hex)', defaultValue: '#C8102E' }),
        bg: fields.text({ label: 'Background CSS', defaultValue: 'var(--red-soft)' }),
        headline: fields.text({ label: 'Headline da página de serviço' }),
        intro: fields.text({ label: 'Introdução', multiline: true }),
        desc: fields.text({ label: 'Descrição completa', multiline: true }),
        homeDesc: fields.text({
          label: 'Descrição curta para Homepage',
          multiline: true,
          description: 'Texto do card na página inicial. Se em branco, usa a Introdução.',
        }),
        items: fields.array(
          fields.text({ label: 'Item' }),
          { label: 'O que inclui', itemLabel: (p) => p.value }
        ),
        image: fields.image({
          label: 'Imagem de destaque',
          description: 'Arraste ou clique. Aparece no hero da página do serviço. Recomendado: 1200 × 800 px.',
          directory: 'public/servicos-img',
          publicPath: '/servicos-img/',
        }),
        cta: fields.text({ label: 'Texto do botão CTA' }),
        metaDescription: fields.text({
          label: 'Meta Descrição SEO (até 160 caracteres)',
          multiline: true,
          validation: { length: { max: 160 } },
        }),
      },
    }),

    cases: collection({
      label: 'Cases',
      slugField: 'client',
      path: 'src/content/cases/*',
      format: { data: 'json' },
      previewUrl: 'https://mclair.vercel.app/cases/{slug}',
      schema: {
        client: fields.slug({ name: { label: 'Nome do cliente' } }),
        num: fields.text({ label: 'Número (01, 02...)' }),
        color: fields.text({ label: 'Cor CSS', defaultValue: 'var(--red)' }),
        accent: fields.text({ label: 'Cor accent (hex)', defaultValue: '#C8102E' }),
        sector: fields.text({ label: 'Setor / Tipo de trabalho' }),
        challenge: fields.text({ label: 'Desafio', multiline: true }),
        solution: fields.text({ label: 'Solução', multiline: true }),
        results: fields.array(
          fields.object({
            v: fields.text({ label: 'Valor (ex: 11.600+)' }),
            l: fields.text({ label: 'Rótulo' }),
          }, { layout: [4, 8] }),
          { label: 'Resultados', itemLabel: (p) => `${p.fields.v.value} — ${p.fields.l.value}` }
        ),
        tags: fields.array(
          fields.text({ label: 'Tag' }),
          { label: 'Tags', itemLabel: (p) => p.value }
        ),
        img: fields.image({
          label: 'Imagem principal',
          description: 'Arraste ou clique para enviar. Recomendado: 1200 × 630 px.',
          directory: 'public/cases-img',
          publicPath: '/cases-img/',
        }),
        logo: fields.image({
          label: 'Logo do cliente',
          description: 'Arraste ou clique. Formatos: PNG, SVG, WebP.',
          directory: 'public/cases-img/logos',
          publicPath: '/cases-img/logos/',
        }),
        gallery: fields.array(
          fields.object({
            src: fields.image({
              label: 'Foto',
              description: 'Arraste ou clique.',
              directory: 'public/cases-img',
              publicPath: '/cases-img/',
            }),
            caption: fields.text({ label: 'Legenda' }),
          }, { layout: [6, 6] }),
          { label: 'Galeria', itemLabel: (p) => p.fields.caption.value }
        ),
        homeResult: fields.text({
          label: 'Resultado para Homepage',
          description: 'Texto curto exibido no card da página inicial',
        }),
        metaDescription: fields.text({
          label: 'Meta Descrição SEO (até 160 caracteres)',
          multiline: true,
          validation: { length: { max: 160 } },
        }),
      },
    }),
  },
})
