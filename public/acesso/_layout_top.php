<?php
// Shared admin shell: sidebar + topbar. Include after auth.php/db.php,
// call adminLayoutTop($activeNav, $pageTitle) then page content, then
// adminLayoutBottom() at the end.

// Small animated checkmark used in the "saved" confirmation pill (#savedMsg).
function cmsCheckIcon(): string {
    return '<svg class="check-icon" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function adminLayoutTop(string $active, string $title, ?array $crumb = null, ?string $liveUrl = null): void {
    if (isset($_GET['dismiss_pw_reminder'])) $_SESSION['cms_pw_reminder_dismissed'] = true;
    $pwState = $active === 'profile' ? 'none' : cmsPasswordPromptState();
    if ($pwState === 'remind' && !empty($_SESSION['cms_pw_reminder_dismissed'])) $pwState = 'none';

    $toastMsg = null;
    if (isset($_GET['pw_changed'])) $toastMsg = 'Senha atualizada com sucesso.';
    elseif ($active === 'users' && isset($_GET['saved'])) $toastMsg = 'Perfil atualizado.';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
<title><?= htmlspecialchars($title) ?> · Painel Mclair</title>
<style>
  /* Smooth cross-page navigation (native browser feature, no JS/SPA needed --
     same underlying API the public site's Astro ClientRouter uses). Falls
     back to a normal instant page load on browsers that don't support it. */
  @view-transition { navigation: auto; }
  .sidebar { view-transition-name: admin-sidebar; }
  .topbar { view-transition-name: admin-topbar; }

  :root { --red:#C8102E; --ink:#1A1B1E; --ink-3:#6B7280; --line:#E5E7EB; --bg:#F6F7F9; --soft:#F1F2F4; --sidebar:#ffffff; --paper:#fff; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--bg); color:var(--ink); }
  a { color: inherit; text-decoration: none; }

  .shell { display:flex; min-height:100vh; }

  /* Sidebar */
  .sidebar { width:236px; background:var(--sidebar); color:var(--ink); flex-shrink:0; display:flex; flex-direction:column; padding:22px 12px 14px; border-right:1px solid var(--line); }
  .sidebar-brand { display:flex; align-items:center; gap:10px; padding:0 10px 26px; }
  .sidebar-brand .logo-link { position:relative; width:26px; height:26px; flex-shrink:0; }
  .sidebar-brand .logo-link img { width:26px; height:26px; object-fit:contain; display:block; }
  .sidebar-brand .logo-link .tip {
    position:absolute; top:calc(100% + 6px); left:50%; transform:translateX(-50%);
    background:var(--ink); color:#fff; font-size:.68rem; font-weight:600; white-space:nowrap;
    padding:4px 8px; border-radius:6px; opacity:0; pointer-events:none; transition:opacity .15s; z-index:20;
  }
  .sidebar-brand .logo-link:hover .tip { opacity:1; }
  .sidebar-brand strong { font-size:.95rem; letter-spacing:.01em; }
  .sidebar-brand small { display:block; font-size:.66rem; color:var(--ink-3); font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
  .nav-label { padding:14px 10px 6px; font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#9CA3AF; }
  .sidebar nav a {
    display:flex; align-items:center; gap:11px;
    padding:10px 10px; margin:1px 0; font-size:.85rem; font-weight:600;
    color:var(--ink-3); border-radius:8px;
  }
  .sidebar nav a svg { width:16px; height:16px; flex-shrink:0; opacity:.75; }
  .sidebar nav a:hover { color:var(--ink); background:var(--soft); }
  .sidebar nav a.active { color:var(--red); background:rgba(200,16,46,.08); }
  .sidebar nav a.active svg { opacity:1; }
  .nav-tree { display:flex; flex-direction:column; margin:2px 0 4px 22px; padding-left:12px; border-left:1.5px solid var(--line); }
  .nav-tree a { display:block; padding:7px 10px; margin:1px 0; font-size:.8rem; font-weight:600; color:var(--ink-3); border-radius:8px; }
  .nav-tree a:hover { color:var(--ink); background:var(--soft); }
  .nav-tree a.active { color:var(--red); background:rgba(200,16,46,.08); }
  .sidebar-user {
    margin-top:auto; display:flex; align-items:center; gap:10px;
    padding:12px 10px; border-top:1px solid var(--line);
  }
  .sidebar-user .avatar {
    width:32px; height:32px; border-radius:50%; background:var(--red); color:#fff;
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.85rem; flex-shrink:0;
    overflow:hidden;
  }
  .sidebar-user .avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .sidebar-user .who { min-width:0; }
  .sidebar-user .who strong { display:block; font-size:.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .sidebar-user .who a { font-size:.72rem; color:var(--ink-3); font-weight:600; }
  .sidebar-user .who a:hover { color:var(--ink); }

  /* Main */
  .main { flex:1; min-width:0; }
  .topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 28px; background:var(--paper); border-bottom:1px solid var(--line);
  }
  .topbar h1 { font-size:1.1rem; margin:0; }
  .breadcrumb { display:flex; align-items:center; gap:6px; font-size:.75rem; font-weight:600; color:var(--ink-3); margin-bottom:3px; }
  .breadcrumb a:hover { color:var(--red); }
  .breadcrumb span { color:#C7CBD1; }
  .topbar .meta { font-size:.78rem; color:var(--ink-3); font-weight:600; }
  .content { padding:28px; max-width:1200px; }

  /* Shared widgets */
  .msg { padding:10px 16px; border-radius:8px; margin-bottom:18px; font-size:.88rem; display:flex; align-items:center; gap:10px; }
  .msg.ok { background:#2F7D4F; color:#fff; }
  .msg.err { background:var(--red); color:#fff; }
  .msg .spin { width:13px; height:13px; border-radius:50%; border:2px solid rgba(255,255,255,.4); border-top-color:#fff; animation:msgspin .7s linear infinite; flex-shrink:0; }
  @keyframes msgspin { to { transform:rotate(360deg); } }
  .msg a { color:#fff; font-weight:700; text-decoration:underline; }

  /* Save confirmation: small pill, right-aligned, animated check */
  #savedMsg { width:fit-content; max-width:100%; margin:0 0 18px auto; padding:7px 12px; font-size:.8rem; gap:6px; }
  #savedMsg .check-icon { width:13px; height:13px; flex-shrink:0; }
  #savedMsg .check-icon path { stroke-dasharray:30; stroke-dashoffset:30; animation:checkdraw .45s cubic-bezier(.65,0,.35,1) forwards .1s; }
  @keyframes checkdraw { to { stroke-dashoffset:0; } }

  /* Stat cards */
  .stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:22px; }
  .stat { background:var(--paper); border:1px solid var(--line); border-radius:12px; padding:18px 20px 14px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .stat .num { font-size:1.9rem; font-weight:800; letter-spacing:-.02em; line-height:1; }
  .stat .lbl { font-size:.78rem; color:var(--ink-3); margin-top:7px; font-weight:600; }
  .stat .more { display:inline-block; margin-top:10px; font-size:.74rem; font-weight:700; color:var(--red); }

  /* Table card */
  .tablecard { background:var(--paper); border:1px solid var(--line); border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden; }
  .tablecard-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--line); }
  .tablecard-head strong { font-size:.95rem; }
  .tablecard-head .count { font-size:.72rem; font-weight:700; color:var(--ink-3); background:var(--soft); padding:3px 10px; border-radius:99px; margin-left:10px; }

  table.dt { width:100%; border-collapse:collapse; background:var(--paper); }
  table.dt th { text-align:left; padding:11px 16px; background:var(--soft); color:var(--ink-3); font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--line); }
  table.dt th.s::after { content:" ↓"; color:var(--red); }
  table.dt td { padding:12px 16px; border-bottom:1px solid var(--line); font-size:.85rem; vertical-align:middle; }
  table.dt tr:last-child td { border-bottom:none; }
  table.dt tr:hover td { background:var(--soft); }
  .dt-thumb { width:56px; height:36px; object-fit:cover; border-radius:6px; background:var(--soft); display:block; }
  .dt-thumb-empty { width:56px; height:36px; border-radius:6px; background:var(--soft); border:1px dashed var(--line); }
  .badge { display:inline-block; padding:3px 9px; border-radius:99px; font-size:.7rem; font-weight:700; background:var(--soft); color:var(--ink-3); }
  .dt-actions a, .dt-actions button { font-size:.78rem; font-weight:700; margin-right:12px; background:none; border:none; padding:0; cursor:pointer; }
  .dt-actions a { color:var(--red); }
  .dt-actions button.del { color:#9a1f1f; text-decoration:underline; }

  .btn { display:inline-flex; align-items:center; gap:6px; background:var(--red); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; font-size:.85rem; cursor:pointer; }
  .btn.secondary { background:var(--paper); color:var(--ink); border:1px solid var(--line); }

  .card { background:var(--paper); border:1px solid var(--line); border-radius:12px; padding:22px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  label { display:block; font-weight:700; margin:14px 0 5px; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-3); }
  .hint { font-size:.72rem; color:var(--ink-3); margin:4px 0 0; }
  input[type=text], input[type=password], input[type=date], input[type=email], select, textarea {
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
  .hero-card { background:var(--paper); border:1px solid var(--line); border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden; margin-bottom:20px; }
  .hero-preview { position:relative; background:var(--ink); min-height:220px; max-height:420px; display:flex; align-items:center; justify-content:center; }
  .hero-preview img, .hero-preview video, .hero-preview iframe { width:100%; max-height:420px; object-fit:cover; display:block; border:0; }
  .hero-preview iframe { aspect-ratio:16/9; }
  .hero-preview .empty { color:rgba(255,255,255,.4); font-size:.85rem; font-weight:600; padding:60px 20px; }
  .hero-chip { position:absolute; top:14px; left:14px; background:rgba(255,255,255,.92); color:var(--ink); font-size:.7rem; font-weight:800; padding:5px 12px; border-radius:99px; letter-spacing:.04em; text-transform:uppercase; }
  .hero-controls { padding:14px 18px 18px; }
  .hero-tabs { display:inline-flex; gap:4px; background:var(--soft); border-radius:99px; padding:3px; margin-bottom:10px; }
  .hero-tabs button { border:none; background:transparent; padding:6px 16px; border-radius:99px; font-size:.78rem; font-weight:700; cursor:pointer; color:var(--ink-3); font-family:inherit; }
  .hero-tabs button.on { background:var(--ink); color:#fff; }

  /* Markdown toolbar */
  .mdbar { display:flex; flex-wrap:wrap; gap:4px; background:var(--soft); border:1px solid var(--line); border-bottom:none; border-radius:8px 8px 0 0; padding:6px 8px; }
  .mdbar button { border:1px solid transparent; background:transparent; border-radius:6px; padding:5px 10px; font-size:.8rem; font-weight:700; cursor:pointer; color:var(--ink); font-family:inherit; min-width:32px; }
  .mdbar button:hover { background:#fff; border-color:var(--line); }
  .mdbar .div { width:1px; background:var(--line); margin:4px 4px; }
  .mdbar + textarea { border-radius:0 0 8px 8px; margin-top:0; }

  /* Editor sidebar sections */
  .side-sec { border-top:1px solid var(--line); margin-top:18px; padding-top:14px; }
  .side-sec:first-child { border-top:none; margin-top:0; padding-top:0; }
  .side-sec > strong { font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); }

  /* Drag-and-drop image upload -- IS the photo slot (backs a hidden image-URL input) */
  .imgdrop { position:relative; margin-top:8px; border:1.5px dashed var(--line); border-radius:8px; background:var(--paper); overflow:hidden; text-align:center; cursor:pointer; min-height:52px; display:flex; align-items:center; justify-content:center; }
  .imgdrop.over { border-color:var(--red); background:rgba(200,16,46,.04); }
  .imgdrop img { display:none; width:100%; height:190px; object-fit:cover; }
  .imgdrop.has-img { border-style:solid; padding:0; }
  .imgdrop.has-img img { display:block; }
  .imgdrop .imgdrop-msg { font-size:.76rem; font-weight:600; color:var(--ink-3); padding:14px; }
  .imgdrop.has-img .imgdrop-msg { position:absolute; inset:auto 0 0 0; padding:8px 10px; text-align:left; color:#fff; background:linear-gradient(0deg,rgba(0,0,0,.65),rgba(0,0,0,0)); opacity:0; transition:opacity .15s; }
  .imgdrop.has-img:hover .imgdrop-msg { opacity:1; }
  .imgdrop-err { font-size:.74rem; font-weight:600; color:var(--red); margin:6px 0 0; }

  /* Repeater items that carry little info per item (a photo+caption, a
     number+label...) sit side by side instead of stacking full-width.
     auto-fit (not auto-fill) so a half-empty last row stretches its items
     to fill the width instead of leaving blank trailing columns. */
  .rep.grid-cols { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(200px, 100%), 1fr)); gap:12px; align-items:start; }
  .rep.grid-cols .rep-item { margin-bottom:0; }

  /* Inside a compact item, pair up its short fields (a number + a suffix)
     instead of each stacking full-width, and let the last field (usually
     the label/caption) take the full row underneath them. */
  .rep.tight-fields .rep-fields { grid-template-columns:repeat(auto-fit, minmax(min(70px, 100%), 1fr)); gap:8px; }
  .rep.tight-fields .rep-fields > *:last-child { grid-column:1 / -1; }

  /* Inline confirmation (replaces native confirm()) */
  .ci { display:inline-flex; align-items:center; gap:8px; font-size:.78rem; }
  .ci .ci-msg { color:#9a1f1f; font-weight:600; }
  .ci button.ci-yes { background:var(--red); color:#fff; border:none; padding:4px 10px; border-radius:6px; font-family:inherit; font-size:.76rem; font-weight:700; cursor:pointer; }
  .ci button.ci-no { background:none; border:none; padding:0; color:var(--ink-3); text-decoration:underline; font-family:inherit; font-size:.76rem; font-weight:700; cursor:pointer; }

  /* Inline URL ask (replaces native prompt()) */
  .urlask { display:inline-flex; align-items:center; gap:4px; }
  .urlask input[type=text] { width:220px; padding:5px 8px; font-size:.8rem; }

  /* Forced password-change modal (first 3 logins after a create/reset) --
     no close button, no backdrop-click dismiss, on purpose. */
  .pw-modal-backdrop { position:fixed; inset:0; background:rgba(20,16,12,.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:24px; }
  .pw-modal { background:var(--paper); border-radius:14px; width:100%; max-width:380px; padding:26px; box-shadow:0 30px 70px -24px rgba(20,16,12,.4); }
  .pw-modal h2 { margin:0 0 6px; font-size:1.05rem; }
  .pw-modal p { margin:0 0 4px; font-size:.85rem; color:var(--ink-3); }

  /* Soft reminder banner (after the 3rd forced login) -- dismissible */
  .pw-banner { display:flex; align-items:center; justify-content:space-between; gap:12px; background:#FFF4E5; color:#7A4A00; border:1px solid #F3D9A4; padding:10px 16px; font-size:.85rem; }
  .pw-banner a { font-weight:700; text-decoration:underline; }

  /* Floating success toast, top-right -- for saves that don't have (or
     shouldn't be tied to) a specific spot on the page, e.g. profile/password
     changes. Distinct from the inline .msg.ok banners used elsewhere. */
  .toast { position:fixed; top:20px; right:20px; z-index:200; display:flex; align-items:center; gap:9px; background:#1E1F22; color:#fff; padding:13px 20px; border-radius:10px; font-size:.88rem; font-weight:600; box-shadow:0 12px 30px -8px rgba(0,0,0,.35); animation:toastIn .25s ease-out; }
  .toast.out { animation:toastOut .25s ease-in forwards; }
  .toast .check-icon { width:16px; height:16px; flex-shrink:0; background:#2F7D4F; border-radius:50%; padding:3px; box-sizing:border-box; }
  @keyframes toastIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
  @keyframes toastOut { to { opacity:0; transform:translateY(-10px); } }
  .pw-banner .pw-banner-links { display:flex; gap:16px; flex-shrink:0; }

  /* ---- Responsive: phone/tablet ---- */
  .nav-toggle { display:none; align-items:center; justify-content:center; width:44px; height:44px; margin-left:-8px; padding:0; border:none; background:none; border-radius:8px; cursor:pointer; color:var(--ink); flex-shrink:0; }
  .nav-toggle svg { width:22px; height:22px; }
  .nav-backdrop { position:fixed; inset:0; background:rgba(26,27,30,.45); z-index:55; opacity:0; pointer-events:none; transition:opacity .2s; }
  .nav-backdrop.show { opacity:1; pointer-events:auto; }
  .topbar-left { display:flex; align-items:center; gap:8px; min-width:0; }
  .topbar-left > div { min-width:0; }

  @media (max-width: 768px) {
    /* Sidebar becomes an off-canvas drawer, opened by the topbar hamburger */
    .nav-toggle { display:inline-flex; }
    .sidebar { position:fixed; top:0; bottom:0; left:0; z-index:60; width:min(300px, 85vw); transform:translateX(-105%); transition:transform .25s ease; overflow-y:auto; }
    .sidebar.open { transform:none; box-shadow:0 10px 40px rgba(0,0,0,.25); }
    body.nav-open { overflow:hidden; }
    .sidebar nav a { padding:13px 12px; font-size:.95rem; }
    .nav-tree a { padding:11px 10px; font-size:.88rem; }
    .sidebar-user .who a { display:inline-block; padding:4px 0; }

    .topbar { padding:12px 16px; gap:10px; }
    .topbar h1 { font-size:1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .topbar .btn.secondary { padding:10px 14px !important; }
    .content { padding:16px; }

    /* iOS Safari force-zooms the page when a focused field is under 16px */
    input[type=text], input[type=password], input[type=date], input[type=email], select, textarea { font-size:16px !important; }

    /* Tables scroll sideways inside the card instead of squishing */
    .tablecard { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .tablecard-head { position:sticky; left:0; }
    table.dt { min-width:600px; }
    .dt-actions a, .dt-actions button { display:inline-block; padding:10px 0; margin-right:16px; }

    .btn { padding:12px 18px; }
    .pagination { flex-wrap:wrap; }
    .pagination a, .pagination span { padding:10px 14px; }
    .hero-tabs button { padding:9px 16px; }

    /* Markdown toolbar: one swipeable row of finger-sized buttons that
       sticks to the top of the screen while writing */
    .mdbar { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; position:sticky; top:0; z-index:5; }
    .mdbar button { min-width:44px; min-height:44px; padding:8px 12px; font-size:.9rem; flex-shrink:0; }
    .mdbar .div { flex-shrink:0; }
    .urlask input[type=text] { width:min(220px, 55vw); }
    .editor-grid { gap:16px; }

    /* Save bar: editor-form actions pin to the bottom of the viewport so
       Salvar never needs a long scroll to reach */
    .editor-actions { position:fixed; bottom:0; left:0; right:0; z-index:40; display:flex; gap:10px; margin:0; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:var(--paper); border-top:1px solid var(--line); box-shadow:0 -6px 20px rgba(0,0,0,.06); }
    .editor-actions .btn { margin:0 !important; flex:1; justify-content:center; white-space:nowrap; }
    .content:has(.editor-actions) { padding-bottom:88px; }
  }

  @media (max-width: 480px) {
    .content { padding:12px; }
    .card { padding:16px; }
    .topbar { padding:10px 12px; }
    .topbar .meta { display:none; }
    .stats { gap:10px; grid-template-columns:repeat(auto-fit, minmax(96px, 1fr)); }
    .stat { padding:12px 14px 10px; }
    .stat .num { font-size:1.4rem; }
    .stat .lbl { font-size:.7rem; }
    .tablecard-head { padding:12px 14px; }
    .toast { left:12px; right:12px; }
    .pw-banner { flex-wrap:wrap; }
  }
</style>
<script>
// ---- Drag-and-drop image upload ----
// Enhances an image-URL text input (class "img-url") with a drop zone that
// POSTs to /acesso/upload.php and keeps a live thumbnail preview in sync.
// data-imgdrop-nothumb skips the thumbnail (when a bigger preview exists).
function imgDrop(input) {
  if (input.dataset.imgdrop) return; // idempotent: safe to re-init a container
  input.dataset.imgdrop = '1';
  var noThumb = input.hasAttribute('data-imgdrop-nothumb');
  input.style.display = 'none'; // the drop zone below IS the field now -- no raw URL shown

  var zone = document.createElement('div');
  zone.className = 'imgdrop';
  var img = document.createElement('img');
  img.alt = '';
  var msg = document.createElement('div');
  msg.className = 'imgdrop-msg';
  var err = document.createElement('p');
  err.className = 'imgdrop-err';
  err.style.display = 'none';
  var file = document.createElement('input');
  file.type = 'file';
  file.accept = 'image/*';
  file.style.display = 'none';
  zone.append(img, msg, file);
  input.after(zone, err);

  function render() {
    var url = input.value.trim();
    var show = url !== '' && !noThumb;
    zone.classList.toggle('has-img', show);
    img.style.display = show ? '' : 'none';
    if (show) img.src = url;
    msg.textContent = show ? 'Clique ou arraste para trocar' : 'Arraste uma imagem aqui ou clique para escolher';
  }

  function fail(text) {
    err.textContent = text;
    err.style.display = '';
    render();
  }

  function upload(f) {
    if (!f) return;
    err.style.display = 'none';
    msg.textContent = 'Enviando...';
    var fd = new FormData();
    fd.append('file', f);
    fetch('/acesso/upload.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j.url) { fail(res.j.error || 'Falha no upload.'); return; }
        input.value = res.j.url;
        // fires any oninput preview (renderHero etc.) the input already has
        input.dispatchEvent(new Event('input', { bubbles: true }));
        render();
        checkRatio(res.j.url);
      })
      .catch(function () { fail('Falha de rede ao enviar. Tente de novo.'); });
  }

  // Soft warning (doesn't block the upload) when the field has a known
  // consistent shape (data-imgdrop-ratio="W:H", e.g. square logos/headshots)
  // and the new image doesn't match it -- easy to miss otherwise since the
  // preview box crops/contains the image either way.
  function checkRatio(url) {
    var spec = input.getAttribute('data-imgdrop-ratio');
    if (!spec) return;
    var parts = spec.split(':').map(Number);
    var expected = parts[0] / parts[1];
    var probe = new Image();
    probe.onload = function () {
      var actual = probe.naturalWidth / probe.naturalHeight;
      if (Math.abs(actual - expected) / expected > 0.1) {
        err.textContent = 'Aviso: essa imagem é ' + probe.naturalWidth + '×' + probe.naturalHeight + ', as demais deste campo são ' + spec + '. Pode enviar mesmo assim, mas o enquadramento pode ficar diferente.';
        err.style.display = '';
      }
    };
    probe.src = url;
  }

  zone.addEventListener('click', function () { file.click(); });
  file.addEventListener('change', function () { upload(file.files[0]); file.value = ''; });
  zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('over'); });
  zone.addEventListener('dragleave', function () { zone.classList.remove('over'); });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.classList.remove('over');
    upload(e.dataTransfer.files[0]);
  });
  input.addEventListener('input', render);
  render();
}
function imgDropInit(root) {
  root.querySelectorAll('input.img-url').forEach(imgDrop);
}
document.addEventListener('DOMContentLoaded', function () { imgDropInit(document); });

// ---- "Ver ao vivo" -- watches the rebuild actually finish after a save ----
// The save itself only queues a rebuild (~1min cron); this polls a small
// timestamp file rebuild.sh writes on every successful publish and swaps the
// static "Salvo no banco." message for a live link once that build is newer
// than this save (?t=<serverUnixTime set at save-redirect time>).
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('savedMsg');
  if (!el) return;
  var liveUrl = el.getAttribute('data-live-url');
  var t = parseInt(new URLSearchParams(location.search).get('t') || '0', 10);
  if (!liveUrl || !t) return;

  var textEl = el.querySelector('.msg-text');
  var spin = document.createElement('span');
  spin.className = 'spin';
  el.prepend(spin);
  textEl.textContent = 'Salvo. Publicando no site...';

  var elapsed = 0;
  var iv = setInterval(function () {
    elapsed += 3000;
    fetch('/acesso/build-status.json?_=' + Date.now(), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data && data.builtAt >= t) {
          clearInterval(iv);
          spin.remove();
          textEl.innerHTML = 'Publicado! <a href="' + liveUrl + '" target="_blank" rel="noopener">Ver ao vivo →</a>';
        } else if (elapsed >= 120000) {
          clearInterval(iv);
          spin.remove();
          textEl.textContent = 'Salvo. Ainda publicando -- deve aparecer no site em instantes.';
        }
      })
      .catch(function () {});
  }, 3000);
});

// ---- Inline delete confirmation (replaces native confirm()) ----
// Delete buttons are type="button" with onclick="askConfirm(this)"; the first
// click swaps in "mensagem + sim / cancelar", the second actually submits.
function askConfirm(btn) {
  var box = document.createElement('span');
  box.className = 'ci';
  var m = document.createElement('span');
  m.className = 'ci-msg';
  m.textContent = btn.dataset.confirm || 'Tem certeza?';
  var yes = document.createElement('button');
  yes.type = 'button';
  yes.className = 'ci-yes';
  yes.textContent = btn.dataset.yes || 'sim, apagar';
  var no = document.createElement('button');
  no.type = 'button';
  no.className = 'ci-no';
  no.textContent = 'cancelar';
  yes.addEventListener('click', function () { btn.form.submit(); });
  no.addEventListener('click', function () { box.remove(); btn.style.display = ''; });
  box.append(m, yes, no);
  btn.style.display = 'none';
  btn.after(box);
}

// ---- Mobile nav drawer (sidebar is off-canvas below 768px) ----
function navDrawer(open) {
  document.querySelector('.sidebar').classList.toggle('open', open);
  document.getElementById('navBackdrop').classList.toggle('show', open);
  document.body.classList.toggle('nav-open', open);
}
document.addEventListener('DOMContentLoaded', function () {
  var sb = document.querySelector('.sidebar');
  if (sb) sb.addEventListener('click', function (e) { if (e.target.closest('a')) navDrawer(false); });
});

// ---- Inline URL ask (replaces native prompt(); used by the md toolbars) ----
function askUrl(anchor, cb) {
  var old = document.querySelector('.urlask');
  if (old) old.remove();
  var box = document.createElement('span');
  box.className = 'urlask';
  var inp = document.createElement('input');
  inp.type = 'text';
  inp.value = 'https://';
  var ok = document.createElement('button');
  ok.type = 'button';
  ok.textContent = 'OK';
  var no = document.createElement('button');
  no.type = 'button';
  no.textContent = '✕';
  ok.addEventListener('click', function () {
    var u = inp.value.trim();
    box.remove();
    if (u && u !== 'https://') cb(u);
  });
  no.addEventListener('click', function () { box.remove(); });
  inp.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); ok.click(); }
    if (e.key === 'Escape') box.remove();
  });
  box.append(inp, ok, no);
  anchor.after(box);
  inp.focus();
  inp.setSelectionRange(inp.value.length, inp.value.length);
}
</script>
</head>
<body>
<?php if ($toastMsg): ?>
<div class="toast" id="cmsToast"><?= cmsCheckIcon() ?><span><?= htmlspecialchars($toastMsg) ?></span></div>
<script>
  setTimeout(function () {
    var t = document.getElementById('cmsToast');
    if (!t) return;
    t.classList.add('out');
    setTimeout(function () { t.remove(); }, 250);
  }, 3000);
</script>
<?php endif; ?>
<?php if ($pwState === 'force'): ?>
<div class="pw-modal-backdrop">
  <div class="pw-modal">
    <h2>Troque sua senha</h2>
    <p>Por segurança, você precisa definir uma senha própria antes de continuar usando o painel.</p>
    <form method="post" action="/acesso/usuarios" style="margin-top:14px">
      <input type="hidden" name="action" value="password" />
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" />
      <label>Nova senha (mín. 8 caracteres)</label>
      <input type="password" name="new_password" minlength="8" required autofocus />
      <label>Confirmar nova senha</label>
      <input type="password" name="confirm_password" minlength="8" required />
      <button type="submit" class="btn" style="width:100%;margin-top:16px">Trocar senha</button>
    </form>
  </div>
</div>
<?php elseif ($pwState === 'remind'): ?>
<div class="pw-banner">
  <span>Você ainda está usando a senha inicial. Recomendamos trocar por uma sua.</span>
  <div class="pw-banner-links">
    <a href="/acesso/usuarios?edit=<?= (int)($_SESSION['cms_user_id'] ?? 0) ?>">trocar agora</a>
    <a href="?dismiss_pw_reminder=1">lembrar depois</a>
  </div>
</div>
<?php endif; ?>
<div class="nav-backdrop" id="navBackdrop" onclick="navDrawer(false)"></div>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <a class="logo-link" href="https://mclair.com.br/" target="_blank" rel="noopener">
        <img src="https://mclair.com.br/logos/logo-icone-M.png" alt="Mclair" />
        <span class="tip">ir para o site</span>
      </a>
      <div><strong>Mclair</strong><small>Painel de conteúdo</small></div>
    </div>
    <nav>
      <div class="nav-label">Conteúdo</div>
      <a href="/acesso/posts" class="<?= $active === 'blog' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h13v16H4z"/><path d="M8 8h5M8 12h5M8 16h3"/></svg>
        Posts do blog
      </a>
      <?php if (cmsRole() !== 'author'): ?>
      <?php $pagesAreaOpen = in_array($active, ['pages', 'cases', 'services'], true); ?>
      <a href="/acesso/paginas" class="<?= $active === 'pages' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Páginas
      </a>
      <?php if ($pagesAreaOpen): ?>
      <div class="nav-tree">
        <a href="/acesso/servicos" class="<?= $active === 'services' ? 'active' : '' ?>">Serviços</a>
        <a href="/acesso/casos" class="<?= $active === 'cases' ? 'active' : '' ?>">Cases</a>
        <a href="/acesso/paginas?slug=mentorias" class="<?= $active === 'pages' && ($_GET['slug'] ?? '') === 'mentorias' ? 'active' : '' ?>">Mentorias</a>
        <a href="/acesso/paginas?slug=sobre" class="<?= $active === 'pages' && ($_GET['slug'] ?? '') === 'sobre' ? 'active' : '' ?>">Sobre</a>
        <a href="/acesso/paginas?slug=clientes" class="<?= $active === 'pages' && ($_GET['slug'] ?? '') === 'clientes' ? 'active' : '' ?>">Clientes</a>
      </div>
      <?php endif; ?>
      <a href="/acesso/menu" class="<?= $active === 'menu' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        Menu
      </a>
      <div class="nav-label">Administração</div>
      <?php endif; ?>
      <?php if (cmsRole() === 'admin'): ?>
      <a href="/acesso/usuarios" class="<?= $active === 'users' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16.5 4.8a3.5 3.5 0 0 1 0 6.4M18 14.7c2.1.8 3.5 2.4 3.5 5.3"/></svg>
        Usuários
      </a>
      <?php endif; ?>
      <?php if (cmsRole() !== 'author'): ?>
      <a href="/acesso/configuracoes" class="<?= $active === 'settings' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Configurações
      </a>
      <?php endif; ?>
    </nav>
    <?php $meName = $_SESSION['cms_display_name'] ?? $_SESSION['cms_username'] ?? ''; ?>
    <?php $meHref = '/acesso/usuarios?edit=' . (int) ($_SESSION['cms_user_id'] ?? 0); ?>
    <div class="sidebar-user">
      <a class="avatar" href="<?= htmlspecialchars($meHref) ?>">
        <?php if (!empty($_SESSION['cms_avatar_url'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['cms_avatar_url']) ?>" alt="" />
        <?php else: ?>
        <?= htmlspecialchars(mb_strtoupper(mb_substr($meName ?: '?', 0, 1))) ?>
        <?php endif; ?>
      </a>
      <div class="who">
        <a href="<?= htmlspecialchars($meHref) ?>"><strong><?= htmlspecialchars($meName) ?></strong></a>
        <a href="/acesso/manual.php" target="_blank" rel="noopener">manual</a>
        <span style="color:var(--ink-3);font-size:.7rem">·</span>
        <a href="/acesso/posts?logout=1">sair do painel</a>
      </div>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <button type="button" class="nav-toggle" onclick="navDrawer(true)" aria-label="Abrir menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div>
          <?php if ($crumb): ?>
          <div class="breadcrumb"><a href="<?= htmlspecialchars($crumb['href']) ?>"><?= htmlspecialchars($crumb['label']) ?></a><span>/</span></div>
          <?php endif; ?>
          <h1><?= htmlspecialchars($title) ?></h1>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:14px">
        <?php if ($liveUrl): ?>
        <a class="btn secondary" href="<?= htmlspecialchars($liveUrl) ?>" target="_blank" rel="noopener" style="padding:7px 14px;font-size:.78rem">Ver no site ↗</a>
        <?php endif; ?>
        <div class="meta"><?= date('d/m/Y') ?></div>
      </div>
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
