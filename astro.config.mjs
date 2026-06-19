// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import keystatic from '@keystatic/astro';
import vercel from '@astrojs/vercel';
import react from '@astrojs/react';

export default defineConfig({
  site: 'https://mclair.vercel.app',
  adapter: vercel(),
  integrations: [
    sitemap({
      changefreq: 'weekly',
      priority: 0.8,
      lastmod: new Date(),
      customPages: [
        'https://mclair.vercel.app/sobre',
        'https://mclair.vercel.app/servicos',
        'https://mclair.vercel.app/servicos/marketing-de-autoridade',
        'https://mclair.vercel.app/servicos/assessoria-de-imprensa',
        'https://mclair.vercel.app/servicos/branding-estrategico',
        'https://mclair.vercel.app/servicos/marketing-digital',
        'https://mclair.vercel.app/servicos/consultoria-em-comunicacao',
        'https://mclair.vercel.app/servicos/mentorias-exclusivas',
        'https://mclair.vercel.app/mentorias',
        'https://mclair.vercel.app/cases',
        'https://mclair.vercel.app/cases/claudia-elisa',
        'https://mclair.vercel.app/cases/fidalgo',
        'https://mclair.vercel.app/cases/alexandre-magno',
        'https://mclair.vercel.app/cases/elemar-jr',
        'https://mclair.vercel.app/cases/bms-abimaq',
        'https://mclair.vercel.app/clientes',
        'https://mclair.vercel.app/contato',
      ],
    }),
    react(),
    keystatic(),
  ],
});
