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

if (!cmsCsrfValidate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.']);
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

// Toda foto enviada sai com até 200 KB, no mesmo formato e no mesmo nome
// (o endereço não muda): JPG/WEBP tentam qualidades e larguras decrescentes;
// PNG vira 256 cores e, se precisar, reduz a largura. Só troca o arquivo se
// o resultado ficar menor. Se o GD faltar ou falhar, o arquivo vai como
// veio: nunca é motivo para perder o upload.
const ALVO_BYTES = 200 * 1024;
const LARGURAS = [1920, 1600, 1366, 1200, 1024, 900, 800, 700];
const QUALIDADES = [80, 72, 64, 56];
function reduzirSePrecisar(string $caminho, string $mime): void {
    if (!function_exists('imagecreatefromjpeg') || $mime === 'image/gif' || $mime === 'image/svg+xml') return;
    $info = @getimagesize($caminho);
    if (!$info) return;
    clearstatcache();
    $original = filesize($caminho);
    if ($original <= ALVO_BYTES && max($info[0], $info[1]) <= LARGURAS[0]) return;
    @ini_set('memory_limit', '512M');
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($caminho),
        'image/png'  => @imagecreatefrompng($caminho),
        'image/webp' => @imagecreatefromwebp($caminho),
        default      => false,
    };
    if (!$img) return;
    // Fotos de celular vêm giradas por EXIF; endireita antes de reduzir.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($caminho)['Orientation'] ?? 1);
        if ($o === 3) $img = imagerotate($img, 180, 0);
        elseif ($o === 6) $img = imagerotate($img, -90, 0);
        elseif ($o === 8) $img = imagerotate($img, 90, 0);
    }
    $tmp = $caminho . '.tmp';
    $guardado = $caminho . '.melhor';
    $melhor = PHP_INT_MAX;
    foreach (LARGURAS as $larg) {
        $w = imagesx($img); $h = imagesy($img);
        $f = min(1, $larg / max($w, $h));
        $nw = max(1, (int) round($w * $f)); $nh = max(1, (int) round($h * $f));
        $r = imagecreatetruecolor($nw, $nh);
        if ($mime !== 'image/jpeg') { imagealphablending($r, false); imagesavealpha($r, true); }
        imagecopyresampled($r, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if ($mime === 'image/png') imagetruecolortopalette($r, true, 256);
        if ($mime === 'image/jpeg') imageinterlace($r, true);
        foreach ($mime === 'image/png' ? [0] : QUALIDADES as $q) {
            $ok = match ($mime) {
                'image/jpeg' => imagejpeg($r, $tmp, $q),
                'image/webp' => imagewebp($r, $tmp, $q),
                default      => imagepng($r, $tmp, 9),
            };
            clearstatcache();
            $t = $ok ? (int) @filesize($tmp) : 0;
            if ($t > 0 && $t < $melhor) { $melhor = $t; rename($tmp, $guardado); }
            if ($t > 0 && $t <= ALVO_BYTES) break 2;
        }
    }
    @unlink($tmp);
    if ($melhor < $original && is_file($guardado)) rename($guardado, $caminho); else @unlink($guardado);
}

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
} else {
    reduzirSePrecisar($dest, $mime);
}

// Dimensões finais: o editor grava no post para o site reservar o espaço
// da imagem antes de ela carregar.
$dim = $mime === 'image/svg+xml' ? false : @getimagesize($dest);
echo json_encode([
    'url'    => '/uploads/' . $name,
    'width'  => $dim ? (int) $dim[0] : null,
    'height' => $dim ? (int) $dim[1] : null,
    'bytes'  => (int) filesize($dest),
]);
