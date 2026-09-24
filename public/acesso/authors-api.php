<?php
// Salva um autor (criar ou editar) a partir do modal. Chamado por fetch()
// tanto da tela /acesso/autores quanto do "+ novo autor" dentro do editor de
// post, por isso responde JSON e nunca redireciona. Qualquer usuário logado
// pode criar ou editar; remover é só na tela de autores (admin/editor).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function apiErro(int $status, string $msg): void {
    http_response_code($status);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiErro(405, 'Método inválido.');
if (!cmsCsrfValidate($_POST['csrf'] ?? '')) apiErro(403, 'Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
if (($_POST['action'] ?? '') !== 'save') apiErro(400, 'Ação desconhecida.');

$pdo = cmsDb();
if (!cmsAuthorsReady($pdo)) apiErro(503, CMS_AUTHORS_MIGRATION_HINT);

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$jobTitle = trim((string) ($_POST['job_title'] ?? ''));
$photo = trim((string) ($_POST['photo_url'] ?? ''));
$bioShort = trim(str_replace("\r\n", "\n", (string) ($_POST['bio_short'] ?? '')));
$bioLong = trim(str_replace("\r\n", "\n", (string) ($_POST['bio_long'] ?? '')));

if (mb_strlen($name) < 2) apiErro(422, 'Informe o nome do autor.');
if (mb_strlen($name) > 190) apiErro(422, 'O nome passou de 190 caracteres.');
if (mb_strlen($jobTitle) > 190) apiErro(422, 'O cargo passou de 190 caracteres.');
if (mb_strlen($bioShort) > 500) apiErro(422, 'A bio curta passou de 500 caracteres. Use até 300 para a assinatura caber bem.');
if (mb_strlen($bioLong) > 20000) apiErro(422, 'A bio completa está grande demais.');

// Foto: só o que o upload.php do painel devolve (/uploads/...) ou uma URL
// https completa. Nada de caminho arbitrário do servidor.
if ($photo !== '' && !preg_match('#^(/uploads/[A-Za-z0-9._-]+|https://\S+)$#', $photo)) {
    apiErro(422, 'A foto precisa vir do upload do painel (/uploads/...) ou ser um endereço https.');
}
if (mb_strlen($photo) > 255) apiErro(422, 'Endereço da foto grande demais.');

// Áreas de especialidade: texto separado por vírgula -> lista sem repetição.
$topics = [];
foreach (explode(',', (string) ($_POST['topics'] ?? '')) as $t) {
    $t = trim($t);
    if ($t === '' || mb_strlen($t) > 60) continue;
    if (!in_array($t, $topics, true)) $topics[] = $t;
    if (count($topics) >= 12) break;
}

// Links: pares tipo/URL. URL sem esquema ganha https://, e cada uma passa
// por validação real; um link quebrado na assinatura de todo artigo é pior
// do que nenhum.
$tipos = cmsAuthorSocialTypes();
$links = [];
$tipoPost = (array) ($_POST['social_type'] ?? []);
$urlPost = (array) ($_POST['social_url'] ?? []);
foreach ($urlPost as $i => $u) {
    $u = trim((string) $u);
    if ($u === '') continue;
    if (!preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
    if (mb_strlen($u) > 300 || !filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https?://[^/\s]+\.[^/\s]+#i', $u)) {
        apiErro(422, 'Link inválido: ' . $u . '. Cole o endereço completo, como https://www.linkedin.com/in/nome.');
    }
    $tipo = (string) ($tipoPost[$i] ?? 'outro');
    if (!isset($tipos[$tipo])) $tipo = 'outro';
    $links[] = ['type' => $tipo, 'url' => $u];
    if (count($links) >= 8) break;
}

$topicsJson = json_encode($topics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$linksJson = json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$nulo = fn(string $s) => $s === '' ? null : $s;

if ($id > 0) {
    if (!cmsAuthorById($pdo, $id)) apiErro(404, 'Autor não encontrado.');
    // O slug nunca muda numa edição: é a URL pública do perfil, e o Google já
    // pode ter indexado.
    $stmt = $pdo->prepare('UPDATE cmstest_blog_authors SET name = ?, job_title = ?, photo_url = ?, bio_short = ?, bio_long = ?, topics = ?, social_links = ? WHERE id = ?');
    $stmt->execute([$name, $nulo($jobTitle), $nulo($photo), $nulo($bioShort), $nulo($bioLong), $topicsJson, $linksJson, $id]);
    // O nome exibido nos posts acompanha o autor.
    $pdo->prepare('UPDATE cmstest_blog_posts SET author = ? WHERE blog_author_id = ?')->execute([$name, $id]);
} else {
    $base = cmsSlugify($name);
    if ($base === '') $base = 'autor';
    $check = $pdo->prepare('SELECT 1 FROM cmstest_blog_authors WHERE slug = ?');
    $slug = $base;
    for ($n = 2; $n <= 200; $n++) {
        $check->execute([$slug]);
        if (!$check->fetchColumn()) break;
        $slug = $base . '-' . $n;
    }
    $stmt = $pdo->prepare('INSERT INTO cmstest_blog_authors (slug, name, job_title, photo_url, bio_short, bio_long, topics, social_links, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$slug, $name, $nulo($jobTitle), $nulo($photo), $nulo($bioShort), $nulo($bioLong), $topicsJson, $linksJson, (int) $_SESSION['cms_user_id']]);
    $id = (int) $pdo->lastInsertId();
}

queueRebuild();
echo json_encode(['ok' => true, 'author' => cmsAuthorById($pdo, $id)], JSON_UNESCAPED_UNICODE);
