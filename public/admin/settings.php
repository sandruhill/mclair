<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
$pdo = cmsDb();

$stmt = $pdo->prepare('SELECT data FROM cmstest_singletons WHERE slug = ?');
$stmt->execute(['configuracoes']);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); die('Configurações não encontradas'); }

$LABELS = [
    'nomeAgencia'  => 'Nome da agência',
    'email'        => 'E-mail',
    'whatsapp'     => 'WhatsApp',
    'instagram'    => 'Instagram',
    'linkedin'     => 'LinkedIn',
    'siteUrl'      => 'URL do site',
    'corPrincipal' => 'Cor principal',
    'fundadoEm'    => 'Fundado em',
    'idioma'       => 'Idioma',
];

$config = json_decode($row['data'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = [];
    foreach ($config as $key => $orig) {
        $new[$key] = (string) ($_POST['f'][$key] ?? $orig);
    }
    $data = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')->execute([$data, 'configuracoes']);
    queueRebuild();
    header('Location: configuracoes?saved=1');
    exit;
}

adminLayoutTop('settings', 'Configurações');
?>

<?php if (isset($_GET['saved'])): ?><div class="msg ok">Salvo no banco.</div><?php endif; ?>

<div class="card" style="max-width:520px">
<form method="post">
  <?php $first = true; foreach ($config as $key => $val): ?>
    <label<?= $first ? ' style="margin-top:0"' : '' ?>><?= htmlspecialchars($LABELS[$key] ?? $key) ?></label>
    <?php if ($key === 'corPrincipal'): ?>
      <input type="text" name="f[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string) $val) ?>" style="max-width:140px" />
    <?php else: ?>
      <input type="text" name="f[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string) $val) ?>" />
    <?php endif; ?>
  <?php $first = false; endforeach; ?>
  <button type="submit" class="btn" style="margin-top:16px">Salvar</button>
</form>
</div>

<?php adminLayoutBottom(); ?>
