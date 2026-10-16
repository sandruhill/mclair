<?php
// Recebe os formulários do site (contato, modal do WhatsApp, página da Kelly,
// página de quanto custa) e manda um e-mail para a Kelly com mail(). O
// WhatsApp continua abrindo no navegador de quem preencheu; este endpoint só
// garante que o contato também chegue por e-mail.
//
// Segurança: só POST, só mesma origem, corpo limitado, campos saneados,
// cabeçalhos sem CR/LF, honeypot e limite por IP. O contador do limite fica
// FORA do public_html (o deploy faz rsync --delete lá dentro) e cai para o
// diretório temporário do sistema se não der para gravar. Nada de dado
// pessoal vai para log: só o motivo da falha.

declare(strict_types=1);

const MC_DESTINO     = 'kelly@mclair.com.br';
const MC_REMETENTE   = 'site@mclair.com.br';
const MC_NOME_REMET  = 'Site Mclair';
const MC_MAX_BYTES   = 20000;
const MC_LIMITE_JAN  = 600;   // janela de 10 minutos
const MC_LIMITE_MAX  = 5;     // envios por IP na janela
const MC_LIMITE_DIA  = 20;    // envios por IP em 24 horas
const MC_GLOBAL_HORA = 60;    // envios de todo o site por hora (trava contra botnet)
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

// Sem mbstring o endpoint daria erro fatal sem JSON; melhor falhar claro.
if (!function_exists('mb_substr') || !function_exists('mb_scrub')) {
    error_log('contato.php: extensão mbstring ausente');
    mc_responde(500, ['ok' => false, 'erro' => 'config']);
}

// ---------- Mesma origem ----------
// Sem cabeçalho CORS: o navegador bloqueia leitura de outra origem. Além
// disso, recusa POST cujo Origin (ou Referer, na falta dele) seja de outro
// host, para que outro site não use o formulário como disparador de e-mail.
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
    // Sem Origin e sem Referer, só passa se o navegador disser que é da
    // mesma origem (caso de quem usa política de referrer restrita).
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

/** Texto de uma linha: sem controle, sem CR/LF, espaços colapsados, cortado. */
function mc_linha($v, int $max): string
{
    if (is_array($v)) {
        $v = implode(', ', array_filter(array_map(static fn($x) => is_scalar($x) ? (string)$x : '', $v)));
    }
    if (!is_scalar($v)) return '';
    $v = (string)$v;
    if (!mb_check_encoding($v, 'UTF-8')) $v = mb_scrub($v, 'UTF-8');
    $v = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $max, 'UTF-8');
}

/** Texto de várias linhas: mantém quebras, tira o resto do controle. */
function mc_texto($v, int $max): string
{
    if (!is_scalar($v)) return '';
    $v = (string)$v;
    if (!mb_check_encoding($v, 'UTF-8')) $v = mb_scrub($v, 'UTF-8');
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $v) ?? '';
    $v = preg_replace("/\n{3,}/", "\n\n", $v) ?? '';
    return trim(mb_substr($v, 0, $max, 'UTF-8'));
}

function mc_campo(array $d, array $nomes): string
{
    foreach ($nomes as $n) {
        if (isset($d[$n]) && $d[$n] !== '') return is_array($d[$n]) ? mc_linha($d[$n], 300) : (string)$d[$n];
    }
    return '';
}

// Honeypot: campo escondido que gente não vê. Robô preenche; a resposta é
// igual à de sucesso para ele não aprender nada.
if (mc_linha($dados['website'] ?? '', 200) !== '') {
    mc_responde(200, ['ok' => true]);
}

$nome      = mc_linha(mc_campo($dados, ['nome']), 120);
$emailIn   = mc_linha(mc_campo($dados, ['email']), 190);
$telefone  = mc_linha(mc_campo($dados, ['telefone', 'whatsapp', 'tel']), 40);
$empresa   = mc_linha(mc_campo($dados, ['empresa']), 150);
$assunto   = mc_linha(mc_campo($dados, ['assunto', 'interesse']), 150);
$servicos  = mc_linha($dados['servicos'] ?? '', 300);
$objetivo  = mc_texto(mc_campo($dados, ['objetivo']), 1500);
$orcamento = mc_linha(mc_campo($dados, ['orcamento']), 80);
$mensagem  = mc_texto(mc_campo($dados, ['mensagem', 'msg']), 4000);
$pagina    = mc_linha(mc_campo($dados, ['pagina']), 300);
$form      = mc_linha(mc_campo($dados, ['formulario']), 60);

// Página de origem: só o caminho, para não virar link arbitrário no e-mail.
$caminho = (string)(parse_url($pagina, PHP_URL_PATH) ?? '');
$caminho = preg_match('#^/[A-Za-z0-9/_\-.%]*$#', $caminho) ? $caminho : '';
$form = preg_replace('/[^a-z0-9\-]/', '', strtolower($form)) ?? '';

$email = '';
if ($emailIn !== '' && filter_var($emailIn, FILTER_VALIDATE_EMAIL)) {
    $email = $emailIn;
}

