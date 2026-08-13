import { checkAndIncrement } from './kv.ts';

export function isValidMclairEmail(email: string): boolean {
  return /^[^\s@]+@mclair\.com\.br$/i.test(email.trim());
}

export function generateCode(): string {
  const n = (crypto.getRandomValues(new Uint32Array(1))[0] % 900000) + 100000;
  return String(n);
}

const CODE_TTL_MS = 15 * 60 * 1000;
const MAX_VERIFY_ATTEMPTS = 5;

function codeKey(email: string): Deno.KvKey {
  return ['code', email.toLowerCase()];
}

function verifyAttemptsKey(email: string): Deno.KvKey {
  return ['verify-attempts', email.toLowerCase()];
}

export async function storeCode(kv: Deno.Kv, email: string, code: string): Promise<void> {
  await kv.set(codeKey(email), code, { expireIn: CODE_TTL_MS });
  // A fresh code means a fresh set of guesses — otherwise someone who legitimately
  // burns through the verify-attempt cap and requests a new code (as instructed)
  // would stay locked out until the old attempt counter's TTL expires on its own.
  await kv.delete(verifyAttemptsKey(email));
}

// Checking the code and consuming it are separate steps, so a caller can
// validate the code without burning it until every other step of the flow
// (e.g. the GitHub username lookup) has also succeeded.
export async function codeMatches(kv: Deno.Kv, email: string, code: string): Promise<boolean> {
  const entry = await kv.get<string>(codeKey(email));
  return entry.value !== null && entry.value === code;
}

export async function consumeCode(kv: Deno.Kv, email: string): Promise<void> {
  await kv.delete(codeKey(email));
}

// Called once per confirm request, before the submitted code is even looked
// at: if this email already has MAX_VERIFY_ATTEMPTS on the books, block
// immediately and force-delete the stored code so a fresh one is required.
export async function isVerifyAttemptLimited(kv: Deno.Kv, email: string): Promise<boolean> {
  const allowed = await checkAndIncrement(kv, verifyAttemptsKey(email), MAX_VERIFY_ATTEMPTS, CODE_TTL_MS);
  if (!allowed) {
    await kv.delete(codeKey(email));
    return true;
  }
  return false;
}
