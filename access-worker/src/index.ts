export interface Env {
  CODES: KVNamespace;
  RESEND_API_KEY: string;
  GITHUB_ADMIN_TOKEN: string;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    if (request.method === 'GET' && url.pathname === '/health') {
      return new Response('ok', { status: 200 });
    }
    return new Response('Not found', { status: 404 });
  },
};