// ---------- Validação ----------
$erros = [];
if (mb_strlen($nome, 'UTF-8') < 2) $erros[] = 'nome';
// E-mail ou telefone mal digitados não derrubam o contato: o e-mail inválido
// só deixa de virar Reply-To e aparece no corpo marcado como não conferido.
if ($assunto === '' && $servicos === '' && $objetivo === '' && $mensagem === '') $erros[] = 'mensagem';
if ($erros) {
    mc_responde(422, ['ok' => false, 'erro' => 'validacao', 'campos' => $erros]);
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
 * grava o envio. A gravação acontece só depois de o mail() dar certo, para
 * uma falha do servidor de e-mail não gastar a cota de quem tentou.
 */
function mc_contador(string $chave, array $regras, bool $registrar): bool
{
    $dir = mc_dir_limite();
    if ($dir === null) {
        error_log('contato.php: sem pasta gravável para o limite');
        return false; // não bloqueia o contato real por falta de pasta
    }
    // Arquivo pelo hash da chave: o IP em si não fica gravado.
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

    // Faxina ocasional dos arquivos sem movimento há mais de um dia.
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

// REMOTE_ADDR e não X-Forwarded-For, que o cliente forja. Se a Hostinger
// puser proxy na frente, conferir no servidor qual IP chega aqui.
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$regrasIp = [MC_LIMITE_JAN => MC_LIMITE_MAX, 86400 => MC_LIMITE_DIA];
$regrasGlobal = [3600 => MC_GLOBAL_HORA];
if (($ip !== '' && mc_contador(mc_chave_ip($ip), $regrasIp, false)) || mc_contador('global', $regrasGlobal, false)) {
    mc_responde(429, ['ok' => false, 'erro' => 'limite']);
}

// ---------- E-mail ----------
$rotuloForm = [
    'contact-form'  => 'Formulário da página de contato',
    'wa-form'       => 'Janela do WhatsApp',
    'wa-form-kelly' => 'Formulário da página da Kelly',
    'wa-form-preco' => 'Formulário de quanto custa assessoria de imprensa',
][$form] ?? 'Formulário do site';

$assuntoEmail = 'Contato pelo site: ' . ($assunto !== '' ? $assunto : ($servicos !== '' ? $servicos : 'nova mensagem'));
$assuntoEmail = mb_substr(mc_linha($assuntoEmail, 200), 0, 150, 'UTF-8');

$linhas = [
    'Nova mensagem pelo site da Mclair.',
    '',
    'Nome: ' . $nome,
    'E-mail: ' . ($email !== '' ? $email : ($emailIn !== '' ? $emailIn . ' (formato inválido, conferir)' : '(não informado)')),
    'WhatsApp/telefone: ' . ($telefone !== '' ? $telefone : '(não informado)'),
    'Empresa: ' . ($empresa !== '' ? $empresa : '(não informada)'),
];
if ($assunto !== '')   $linhas[] = 'Assunto: ' . $assunto;
if ($servicos !== '')  $linhas[] = 'Serviços de interesse: ' . $servicos;
if ($orcamento !== '') $linhas[] = 'Orçamento: ' . $orcamento;
if ($objetivo !== '') {
    $linhas[] = '';
    $linhas[] = 'O que quer divulgar:';
    $linhas[] = $objetivo;
}
if ($mensagem !== '') {
    $linhas[] = '';
    $linhas[] = 'Mensagem:';
    $linhas[] = $mensagem;
}
$linhas[] = '';
$linhas[] = '---';
$linhas[] = 'Origem: ' . $rotuloForm;
$linhas[] = 'Página: https://mclair.com.br' . ($caminho !== '' ? $caminho : '/');
$linhas[] = 'Enviado em: ' . (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
$linhas[] = 'A pessoa também foi levada ao WhatsApp com a mensagem pronta.';

// Corpo em base64: nenhuma linha passa do limite do SMTP, ponto no começo
// de linha não encerra a mensagem e o UTF-8 chega inteiro em qualquer MTA.
$corpo = rtrim(chunk_split(base64_encode(implode("\r\n", $linhas)), 76, "\r\n"));

$cabecalhos = [
    'From: ' . MC_NOME_REMET . ' <' . MC_REMETENTE . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    'X-Mailer: mclair-site',
];
if ($email !== '') {
    // filter_var já recusa CR/LF; o str_replace é a segunda trava.
    $cabecalhos[] = 'Reply-To: ' . str_replace(["\r", "\n"], '', $email);
}

$ok = @mail(
    MC_DESTINO,
    // Assunto já sem CR/LF e com no máximo 150 caracteres: cabe numa
    // palavra codificada só, sem dobra de cabeçalho.
    '=?UTF-8?B?' . base64_encode($assuntoEmail) . '?=',
    $corpo,
    implode("\r\n", $cabecalhos),
    '-f' . MC_REMETENTE
);

if (!$ok) {
    error_log('contato.php: mail() retornou false');
    mc_responde(502, ['ok' => false, 'erro' => 'envio']);
}
if ($ip !== '') mc_contador(mc_chave_ip($ip), $regrasIp, true);
mc_contador('global', $regrasGlobal, true);
mc_responde(200, ['ok' => true]);
