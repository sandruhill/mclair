import { config, collection, fields } from '@keystatic/core'

export default config({
  storage: process.env.KEYSTATIC_GITHUB_CLIENT_ID
    ? {
        kind: 'github' as const,
        repo: { owner: 'sandruhill', name: 'mclair' },
        branchPrefix: 'keystatic/',
      }
    : { kind: 'local' as const },

  ui: {
    brand: { name: 'Mclair CMS' },
    navigation: {
      Blog: ['blog'],
    },
  },

  collections: {
    blog: collection({
      label: 'Blog',
      slugField: 'title',
      path: 'src/content/blog/*',
      format: { contentField: 'content' },
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

        image: fields.url({
          label: 'Imagem de destaque (URL)',
          description: 'Recomendado: 1200 × 630 px',
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
          }),
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
          images: false,
        }),
      },
    }),
  },
})
