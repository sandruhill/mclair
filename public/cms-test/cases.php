<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$pdo = cmsDb();
$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slug) {
    $stmt = $pdo->prepare('UPDATE cmstest_cases SET client = ?, sector = ?, challenge = ?, solution = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$_POST['client'], $_POST['sector'], $_POST['challenge'], $_POST['solution'], $_SESSION['cms_user_id'], $slug]);
    header('Location: cases.php?slug=' . urlencode($slug) . '&saved=1');
    exit;
}

if ($slug) {
    $stmt = $pdo->prepare('
      SELECT c.*, u.username AS updated_by_name
      FROM cmstest_cases c LEFT JOIN cmstest_users u ON u.id = c.updated_by
      WHERE c.slug = ?
    ');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); die('Case não encontrado'); }
}
$list = $pdo->query('SELECT slug, client, sector, num FROM cmstest_cases ORDER BY num')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" /><meta name="robots" content="noindex" />
<title>Cases — CMS Teste</title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; background: #F1EBDD; color: #211B14; }
  h1 { color: #C8102E; font-size: 1.3rem; }
  a.back { display: inline-block; margin-bottom: 16px; color: #C8102E; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
  th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
  th { background: #211B14; color: #fff; }
  label { display: block; font-weight: 700; margin: 14px 0 5px; font-size: 0.8rem; }
  input, textarea { width: 100%; padding: 9px; border: 1px solid #D6C9A8; border-radius: 6px; font-family: inherit; box-sizing: border-box; }
  textarea { min-height: 120px; }
  button { margin-top: 14px; background: #C8102E; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; }
  .saved { background: #2F7D4F; color: #fff; padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
</style>
</head>
<body>
<a class="back" href="index.php">&larr; voltar</a>

<?php if (!$slug): ?>
  <h1>Cases</h1>
  <table>
  <tr><th>#</th><th>Cliente</th><th>Setor</th><th></th></tr>
  <?php foreach ($list as $c): ?>
  <tr>
    <td><?= htmlspecialchars($c['num']) ?></td>
    <td><?= htmlspecialchars($c['client']) ?></td>
    <td><?= htmlspecialchars($c['sector']) ?></td>
    <td><a href="cases.php?slug=<?= urlencode($c['slug']) ?>">editar</a></td>
  </tr>
  <?php endforeach; ?>
  </table>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?><div class="saved">Salvo no banco.</div><?php endif; ?>
  <h1>Editando case: <?= htmlspecialchars($item['client']) ?></h1>
  <p style="font-size:.85rem;color:#665D4D">
    Logado como <strong><?= htmlspecialchars($_SESSION['cms_username']) ?></strong>
    <?php if ($item['updated_by_name']): ?> · última edição por <strong><?= htmlspecialchars($item['updated_by_name']) ?></strong><?php endif; ?>
  </p>
  <form method="post">
    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>" />
    <label>Cliente</label>
    <input type="text" name="client" value="<?= htmlspecialchars($item['client']) ?>" />
    <label>Setor</label>
    <input type="text" name="sector" value="<?= htmlspecialchars($item['sector']) ?>" />
    <label>Desafio</label>
    <textarea name="challenge"><?= htmlspecialchars($item['challenge']) ?></textarea>
    <label>Solução</label>
    <textarea name="solution"><?= htmlspecialchars($item['solution']) ?></textarea>
    <button type="submit">Salvar</button>
  </form>
<?php endif; ?>
</body>
</html>
