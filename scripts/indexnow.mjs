// Avisa o Bing (e quem usa IndexNow) das URLs do sitemap, depois de publicar.
// Uso: node scripts/indexnow.mjs            -> todas as URLs do sitemap
//      node scripts/indexnow.mjs URL URL ... -> só essas
// A chave é pública por desenho: o arquivo public/<chave>.txt prova o domínio.
import { readdirSync, readFileSync } from 'node:fs';

const HOST = 'mclair.com.br';
const arq = readdirSync(new URL('../public/', import.meta.url)).find((f) => /^[0-9a-f]{32}\.txt$/.test(f));
if (!arq) throw new Error('arquivo da chave do IndexNow não encontrado em public/');
const key = readFileSync(new URL(`../public/${arq}`, import.meta.url), 'utf8').trim();

let urls = process.argv.slice(2);
if (!urls.length) {
  const idx = await (await fetch(`https://${HOST}/sitemap-index.xml`)).text();
  for (const sm of idx.match(/<loc>[^<]+<\/loc>/g) ?? []) {
    const xml = await (await fetch(sm.slice(5, -6))).text();
    urls.push(...(xml.match(/<loc>[^<]+<\/loc>/g) ?? []).map((l) => l.slice(5, -6)));
  }
}
const r = await fetch('https://www.bing.com/indexnow', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json; charset=utf-8' },
  body: JSON.stringify({ host: HOST, key, keyLocation: `https://${HOST}/${arq}`, urlList: urls.slice(0, 10000) }),
});
console.log(`IndexNow: ${urls.length} URLs, resposta ${r.status}`);
