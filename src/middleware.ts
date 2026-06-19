import { defineMiddleware } from 'astro:middleware';

// Keystar define vars em .kui-scheme--auto / .kui-scheme--light, não em :root.
// !important bate o injectGlobal não-important do Emotion, independente de ordem.
const THEME = `<style>
.kui-scheme--auto,.kui-scheme--light,.kui-scheme--dark{
--kui-color-scale-indigo1:#fefeff!important;
--kui-color-scale-indigo2:#fdfcff!important;
--kui-color-scale-indigo3:#f9f7ff!important;
--kui-color-scale-indigo4:#f3eeff!important;
--kui-color-scale-indigo5:#ede9fe!important;
--kui-color-scale-indigo6:#ddd6fe!important;
--kui-color-scale-indigo7:#c4b5fd!important;
--kui-color-scale-indigo8:#a78bfa!important;
--kui-color-scale-indigo9:#7c3aed!important;
--kui-color-scale-indigo10:#6d28d9!important;
--kui-color-scale-indigo11:#5b21b6!important;
--kui-color-scale-slate1:#f8f7ff!important;
--kui-color-scale-slate2:#ffffff!important;
--kui-size-radius-small:6px!important;
--kui-size-radius-regular:10px!important;
--kui-size-radius-medium:12px!important;
--kui-size-radius-large:16px!important;
--kui-size-radius-xlarge:20px!important;
--kui-typography-font-family-base:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif!important;
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>`;

export const onRequest = defineMiddleware(async (context, next) => {
  const response = await next();

  if (!context.url.pathname.startsWith('/keystatic')) return response;

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('text/html')) return response;

  const html = await response.text();

  return new Response(
    html.replace(/<!DOCTYPE html>/i, `<!DOCTYPE html>${THEME}`),
    { status: response.status, headers: response.headers },
  );
});
