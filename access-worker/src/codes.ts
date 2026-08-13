export function isValidMclairEmail(email: string): boolean {
  return /^[^\s@]+@mclair\.com\.br$/i.test(email.trim());
}

export function generateCode(): string {
  const n = (crypto.getRandomValues(new Uint32Array(1))[0] % 900000) + 100000;
  return String(n);
}

const CODE_TTL_SECONDS = 15 * 60;

export async function storeCode(kv: KVNamespace, email: string, code: string): Promise<void> {
  await kv.put(`code:${email.toLowerCase()}`, code, { expirationTtl: CODE_TTL_SECONDS });
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
