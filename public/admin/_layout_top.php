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
  :root { --red:#C8102E; --ink:#211B14; --ink-3:#665D4D; --line:#D6C9A8; --cream:#F1EBDD; --sidebar:#211B14; --paper:#fff; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--cream); color:var(--ink); }
  a { color: inherit; text-decoration: none; }

  .shell { display:flex; min-height:100vh; }

  /* Sidebar */
  .sidebar { width:236px; background:var(--sidebar); color:#fff; flex-shrink:0; display:flex; flex-direction:column; padding:22px 12px 14px; }
  .sidebar-brand { display:flex; align-items:center; gap:10px; padding:0 10px 26px; }
  .sidebar-brand .dot { width:26px; height:26px; border-radius:8px; background:var(--red); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.85rem; }
  .sidebar-brand strong { font-size:.95rem; letter-spacing:.01em; }
  .sidebar-brand small { display:block; font-size:.66rem; color:rgba(255,255,255,.45); font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
  .nav-label { padding:14px 10px 6px; font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.35); }
  .sidebar nav a {
    display:flex; align-items:center; gap:11px;
    padding:10px 10px; margin:1px 0; font-size:.85rem; font-weight:600;
    color:rgba(255,255,255,.62); border-radius:8px;
  }
  .sidebar nav a svg { width:16px; height:16px; flex-shrink:0; opacity:.75; }
  .sidebar nav a:hover { color:#fff; background:rgba(255,255,255,.05); }
  .sidebar nav a.active { color:#fff; background:rgba(200,16,46,.22); box-shadow:inset 3px 0 0 var(--red); }
  .sidebar nav a.active svg { opacity:1; }
  .sidebar-user {
    margin-top:auto; display:flex; align-items:center; gap:10px;
    padding:12px 10px; border-top:1px solid rgba(255,255,255,.09);
  }
  .sidebar-user .avatar {
    width:32px; height:32px; border-radius:50%; background:var(--red); color:#fff;
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.85rem; flex-shrink:0;
  }
  .sidebar-user .who { min-width:0; }
  .sidebar-user .who strong { display:block; font-size:.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .sidebar-user .who a { font-size:.72rem; color:rgba(255,255,255,.5); font-weight:600; }
  .sidebar-user .who a:hover { color:#fff; }

  /* Main */
  .main { flex:1; min-width:0; }
  .topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 28px; background:var(--paper); border-bottom:1px solid var(--line);
  }
  .topbar h1 { font-size:1.1rem; margin:0; }
  .topbar .meta { font-size:.78rem; color:var(--ink-3); font-weight:600; }
  .content { padding:28px; max-width:1200px; }

  /* Shared widgets */
  .msg { padding:10px 16px; border-radius:8px; margin-bottom:18px; font-size:.88rem; }
  .msg.ok { background:#2F7D4F; color:#fff; }
  .msg.err { background:var(--red); color:#fff; }

  /* Stat cards */
  .stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:22px; }
  .stat { background:var(--paper); border:1px solid #e8e1d0; border-radius:12px; padding:18px 20px 14px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .stat .num { font-size:1.9rem; font-weight:800; letter-spacing:-.02em; line-height:1; }
  .stat .lbl { font-size:.78rem; color:var(--ink-3); margin-top:7px; font-weight:600; }
  .stat .more { display:inline-block; margin-top:10px; font-size:.74rem; font-weight:700; color:var(--red); }

  /* Table card */
  .tablecard { background:var(--paper); border:1px solid #e8e1d0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden; }
  .tablecard-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #f0ebe0; }
  .tablecard-head strong { font-size:.95rem; }
  .tablecard-head .count { font-size:.72rem; font-weight:700; color:var(--ink-3); background:var(--cream); padding:3px 10px; border-radius:99px; margin-left:10px; }

  table.dt { width:100%; border-collapse:collapse; background:var(--paper); }
  table.dt th { text-align:left; padding:11px 16px; background:#faf7f0; color:var(--ink-3); font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #f0ebe0; }
  table.dt th.s::after { content:" ↓"; color:var(--red); }
  table.dt td { padding:12px 16px; border-bottom:1px solid #f0ebe0; font-size:.85rem; vertical-align:middle; }
  table.dt tr:last-child td { border-bottom:none; }
  table.dt tr:hover td { background:#faf7f0; }
  .dt-thumb { width:56px; height:36px; object-fit:cover; border-radius:6px; background:var(--cream); display:block; }
  .dt-thumb-empty { width:56px; height:36px; border-radius:6px; background:var(--cream); border:1px dashed var(--line); }
  .badge { display:inline-block; padding:3px 9px; border-radius:99px; font-size:.7rem; font-weight:700; background:var(--cream); color:var(--ink-3); }
  .dt-actions a, .dt-actions button { font-size:.78rem; font-weight:700; margin-right:12px; background:none; border:none; padding:0; cursor:pointer; }
  .dt-actions a { color:var(--red); }
  .dt-actions button.del { color:#9a1f1f; text-decoration:underline; }

  .btn { display:inline-flex; align-items:center; gap:6px; background:var(--red); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; font-size:.85rem; cursor:pointer; }
  .btn.secondary { background:var(--paper); color:var(--ink); border:1px solid var(--line); }

  .card { background:var(--paper); border:1px solid #e8e1d0; border-radius:12px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  label { display:block; font-weight:700; margin:14px 0 5px; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-3); }
  .hint { font-size:.72rem; color:var(--ink-3); margin:4px 0 0; }
  input[type=text], input[type=password], input[type=date], select, textarea {
    width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px;
    font-family:inherit; font-size:.9rem; box-sizing:border-box; background:var(--paper);
  }
  input:focus, select:focus, textarea:focus { outline:2px solid rgba(200,16,46,.25); outline-offset:0; border-color:var(--red); }
  textarea { min-height:280px; line-height:1.6; }

  .pagination { display:flex; gap:6px; margin-top:20px; }
  .pagination a, .pagination span { padding:7px 12px; border-radius:6px; font-size:.82rem; font-weight:700; border:1px solid var(--line); background:var(--paper); }
  .pagination a:hover { border-color:var(--red); color:var(--red); }
  .pagination .active { background:var(--red); color:#fff; border-color:var(--red); }

  /* Editor: hero + two-column */
  .editor-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
  @media (max-width: 980px) { .editor-grid { grid-template-columns:1fr; } }
  .hero-card { background:var(--paper); border:1px solid #e8e1d0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden; margin-bottom:20px; }
  .hero-preview { position:relative; background:var(--ink); min-height:220px; max-height:420px; display:flex; align-items:center; justify-content:center; }
  .hero-preview img, .hero-preview video, .hero-preview iframe { width:100%; max-height:420px; object-fit:cover; display:block; border:0; }
  .hero-preview iframe { aspect-ratio:16/9; }
  .hero-preview .empty { color:rgba(255,255,255,.4); font-size:.85rem; font-weight:600; padding:60px 20px; }
  .hero-chip { position:absolute; top:14px; left:14px; background:rgba(255,255,255,.92); color:var(--ink); font-size:.7rem; font-weight:800; padding:5px 12px; border-radius:99px; letter-spacing:.04em; text-transform:uppercase; }
  .hero-controls { padding:14px 18px 18px; }
  .hero-tabs { display:inline-flex; gap:4px; background:var(--cream); border-radius:99px; padding:3px; margin-bottom:10px; }
  .hero-tabs button { border:none; background:transparent; padding:6px 16px; border-radius:99px; font-size:.78rem; font-weight:700; cursor:pointer; color:var(--ink-3); font-family:inherit; }
  .hero-tabs button.on { background:var(--ink); color:#fff; }

  /* Markdown toolbar */
  .mdbar { display:flex; flex-wrap:wrap; gap:4px; background:#faf7f0; border:1px solid var(--line); border-bottom:none; border-radius:8px 8px 0 0; padding:6px 8px; }
  .mdbar button { border:1px solid transparent; background:transparent; border-radius:6px; padding:5px 10px; font-size:.8rem; font-weight:700; cursor:pointer; color:var(--ink); font-family:inherit; min-width:32px; }
  .mdbar button:hover { background:#fff; border-color:var(--line); }
  .mdbar .div { width:1px; background:var(--line); margin:4px 4px; }
  .mdbar + textarea { border-radius:0 0 8px 8px; margin-top:0; }

  /* Editor sidebar sections */
  .side-sec { border-top:1px solid #f0ebe0; margin-top:18px; padding-top:14px; }
  .side-sec:first-child { border-top:none; margin-top:0; padding-top:0; }
  .side-sec > strong { font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); }
</style>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="dot">M</span>
      <div><strong>Mclair</strong><small>Painel de conteúdo</small></div>
    </div>
    <nav>
      <div class="nav-label">Conteúdo</div>
      <a href="index.php" class="<?= $active === 'blog' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h13v16H4z"/><path d="M8 8h5M8 12h5M8 16h3"/></svg>
        Posts do blog
      </a>
      <a href="cases.php" class="<?= $active === 'cases' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Cases
      </a>
      <a href="services.php" class="<?= $active === 'services' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5-10-5z"/><path d="m2 13 10 5 10-5"/></svg>
        Serviços
      </a>
      <div class="nav-label">Administração</div>
      <a href="users.php" class="<?= $active === 'users' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16.5 4.8a3.5 3.5 0 0 1 0 6.4M18 14.7c2.1.8 3.5 2.4 3.5 5.3"/></svg>
        Usuários
      </a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['cms_username'] ?? '?', 0, 1))) ?></div>
      <div class="who">
        <strong><?= htmlspecialchars($_SESSION['cms_username'] ?? '') ?></strong>
        <a href="index.php?logout=1">sair do painel</a>
      </div>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <h1><?= htmlspecialchars($title) ?></h1>
      <div class="meta"><?= date('d/m/Y') ?></div>
    </div>
    <div class="content">
<?php
}

// Renders the stat-cards row. Each card: ['num' => ..., 'lbl' => ..., 'href' => optional, 'more' => optional link label].
function adminStatCards(array $cards): void {
    echo '<div class="stats">';
    foreach ($cards as $c) {
        echo '<div class="stat"><div class="num">' . htmlspecialchars((string)$c['num']) . '</div>';
        echo '<div class="lbl">' . htmlspecialchars($c['lbl']) . '</div>';
        if (!empty($c['href'])) {
            echo '<a class="more" href="' . htmlspecialchars($c['href']) . '">' . htmlspecialchars($c['more'] ?? 'Ver') . ' →</a>';
        }
        echo '</div>';
    }
    echo '</div>';
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
