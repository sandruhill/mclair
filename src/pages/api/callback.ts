export const prerender = false;
import type { APIRoute } from 'astro';

export const GET: APIRoute = async ({ request }) => {
  const code = new URL(request.url).searchParams.get('code');
  if (!code) return new Response('No code', { status: 400 });

  const res = await fetch('https://github.com/login/oauth/access_token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({
      client_id: import.meta.env.GITHUB_CLIENT_ID,
      client_secret: import.meta.env.GITHUB_CLIENT_SECRET,
      code,
    }),
  });

  const data = await res.json() as { access_token?: string };
  if (!data.access_token) return new Response('Auth failed', { status: 401 });

  const payload = JSON.stringify({ token: data.access_token, provider: 'github' });

  const html = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<script>
(function(){
  var d=${payload};
  function receive(e){
    window.opener.postMessage('authorization:'+d.provider+':success:'+JSON.stringify(d),e.origin);
    window.removeEventListener('message',receive,false);
  }
  window.addEventListener('message',receive,false);
  window.opener.postMessage('authorizing:'+d.provider,'*');
})();
<\/script></body></html>`;

  return new Response(html, { headers: { 'Content-Type': 'text/html;charset=utf-8' } });
};
