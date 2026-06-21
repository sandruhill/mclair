import { defineMiddleware } from 'astro:middleware';

const BLOCKED_EXTENSIONS = /\.(php|asp|aspx|jsp|cgi|pl|py|rb|sh|bash|env|git|sql|bak|log|cfg|ini|htaccess|htpasswd|DS_Store)$/i;
const BLOCKED_PATHS = /^\/(wp-admin|wp-login|xmlrpc|phpmyadmin|cpanel|webmail|admin\.php|config\.php|setup\.php|install\.php|composer\.json|package\.json|package-lock\.json)/i;
const PATH_TRAVERSAL = /\.\.[\/\\]/;
const SUSPICIOUS_UA = /^(curl|wget|python-requests|go-http-client|java\/|masscan|zgrab|nmap|nikto|sqlmap|dirbuster|gobuster|nuclei|httpx)/i;

export const onRequest = defineMiddleware((context, next) => {
  const url = context.url;
  const path = url.pathname;
  const ua = context.request.headers.get('user-agent') ?? '';

  // Bloquear path traversal
  if (PATH_TRAVERSAL.test(path)) {
    return new Response('Not Found', { status: 404 });
  }

  // Bloquear extensões de arquivos de servidor (scanners tentam isso)
  if (BLOCKED_EXTENSIONS.test(path)) {
    return new Response('Not Found', { status: 404 });
  }

  // Bloquear caminhos de admin de outros CMSs (WordPress, etc.)
  if (BLOCKED_PATHS.test(path)) {
    return new Response('Not Found', { status: 404 });
  }

  // Bloquear scanners de vulnerabilidade conhecidos
  if (SUSPICIOUS_UA.test(ua)) {
    return new Response('Forbidden', { status: 403 });
  }

  return next();
});
