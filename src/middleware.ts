import { defineMiddleware } from 'astro:middleware';

const THEME_LINK = `<link rel="stylesheet" href="/keystatic-theme.css" />`
  + `<link rel="preconnect" href="https://fonts.googleapis.com" />`
  + `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />`
  + `<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />`;

export const onRequest = defineMiddleware(async (context, next) => {
  const response = await next();

  if (!context.url.pathname.startsWith('/keystatic')) return response;

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('text/html')) return response;

  const html = await response.text();
  const themed = html.replace('</head>', `${THEME_LINK}</head>`);

  return new Response(themed, {
    status: response.status,
    headers: response.headers,
  });
});
