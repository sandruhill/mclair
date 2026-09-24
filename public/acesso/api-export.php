<?php
// Read-only JSON export for build-time consumption (Astro fetches this over
// HTTPS instead of connecting to MySQL directly -- keeps the database
// closed to local-only connections, no port opened to the internet).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_SERVER['HTTP_X_EXPORT_TOKEN'] ?? '';
if (!hash_equals(CMS_EXPORT_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$pdo = cmsDb();
$type = $_GET['type'] ?? 'blog_posts';

$allowed = [
    'blog_posts' => 'SELECT * FROM cmstest_blog_posts ORDER BY post_date DESC',
    // Tabela criada por db/migrations/2026-09-25-blog-autores.sql. Se ainda não
    // existe, devolve [] (tratado abaixo) para o build seguir sem assinaturas.
    'blog_authors' => 'SELECT a.*, (SELECT COUNT(*) FROM cmstest_blog_posts p WHERE p.blog_author_id = a.id) AS post_count FROM cmstest_blog_authors a ORDER BY a.name',
    'services'   => 'SELECT * FROM cmstest_services ORDER BY num',
    'cases'      => 'SELECT * FROM cmstest_cases ORDER BY num',
    'singletons' => 'SELECT * FROM cmstest_singletons',
    'menu'       => 'SELECT id, parent_id, label, link_type, link_value, sort_order FROM cmstest_menu ORDER BY parent_id IS NOT NULL, sort_order',
];

if (!isset($allowed[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid type']);
    exit;
}

try {
    $rows = $pdo->query($allowed[$type])->fetchAll();
} catch (PDOException $e) {
    // Só o tipo novo pode faltar (migração ainda não rodada); os demais
    // continuam falhando alto, como antes.
    if ($type !== 'blog_authors') throw $e;
    $rows = [];
}

// JSON columns come back as strings from PDO -- decode them so the
// consumer gets real arrays/objects, not double-encoded JSON strings.
$jsonCols = ['keywords', 'about_topics', 'faq_items', 'items', 'results', 'tags', 'gallery', 'data', 'topics', 'social_links'];
foreach ($rows as &$row) {
    foreach ($jsonCols as $col) {
        if (isset($row[$col])) {
            $decoded = json_decode($row[$col], true);
            if ($decoded !== null) $row[$col] = $decoded;
        }
    }
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
