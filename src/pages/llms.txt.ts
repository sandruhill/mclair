export const prerender = false;
import type { APIRoute } from 'astro';
import llmsData from '../content/singletons/llms.json';

export const GET: APIRoute = () => {
  return new Response((llmsData as { content: string }).content, {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'public, max-age=3600',
    },
  });
};
