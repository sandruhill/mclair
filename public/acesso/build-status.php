<?php
// Estado da publicação do site, para o painel saber se o que acabou de salvar
// já está no ar. Lê (só lê) o que rebuild.sh e check-queue.sh deixam em
// ~/mclair-build: a fila, a trava e o último log. Só para quem está logado.
//
// Resposta:
//   { "state": "idle" | "queued" | "building" | "failed",
//     "builtAt": 1790365694,            // última publicação concluída (build-status.json)
//     "changedAt": 1790365800,          // quando o estado atual começou (mtime do sinal)
//     "log": "rebuild-20260925-194808.log",
//     "error": "build failed after 5 attempts, giving up"   // só em failed
//   }
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = CMS_BUILD_DIR;
$status = ['state' => 'idle', 'builtAt' => 0, 'changedAt' => 0, 'log' => null];

// Última publicação concluída: o mesmo arquivo que rebuild.sh grava no dist.
$statusFile = __DIR__ . '/build-status.json';
if (is_file($statusFile)) {
    $j = json_decode((string) file_get_contents($statusFile), true);
    $status['builtAt'] = (int) ($j['builtAt'] ?? 0);
}

// Último log, pelo nome (rebuild-AAAAMMDD-HHMMSS.log ordena certo).
$ultimoLog = null;
$logs = is_dir("$base/logs") ? glob("$base/logs/rebuild-*.log") : [];
if ($logs) {
    sort($logs);
    $ultimoLog = end($logs);
    $status['log'] = basename($ultimoLog);
}

if (is_file("$base/.building")) {
    $status['state'] = 'building';
    $status['changedAt'] = (int) filemtime("$base/.building");
} elseif (is_file("$base/queue/pending")) {
    $status['state'] = 'queued';
    $status['changedAt'] = (int) filemtime("$base/queue/pending");
} elseif ($ultimoLog) {
    // Sem trava e sem fila: ou terminou bem (builtAt novo) ou falhou. O log de
    // um build que deu certo termina com "rebuild finished"; o que desistiu
    // diz isso nas últimas linhas.
    $cauda = cmsLerCauda($ultimoLog, 4096);
    $mtime = (int) filemtime($ultimoLog);
    if (preg_match('/build failed after \d+ attempts, giving up|BUILD FALHOU/i', $cauda) && $mtime > $status['builtAt']) {
        $status['state'] = 'failed';
        $status['changedAt'] = $mtime;
        // Só as linhas que explicam, sem o log inteiro.
        $linhas = array_values(array_filter(array_map('trim', explode("\n", $cauda)), fn($l) => preg_match('/error|fail|panic|EAGAIN|giving up|npm ERR/i', $l)));
        $status['error'] = implode("\n", array_slice($linhas, -6));
    } elseif (!preg_match('/rebuild finished|already running/', $cauda) && $mtime > $status['builtAt'] && (time() - $mtime) < 1800) {
        // Log aberto sem trava: o processo morreu no meio (o trap remove a
        // trava). Depois de 30 min sem terminar, trata como falha.
        $status['state'] = 'building';
        $status['changedAt'] = $mtime;
    }
}

echo json_encode($status, JSON_UNESCAPED_UNICODE);

function cmsLerCauda(string $arquivo, int $bytes): string {
    $tam = filesize($arquivo) ?: 0;
    $fh = fopen($arquivo, 'rb');
    if (!$fh) return '';
    if ($tam > $bytes) fseek($fh, -$bytes, SEEK_END);
    $s = (string) stream_get_contents($fh);
    fclose($fh);
    return $s;
}
