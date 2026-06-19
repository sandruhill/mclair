import { defineMiddleware } from 'astro:middleware';

// Keystar define as CSS vars em .kui-scheme--auto / .kui-scheme--light,
// NÃO em :root. Então precisamos:
// 1. Injetar <style> com !important visando essas classes
// 2. Também setar via JS nos elementos ao vivo (para client-side navigation)
const THEME_INJECT = `
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script>(function(){
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

// Injeta <style> com !important targeting .kui-scheme--auto
function injectStyle(){
  if(document.getElementById('kui-theme-override'))return;
  var css=".kui-scheme--auto,.kui-scheme--light,.kui-scheme--dark{";
  for(var k in V)css+=k+":"+V[k]+" !important;";
  css+="}";
  var s=document.createElement("style");
  s.id="kui-theme-override";
  s.textContent=css;
  (document.head||document.documentElement).appendChild(s);
}

// Aplica via style.setProperty nos elementos .kui-scheme--* existentes
function applyToSchemeEls(){
  var els=document.querySelectorAll(".kui-scheme--auto,.kui-scheme--light,.kui-scheme--dark");
  els.forEach(function(el){for(var k in V)el.style.setProperty(k,V[k]);});
}

// Tenta injetar o <style> imediatamente e nos eventos seguintes
injectStyle();
document.addEventListener("DOMContentLoaded",function(){injectStyle();applyToSchemeEls();});
window.addEventListener("load",function(){injectStyle();applyToSchemeEls();});

// MutationObserver: quando Emotion injeta <style> ou React adiciona elementos
new MutationObserver(function(ms){
  var hasStyle=false,hasNode=false;
  ms.forEach(function(m){
    m.addedNodes.forEach(function(n){
      if(n.nodeName==="STYLE")hasStyle=true;
      if(n.nodeType===1)hasNode=true;
    });
  });
  if(hasStyle)injectStyle();
  if(hasNode)applyToSchemeEls();
}).observe(document.documentElement,{childList:true,subtree:true});
})();<\/script>`;

export const onRequest = defineMiddleware(async (context, next) => {
  const response = await next();

  if (!context.url.pathname.startsWith('/keystatic')) return response;

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('text/html')) return response;

  const html = await response.text();

  const themed = html.replace(/<!DOCTYPE html>/i, `<!DOCTYPE html>${THEME_INJECT}`);

  return new Response(themed, {
    status: response.status,
    headers: response.headers,
  });
});
