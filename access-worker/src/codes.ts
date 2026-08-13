export function isValidMclairEmail(email: string): boolean {
  return /^[^\s@]+@mclair\.com\.br$/i.test(email.trim());
}

export function generateCode(): string {
  const n = (crypto.getRandomValues(new Uint32Array(1))[0] % 900000) + 100000;
  return String(n);
}

const CODE_TTL_SECONDS = 15 * 60;

export async function storeCode(kv: KVNamespace, email: string, code: string): Promise<void> {
  const normalized = email.toLowerCase();
  await kv.put(`code:${normalized}`, code, { expirationTtl: CODE_TTL_SECONDS });
  // A fresh code means a fresh set of guesses — otherwise someone who legitimately
  // burns through the verify-attempt cap and requests a new code (as instructed)
  // would stay locked out until the old attempt counter's TTL expires on its own.
  await kv.delete(`verify-attempts:${normalized}`);
}

export async function verifyAndConsumeCode(
  kv: KVNamespace,
  email: string,
  code: string
): Promise<boolean> {
  const key = `code:${email.toLowerCase()}`;
  const stored = await kv.get(key);
  if (stored === null || stored !== code) return false;
  await kv.delete(key);
  return true;
}

const MAX_VERIFY_ATTEMPTS = 5;

// Same check-and-increment KV-counter pattern as ratelimit.ts's isRateLimited,
// but scoped to /confirmar-codigo guesses instead of /solicitar-codigo requests.
// Called once per request, before the submitted code is checked: if this email
// already has MAX_VERIFY_ATTEMPTS on the books, block immediately (without even
// looking at the code) and force-delete the stored code so a fresh one is required.
export async function isVerifyAttemptLimited(kv: KVNamespace, email: string): Promise<boolean> {
  const key = `verify-attempts:${email.toLowerCase()}`;
  const current = await kv.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= MAX_VERIFY_ATTEMPTS) {
    await kv.delete(`code:${email.toLowerCase()}`);
    return true;
  }
  await kv.put(key, String(count + 1), { expirationTtl: CODE_TTL_SECONDS });
  return false;
}
