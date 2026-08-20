<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$pdo = cmsDb();
$posts = $pdo->query('SELECT slug, title, post_date, category FROM cmstest_blog_posts ORDER BY post_date DESC LIMIT 50')->fetchAll();
$counts = $pdo->query("
  SELECT 'blog_posts' t, COUNT(*) n FROM cmstest_blog_posts
  UNION SELECT 'services', COUNT(*) FROM cmstest_services
  UNION SELECT 'cases', COUNT(*) FROM cmstest_cases
  UNION SELECT 'singletons', COUNT(*) FROM cmstest_singletons
")->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex" />
<title>CMS Teste — Mclair</title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; background: #F1EBDD; color: #211B14; }
  h1 { color: #C8102E; }
  .counts { display: flex; gap: 16px; margin-bottom: 30px; }
  .count { background: #fff; padding: 12px 20px; border-radius: 8px; border: 1px solid #D6C9A8; }
  .count b { display: block; font-size: 1.4rem; color: #C8102E; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
  th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
  th { background: #211B14; color: #fff; }
  a { color: #C8102E; }
</style>
</head>
<body>
<h1>CMS Teste — banco de dados isolado</h1>
<p>Logado como <strong><?= htmlspecialchars($_SESSION['cms_username']) ?></strong> ·
  <a href="cases.php">Cases</a> ·
  <a href="services.php">Serviços</a> ·
  <a href="users.php">Usuários</a>
</p>

<div class="counts">
<?php foreach ($counts as $c): ?>
  <div class="count"><b><?= htmlspecialchars($c['n']) ?></b><?= htmlspecialchars($c['t']) ?></div>
<?php endforeach; ?>
</div>

<h2>Posts do blog (últimos 50, vindos do banco)</h2>
<table>
<tr><th>Data</th><th>Título</th><th>Categoria</th><th></th></tr>
<?php foreach ($posts as $p): ?>
<tr>
  <td><?= htmlspecialchars($p['post_date']) ?></td>
  <td><?= htmlspecialchars($p['title']) ?></td>
  <td><?= htmlspecialchars($p['category']) ?></td>
  <td><a href="edit.php?slug=<?= urlencode($p['slug']) ?>">editar</a></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
