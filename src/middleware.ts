import { defineMiddleware } from 'astro:middleware';

// Keystar CSS usa .kui-scheme--auto / .kui-scheme--light para as vars,
// não :root. Precisamos de !important para ganhar do injectGlobal não-important.
// Estratégia: (1) <style> estático no HTML + (2) JS que re-aplica ao vivo.

function buildCssVars(): string {
  const V: Record<string, string> = {
    '--kui-color-scale-indigo1':  '#fefeff',
    '--kui-color-scale-indigo2':  '#fdfcff',
    '--kui-color-scale-indigo3':  '#f9f7ff',
    '--kui-color-scale-indigo4':  '#f3eeff',
    '--kui-color-scale-indigo5':  '#ede9fe',
    '--kui-color-scale-indigo6':  '#ddd6fe',
    '--kui-color-scale-indigo7':  '#c4b5fd',
    '--kui-color-scale-indigo8':  '#a78bfa',
    '--kui-color-scale-indigo9':  '#7c3aed',
    '--kui-color-scale-indigo10': '#6d28d9',
    '--kui-color-scale-indigo11': '#5b21b6',
    '--kui-color-scale-slate1':   '#f8f7ff',
    '--kui-color-scale-slate2':   '#ffffff',
    '--kui-size-radius-small':    '6px',
    '--kui-size-radius-regular':  '10px',
    '--kui-size-radius-medium':   '12px',
    '--kui-size-radius-large':    '16px',
    '--kui-size-radius-xlarge':   '20px',
    '--kui-typography-font-family-base':
      "'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif",
  };
  return Object.entries(V).map(([k, v]) => `  ${k}:${v}!important;`).join('\n');
}

const CSS_VARS = buildCssVars();

// <style> estático — injected direto no HTML, sem depender de JS
const STATIC_STYLE = `<style id="kui-theme-override">
.kui-scheme--auto,.kui-scheme--light,.kui-scheme--dark,
.kui-scheme--auto *,.kui-scheme--light *,.kui-scheme--dark *{
${CSS_VARS}
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>`;

// <script> como reforço — aplica via style.setProperty() nos elementos ao vivo
const JS_VARS = Object.entries({
  '--kui-color-scale-indigo1':  '#fefeff',
  '--kui-color-scale-indigo2':  '#fdfcff',
  '--kui-color-scale-indigo3':  '#f9f7ff',
  '--kui-color-scale-indigo4':  '#f3eeff',
  '--kui-color-scale-indigo5':  '#ede9fe',
  '--kui-color-scale-indigo6':  '#ddd6fe',
  '--kui-color-scale-indigo7':  '#c4b5fd',
  '--kui-color-scale-indigo8':  '#a78bfa',
  '--kui-color-scale-indigo9':  '#7c3aed',
  '--kui-color-scale-indigo10': '#6d28d9',
  '--kui-color-scale-indigo11': '#5b21b6',
  '--kui-color-scale-slate1':   '#f8f7ff',
  '--kui-color-scale-slate2':   '#ffffff',
  '--kui-size-radius-small':    '6px',
  '--kui-size-radius-regular':  '10px',
  '--kui-size-radius-medium':   '12px',
  '--kui-size-radius-large':    '16px',
  '--kui-size-radius-xlarge':   '20px',
}).map(([k, v]) => `"${k}":"${v}"`).join(',');

const REINFORCE_SCRIPT = `<script>(function(){
var V={${JS_VARS}};
function applyEl(el){for(var k in V)el.style.setProperty(k,V[k]);}
function applyAll(){
  document.querySelectorAll('.kui-scheme--auto,.kui-scheme--light,.kui-scheme--dark')
    .forEach(applyEl);
}
// Ensure our <style> is last in <head> (after Emotion)
function reorderStyle(){
  var s=document.getElementById('kui-theme-override');
  if(s&&s.parentNode)s.parentNode.appendChild(s);
}
new MutationObserver(function(ms){
  var act=false;
  ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1)act=true;});});
  if(act){reorderStyle();applyAll();}
}).observe(document.documentElement,{childList:true,subtree:true});
document.addEventListener('DOMContentLoaded',function(){reorderStyle();applyAll();});
window.addEventListener('load',function(){reorderStyle();applyAll();});
})();<\/script>`;

export const onRequest = defineMiddleware(async (context, next) => {
  const response = await next();

  if (!context.url.pathname.startsWith('/keystatic')) return response;

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('text/html')) return response;

  const html = await response.text();

  const themed = html.replace(
    /<!DOCTYPE html>/i,
    `<!DOCTYPE html>${STATIC_STYLE}${REINFORCE_SCRIPT}`,
  );

  return new Response(themed, {
    status: response.status,
    headers: response.headers,
  });
});
