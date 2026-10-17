<?php
/**
 * Plano de 12 meses: o corpo da página /dossie-seo-2026/ (o dossiê de setembro fica no anexo).
 *
 * Só funciona incluído pelo index.php, que já abriu a conexão e a sessão.
 * Aberto a qualquer um com o link, como o dossiê, com duas exceções:
 *   - mudar o estado de uma tarefa ou apagar uma resposta pede a chave de
 *     edição (?k=CHAVE). A chave NÃO fica no repositório: vem da constante
 *     DOSSIE_CHAVE (config.php), da variável de ambiente DOSSIE_CHAVE ou do
 *     hash sha256 gravado em dossie_config (nome = 'chave_edicao_sha256');
 *   - responder é livre, mas com token por hora, armadilha para robô, limite
 *     por IP e conferência do tipo real de cada arquivo.
 *
 * Estado, respostas e limites ficam no MySQL e os anexos ficam FORA do
 * public_html (~/dossie-anexos), porque o deploy roda `rsync -a --delete`
 * sobre o public_html e apagaria qualquer arquivo gravado ali. Anexo só sai
 * por download (?plano=baixar), nunca servido nem executado direto.
 */

declare(strict_types=1);

if (!defined('DOSSIE_PLANO')) {
    http_response_code(404);
    exit;
}

const PLANO_BASE          = '/dossie-seo-2026/';
const PLANO_ESTADOS       = ['todo', 'doing', 'done', 'blocked'];
const PLANO_ID_RE         = '/^[a-z][a-z0-9_]{1,11}$/';
const PLANO_MAX_ARQUIVOS  = 3;
const PLANO_MAX_BYTES     = 10 * 1024 * 1024;
const PLANO_MAX_TEXTO     = 5000;
const PLANO_MAX_NOME      = 80;
const PLANO_ENVIOS_HORA   = 10;          // por IP
const PLANO_ENVIOS_GERAL  = 60;          // todos os IPs somados, por hora
const PLANO_MAX_POR_TAREFA = 100;        // respostas guardadas em uma tarefa
const PLANO_MAX_DISCO     = 1024 * 1024 * 1024; // anexos somados, no máximo 1 GB
const PLANO_CHAVE_FALHAS  = 20;          // tentativas erradas de chave por IP e hora
const PLANO_AVISO_INTERVALO = 600;       // no máximo um e-mail de aviso a cada 10 minutos
const PLANO_SCHEMA_VERSAO = '1';

// Extensão permitida => tipos que o finfo pode devolver para ela.
const PLANO_TIPOS = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'txt'  => ['text/plain'],
];

/* ---------------------------------------------------------------- dados -- */

function planoSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_config (
        nome  VARCHAR(64) NOT NULL PRIMARY KEY,
        valor VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_plano_estado (
        tarefa        VARCHAR(12) NOT NULL PRIMARY KEY,
        estado        VARCHAR(10) NOT NULL,
        atualizado_em DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_plano_resposta (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo    CHAR(16) NOT NULL,
        tarefa    VARCHAR(12) NOT NULL,
        nome      VARCHAR(80) NOT NULL,
        texto     TEXT NOT NULL,
        criado_em DATETIME NOT NULL,
        UNIQUE (codigo),
        INDEX tarefa_criado (tarefa, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_plano_anexo (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo      CHAR(24) NOT NULL,
        resposta_id INT UNSIGNED NOT NULL,
        nome        VARCHAR(190) NOT NULL,
        ext         VARCHAR(8) NOT NULL,
        mime        VARCHAR(127) NOT NULL,
        bytes       INT UNSIGNED NOT NULL,
        UNIQUE (codigo),
        INDEX resposta (resposta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_plano_limite (
        chave CHAR(64) NOT NULL,
        hora  INT UNSIGNED NOT NULL,
        n     INT UNSIGNED NOT NULL,
        PRIMARY KEY (chave, hora)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function planoConfig(PDO $pdo, string $nome): ?string
{
    $stmt = $pdo->prepare('SELECT valor FROM dossie_config WHERE nome = ?');
    $stmt->execute([$nome]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string) $v;
}

/** Segredo dos tokens e do limite por IP: gerado na primeira execução e guardado no banco. */
function planoSegredo(PDO $pdo): string
{
    $s = planoConfig($pdo, 'segredo_formulario');
    if ($s !== null && strlen($s) >= 32) {
        return $s;
    }
    try {
        $pdo->prepare('INSERT INTO dossie_config (nome, valor) VALUES (?, ?)')
            ->execute(['segredo_formulario', bin2hex(random_bytes(32))]);
    } catch (PDOException $e) {
        // Outra requisição gravou primeiro: vale a dela.
    }
    $s = planoConfig($pdo, 'segredo_formulario');
    if ($s === null) {
        throw new RuntimeException('dossie_config sem segredo_formulario');
    }
    return $s;
}

/**
 * A chave de edição confere? Ordem: constante, variável de ambiente, hash no
 * banco. Tentativa errada conta no limite do IP; passou do teto, nem confere.
 */
function planoChaveOk(PDO $pdo, string $k): bool
{
    static $cache = [];
    if ($k === '' || strlen($k) > 200) {
        return false;
    }
    if (isset($cache[$k])) {
        return $cache[$k];
    }
    $balde = 'chave|' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '0'));
    if (planoContagem($pdo, $balde) >= PLANO_CHAVE_FALHAS) {
        return $cache[$k] = false;
    }
    $ok = planoChaveConfere($pdo, $k);
    if (!$ok) {
        planoConta($pdo, $balde, PHP_INT_MAX);
    }
    return $cache[$k] = $ok;
}

function planoChaveConfere(PDO $pdo, string $k): bool
{
    if (defined('DOSSIE_CHAVE') && is_string(DOSSIE_CHAVE) && DOSSIE_CHAVE !== '') {
        return hash_equals(DOSSIE_CHAVE, $k);
    }
    $env = getenv('DOSSIE_CHAVE');
    if (is_string($env) && $env !== '') {
        return hash_equals($env, $k);
    }
    $hash = planoConfig($pdo, 'chave_edicao_sha256');
    if ($hash === null || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
        return false;
    }
    return hash_equals($hash, hash('sha256', $k));
}

/** Ids das tarefas, lidos do próprio plano.js para o servidor só aceitar tarefa que existe. */
function planoIds(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $js = @file_get_contents(__DIR__ . '/plano.js');
    preg_match_all('/\{id:"([a-z][a-z0-9_]{1,11})"/', (string) $js, $m);
    $ids = array_fill_keys($m[1], true);
    return $ids;
}

function planoTarefaOk(string $id): bool
{
    return preg_match(PLANO_ID_RE, $id) === 1 && isset(planoIds()[$id]);
}

function planoAgora(): string
{
    return gmdate('Y-m-d H:i:s');
}

function planoIso(string $utc): string
{
    return str_replace(' ', 'T', $utc) . 'Z';
}

/** Na primeira execução, o estado do quadro antigo vira o estado inicial. */
function planoSemeiaEstado(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM dossie_plano_estado')->fetchColumn() > 0) {
        return;
    }
    $json = json_decode((string) @file_get_contents(__DIR__ . '/dados/estado-inicial.json'), true);
    if (!is_array($json) || !$json) {
        return;
    }
    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO dossie_plano_estado (tarefa, estado, atualizado_em) VALUES (?, ?, ?)');
        foreach ($json as $id => $d) {
            $id = (string) $id;
            $st = is_array($d) ? (string) ($d['status'] ?? '') : '';
            if (!planoTarefaOk($id) || !in_array($st, PLANO_ESTADOS, true)) {
                continue;
            }
            $t = strtotime((string) ($d['updatedAt'] ?? '')) ?: time();
            $ins->execute([$id, $st, gmdate('Y-m-d H:i:s', $t)]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        // Duas primeiras visitas ao mesmo tempo: a outra já semeou.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function planoEstado(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SELECT tarefa, estado, atualizado_em FROM dossie_plano_estado') as $r) {
        $out[(string) $r['tarefa']] = ['status' => (string) $r['estado'], 'updatedAt' => planoIso((string) $r['atualizado_em'])];
    }
    return $out;
}

function planoSalvaEstado(PDO $pdo, string $id, string $estado, string $quando): void
{
    // Apaga e grava na mesma transação: funciona igual no MariaDB e no SQLite de teste.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM dossie_plano_estado WHERE tarefa = ?')->execute([$id]);
        $pdo->prepare('INSERT INTO dossie_plano_estado (tarefa, estado, atualizado_em) VALUES (?, ?, ?)')
            ->execute([$id, $estado, $quando]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ------------------------------------------------------------ respostas -- */

function planoH(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function planoDirAnexos(): string
{
    if (defined('DOSSIE_ANEXOS_DIR') && is_string(DOSSIE_ANEXOS_DIR) && DOSSIE_ANEXOS_DIR !== '') {
        return rtrim(DOSSIE_ANEXOS_DIR, '/');
    }
    $env = getenv('DOSSIE_ANEXOS_DIR');
    if (is_string($env) && $env !== '') {
        return rtrim($env, '/');
    }
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $u = posix_getpwuid(posix_geteuid());
        if (is_array($u) && !empty($u['dir']) && $u['dir'] !== '/') {
            return rtrim((string) $u['dir'], '/') . '/dossie-anexos';
        }
    }
    error_log('dossie-plano: pasta de anexos sem HOME; defina DOSSIE_ANEXOS_DIR');
    return '';
}

/** Cria a pasta de anexos se faltar, fechada para outros usuários. */
function planoPreparaDir(): ?string
{
    $dir = planoDirAnexos();
    if ($dir === '') {
        return null;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('dossie-plano: não deu para criar ' . $dir);
        return null;
    }
    @chmod($dir, 0700);
    return is_writable($dir) ? $dir : null;
}

/** Token do formulário: vale a hora atual e a anterior. */
function planoToken(string $segredo, int $desfase = 0): string
{
    return hash_hmac('sha256', 'resposta|' . (intdiv(time(), 3600) - $desfase), $segredo);
}

function planoTokenOk(string $segredo, string $t): bool
{
    return $t !== '' && (hash_equals(planoToken($segredo, 0), $t) || hash_equals(planoToken($segredo, 1), $t));
}

/** Quantas vezes o balde já contou nesta hora. */
function planoContagem(PDO $pdo, string $balde): int
{
    $chave = hash('sha256', $balde);
    $sel = $pdo->prepare('SELECT n FROM dossie_plano_limite WHERE chave = ? AND hora = ?');
    $sel->execute([$chave, intdiv(time(), 3600)]);
    return (int) $sel->fetchColumn();
}

/** Conta mais um no balde desta hora, se ainda estiver abaixo do teto. */
function planoConta(PDO $pdo, string $balde, int $teto): bool
{
    $hora  = intdiv(time(), 3600);
    $chave = hash('sha256', $balde);
    $pdo->prepare('DELETE FROM dossie_plano_limite WHERE hora < ?')->execute([$hora]);
    $sel = $pdo->prepare('SELECT n FROM dossie_plano_limite WHERE chave = ? AND hora = ?');
    $sel->execute([$chave, $hora]);
    $n = $sel->fetchColumn();
    if ($n !== false && (int) $n >= $teto) {
        return false;
    }
    if ($n === false) {
        try {
            $pdo->prepare('INSERT INTO dossie_plano_limite (chave, hora, n) VALUES (?, ?, 1)')->execute([$chave, $hora]);
            return true;
        } catch (PDOException $e) {
            // Corrida com outro envio: cai no UPDATE.
        }
    }
    $upd = $pdo->prepare('UPDATE dossie_plano_limite SET n = n + 1 WHERE chave = ? AND hora = ? AND n < ?');
    $upd->execute([$chave, $hora, $teto]);
    return $upd->rowCount() > 0;
}

/** Limite de envios: por IP e, somando todos, por hora. */
function planoLimiteOk(PDO $pdo, string $segredo): bool
{
    $ip = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '0'), $segredo);
    if (planoContagem($pdo, 'geral') >= PLANO_ENVIOS_GERAL) {
        return false;
    }
    return planoConta($pdo, 'ip|' . $ip, PLANO_ENVIOS_HORA) && planoConta($pdo, 'geral', PLANO_ENVIOS_GERAL);
}

/** Bytes de anexos já guardados. */
function planoDiscoUsado(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(SUM(bytes), 0) FROM dossie_plano_anexo')->fetchColumn();
}

/** Uma linha, sem quebra nem caractere de controle: nada entra em cabeçalho. */
/** Caracteres invisíveis que enganam na tela (bidi, largura zero). */
const PLANO_INVISIVEIS = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

function planoLinha(string $s, int $max): string
{
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = preg_replace(PLANO_INVISIVEIS, '', $s) ?? '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

/** Texto livre: mantém quebra de linha e tab, tira o resto dos caracteres de controle. */
function planoTextoLimpo(string $s, int $max): string
{
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace(PLANO_INVISIVEIS, '', $s) ?? '';
    $s = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function planoNomeArquivo(string $n): string
{
    if (!mb_check_encoding($n, 'UTF-8')) {
        $n = mb_convert_encoding($n, 'UTF-8', 'UTF-8');
    }
    $n = basename(str_replace('\\', '/', $n));
    $n = preg_replace(PLANO_INVISIVEIS, '', $n) ?? '';
    $n = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $n) ?? 'arquivo';
    $n = trim($n, " .\t");
    $n = mb_substr($n, 0, 120);
    return $n === '' ? 'arquivo' : $n;
}

/** $_FILES['arquivos'] (múltiplo) em lista. */
function planoListaArquivos(): array
{
    $f = $_FILES['arquivos'] ?? null;
    if (!is_array($f) || !is_array($f['name'] ?? null)) {
        return [];
    }
    $out = [];
    foreach ($f['name'] as $i => $nome) {
        $erro = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'  => (string) $nome,
            'tmp'   => (string) ($f['tmp_name'][$i] ?? ''),
            'error' => $erro,
            'size'  => (int) ($f['size'][$i] ?? 0),
        ];
    }
    return $out;
}

/** O arquivo é mesmo do tipo que a extensão diz? */
function planoTipoOk(string $tmp, string $ext, string &$mime): bool
{
    if (!isset(PLANO_TIPOS[$ext])) {
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    if (!in_array($mime, PLANO_TIPOS[$ext], true)) {
        return false;
    }
    // Um .docx/.xlsx que o finfo vê só como zip precisa trazer a estrutura do Office.
    if ($ext === 'docx' || $ext === 'xlsx') {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            return false;
        }
        $parte = $ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
        $ok = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($parte) !== false;
        // Arquivo com macro não entra, mesmo com a extensão certa.
        for ($i = 0; $ok && $i < $zip->numFiles; $i++) {
            if (stripos((string) $zip->getNameIndex($i), 'vbaProject') !== false) {
                $ok = false;
            }
        }
        $zip->close();
        if (!$ok) {
            return false;
        }
    }
    return true;
}

function planoPeso(int $b): string
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', '.') . ' MB';
    }
    return max(1, (int) round($b / 1024)) . ' KB';
}

function planoData(string $utc): string
{
    $t = strtotime($utc . ' UTC');
    if (!$t) {
        return '';
    }
    $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y') . ', ' . $d->format('H:i');
}

/** Respostas como a página mostra: nada de caminho interno nem MIME. */
function planoRespostas(PDO $pdo): array
{
    $anexos = [];
    foreach ($pdo->query('SELECT resposta_id, codigo, nome, bytes FROM dossie_plano_anexo ORDER BY id') as $a) {
        $anexos[(int) $a['resposta_id']][] = ['id' => (string) $a['codigo'], 'n' => (string) $a['nome'], 'p' => planoPeso((int) $a['bytes'])];
    }
    $out = [];
    foreach ($pdo->query('SELECT id, codigo, tarefa, nome, texto, criado_em FROM dossie_plano_resposta ORDER BY id') as $r) {
        $out[] = [
            'id'     => (string) $r['codigo'],
            'tarefa' => (string) $r['tarefa'],
            'q'      => (string) $r['nome'],
            'x'      => (string) $r['texto'],
            'f'      => planoData((string) $r['criado_em']),
            'a'      => $anexos[(int) $r['id']] ?? [],
        ];
    }
    return $out;
}

function planoEmailAviso(PDO $pdo): string
{
    $email = defined('DOSSIE_AVISO_EMAIL') && is_string(DOSSIE_AVISO_EMAIL) ? DOSSIE_AVISO_EMAIL : (planoConfig($pdo, 'aviso_email') ?? '');
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/** Aviso por e-mail. Endereço vazio = não envia; se mail() falhar, a resposta já ficou gravada. */
function planoAvisa(PDO $pdo, string $tarefa, string $nome, string $texto, int $nArquivos): void
{
    $para = planoEmailAviso($pdo);
    if ($para === '') {
        return;
    }
    try {
        // No máximo um aviso a cada 10 minutos: o formulário é aberto e não
        // pode virar disparador de e-mail a partir do domínio.
        $ultimo = (int) (planoConfig($pdo, 'aviso_ultimo') ?? 0);
        if (time() - $ultimo < PLANO_AVISO_INTERVALO) {
            return;
        }
        $pdo->prepare('DELETE FROM dossie_config WHERE nome = ?')->execute(['aviso_ultimo']);
        $pdo->prepare('INSERT INTO dossie_config (nome, valor) VALUES (?, ?)')->execute(['aviso_ultimo', (string) time()]);

        $assunto = planoLinha('Dossiê Mclair: resposta nova na tarefa ' . $tarefa, 120);
        $corpo = "Chegou uma resposta nova no Plano de 12 meses do dossiê da Mclair.\n\n"
            . 'Tarefa: ' . $tarefa . "\n"
            . 'Nome: ' . (planoLinha($nome, 80) !== '' ? planoLinha($nome, 80) : 'sem nome') . "\n"
            . 'Arquivos: ' . $nArquivos . "\n"
            . 'Texto: ' . (planoLinha($texto, 400) !== '' ? planoLinha($texto, 400) : '(sem texto)') . "\n\n"
            . 'Ver na página: https://mclair.com.br' . PLANO_BASE . '#t-' . $tarefa . "\n\n"
            . "Avisos chegam no máximo a cada 10 minutos; confira a página para ver tudo o que entrou.\n";
        $cab = 'From: ' . mb_encode_mimeheader('Dossiê Mclair', 'UTF-8', 'B') . " <no-reply@mclair.com.br>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit";
        $ok = @mail($para, mb_encode_mimeheader($assunto, 'UTF-8', 'B', "\r\n"), $corpo, $cab, '-fno-reply@mclair.com.br');
        if (!$ok) {
            error_log('dossie-plano: mail() devolveu false');
        }
    } catch (Throwable $e) {
        error_log('dossie-plano: aviso falhou: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------- ações -- */

function planoJson(int $codigo, array $dados): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Volta para a tarefa com o resultado na URL. A chave só volta se era válida. */
function planoVolta(string $tarefa, string $q, bool $comChave): void
{
    $url = PLANO_BASE . '?';
    if (planoTarefaOk($tarefa)) {
        $url .= 't=' . $tarefa . '&';
    }
    $url .= $q;
    if ($comChave) {
        $url .= '&k=' . rawurlencode((string) ($_POST['k'] ?? ''));
    }
    $url .= planoTarefaOk($tarefa) ? '#t-' . $tarefa : '#plano';
    header('Location: ' . $url, true, 303);
    exit;
}

function planoResponder(PDO $pdo, string $segredo): void
{
    $tarefa    = (string) ($_POST['tarefa'] ?? '');
    $comChave  = planoChaveOk($pdo, (string) ($_POST['k'] ?? ''));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        planoVolta('', 'e=falha', false);
    }
    // post_max_size estourado: o PHP entrega $_POST e $_FILES vazios.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        planoVolta((string) ($_GET['t'] ?? ''), 'e=grande', false);
    }
    if (!planoTokenOk($segredo, (string) ($_POST['token'] ?? ''))) {
        planoVolta($tarefa, 'e=token', $comChave);
    }
    if ((string) ($_POST['site'] ?? '') !== '') {
        planoVolta($tarefa, 'ok=1', $comChave); // armadilha para robô: finge que deu certo
    }
    if (!planoTarefaOk($tarefa)) {
        planoVolta('', 'e=tarefa', $comChave);
    }
    if (!planoLimiteOk($pdo, $segredo)) {
        planoVolta($tarefa, 'e=limite', $comChave);
    }

    $nome  = planoLinha((string) ($_POST['nome'] ?? ''), PLANO_MAX_NOME);
    $texto = planoTextoLimpo((string) ($_POST['texto'] ?? ''), PLANO_MAX_TEXTO);
    $subidos = planoListaArquivos();

    if ($texto === '' && !$subidos) {
        planoVolta($tarefa, 'e=vazio', $comChave);
    }
    if (count($subidos) > PLANO_MAX_ARQUIVOS) {
        planoVolta($tarefa, 'e=muitos', $comChave);
    }
    $nTarefa = $pdo->prepare('SELECT COUNT(*) FROM dossie_plano_resposta WHERE tarefa = ?');
    $nTarefa->execute([$tarefa]);
    if ((int) $nTarefa->fetchColumn() >= PLANO_MAX_POR_TAREFA) {
        planoVolta($tarefa, 'e=cheio', $comChave);
    }
    $somaEnvio = array_sum(array_column($subidos, 'size'));
    if ($subidos && planoDiscoUsado($pdo) + $somaEnvio > PLANO_MAX_DISCO) {
        planoVolta($tarefa, 'e=cheio', $comChave);
    }

    $dir = $subidos ? planoPreparaDir() : null;
    if ($subidos && $dir === null) {
        planoVolta($tarefa, 'e=falha', $comChave);
    }

    $guardados  = [];
    $recusados  = 0;
    $grande     = false;
    foreach ($subidos as $a) {
        $orig = planoNomeArquivo($a['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (in_array($a['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $a['size'] > PLANO_MAX_BYTES) {
            $grande = true;
            $recusados++;
            continue;
        }
        $mime = '';
        if ($a['error'] !== UPLOAD_ERR_OK || $a['size'] <= 0 || !is_uploaded_file($a['tmp']) || !planoTipoOk($a['tmp'], $ext, $mime)) {
            $recusados++;
            continue;
        }
        $codigo  = bin2hex(random_bytes(12));
        $destino = $dir . '/' . $codigo;
        if (!move_uploaded_file($a['tmp'], $destino)) {
            $recusados++;
            continue;
        }
        @chmod($destino, 0600);
        $guardados[] = ['codigo' => $codigo, 'nome' => $orig, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext, 'mime' => $mime, 'bytes' => (int) $a['size'], 'caminho' => $destino];
    }

    if ($texto === '' && !$guardados) {
        planoVolta($tarefa, $grande ? 'e=grande' : 'e=tipo', $comChave);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO dossie_plano_resposta (codigo, tarefa, nome, texto, criado_em) VALUES (?, ?, ?, ?, ?)')
            ->execute([bin2hex(random_bytes(8)), $tarefa, $nome, $texto, planoAgora()]);
        $respostaId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO dossie_plano_anexo (codigo, resposta_id, nome, ext, mime, bytes) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($guardados as $g) {
            $ins->execute([$g['codigo'], $respostaId, $g['nome'], $g['ext'], $g['mime'], $g['bytes']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($guardados as $g) {
            @unlink($g['caminho']);
        }
        error_log('dossie-plano: resposta não gravada: ' . $e->getMessage());
        planoVolta($tarefa, 'e=falha', $comChave);
    }

    planoAvisa($pdo, $tarefa, $nome, $texto, count($guardados));
    planoVolta($tarefa, $recusados ? 'ok=1&r=' . $recusados : 'ok=1', $comChave);
}

function planoApagar(PDO $pdo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !planoChaveOk($pdo, (string) ($_POST['k'] ?? ''))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Sem permissão.';
        exit;
    }
    $tarefa = (string) ($_POST['tarefa'] ?? '');
    $codigo = (string) ($_POST['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $codigo)) {
        planoVolta($tarefa, 'e=falha', true);
    }
    $sel = $pdo->prepare('SELECT id FROM dossie_plano_resposta WHERE codigo = ?');
    $sel->execute([$codigo]);
    $id = $sel->fetchColumn();
    if ($id !== false) {
        $arq = $pdo->prepare('SELECT codigo FROM dossie_plano_anexo WHERE resposta_id = ?');
        $arq->execute([(int) $id]);
        $codigos = $arq->fetchAll(PDO::FETCH_COLUMN);
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM dossie_plano_anexo WHERE resposta_id = ?')->execute([(int) $id]);
        $pdo->prepare('DELETE FROM dossie_plano_resposta WHERE id = ?')->execute([(int) $id]);
        $pdo->commit();
        $dir = planoDirAnexos();
        foreach ($codigos as $c) {
            if ($dir !== '' && preg_match('/^[a-f0-9]{24}$/', (string) $c)) {
                @unlink($dir . '/' . $c);
            }
        }
    }
    planoVolta($tarefa, 'apagado=1', true);
}

function planoBaixar(PDO $pdo): void
{
    $codigo = (string) ($_GET['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{24}$/', $codigo)) {
        http_response_code(404);
        exit;
    }
    $sel = $pdo->prepare('SELECT nome, bytes FROM dossie_plano_anexo WHERE codigo = ?');
    $sel->execute([$codigo]);
    $meta = $sel->fetch();
    $base = planoDirAnexos();
    $caminho = $base . '/' . $codigo;
    if (!$meta || $base === '' || !is_file($caminho)) {
        http_response_code(404);
        exit;
    }
    $nome  = planoNomeArquivo((string) $meta['nome']);
    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nome) ?: 'arquivo';
    header('Content-Type: application/octet-stream');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
    header('Content-Length: ' . (string) filesize($caminho));
    header('Cache-Control: private, no-store');
    readfile($caminho);
    exit;
}

/**
 * Prepara o plano para a página e atende as ações ?plano=... (que terminam
 * aqui mesmo, com exit). Devolve o que a seção precisa para ser desenhada.
 */
function planoInicia(PDO $pdo): array
{
    // O esquema só é conferido de novo quando a versão marcada no banco muda.
    $versao = null;
    try {
        $versao = planoConfig($pdo, 'schema_plano');
    } catch (PDOException $e) {
        // dossie_config ainda não existe: primeira execução.
    }
    if ($versao !== PLANO_SCHEMA_VERSAO) {
        planoSchema($pdo);
        planoSemeiaEstado($pdo);
        $pdo->prepare('DELETE FROM dossie_config WHERE nome = ?')->execute(['schema_plano']);
        $pdo->prepare('INSERT INTO dossie_config (nome, valor) VALUES (?, ?)')->execute(['schema_plano', PLANO_SCHEMA_VERSAO]);
    }
    $segredo = planoSegredo($pdo);

    $acao = (string) ($_GET['plano'] ?? '');
    if ($acao === 'estado') {
        planoJson(200, planoEstado($pdo));
    }
    if ($acao === 'salvar') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            planoJson(405, ['ok' => false]);
        }
        $corpo = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
        $corpo = is_array($corpo) ? $corpo : [];
        if (!planoChaveOk($pdo, (string) ($corpo['k'] ?? ''))) {
            planoJson(403, ['ok' => false]);
        }
        $id     = (string) ($corpo['id'] ?? '');
        $estado = (string) ($corpo['status'] ?? '');
        if (!planoTarefaOk($id) || !in_array($estado, PLANO_ESTADOS, true)) {
            planoJson(400, ['ok' => false]);
        }
        $quando = planoAgora();
        try {
            planoSalvaEstado($pdo, $id, $estado, $quando);
        } catch (Throwable $e) {
            // Dois cliques ao mesmo tempo na mesma tarefa: o outro venceu.
            error_log('dossie-plano: estado não gravado: ' . $e->getMessage());
            planoJson(409, ['ok' => false]);
        }
        planoJson(200, ['ok' => true, 'updatedAt' => planoIso($quando)]);
    }
    if ($acao === 'responder') {
        planoResponder($pdo, $segredo);
    }
    if ($acao === 'apagar') {
        planoApagar($pdo);
    }
    if ($acao === 'baixar') {
        planoBaixar($pdo);
    }
    if ($acao !== '') {
        http_response_code(404);
        exit;
    }

    $k = (string) ($_GET['k'] ?? '');
    $editar = planoChaveOk($pdo, $k);
    // A página leva token e, no modo de edição, a própria chave: nada de cache.
    header('Cache-Control: no-store, private');

    return [
        'editar' => $editar,
        'chave'  => $editar ? $k : '',
        'token'  => planoToken($segredo),
        'estado' => planoEstado($pdo),
        'resp'   => planoRespostas($pdo),
    ];
}

/** Lê um JSON do repositório (dados/) já decodificado; null se faltar. */
function planoDadosJson(string $arquivo): ?array
{
    $d = json_decode((string) @file_get_contents(__DIR__ . '/dados/' . $arquivo), true);
    return is_array($d) ? $d : null;
}

/** Desenha a seção do plano. Os dados vão num bloco JSON não executável, escapado. */
function planoSecao(array $p): void
{
    $dados = [
        'editar'     => $p['editar'],
        'chave'      => $p['chave'],
        'token'      => $p['token'],
        'estado'     => $p['estado'],
        'respostas'  => $p['resp'],
        'posicoes'   => planoDadosJson('posicoes.json'),
        'desempenho' => planoDadosJson('desempenho.json'),
        'limites'    => [
            'arquivos' => PLANO_MAX_ARQUIVOS,
            'mb'       => (int) (PLANO_MAX_BYTES / 1048576),
            'texto'    => PLANO_MAX_TEXTO,
            'tipos'    => '.' . implode(',.', array_keys(PLANO_TIPOS)),
        ],
    ];
    $json = json_encode(
        $dados,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '{}';
    $versao = substr((string) @md5_file(__DIR__ . '/plano.js'), 0, 10);
    $atualizado = planoUltimaData($p['estado']);
    require __DIR__ . '/plano-secao.php';
}

/** Data da mudança de estado mais recente; sem estado, a data do plano.js. */
function planoUltimaData(array $estado): string
{
    $t = 0;
    foreach ($estado as $e) {
        $t = max($t, (int) strtotime((string) ($e['updatedAt'] ?? '')));
    }
    if ($t <= 0) {
        $t = (int) @filemtime(__DIR__ . '/plano.js');
    }
    if ($t <= 0) {
        return '';
    }
    $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y');
}
