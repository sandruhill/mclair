import { d as defineMiddleware, s as sequence } from './chunks/sequence_DIPiI1iY.mjs';
import 'piccolore';
import 'clsx';

const BLOCKED_EXTENSIONS = /\.(php|asp|aspx|jsp|cgi|pl|py|rb|sh|bash|env|git|sql|bak|log|cfg|ini|htaccess|htpasswd|DS_Store)$/i;
const BLOCKED_PATHS = /^\/(wp-admin|wp-login|xmlrpc|phpmyadmin|cpanel|webmail|admin\.php|config\.php|setup\.php|install\.php|composer\.json|package\.json|package-lock\.json)/i;
const PATH_TRAVERSAL = /\.\.[\/\\]/;
const SUSPICIOUS_UA = /^(curl|wget|python-requests|go-http-client|java\/|masscan|zgrab|nmap|nikto|sqlmap|dirbuster|gobuster|nuclei|httpx)/i;
const onRequest$1 = defineMiddleware((context, next) => {
  const url = context.url;
  const path = url.pathname;
  const ua = context.request.headers.get("user-agent") ?? "";
  if (PATH_TRAVERSAL.test(path)) {
    return new Response("Not Found", { status: 404 });
  }
  if (BLOCKED_EXTENSIONS.test(path)) {
    return new Response("Not Found", { status: 404 });
  }
  if (BLOCKED_PATHS.test(path)) {
    return new Response("Not Found", { status: 404 });
  }
  if (SUSPICIOUS_UA.test(ua)) {
    return new Response("Forbidden", { status: 403 });
  }
  return next();
});

const onRequest = sequence(
	
	onRequest$1
	
);

export { onRequest };
