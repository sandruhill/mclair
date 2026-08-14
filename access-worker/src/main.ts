import {
  isValidMclairEmail,
  generateCode,
  storeCode,
  codeMatches,
  consumeCode,
  isVerifyAttemptLimited,
} from './codes.ts';
import {
  isRateLimited,
  isDailyEmailCapReached,
  incrementDailyEmailCount,
  isIpRateLimited,
} from './ratelimit.ts';
import { sendVerificationCode } from './email.ts';
import { githubUserExists, isAlreadyCollaborator, addCollaborator } from './github.ts';
import { SIGNUP_PAGE_HTML } from './signup-page.ts';
import { openKv } from './kv.ts';

export interface Secrets {
  resendApiKey: string;
  githubAdminToken: string;
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

// X-Forwarded-For is a header proxies APPEND to as a request passes through
// them, not a header the original client fully controls the meaning of — but
// the LEFTMOST entry is exactly what the original client claimed, which is
// fully attacker-forgeable (anyone can send `X-Forwarded-For: 1.2.3.4`
// themselves). Nothing sits between Deno Deploy's edge and this handler, so
// the RIGHTMOST entry is the one appended by Deploy's own edge proxy — the
// only entry in the list an attacker cannot forge. Reading the leftmost value
// here would let an attacker mint a fresh per-IP rate-limit bucket on every
// request just by varying that header, defeating the per-IP daily cap.
function clientIp(request: Request): string {
  const forwarded = request.headers.get('x-forwarded-for');
  if (forwarded) {
    const parts = forwarded.split(',');
    const last = parts[parts.length - 1]?.trim();
    if (last) return last;
  }
  const realIp = request.headers.get('x-real-ip');
  if (realIp) return realIp;
  console.warn('client IP could not be determined, falling back to shared bucket');
  return 'unknown';
}

async function handleSolicitarCodigo(
  request: Request,
  kv: Deno.Kv,
  secrets: Secrets,
  ip: string
): Promise<Response> {
  if (await isIpRateLimited(kv, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const { email: rawEmail, githubUsername: rawGithubUsername } = (await request.json()) as {
    email?: string;
    githubUsername?: string;
  };

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
  if (await isDailyEmailCapReached(kv)) {
    return json({ ok: false, error: 'Tenta de novo mais tarde.' });
  }

  if (await isRateLimited(kv, email)) {
    return json({
      ok: false,
      error: 'Muitos pedidos de código pra esse e-mail. Tenta de novo daqui a pouco.',
    });
  }

  const code = generateCode();
  await storeCode(kv, email, code);
  const sent = await sendVerificationCode(secrets.resendApiKey, email, code);
  if (!sent) {
    return json(
      { ok: false, error: 'Não consegui mandar o e-mail agora. Tenta de novo em alguns minutos.' },
      500
    );
  }
  // Only count against the shared daily quota once an email was actually sent —
  // a Resend failure above must not burn quota for a code nobody received.
  await incrementDailyEmailCount(kv);

  return json({ ok: true });
}

async function handleConfirmarCodigo(
  request: Request,
  kv: Deno.Kv,
  secrets: Secrets,
  ip: string
): Promise<Response> {
  if (await isIpRateLimited(kv, ip)) {
    return json(IP_RATE_LIMITED_ERROR);
  }

  const {
    email: rawEmail,
    code,
    githubUsername: rawGithubUsername,
  } = (await request.json()) as {
    email?: string;
    code?: string;
    githubUsername?: string;
  };

  const email = (rawEmail ?? '').trim().toLowerCase();
  const githubUsername = (rawGithubUsername ?? '').trim();

  if (!email || !code || !githubUsername) {
    return json({ ok: false, error: 'Preencha todos os campos.' });
  }

  if (await isVerifyAttemptLimited(kv, email)) {
    return json({ ok: false, error: 'Muitas tentativas. Pede um novo código.' });
  }

  // Check the code BEFORE touching the GitHub API — without this, anyone who knows
  // (or guesses) an @mclair.com.br local-part can drive githubUserExists with no
  // code at all, spending the admin token's shared rate-limit budget. The code is
  // not consumed yet, though: a subsequent username typo or a transient GitHub API
  // failure must not burn it, so the person can retry with the same code.
  const matches = await codeMatches(kv, email, code);
  if (!matches) {
    return json({ ok: false, error: 'Código inválido ou expirado. Pede um novo.' });
  }

  const userExists = await githubUserExists(secrets.githubAdminToken, githubUsername);
  if (!userExists) {
    return json({
      ok: false,
      error: 'Não encontrei esse usuário no GitHub. Confere se digitou certo.',
    });
  }

  const already = await isAlreadyCollaborator(secrets.githubAdminToken, githubUsername);
  if (already) {
    await consumeCode(kv, email);
    console.log(`access granted (already had access): ${email} -> ${githubUsername}`);
    return json({ ok: true, message: 'Você já tem acesso! Pode ir direto pro /admin/.' });
  }

  const added = await addCollaborator(secrets.githubAdminToken, githubUsername);
  if (!added) {
    return json(
      { ok: false, error: 'Não consegui liberar o acesso agora. Tenta de novo ou chama o Sandru.' },
      500
    );
  }

  await consumeCode(kv, email);
  console.log(`access granted: ${email} -> ${githubUsername}`);
  return json({
    ok: true,
    message:
      'Prontinho! Confere seu e-mail ou as notificações do GitHub pra aceitar o convite, e depois acessa o /admin/.',
  });
}

export function makeHandler(kv: Deno.Kv, secrets: Secrets): (request: Request) => Promise<Response> {
  return async (request: Request): Promise<Response> => {
    const url = new URL(request.url);
    const ip = clientIp(request);

    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(SIGNUP_PAGE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }

    if (request.method === 'POST' && url.pathname === '/solicitar-codigo') {
      try {
        return await handleSolicitarCodigo(request, kv, secrets, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    if (request.method === 'POST' && url.pathname === '/confirmar-codigo') {
      try {
        return await handleConfirmarCodigo(request, kv, secrets, ip);
      } catch (err) {
        console.error(err);
        return json(GENERIC_ERROR, 500);
      }
    }

    return new Response('Not found', { status: 404 });
  };
}

// Real entry point — not exercised by tests, which call makeHandler(...) directly
// against an in-memory KV instance instead of hitting this module-scope side effect.
if (import.meta.main) {
  const kv = await openKv();
  const resendApiKey = Deno.env.get('RESEND_API_KEY') ?? '';
  const githubAdminToken = Deno.env.get('GITHUB_ADMIN_TOKEN') ?? '';
  if (!resendApiKey || !githubAdminToken) {
    throw new Error(
      'RESEND_API_KEY and GITHUB_ADMIN_TOKEN must both be set as environment variables/secrets before starting.'
    );
  }
  const secrets: Secrets = { resendApiKey, githubAdminToken };
  Deno.serve(makeHandler(kv, secrets));
}
