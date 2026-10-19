<?php
// Recebe os formulários do site (contato, janela do WhatsApp, página da
// Kelly, quanto custa, leiloeiros, formulários de serviço). Ordem fixa:
// validar -> GRAVAR em cmstest_mensagens -> mandar o e-mail para a Kelly ->
// marcar na ficha se o e-mail saiu. Se o e-mail falhar, o lead continua
// salvo e o painel (/acesso/mensagens) mostra a falha. O WhatsApp continua
// abrindo no navegador de quem preencheu.
//
// Dois modos, no campo "modo":
//   (vazio)     envio completo: grava, manda o e-mail, responde {ok, id}.
//   "rascunho"  captura antes do envio: assim que a pessoa preencheu nome e
//               e-mail (ou telefone), o form-contato.js manda um registro
//               parcial com uma "chave" de 32 hex gerada no navegador. Se
//               ela envia depois, o MESMO registro é completado (pela
//               chave; sem chave, por e-mail/telefone + IP nas últimas 24h).
//               Quem nunca envia fica no painel como "abandonado".
//
// Segurança: só POST, só mesma origem (Origin/Referer/Sec-Fetch-Site), corpo
// limitado, campos saneados, cabeçalhos de e-mail sem CR/LF, honeypot,
// limite por IP (envios e rascunhos novos contam separado) e limite global.
// O contador do limite fica FORA do public_html (o deploy faz rsync --delete
// lá dentro). Prepared statements em tudo. Nada de dado pessoal vai para
// log: só o motivo da falha.

declare(strict_types=1);

const MC_MAX_BYTES   = 40000;
const MC_LIMITE_JAN  = 600;   // janela de 10 minutos
const MC_LIMITE_MAX  = 5;     // envios por IP na janela
const MC_LIMITE_DIA  = 20;    // envios por IP em 24 horas
const MC_RASC_MAX    = 12;    // rascunhos NOVOS por IP em 10 minutos
const MC_RASC_DIA    = 40;    // rascunhos novos por IP em 24 horas
const MC_GLOBAL_HORA = 120;   // gravações de todo o site por hora (trava contra botnet)
// Hosts aceitos no Origin/Referer. Não usa HTTP_HOST, que vem do cliente.
// Para teste local, a variável de ambiente MC_CONTATO_HOST_TESTE acrescenta
// um host (ex.: 127.0.0.1); em produção ela não existe.
const MC_HOSTS = ['mclair.com.br', 'www.mclair.com.br'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
mb_internal_encoding('UTF-8');

function mc_responde(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    mc_responde(405, ['ok' => false, 'erro' => 'metodo']);
}

if (!function_exists('mb_substr') || !function_exists('mb_scrub')) {
    error_log('contato.php: extensão mbstring ausente');
    mc_responde(500, ['ok' => false, 'erro' => 'config']);
}

require_once dirname(__DIR__) . '/acesso/mensagens-lib.php';

// ---------- Mesma origem ----------
$hostsOk = MC_HOSTS;
$hostTeste = getenv('MC_CONTATO_HOST_TESTE');
if (is_string($hostTeste) && $hostTeste !== '') $hostsOk[] = strtolower($hostTeste);

$sfs = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
if ($sfs !== '' && $sfs !== 'same-origin') {
    mc_responde(403, ['ok' => false, 'erro' => 'origem']);
}
$origem = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origem === '' || $origem === 'null') {
    $origem = (string)($_SERVER['HTTP_REFERER'] ?? '');
}
if ($origem !== '') {
    $hostOrigem = strtolower((string)(parse_url($origem, PHP_URL_HOST) ?? ''));
    if (!in_array($hostOrigem, $hostsOk, true)) {
        mc_responde(403, ['ok' => false, 'erro' => 'origem']);
    }
} elseif ($sfs !== 'same-origin') {
    mc_responde(403, ['ok' => false, 'erro' => 'origem']);
}

// ---------- Corpo ----------
$tamanho = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($tamanho > MC_MAX_BYTES) {
    mc_responde(413, ['ok' => false, 'erro' => 'tamanho']);
}
$bruto = (string)file_get_contents('php://input', false, null, 0, MC_MAX_BYTES + 1);
if (strlen($bruto) > MC_MAX_BYTES) {
    mc_responde(413, ['ok' => false, 'erro' => 'tamanho']);
}

