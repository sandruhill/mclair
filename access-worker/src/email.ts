// Deno Deploy blocks outbound SMTP (ports 25/465/587), so we can't talk to
// a mailbox directly from here. Instead, a small PHP endpoint on the
// Hostinger account that already hosts mclair's site (mail-relay/send.php)
// sends the email using a mailbox that already exists there — reached over
// plain HTTPS, which isn't blocked. A shared secret (never sent to the
// browser) authenticates this call; the PHP side independently re-validates
// the recipient domain and code format too, so a leaked secret alone can't
// turn the relay into a way to send arbitrary mail to arbitrary addresses.
const MAIL_RELAY_URL = 'https://olive-gnat-658393.hostingersite.com/mail-relay/send.php';

export async function sendVerificationCode(
  relaySecret: string,
  toEmail: string,
  code: string
): Promise<boolean> {
  const res = await fetch(MAIL_RELAY_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      secret: relaySecret,
      to: toEmail,
      code,
    }),
    signal: AbortSignal.timeout(10_000),
  });
  if (!res.ok) return false;
  const data = (await res.json().catch(() => null)) as { ok?: boolean } | null;
  return data?.ok === true;
}
