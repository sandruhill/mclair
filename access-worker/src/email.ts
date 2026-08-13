export async function sendVerificationCode(
  apiKey: string,
  toEmail: string,
  code: string
): Promise<boolean> {
  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: 'Painel Mclair <acesso@mclair.com.br>',
      to: toEmail,
      subject: `Seu código de acesso: ${code}`,
      html: `<p>Seu código de verificação é <strong>${code}</strong>. Ele expira em 15 minutos.</p>`,
    }),
  });
  return res.ok;
}
