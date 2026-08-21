// Gera TOPICAL-MAP-MCLAIR.md a partir do crawl-raw.json já em disco.
// Classificação por palavra-chave no título -- é uma primeira aproximação,
// não substitui uma leitura editorial real dos 265 posts.
import { readFileSync, writeFileSync } from 'node:fs';

const OUT_DIR = new URL('../audit-out/', import.meta.url).pathname;
const raw = JSON.parse(readFileSync(OUT_DIR + 'crawl-raw.json', 'utf-8'));
const posts = raw.filter((r) => r.type === 'blog-post' && r.row.Status === 200);

const clusters = {
  'Marketing de Autoridade': /autoridade|posicionamento|thought leadership|liderança|referência no mercado/i,
  'Assessoria de Imprensa': /imprensa|release|mídia espontânea|jornalist|inserç(ã|a)o na mídia|repórter/i,
  'Branding Estratégico': /branding|identidade visual|reposicionamento de marca|marca própria/i,
  'Marca Pessoal': /marca pessoal|personal branding|carreira|currículo|linkedin/i,
  'Gestão de Crise': /crise|escândalo|reputação em risco|dano à imagem/i,
  'Gestão de Reputação': /reputação|imagem institucional|percepção de marca/i,
  'Marketing Digital': /digital|redes sociais|instagram|seo\b|tráfego pago|ads\b|growth|inbound/i,
  'Comunicação Estratégica': /comunicação estratégica|comunicação corporativa|storytelling|narrativa/i,
  'Conteúdo de cliente': /vagas abertas|processo seletivo|abre vagas|recruta|contrata|leilão de|leilões|arremat/i,
};

function classify(title) {
  for (const [name, re] of Object.entries(clusters)) {
    if (re.test(title)) return name;
  }
  return 'Outros / Indeterminado';
}

const byCluster = new Map();
for (const p of posts) {
  const cluster = classify(p.row.Title || '');
  if (!byCluster.has(cluster)) byCluster.set(cluster, []);
  byCluster.get(cluster).push(p);
}

const order = [...Object.keys(clusters), 'Outros / Indeterminado'];
let md = `# Mapa Topical -- Mclair (blog)\n\n`;
md += `Gerado a partir dos ${posts.length} posts do blog atualmente publicados, classificados por palavra-chave no título. É uma primeira aproximação automatizada -- não substitui leitura editorial completa dos 265 posts, que fica fora do orçamento de uma única sessão.\n\n`;
md += `**Não implementa SILO novo.** Apenas mapeia o que já existe.\n\n`;
md += `## Distribuição por cluster\n\n`;
md += `| Cluster | Posts | % do total |\n|---|---|---|\n`;
for (const c of order) {
  const n = (byCluster.get(c) || []).length;
  md += `| ${c} | ${n} | ${((n / posts.length) * 100).toFixed(1)}% |\n`;
}
md += `\n## Detalhe por cluster\n\n`;
for (const c of order) {
  const list = byCluster.get(c) || [];
  if (!list.length) continue;
  md += `### ${c} (${list.length})\n\n`;
  const strong = list.filter((p) => p.row.InternalLinks >= 5).slice(0, 5);
  md += `Páginas mais linkadas internamente neste cluster:\n\n`;
  for (const p of strong.length ? strong : list.slice(0, 5)) {
    md += `- [${p.row.Title}](${p.url}) -- ${p.row.InternalLinks} links internos recebidos+enviados, ${p.row.WordCount} palavras\n`;
  }
  md += `\n`;
}

md += `## Lacunas observadas\n\n`;
md += `- "Outros / Indeterminado" concentra a maioria dos posts (${(byCluster.get('Outros / Indeterminado') || []).length} de ${posts.length}) -- são majoritariamente releases de clientes específicos (leilões, vagas, notícias pontuais de empresas atendidas) que não se encaixam nos 6 pilares de serviço da Mclair. Isso é esperado dado o modelo de negócio (assessoria de imprensa gera muito conteúdo *sobre* o cliente, não *sobre* a Mclair), mas confirma o diagnóstico já feito na primeira auditoria: o blog mistura editorial próprio com releases de terceiros sem distinção visual/estrutural -- ponto já documentado para a decisão de SILO/newsroom (fora do escopo desta rodada).\n`;
md += `- **Assessoria de Imprensa** e **Marketing de Autoridade**, os dois serviços-carro-chefe segundo o posicionamento da agência, têm muito poucos posts próprios classificados diretamente sob esse rótulo -- a maior parte do conteúdo sobre esses temas provavelmente está nas páginas de serviço, não no blog. Oportunidade de conteúdo (não implementada nesta rodada -- é decisão editorial).\n`;
md += `- **Canibalização real encontrada**: ver \`CANIBALIZACAO-SEO.csv\` -- casos concretos de dois posts cobrindo o mesmo evento/anúncio (ex: GPT-W Lambda3, Projeto Zé Conecta, exportações de máquinas). Marcados para revisão humana, não consolidados automaticamente.\n`;

writeFileSync('/Users/macbook/mclair/TOPICAL-MAP-MCLAIR.md', md, 'utf-8');
console.log('wrote TOPICAL-MAP-MCLAIR.md');
console.log([...byCluster.entries()].map(([k, v]) => `${k}: ${v.length}`).join('\n'));
