<?php
// Sends the verification code via the Hostinger account's own mail
// transport (PHP mail()) — no external relay needed now that this runs on
// the same server as the mailbox's domain.
//
// ponytail: deliverability depends on Hostinger's shared-IP mail sending
// passing SPF/DKIM at the recipient (mclair.com.br's SPF record currently
// only authorizes Google Workspace + a couple of other senders, not
// Hostinger). Verify a real code email actually lands in the inbox (not
// spam) during testing — if it doesn't, switch to a transactional API
// (Resend, Postmark) instead of PHP mail().
function sendVerificationCode(string $toEmail, string $code): bool {
    $from = 'acesso@mclair.com.br';
    $subject = 'Seu código de acesso — Painel Mclair';
    $body = <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,sans-serif;background:#F1EBDD;padding:32px;margin:0;">
  <div style="max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #D6C9A8;">
    <h1 style="font-size:18px;margin:0 0 12px;color:#211B14;">Acesso ao Painel Mclair</h1>
    <p style="color:#665D4D;font-size:14px;margin:0 0 24px;">Use o código abaixo pra confirmar seu acesso. Ele vale por 15 minutos.</p>
    <div style="font-size:32px;font-weight:700;letter-spacing:0.1em;color:#C8102E;text-align:center;padding:16px;background:#F1EBDD;border-radius:8px;">{$code}</div>
    <p style="color:#8C8168;font-size:12px;margin:24px 0 0;">Se você não pediu esse código, pode ignorar este e-mail.</p>
  </div>
</body></html>
HTML;

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Painel Mclair <{$from}>\r\n";

    return mail($toEmail, $subject, $body, $headers);
}
