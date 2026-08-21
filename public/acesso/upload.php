<?php
require_once __DIR__ . '/auth.php';

// Uploaded files are saved to public_html/uploads/, a sibling of this admin/
// directory that is deliberately excluded from the rebuild's rsync --delete
// (see ~/mclair-build/rebuild.sh on the server) so they survive every publish.
$uploadDir = __DIR__ . '/../uploads';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Falha no upload.']);
    exit;
}

const MAX_BYTES = 8 * 1024 * 1024; // 8MB
if ($file['size'] > MAX_BYTES) {
    http_response_code(400);
    echo json_encode(['error' => 'Arquivo muito grande (máx. 8MB).']);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/svg+xml' => 'svg',
];
$mime = mime_content_type($file['tmp_name']);
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato não suportado. Use JPG, PNG, WEBP, GIF ou SVG.']);
    exit;
}

// SVG is XML, and a malicious one can carry <script>/on*= handlers that run
// as this origin's JS if someone opens the file URL directly (stored XSS).
// Strip anything executable before it ever touches disk.
function sanitizeSvg(string $svg): ?string {
    $dom = new DOMDocument();
    $prevErrors = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_use_internal_errors($prevErrors);
    if (!$ok || $dom->documentElement === null || strtolower($dom->documentElement->localName) !== 'svg') return null;

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script | //*[local-name()="foreignObject"]') as $node) {
        $node->parentNode->removeChild($node);
    }
    foreach ($xpath->query('//@*') as $attr) {
        $lname = strtolower($attr->nodeName);
        $val = trim($attr->nodeValue);
        if (str_starts_with($lname, 'on') || (in_array($lname, ['href', 'xlink:href'], true) && stripos($val, 'javascript:') === 0)) {
            $attr->ownerElement->removeAttributeNode($attr);
        }
    }
    return $dom->saveXML($dom->documentElement);
}

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = $allowed[$mime];
$name = bin2hex(random_bytes(8)) . '.' . $ext;
$dest = $uploadDir . '/' . $name;

if ($mime === 'image/svg+xml') {
    $clean = sanitizeSvg((string) file_get_contents($file['tmp_name']));
    if ($clean === null) {
        http_response_code(400);
        echo json_encode(['error' => 'SVG inválido.']);
        exit;
    }
    file_put_contents($dest, $clean);
} elseif (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o arquivo.']);
    exit;
}

echo json_encode(['url' => '/uploads/' . $name]);
