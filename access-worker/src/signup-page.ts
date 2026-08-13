export const SIGNUP_PAGE_HTML = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Acesso ao Painel Mclair</title>
<style>
  :root { --red: #C8102E; --ink: #1C1A17; --ink-3: #6B6560; --line: #E5E2DD; --cream: #FAFAF8; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--cream); color: var(--ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
  .wrap { max-width: 420px; margin: 0 auto; padding: 48px 20px; }
  h1 { font-size: 1.4rem; margin: 0 0 8px; }
  p.lead { color: var(--ink-3); font-size: 0.95rem; margin: 0 0 32px; }
  label { display: block; font-size: 0.85rem; font-weight: 600; margin: 16px 0 6px; }
  input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 1rem; }
  button { width: 100%; margin-top: 24px; padding: 14px; background: var(--red); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; }
  button:disabled { opacity: 0.5; cursor: default; }
  #msg { margin-top: 16px; font-size: 0.9rem; }
  #msg.error { color: var(--red); }
  #msg.ok { color: #2F7D4F; }
  #step2 { display: none; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Acesso ao Painel Mclair</h1>
  <p class="lead">Preenche com seu e-mail @mclair.com.br e seu usuário do GitHub pra liberar seu acesso ao painel de edição do site.</p>

  <form id="step1">
    <label for="email">E-mail</label>
    <input id="email" type="email" placeholder="seunome@mclair.com.br" required />
    <label for="github">Usuário do GitHub</label>
    <input id="github" type="text" placeholder="seu-usuario" required />
    <button type="submit">Enviar código</button>
  </form>

  <form id="step2">
    <label for="code">Código recebido por e-mail</label>
    <input id="code" type="text" inputmode="numeric" pattern="\\d{6}" maxlength="6" placeholder="000000" required />
    <button type="submit">Confirmar</button>
  </form>

  <div id="msg"></div>
</div>

<script>
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const msg = document.getElementById('msg');
  let email = '';
  let github = '';

  function showMsg(text, kind) {
    msg.textContent = text;
    msg.className = kind;
  }

  step1.addEventListener('submit', async (e) => {
    e.preventDefault();
    email = document.getElementById('email').value.trim();
    github = document.getElementById('github').value.trim();
    showMsg('Enviando...', '');
    const res = await fetch('/solicitar-codigo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, githubUsername: github }),
    });
    const data = await res.json();
    if (data.ok) {
      showMsg('Código enviado! Confere seu e-mail.', 'ok');
      step1.style.display = 'none';
      step2.style.display = 'block';
    } else {
      showMsg(data.error || 'Algo deu errado.', 'error');
    }
  });

  step2.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = document.getElementById('code').value.trim();
    showMsg('Confirmando...', '');
    const res = await fetch('/confirmar-codigo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, code, githubUsername: github }),
    });
    const data = await res.json();
    if (data.ok) {
      showMsg(data.message || 'Acesso liberado!', 'ok');
      step2.style.display = 'none';
    } else {
      showMsg(data.error || 'Algo deu errado.', 'error');
    }
  });
</script>
</body>
</html>`;
