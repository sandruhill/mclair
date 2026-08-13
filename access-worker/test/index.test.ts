// access-worker/test/index.test.ts
import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:test';
import worker from '../src/index';

describe('worker fetch handler', () => {
  it('responds 200 on GET /health', async () => {
    const res = await worker.fetch(new Request('https://worker.test/health'), env);
    expect(res.status).toBe(200);
    expect(await res.text()).toBe('ok');
  });

  it('responds 404 for unknown routes', async () => {
    const res = await worker.fetch(new Request('https://worker.test/nope'), env);
    expect(res.status).toBe(404);
  });
});