$tipo = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($tipo, 'application/x-www-form-urlencoded') === 0 || strpos($tipo, 'multipart/form-data') === 0) {
    $dados = $_POST;
} elseif (strpos($tipo, 'application/json') === 0 || strpos($tipo, 'text/plain') === 0) {
    // text/plain cobre um sendBeacon sem tipo; o corpo continua sendo JSON.
    $dados = json_decode($bruto, true);
    if (!is_array($dados)) {
        mc_responde(400, ['ok' => false, 'erro' => 'json']);
    }
} else {
    mc_responde(415, ['ok' => false, 'erro' => 'tipo']);
}

function mc_campo(array $d, array $nomes): string
{
    foreach ($nomes as $n) {
        if (isset($d[$n]) && $d[$n] !== '') return is_array($d[$n]) ? mc_linha($d[$n], 300) : (string)$d[$n];
    }
    return '';
}

// Honeypot: campo escondido que gente não vê. Robô preenche; a resposta é
// igual à de sucesso para ele não aprender nada. Não grava nada.
if (mc_linha($dados['website'] ?? '', 200) !== '') {
    mc_responde(200, ['ok' => true]);
}

$rascunho  = mc_linha($dados['modo'] ?? '', 20) === 'rascunho';
$chave     = strtolower(mc_linha($dados['chave'] ?? '', 40));
if (!preg_match('/^[0-9a-f]{32}$/', $chave)) $chave = '';

$nome      = mc_linha(mc_campo($dados, ['nome', 'name']), 120);
$emailIn   = mc_linha(mc_campo($dados, ['email']), 190);
$telefone  = mc_linha(mc_campo($dados, ['telefone', 'whatsapp', 'tel']), 40);
$empresa   = mc_linha(mc_campo($dados, ['empresa']), 150);
$assunto   = mc_linha(mc_campo($dados, ['assunto', 'interesse', 'desafio']), 150);
$servicos  = mc_linha($dados['servicos'] ?? ($dados['servico'] ?? ''), 300);
$objetivo  = mc_texto(mc_campo($dados, ['objetivo']), 1500);
$orcamento = mc_linha(mc_campo($dados, ['orcamento']), 80);
$mensagem  = mc_texto(mc_campo($dados, ['mensagem', 'msg']), 4000);
$pagina    = mcmCaminho(mc_campo($dados, ['pagina']));
$titulo    = mc_linha(mc_campo($dados, ['titulo']), 200);
$form      = preg_replace('/[^a-z0-9\-]/', '', strtolower(mc_linha(mc_campo($dados, ['formulario']), 60))) ?? '';

$email = '';
$emailValido = 0;
if ($emailIn !== '' && filter_var($emailIn, FILTER_VALIDATE_EMAIL)) {
    $email = $emailIn;
    $emailValido = 1;
} elseif ($emailIn !== '') {
    $email = $emailIn; // guardado como digitou, marcado como não conferido
}
$telDigitos = strlen(preg_replace('/\D+/', '', $telefone) ?? '');

// Todos os campos do formulário, saneados, para a ficha mostrar mesmo o que
// não tem coluna própria (ex.: um select novo numa página nova).
$camposExtra = [];
foreach ($dados as $k => $v) {
    $k = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$k)) ?? '';
    if ($k === '' || in_array($k, ['website', 'modo', 'chave', 'visita', 'ga', 'ga_sessao', 'tela', 'fuso', 'idioma', 'titulo', 'pagina', 'formulario'], true)) continue;
    $val = is_array($v) ? mc_linha($v, 300) : mc_texto($v, 1500);
    if ($val !== '') $camposExtra[$k] = $val;
    if (count($camposExtra) >= 30) break;
}

// ---------- Validação ----------
$erros = [];
if (mb_strlen($nome, 'UTF-8') < 2) $erros[] = 'nome';
if ($rascunho) {
    if ($emailValido === 0 && $telDigitos < 10) $erros[] = 'contato';
} else {
    if ($assunto === '' && $servicos === '' && $objetivo === '' && $mensagem === '') $erros[] = 'mensagem';
}
if ($erros) {
    mc_responde(422, ['ok' => false, 'erro' => $rascunho ? 'incompleto' : 'validacao', 'campos' => $erros]);
}

