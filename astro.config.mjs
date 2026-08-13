// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://olive-gnat-658393.hostingersite.com',
  output: 'static',
  integrations: [
    sitemap({
      changefreq: 'weekly',
      priority: 0.8,
      lastmod: new Date(),
      customPages: [
        'https://olive-gnat-658393.hostingersite.com/sobre',
        'https://olive-gnat-658393.hostingersite.com/servicos',
        'https://olive-gnat-658393.hostingersite.com/servicos/marketing-de-autoridade',
        'https://olive-gnat-658393.hostingersite.com/servicos/assessoria-de-imprensa',
        'https://olive-gnat-658393.hostingersite.com/servicos/branding-estrategico',
        'https://olive-gnat-658393.hostingersite.com/servicos/marketing-digital',
        'https://olive-gnat-658393.hostingersite.com/servicos/consultoria-em-comunicacao',
        'https://olive-gnat-658393.hostingersite.com/servicos/mentorias-exclusivas',
        'https://olive-gnat-658393.hostingersite.com/mentorias',
        'https://olive-gnat-658393.hostingersite.com/cases',
        'https://olive-gnat-658393.hostingersite.com/cases/claudia-elisa',
        'https://olive-gnat-658393.hostingersite.com/cases/fidalgo',
        'https://olive-gnat-658393.hostingersite.com/cases/alexandre-magno',
        'https://olive-gnat-658393.hostingersite.com/cases/elemar-jr',
        'https://olive-gnat-658393.hostingersite.com/cases/bms-abimaq',
        'https://olive-gnat-658393.hostingersite.com/cases/diego-nogare',
        'https://olive-gnat-658393.hostingersite.com/cases/insight-rh',
        'https://olive-gnat-658393.hostingersite.com/cases/lambda3',
        'https://olive-gnat-658393.hostingersite.com/cases/nexinvoice',
        'https://olive-gnat-658393.hostingersite.com/cases/silene-chiconini',
        'https://olive-gnat-658393.hostingersite.com/cases/zbra',
        'https://olive-gnat-658393.hostingersite.com/cases/globo-leiloes',
        'https://olive-gnat-658393.hostingersite.com/cases/vip-leiloes',
        'https://olive-gnat-658393.hostingersite.com/clientes',
        'https://olive-gnat-658393.hostingersite.com/contato',
        'https://olive-gnat-658393.hostingersite.com/marketing-para-leiloeiros',
      ],
    }),
  ],
});
