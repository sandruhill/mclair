// Diz ao public/api/fb-capi.php se ele precisa conferir o consentimento
// antes de mandar evento para a Meta. Segue PRIVACIDADE_ATIVA.
import type { APIRoute } from 'astro';
import { PRIVACIDADE_ATIVA } from '../../data/privacidade';

export const prerender = true;

export const GET: APIRoute = () =>
  new Response(PRIVACIDADE_ATIVA ? 'sim' : 'nao', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