// ---------- Limite por IP ----------
function mc_dir_limite(): ?string
{
    $candidatos = [dirname(__DIR__, 2) . '/.mclair-contato', rtrim(sys_get_temp_dir(), '/') . '/mclair-contato'];
    foreach ($candidatos as $dir) {
        if (!@is_dir($dir)) @mkdir($dir, 0700, true);
        if (@is_dir($dir) && @is_writable($dir)) return $dir;
    }
    return null;
}

/**
 * Contador em arquivo com flock. Com $registrar = false só confere se a
 * chave já estourou alguma regra [janela em segundos => máximo]; com true,
 * grava a ocorrência.
 */
function mc_contador(string $chave, array $regras, bool $registrar): bool
{
    $dir = mc_dir_limite();
    if ($dir === null) {
        error_log('contato.php: sem pasta gravável para o limite');
        return false; // não bloqueia o contato real por falta de pasta
    }
    $arq = $dir . '/' . hash('sha256', 'mclair-contato|' . $chave) . '.json';
    $fh = @fopen($arq, 'c+');
    if ($fh === false) {
        error_log('contato.php: não abriu o arquivo do limite');
        return false;
    }
    $agora = time();
    $estourou = false;
    if (flock($fh, LOCK_EX)) {
        $lista = json_decode((string)stream_get_contents($fh), true);
        $maior = max(array_keys($regras));
        $lista = is_array($lista) ? array_values(array_filter($lista, static fn($t) => is_int($t) && $t > $agora - $maior)) : [];
        foreach ($regras as $janela => $maximo) {
            $n = count(array_filter($lista, static fn($t) => $t > $agora - $janela));
            if ($n >= $maximo) $estourou = true;
        }
        if ($registrar) {
            $lista[] = $agora;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($lista));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    } else {
        error_log('contato.php: flock falhou no arquivo do limite');
    }
    fclose($fh);

    if ($registrar && mt_rand(1, 50) === 1) {
        foreach ((array)glob($dir . '/*.json') as $velho) {
            if (is_string($velho) && @filemtime($velho) < $agora - 86400) @unlink($velho);
        }
    }
    return $estourou;
}

/** IPv6 é agrupado pelo /64, que costuma ser de um mesmo cliente. */
function mc_chave_ip(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) {
        return 'ip6|' . bin2hex(substr($bin, 0, 8));
    }
    return 'ip|' . $ip;
}

// REMOTE_ADDR e não X-Forwarded-For, que o cliente forja.
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$chaveIp = $ip !== '' ? mc_chave_ip($ip) : '';
$regrasEnvio = [MC_LIMITE_JAN => MC_LIMITE_MAX, 86400 => MC_LIMITE_DIA];
$regrasRasc  = [MC_LIMITE_JAN => MC_RASC_MAX, 86400 => MC_RASC_DIA];
$regrasGlobal = [3600 => MC_GLOBAL_HORA];
// O limite global vale só para rascunhos novos: um envio completo nunca é
// recusado por causa do volume do site inteiro (a regra é gravar primeiro).
if (!$rascunho && $chaveIp !== '' && mc_contador($chaveIp, $regrasEnvio, false)) {
    mc_responde(429, ['ok' => false, 'erro' => 'limite']);
}
if ($rascunho && $chave === '') {
    // Sem chave não há como completar o rascunho depois: não grava.
    mc_responde(422, ['ok' => false, 'erro' => 'chave']);
}

// ---------- Contexto da visita ----------
$visita = is_array($dados['visita'] ?? null) ? $dados['visita'] : [];
$toques = mcmToquesNormalizar($visita['toques'] ?? null);
$jornada = mcmJornadaNormalizar($visita['jornada'] ?? null);
$resumo = mcmJornadaResumo($jornada);
$referrerSessao = mcmReferrer($visita['ref'] ?? '');
$landing = (string)($toques['primeiro']['landing'] ?? '');
$referrer = (string)($toques['primeiro']['ref'] ?? $referrerSessao);
[$origemChave, $origemDetalhe] = mcmOrigemDerivar($toques, $referrerSessao, $landing, $toques !== null || $jornada !== null);
$ultimo = $toques['ultimo'] ?? ($toques['primeiro'] ?? []);
$ga = mc_linha($dados['ga'] ?? '', 64);
if (!preg_match('/^\d{3,}\.\d{3,}$/', $ga)) $ga = '';
$ua = mc_linha($_SERVER['HTTP_USER_AGENT'] ?? '', 300);
[$disp, $nav, $sis] = mcmDispositivo($ua);
$idioma = mc_linha($dados['idioma'] ?? ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 24);
$fuso = mc_linha($dados['fuso'] ?? '', 64);
if (!preg_match('#^[A-Za-z_]+(/[A-Za-z_\-+0-9]+){0,2}$#', $fuso)) $fuso = '';
$tela = mc_linha($dados['tela'] ?? '', 20);
if (!preg_match('/^\d{2,5}x\d{2,5}$/', $tela)) $tela = '';

