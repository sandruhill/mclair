<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
cmsRequireRole(['admin', 'editor']);

header('Content-Type: application/json; charset=utf-8');

// Every action here mutates -- the endpoint is POST-only in practice.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cmsCsrfValidate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.']);
    exit;
}

$pdo = cmsDb();
$stmt = $pdo->prepare('SELECT data FROM cmstest_singletons WHERE slug = ?');
$stmt->execute(['clientes']);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

$data = json_decode($row['data'], true);
$clients = $data['clients'] ?? [];

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $index = $_POST['index'] ?? '';
    $item = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'logo' => trim((string) ($_POST['logo'] ?? '')),
        'case' => trim((string) ($_POST['case'] ?? '')),
    ];
    if ($item['name'] === '') { http_response_code(400); echo json_encode(['error' => 'Nome é obrigatório.']); exit; }
    if ($index === '' || !isset($clients[(int) $index])) {
        $clients[] = $item;
    } else {
        $clients[(int) $index] = $item;
    }
} elseif ($action === 'add_many') {
    $items = json_decode($_POST['items'] ?? '[]', true) ?: [];
    foreach ($items as $it) {
        $name = trim((string) ($it['name'] ?? ''));
        $logo = trim((string) ($it['logo'] ?? ''));
        if ($name === '' || $logo === '') continue;
        $clients[] = ['name' => $name, 'logo' => $logo, 'case' => ''];
    }
} elseif ($action === 'delete') {
    $index = (int) ($_POST['index'] ?? -1);
    if (isset($clients[$index])) array_splice($clients, $index, 1);
} elseif ($action === 'delete_many') {
    $indexes = json_decode($_POST['indexes'] ?? '[]', true) ?: [];
    rsort($indexes);
    foreach ($indexes as $i) {
        if (isset($clients[(int) $i])) array_splice($clients, (int) $i, 1);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'invalid action']);
    exit;
}

$data['clients'] = array_values($clients);
$pdo->prepare('UPDATE cmstest_singletons SET data = ? WHERE slug = ?')
    ->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'clientes']);
queueRebuild();

echo json_encode(['ok' => true, 'clients' => $data['clients']]);
