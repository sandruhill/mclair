import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  codeMatches,
  consumeCode,
  isVerifyAttemptLimited,
} from './codes';
import {
  isRateLimited,
  isDailyEmailCapReached,
  incrementDailyEmailCount,
  isIpRateLimited,
} from './ratelimit';
import { sendVerificationCode } from './email';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from './github';
import { SIGNUP_PAGE_HTML } from './signup-page';

export interface Env {
  CODES: KVNamespace;
  RESEND_API_KEY: string;
  GITHUB_ADMIN_TOKEN: string;
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const GENERIC_ERROR = { ok: false, error: 'Algo deu errado. Tenta de novo.' };
const IP_RATE_LIMITED_ERROR = {
  ok: false,
  error: 'Muitas requisições desse endereço. Tenta de novo mais tarde.',
};

async function handleSolicitarCodigo(request: Request, env: Env, ip: string): Promise<Response> {
  if (await isIpRateLimited(env.CODES, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const { email: rawEmail, githubUsername: rawGithubUsername } = await request.json<{
    email?: string;
    githubUsername?: string;
  }>();

  const email = (rawEmail ?? '').trim().toLowerCase();
  const githubUsername = (rawGithubUsername ?? '').trim();

  if (!email || !isValidMclairEmail(email)) {
    return json({ ok: false, error: 'Precisa ser um e-mail @mclair.com.br.' });
  }
  if (!githubUsername) {
    return json({ ok: false, error: 'Informe seu usuário do GitHub.' });
  }

  // Cheapest / most-global check first, so a request that's going to be refused
  // by the shared daily cap never burns one of this email's hourly issuance slots.
  if (await isDailyEmailCapReached(env.CODES)) {
    return json({ ok: false, error: 'Tenta de novo mais tarde.' });
  }

  if (await isRateLimited(env.CODES, email)) {
    return json({
      ok: false,
      error: 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.',
    });
  }

  const code = generateCode();
  await storeCode(env.CODES, email, code);
  const sent = await sendVerificationCode(env.RESEND_API_KEY, email, code);
  if (!sent) {
    return json(
      { ok: false, error: 'Não consegui mandar o e-mail agora. Tenta de novo em alguns minutos.' },
      500
    );
  }
  // Only count against the shared daily quota once an email was actually sent —
  // a Resend failure above must not burn quota for a code nobody received.
  await incrementDailyEmailCount(env.CODES);

  return json({ ok: true });
}

async function handleConfirmarCodigo(request: Request, env: Env, ip: string): Promise<Response> {
  if (await isIpRateLimited(env.CODES, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const {
    email: rawEmail,
    code,
    githubUsername: rawGithubUsername,
  } = await request.json<{
    email?: string;
    code?: string;
    githubUsername?: string;
  }>();

  const email = (rawEmail ?? '').trim().toLowerCase();
  const githubUsername = (rawGithubUsername ?? '').trim();

  if (!email || !code || !githubUsername) {
    return json({ ok: false, error: 'Preencha todos os campos.' });
  }

  if (await isVerifyAttemptLimited(env.CODES, email)) {
    return json({ ok: false, error: 'Muitas tentativas. Pede um novo código.' });
  }

  // Check the code BEFORE touching the GitHub API — without this, anyone who knows
  // (or guesses) an @mclair.com.br local-part can drive githubUserExists with no
  // code at all, spending GITHUB_ADMIN_TOKEN's shared rate-limit budget. The code
  // is not consumed yet, though: a subsequent username typo or a transient GitHub
  // API failure must not burn it, so the person can retry with the same code.
  const matches = await codeMatches(env.CODES, email, code);
  if (!matches) {
    return json({ ok: false, error: 'Código inválido ou expirado. Pede um novo.' });
  }

  const userExists = await githubUserExists(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (!userExists) {
    return json({
      ok: false,
      error: 'Não encontrei esse usuário no GitHub. Confere se digitou certo.',
    });
  }

  const already = await isAlreadyCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (already) {
    await consumeCode(env.CODES, email);
    return json({ ok: true, message: 'Você já tem acesso! Pode ir direto pro /admin/.' });
  }

  const added = await addCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (!added) {
    return json(
      { ok: false, error: 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.' },
      500
    );
  }

  await consumeCode(env.CODES, email);
  return json({
    ok: true,
    message:
      'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    const ip = request.headers.get('CF-Connecting-IP') ?? 'unknown';

    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(SIGNUP_PAGE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    if (request.method === 'POST' && url.pathname === '/solicitar-codigo') {
      try {
        return await handleSolicitarCodigo(request, env, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    if (request.method === 'POST' && url.pathname === '/confirmar-codigo') {
      try {
        return await handleConfirmarCodigo(request, env, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    return new Response('Not found', { status: 404 });
  },
};
