import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  verifyAndConsumeCode,
  isVerifyAttemptLimited,
} from './codes';
import { isRateLimited, isDailyEmailCapReached } from './ratelimit';
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

async function handleSolicitarCodigo(request: Request, env: Env): Promise<Response> {
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

  if (await isRateLimited(env.CODES, email)) {
    return json({
      ok: false,
      error: 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.',
    });
  }

  if (await isDailyEmailCapReached(env.CODES)) {
    return json({ ok: false, error: 'Tenta de novo mais tarde.' });
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

  return json({ ok: true });
}

async function handleConfirmarCodigo(request: Request, env: Env): Promise<Response> {
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

  // Check the GitHub username before spending the code — a typo here is the most
  // likely user error, and it shouldn't burn the (single-use) verification code.
  const userExists = await githubUserExists(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (!userExists) {
    return json({
      ok: false,
      error: 'Não encontrei esse usuário no GitHub. Confere se digitou certo.',
    });
  }

  const valid = await verifyAndConsumeCode(env.CODES, email, code);
  if (!valid) {
    return json({ ok: false, error: 'Código inválido ou expirado. Pede um novo.' });
  }

  const already = await isAlreadyCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (already) {
    return json({ ok: true, message: 'Você já tem acesso! Pode ir direto pro /admin/.' });
  }

  const added = await addCollaborator(env.GITHUB_ADMIN_TOKEN, githubUsername);
  if (!added) {
    return json(
      { ok: false, error: 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.' },
      500
    );
  }

  return json({
    ok: true,
    message:
      'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(SIGNUP_PAGE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    if (request.method === 'POST' && url.pathname === '/solicitar-codigo') {
      try {
        return await handleSolicitarCodigo(request, env);
      } catch {
        return json(GENERIC_ERROR, 500);
      }
    }

    if (request.method === 'POST' && url.pathname === '/confirmar-codigo') {
      try {
        return await handleConfirmarCodigo(request, env);
      } catch {
        return json(GENERIC_ERROR, 500);
      }
    }

    return new Response('Not found', { status: 404 });
  },
};