$registro = [
    'chave' => $chave !== '' ? $chave : null,
    'formulario' => $form,
    'nome' => $nome,
    'email' => $email,
    'email_valido' => $emailValido,
    'telefone' => $telefone,
    'empresa' => $empresa,
    'assunto' => $assunto,
    'servicos' => $servicos,
    'objetivo' => $objetivo,
    'orcamento' => $orcamento,
    'mensagem' => $mensagem,
    'campos' => $camposExtra ? json_encode($camposExtra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    'pagina' => $pagina,
    'pagina_titulo' => $titulo,
    'landing' => $landing,
    'referrer' => $referrer,
    'origem' => $origemChave,
    'origem_detalhe' => mb_substr($origemDetalhe, 0, 200),
    'utm_source' => (string)($ultimo['utm_source'] ?? ''),
    'utm_medium' => (string)($ultimo['utm_medium'] ?? ''),
    'utm_campaign' => (string)($ultimo['utm_campaign'] ?? ''),
    'utm_term' => (string)($ultimo['utm_term'] ?? ''),
    'utm_content' => (string)($ultimo['utm_content'] ?? ''),
    'gclid' => (string)($ultimo['gclid'] ?? ''),
    'gbraid' => (string)($ultimo['gbraid'] ?? ''),
    'wbraid' => (string)($ultimo['wbraid'] ?? ''),
    'fbclid' => (string)($ultimo['fbclid'] ?? ''),
    'msclkid' => (string)($ultimo['msclkid'] ?? ''),
    'ttclid' => (string)($ultimo['ttclid'] ?? ''),
    'toques' => $toques ? json_encode($toques, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    'jornada' => $jornada ? json_encode($jornada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    'visitas' => $resumo['visitas'],
    'paginas_vistas' => $resumo['paginas'],
    'primeira_visita_em' => ($resumo['primeiro'] > 0 && $resumo['primeiro'] < 4102444800000) ? gmdate('Y-m-d H:i:s', intdiv($resumo['primeiro'], 1000)) : null,
    'ga_client_id' => $ga,
    'dispositivo' => $disp,
    'navegador' => $nav,
    'sistema' => $sis,
    'user_agent' => $ua,
    'idioma' => $idioma,
    'fuso' => $fuso,
    'tela' => $tela,
    'ip' => mb_substr($ip, 0, 45),
];

// ---------- Gravação ----------
$pdo = null;
$id = 0;
$existente = null;
$registroSalvo = null;
try {
    require_once dirname(__DIR__) . '/acesso/config.php';
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    mcmEnsureSchema($pdo);
} catch (Throwable $e) {
    error_log('contato.php: sem banco: ' . $e->getMessage());
    $pdo = null;
}

/** Registro parcial da mesma pessoa: pela chave do navegador ou, no envio completo, por e-mail/telefone + IP nas últimas 24 h. */
function mc_existente(PDO $pdo, string $chave, bool $rascunho, string $ip, string $email, int $emailValido, string $telefone, int $telDigitos): ?array
{
    if ($chave !== '') {
        $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE chave = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$chave]);
        $r = $st->fetch();
        if ($r) return $r;
    }
    if (!$rascunho && $ip !== '' && ($emailValido === 1 || $telDigitos >= 10)) {
        $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE ip = ? AND enviado_em IS NULL AND excluido_em IS NULL AND criado_em > (UTC_TIMESTAMP() - INTERVAL 1 DAY) AND ((email <> \'\' AND email = ?) OR (telefone <> \'\' AND telefone = ?)) ORDER BY id DESC LIMIT 1');
        $st->execute([$ip, $email, $telefone]);
        $r = $st->fetch();
        if ($r) return $r;
    }
    return null;
}

if ($pdo !== null) {
    try {
        $existente = mc_existente($pdo, $chave, $rascunho, $ip, $email, $emailValido, $telefone, $telDigitos);
        if ($existente === null && $rascunho) {
            // Rascunho novo: conta no limite de rascunhos por IP e no global.
            if (($chaveIp !== '' && mc_contador('rasc|' . $chaveIp, $regrasRasc, false)) || mc_contador('global', $regrasGlobal, false)) {
                mc_responde(429, ['ok' => false, 'erro' => 'limite']);
            }
        }
        if ($existente === null) {
            // Geo só quando o registro nasce (uma consulta à base local por lead).
            $geo = mcmGeo($ip);
            $registro['geo_cidade'] = $geo['cidade'] ?? '';
            $registro['geo_estado'] = $geo['estado'] ?? '';
            $registro['geo_pais'] = $geo['pais'] ?? '';
            $registro['geo_fonte'] = $geo['fonte'] ?? '';
            $cols = array_keys($registro);
            $sql = 'INSERT INTO cmstest_mensagens (' . implode(', ', $cols) . ', criado_em, atualizado_em, enviado_em, email_status) VALUES ('
                . implode(', ', array_fill(0, count($cols), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP(), '
                . ($rascunho ? 'NULL' : 'UTC_TIMESTAMP()') . ", 'pendente')";
            try {
                $pdo->prepare($sql)->execute(array_values($registro));
                $id = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                // Chave duplicada: o rascunho e o envio chegaram ao mesmo tempo
                // (blur + clique). Quem perdeu a corrida completa o registro do outro.
                if ((int)($e->errorInfo[1] ?? 0) !== 1062 || $chave === '') throw $e;
                $existente = mc_existente($pdo, $chave, $rascunho, $ip, $email, $emailValido, $telefone, $telDigitos);
                if ($existente === null) throw $e;
            }
        }
        if ($existente !== null) {
            if ($existente['enviado_em'] !== null) {
                // Já enviado com esta chave: um rascunho atrasado não mexe em
                // nada, e um envio repetido da mesma aba não duplica. Se o
                // e-mail daquela vez falhou, tenta de novo agora.
                if (!$rascunho && $existente['email_status'] === 'falhou') {
                    [$okRe, $erroRe] = mcmEmailEnviar($existente);
                    $eid = (int)$existente['id'];
                    if ($okRe) {
                        $pdo->prepare("UPDATE cmstest_mensagens SET email_status = 'enviado', email_erro = '', email_enviado_em = UTC_TIMESTAMP() WHERE id = ?")->execute([$eid]);
                        mcmEvento($pdo, $eid, 'email_ok', 'Aviso enviado por e-mail para ' . MCM_DESTINO . ' (segunda tentativa)');
                    } else {
                        $pdo->prepare("UPDATE cmstest_mensagens SET email_erro = ? WHERE id = ?")->execute([$erroRe, $eid]);
                    }
                }
                mc_responde(200, ['ok' => true, 'id' => (int)$existente['id'], 'repetido' => true]);
            }
            // Completa o que já existe: valor novo só substitui quando veio
            // preenchido; o rascunho não apaga o que a pessoa já tinha digitado.
            $sets = [];
            $vals = [];
            foreach ($registro as $col => $val) {
                if ($col === 'chave') continue;
                $vazio = $val === '' || $val === null || $val === 0;
                if ($vazio && $col !== 'email_valido') continue;
                if ($col === 'email_valido' && $email === '') continue;
                $sets[] = "$col = ?";
                $vals[] = $val;
            }
            if ($chave !== '' && empty($existente['chave'])) { $sets[] = 'chave = ?'; $vals[] = $chave; }
            $sets[] = 'atualizado_em = UTC_TIMESTAMP()';
            if (!$rascunho) $sets[] = 'enviado_em = UTC_TIMESTAMP()';
            $vals[] = (int)$existente['id'];
            // "enviado_em IS NULL" no WHERE: dois envios simultâneos do mesmo
            // registro, só um marca como enviado (e só esse manda o e-mail).
            $up = $pdo->prepare('UPDATE cmstest_mensagens SET ' . implode(', ', $sets) . ' WHERE id = ? AND enviado_em IS NULL');
            $up->execute($vals);
            if (!$rascunho && $up->rowCount() !== 1) {
                mc_responde(200, ['ok' => true, 'id' => (int)$existente['id'], 'repetido' => true]);
            }
            $id = (int)$existente['id'];
        }
    } catch (Throwable $e) {
        // O banco falhou antes de gravar: no envio completo o e-mail ainda
        // sai, para o lead não sumir; no rascunho não há mais o que fazer.
        error_log('contato.php: gravação falhou: ' . $e->getMessage());
        if ($rascunho) mc_responde(500, ['ok' => false, 'erro' => 'gravacao']);
        $id = 0;
    }
}

// Daqui em diante o registro já está no banco (ou não há banco). Falha em
// evento ou na releitura não pode tratar o lead como não gravado.
if ($id > 0 && $pdo !== null) {
    try {
        if ($rascunho) {
            if ($existente === null) {
                mcmEvento($pdo, $id, 'rascunho', 'Começou a preencher o formulário');
                if ($chaveIp !== '') mc_contador('rasc|' . $chaveIp, $regrasRasc, true);
                mc_contador('global', $regrasGlobal, true);
                // Rascunhos abandonados há mais de 90 dias não precisam ficar.
                if (mt_rand(1, 40) === 1) $pdo->exec('DELETE FROM cmstest_mensagens WHERE enviado_em IS NULL AND criado_em < (UTC_TIMESTAMP() - INTERVAL 90 DAY)');
            }
        } else {
            mcmEvento($pdo, $id, 'enviado', $existente !== null ? 'Formulário enviado (completou o rascunho)' : 'Formulário enviado');
        }
    } catch (Throwable $e) {
        error_log('contato.php: evento não gravado: ' . $e->getMessage());
    }
    try {
        $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE id = ?');
        $st->execute([$id]);
        $registroSalvo = $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('contato.php: releitura falhou: ' . $e->getMessage());
    }
}
if ($registroSalvo === null) {
    $registroSalvo = $registro + ['id' => $id, 'criado_em' => gmdate('Y-m-d H:i:s'), 'enviado_em' => gmdate('Y-m-d H:i:s')];
}

if ($rascunho) {
    mc_responde(200, ['ok' => true, 'id' => $id, 'rascunho' => true]);
}

// ---------- E-mail (depois de gravado) ----------
[$okMail, $erroMail] = mcmEmailEnviar($registroSalvo);
if ($pdo !== null && $id > 0) {
    try {
        if ($okMail) {
            $pdo->prepare("UPDATE cmstest_mensagens SET email_status = 'enviado', email_erro = '', email_enviado_em = UTC_TIMESTAMP() WHERE id = ?")->execute([$id]);
            mcmEvento($pdo, $id, 'email_ok', 'Aviso enviado por e-mail para ' . MCM_DESTINO);
        } else {
            $pdo->prepare("UPDATE cmstest_mensagens SET email_status = 'falhou', email_erro = ? WHERE id = ?")->execute([$erroMail, $id]);
            mcmEvento($pdo, $id, 'email_falhou', 'O e-mail de aviso não saiu: ' . $erroMail);
        }
    } catch (Throwable $e) {
        error_log('contato.php: não marcou o e-mail: ' . $e->getMessage());
    }
}
if (!$okMail) error_log('contato.php: mail() falhou: ' . $erroMail);

// ---------- generate_lead no GA4 pelo servidor ----------
// O navegador também empurra o evento no dataLayer; aqui é a garantia para
// quem fechou a página assim que o WhatsApp abriu. Pulado sem a chave.
if ($id > 0 && $pdo !== null && (string)($registroSalvo['ga_client_id'] ?? '') !== '') {
    $sessao = mc_linha($dados['ga_sessao'] ?? '', 20);
    $ga4 = mcmGa4GenerateLead($registroSalvo, $sessao);
    if ($ga4 !== 'pulado') {
        try {
            mcmEvento($pdo, $id, 'ga4', $ga4 === 'ok' ? 'generate_lead enviado ao GA4 pelo servidor (Measurement Protocol)' : 'generate_lead não chegou ao GA4: ' . $ga4);
        } catch (Throwable $e) {
            error_log('contato.php: evento ga4 não gravado: ' . $e->getMessage());
        }
        if ($ga4 !== 'ok') error_log('contato.php: GA4 MP: ' . $ga4);
    }
}

if (!$okMail && $id === 0) {
    // Nem gravou nem mandou: o único caso em que a pessoa vê erro.
    mc_responde(502, ['ok' => false, 'erro' => 'envio']);
}
if ($chaveIp !== '') mc_contador($chaveIp, $regrasEnvio, true);
mc_responde(200, ['ok' => true, 'id' => $id, 'email' => $okMail]);
