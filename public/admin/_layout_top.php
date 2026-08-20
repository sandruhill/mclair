<?php
// Shared admin shell: sidebar + topbar. Include after auth.php/db.php,
// call adminLayoutTop($activeNav, $pageTitle) then page content, then
// adminLayoutBottom() at the end.
function adminLayoutTop(string $active, string $title): void {
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
<title><?= htmlspecialchars($title) ?> — Painel Mclair</title>
<style>
  :root { --red:#C8102E; --ink:#211B14; --ink-3:#665D4D; --line:#D6C9A8; --cream:#F1EBDD; --sidebar:#211B14; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--cream); color:var(--ink); }
  a { color: inherit; text-decoration: none; }

  .shell { display:flex; min-height:100vh; }

  /* Sidebar */
  .sidebar { width:220px; background:var(--sidebar); color:#fff; flex-shrink:0; padding:20px 0; }
  .sidebar-brand { display:flex; align-items:center; gap:10px; padding:0 20px 24px; }
  .sidebar-brand .dot { width:12px; height:12px; border-radius:4px; background:var(--red); }
  .sidebar-brand strong { font-size:.95rem; }
  .sidebar nav a {
    display:flex; align-items:center; gap:10px;
    padding:11px 20px; font-size:.85rem; font-weight:600;
    color:rgba(255,255,255,.6); border-left:3px solid transparent;
  }
  .sidebar nav a:hover { color:#fff; background:rgba(255,255,255,.04); }
  .sidebar nav a.active { color:#fff; background:rgba(200,16,46,.16); border-left-color:var(--red); }
  .sidebar nav .sep { height:1px; background:rgba(255,255,255,.08); margin:12px 20px; }

  /* Main */
  .main { flex:1; min-width:0; }
  .topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 28px; background:#fff; border-bottom:1px solid var(--line);
  }
  .topbar h1 { font-size:1.1rem; margin:0; }
  .topbar .user { font-size:.82rem; color:var(--ink-3); }
  .topbar .user strong { color:var(--ink); }
  .topbar .user a { color:var(--red); margin-left:10px; }
  .content { padding:28px; }

  /* Shared widgets */
  .msg { padding:10px 16px; border-radius:8px; margin-bottom:18px; font-size:.88rem; }
  .msg.ok { background:#2F7D4F; color:#fff; }
  .msg.err { background:var(--red); color:#fff; }

  table.dt { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  table.dt th { text-align:left; padding:12px 16px; background:var(--sidebar); color:#fff; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }
  table.dt td { padding:11px 16px; border-bottom:1px solid #f0ebe0; font-size:.85rem; vertical-align:middle; }
  table.dt tr:last-child td { border-bottom:none; }
  table.dt tr:hover td { background:#faf7f0; }
  .dt-thumb { width:56px; height:36px; object-fit:cover; border-radius:6px; background:var(--cream); display:block; }
  .dt-thumb-empty { width:56px; height:36px; border-radius:6px; background:var(--cream); border:1px dashed var(--line); }
  .badge { display:inline-block; padding:3px 9px; border-radius:99px; font-size:.7rem; font-weight:700; background:var(--cream); color:var(--ink-3); }
  .dt-actions a, .dt-actions button { font-size:.78rem; font-weight:700; margin-right:12px; background:none; border:none; padding:0; cursor:pointer; }
  .dt-actions a { color:var(--red); }
  .dt-actions button.del { color:#9a1f1f; text-decoration:underline; }

  .btn { display:inline-flex; align-items:center; gap:6px; background:var(--red); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; font-size:.85rem; cursor:pointer; }
  .btn.secondary { background:#fff; color:var(--ink); border:1px solid var(--line); }

  .card { background:#fff; border-radius:10px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  label { display:block; font-weight:700; margin:14px 0 5px; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-3); }
  input[type=text], input[type=password], input[type=date], select, textarea {
    width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px;
    font-family:inherit; font-size:.9rem; box-sizing:border-box; background:#fff;
  }
  textarea { min-height:280px; line-height:1.6; }

  .pagination { display:flex; gap:6px; margin-top:20px; }
  .pagination a, .pagination span { padding:7px 12px; border-radius:6px; font-size:.82rem; font-weight:700; border:1px solid var(--line); background:#fff; }
  .pagination a:hover { border-color:var(--red); color:var(--red); }
  .pagination .active { background:var(--red); color:#fff; border-color:var(--red); }
</style>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand"><span class="dot"></span><strong>Painel Mclair</strong></div>
    <nav>
      <a href="index.php" class="<?= $active === 'blog' ? 'active' : '' ?>">Posts do blog</a>
      <a href="cases.php" class="<?= $active === 'cases' ? 'active' : '' ?>">Cases</a>
      <a href="services.php" class="<?= $active === 'services' ? 'active' : '' ?>">Serviços</a>
      <div class="sep"></div>
      <a href="users.php" class="<?= $active === 'users' ? 'active' : '' ?>">Usuários</a>
    </nav>
  </aside>
  <div class="main">
    <div class="topbar">
      <h1><?= htmlspecialchars($title) ?></h1>
      <div class="user">
        Logado como <strong><?= htmlspecialchars($_SESSION['cms_username'] ?? '') ?></strong>
        <a href="?logout=1">sair</a>
      </div>
    </div>
    <div class="content">
<?php
}

function adminLayoutBottom(): void {
?>
    </div>
  </div>
</div>
</body>
</html>
<?php
}
