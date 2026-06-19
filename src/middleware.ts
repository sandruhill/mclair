import { defineMiddleware } from 'astro:middleware';

// A página do Keystatic não tem <head></head> — é HTML mínimo.
// Injetamos logo após o <!DOCTYPE html> para garantir que o script
// rode antes de qualquer coisa da Astro/React/Emotion.
const THEME_SCRIPT = `<script>(function(){
var V={
"--kui-color-scale-indigo1":"#fefeff",
"--kui-color-scale-indigo2":"#fdfcff",
"--kui-color-scale-indigo3":"#f9f7ff",
"--kui-color-scale-indigo4":"#f3eeff",
"--kui-color-scale-indigo5":"#ede9fe",
"--kui-color-scale-indigo6":"#ddd6fe",
"--kui-color-scale-indigo7":"#c4b5fd",
"--kui-color-scale-indigo8":"#a78bfa",
"--kui-color-scale-indigo9":"#7c3aed",
"--kui-color-scale-indigo10":"#6d28d9",
"--kui-color-scale-indigo11":"#5b21b6",
"--kui-color-scale-slate1":"#f8f7ff",
"--kui-color-scale-slate2":"#ffffff",
"--kui-size-radius-small":"6px",
"--kui-size-radius-regular":"10px",
"--kui-size-radius-medium":"12px",
"--kui-size-radius-large":"16px",
"--kui-size-radius-xlarge":"20px",
"--kui-typography-font-family-base":"'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif"
};
function apply(){var r=document.documentElement;for(var k in V)r.style.setProperty(k,V[k]);}
apply();
new MutationObserver(function(ms){
ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeName==="STYLE")apply();});});
}).observe(document.documentElement,{childList:true,subtree:true});
document.addEventListener("DOMContentLoaded",apply);
window.addEventListener("load",apply);
})();<\/script>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>`;

export const onRequest = defineMiddleware(async (context, next) => {
  const response = await next();

  if (!context.url.pathname.startsWith('/keystatic')) return response;

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('text/html')) return response;

  const html = await response.text();

  // Injeta logo após o DOCTYPE (não existe </head> nesta página)
  const themed = html.replace(/<!DOCTYPE html>/i, `<!DOCTYPE html>${THEME_SCRIPT}`);

  return new Response(themed, {
    status: response.status,
    headers: response.headers,
  });
});
