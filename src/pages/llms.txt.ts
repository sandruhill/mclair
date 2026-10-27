import type { APIRoute } from 'astro';
import { getSingleton, getServices, getCases } from '../utils/cmsApi';

export const prerender = true;

const BASE = 'https://mclair.com.br';

// Link do próprio site sem barra final ("https://mclair.com.br/sobre") custa
// um 301 para quem lê o llms.txt. Acrescenta a barra em caminho sem extensão.
function linksComBarra(texto: string): string {
  return texto.replace(/https:\/\/mclair\.com\.br(\/[^\s)#?]*)?(?=[\s)#?]|$)/g, (url, caminho = '') => {
    if (!caminho) return `${BASE}/`;
    if (caminho.endsWith('/') || /\.[a-z0-9]{2,5}$/i.test(caminho)) return url;
    return `${BASE}${caminho}/`;
  });
}

export const GET: APIRoute = async () => {
  let content = await getSingleton('llms');
  content = typeof content === 'string' ? content : '';
  content = linksComBarra(content);

  // As páginas de serviço e de case são as que a IA deve citar. Se o texto do
  // CMS não as lista, entra a lista montada do próprio CMS (título e resumo
  // de cada uma), para não depender de alguém lembrar de atualizar o texto.
  if (!content.includes('/servicos/assessoria-de-imprensa/')) {
    try {
      const services = (await getServices()).filter((s) => s && s.slug && !s.parent_slug);
      if (services.length) {
        const ordem = (s: { slug: string }) => (s.slug === 'assessoria-de-imprensa' ? 0 : 1);
        const linhas = [...services]
          .sort((a, b) => ordem(a) - ordem(b))
          .map((s) => `- [${s.title}](${BASE}/servicos/${s.slug}/): ${(s.meta_description || s.home_desc || '').trim()}`);
        content = `${content.trimEnd()}\n\n## Páginas de serviço\n\n${linhas.join('\n')}\n`;
      }
    } catch {
      // sem serviços: segue com o texto do CMS
    }
  }
  if (!/\/cases\/[a-z0-9-]+\//.test(content)) {
    try {
      const cases = (await getCases()).filter((c) => c && c.slug);
      if (cases.length) {
        const linhas = cases.map((c) => `- [${c.client}](${BASE}/cases/${c.slug}/): ${(c.meta_description || '').trim()}`);
        content = `${content.trimEnd()}\n\n## Cases\n\n${linhas.join('\n')}\n`;
      }
    } catch {
      // sem cases: segue com o texto do CMS
    }
  }

  // Lista de matérias de clientes, montada dos cases no build.
  if (!content.includes('/mclair-na-midia/')) {
    content = `${content.trimEnd()}\n\n## Imprensa\n\n- [Mclair na mídia](${BASE}/mclair-na-midia/): matérias conquistadas pela Mclair para clientes, organizadas por veículo e por cliente, com link para cada matéria e para o case.\n`;
  }

  // Página de setor (27/10/2026): o texto do llms vem do CMS, que não a cita.
  if (!content.includes('/empresas-de-tecnologia/')) {
    content = `${content.trimEnd()}\n\n## Assessoria de imprensa por setor\n\n- [Assessoria de imprensa para empresas de tecnologia](${BASE}/servicos/assessoria-de-imprensa/empresas-de-tecnologia/): assessoria de imprensa para empresas de tecnologia, startups, SaaS e especialistas em software, dados e IA, com cases da Lambda3, Diego Nogare, Elemar Jr. e Zbra na CNN Brasil, Exame, UOL Tecnologia, Canaltech e SBT.\n`;
  }

  // A página da Kelly é a entidade-pessoa do site; garante que apareça no
  // llms.txt mesmo que o texto do CMS ainda não a cite.
  if (!content.includes('/kelly-pinheiro/')) {
    content = `${content.trimEnd()}\n\n## Pessoa\n\n- [Kelly Pinheiro](https://mclair.com.br/kelly-pinheiro/): CEO & Sócio-Fundadora da Mclair, jornalista, mentora (Bolder e Bolder Press) e palestrante sobre marketing, posicionamento e conscientização sobre câncer de mama.\n`;
  }
  return new Response(content, {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'public, max-age=3600',
    },
  });
};
