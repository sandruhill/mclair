import type { APIRoute } from 'astro';
import { getSingleton } from '../utils/cmsApi';

export const prerender = true;

export const GET: APIRoute = async () => {
  let content = await getSingleton('llms');
  content = typeof content === 'string' ? content : '';
  // A página da Kelly é a entidade-pessoa do site; garante que apareça no
  // llms.txt mesmo que o texto do CMS ainda não a cite.
  if (!content.includes('/kelly-pinheiro/')) {
    content = `${content.trimEnd()}\n\n## Pessoa\n\n- [Kelly Pinheiro](https://mclair.com.br/kelly-pinheiro/): CEO & Sócio-Fundadora da Mclair, jornalista, mentora (Bolder Press e Be Bolder) e palestrante sobre marketing, posicionamento e conscientização sobre câncer de mama.\n`;
  }
  return new Response(content, {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'public, max-age=3600',
    },
  });
};
