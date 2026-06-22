export const prerender = false;
import type { APIRoute } from 'astro';

export const GET: APIRoute = ({ request }) => {
  const url = new URL(request.url);
  if (url.searchParams.get('provider') !== 'github') {
    return new Response('Provider not supported', { status: 400 });
  }

  const authUrl = new URL('https://github.com/login/oauth/authorize');
  authUrl.searchParams.set('client_id', import.meta.env.GITHUB_CLIENT_ID);
  authUrl.searchParams.set('redirect_uri', `${url.origin}/api/callback`);
  authUrl.searchParams.set('scope', 'repo');

  return Response.redirect(authUrl.toString(), 302);
};
