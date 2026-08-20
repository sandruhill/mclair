import type { APIRoute } from 'astro';
import { getSingleton } from '../utils/cmsApi';

export const prerender = true;

export const GET: APIRoute = async () => {
  const content = await getSingleton('llms');
  return new Response(typeof content === 'string' ? content : '', {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'public, max-age=3600',
    },
  });
};
