<?php
// Mensagens: tudo que o site grava sobre quem preenche um formulário, e o
// que o painel (/acesso/mensagens) mostra depois. Usado por
// /api/contato.php (grava e manda o e-mail) e por /acesso/messages.php.
//
// Tabelas: cmstest_mensagens (um registro por pessoa que preencheu, enviado
// ou abandonado) e cmstest_mensagens_eventos (linha do tempo: envio,
// e-mail, troca de status, anotações). Criadas aqui mesmo, de forma
// idempotente, no mesmo padrão de db.php (não há ferramenta de migração).
//
// Horários: o banco está em UTC (NOW() = UTC_TIMESTAMP()); grava-se sempre
// em UTC e mostra-se em America/Sao_Paulo.

declare(strict_types=1);

const MCM_FUSO = 'America/Sao_Paulo';

const MCM_STATUS = [
    'novo'             => 'Novo',
    'em_atendimento'   => 'Em atendimento',
    'atendido'         => 'Atendido',
    'proposta_enviada' => 'Proposta enviada',
    'fechado'          => 'Fechado',
    'perdido'          => 'Perdido',
];

const MCM_FORMULARIOS = [
    'contact-form'   => 'Página de contato',
    'wa-form'        => 'Janela do WhatsApp',
    'wa-form-kelly'  => 'Página da Kelly',
    'wa-form-preco'  => 'Quanto custa assessoria',
    'leiloes-form'   => 'Marketing para leiloeiros',
    'wa-form-assessor' => 'Assessor de imprensa',
    'wa-form-brasilia' => 'Assessoria de imprensa em Brasília',
    'wa-form-porto-alegre' => 'Assessoria de imprensa em Porto Alegre',
    'wa-form-crise' => 'Gestão de crise',
    'wa-form-media-training' => 'Media training',
    'wa-form-marketing-autoridade' => 'Marketing de autoridade',
    'wa-form-branding' => 'Branding',
    'wa-form-marketing-digital' => 'Marketing digital',
    'wa-form-consultoria' => 'Consultoria em comunicação',
    'wa-form-mentorias' => 'Mentorias',
];

const MCM_ORIGENS = [
    'google_ads' => 'Google Ads',
    'meta_ads'   => 'Meta Ads',
    'tiktok_ads' => 'TikTok Ads',
    'bing_ads'   => 'Bing Ads',
    'pago'       => 'Mídia paga',
    'campanha'   => 'Campanha (UTM)',
    'busca'      => 'Busca orgânica',
    'social'     => 'Rede social',
    'ia'         => 'Assistente de IA',
    'referencia' => 'Outro site',
    'direto'     => 'Direto',
    'manual'     => 'Cadastro manual',
    ''           => 'Sem dados de origem',
];

const MCM_TOQUE_CHAVES = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid'];

// Hosts de referência conhecidos: [trecho do host, origem, nome].
// Os mais específicos vêm antes (gemini.google.com antes de google.).
const MCM_REF_HOSTS = [
    ['gemini.google.com', 'ia',  'Gemini'],
    ['mail.google.com', 'referencia', 'Gmail'],
    ['www.google.',    'busca',  'Google'],
    ['google.com',     'busca',  'Google'],
    ['google.com.br',  'busca',  'Google'],
    ['bing.com',       'busca',  'Bing'],
    ['duckduckgo.com', 'busca',  'DuckDuckGo'],
    ['yahoo.',         'busca',  'Yahoo'],
    ['ecosia.org',     'busca',  'Ecosia'],
    ['brave.com',      'busca',  'Brave'],
    ['instagram.com',  'social', 'Instagram'],
    ['facebook.com',   'social', 'Facebook'],
    ['fb.com',         'social', 'Facebook'],
    ['linkedin.com',   'social', 'LinkedIn'],
    ['lnkd.in',        'social', 'LinkedIn'],
    ['tiktok.com',     'social', 'TikTok'],
    ['youtube.com',    'social', 'YouTube'],
    ['youtu.be',       'social', 'YouTube'],
    ['twitter.com',    'social', 'X (Twitter)'],
    ['x.com',          'social', 'X (Twitter)'],
    ['t.co',           'social', 'X (Twitter)'],
    ['threads.net',    'social', 'Threads'],
    ['pinterest.',     'social', 'Pinterest'],
    ['whatsapp.com',   'social', 'WhatsApp'],
    ['reddit.com',     'social', 'Reddit'],
    ['chatgpt.com',    'ia',     'ChatGPT'],
    ['openai.com',     'ia',     'ChatGPT'],
    ['perplexity.ai',  'ia',     'Perplexity'],
    ['copilot.microsoft.com', 'ia', 'Copilot'],
    ['claude.ai',      'ia',     'Claude'],
];

const MCM_SITE_HOSTS = ['mclair.com.br', 'www.mclair.com.br'];

// ---------------------------------------------------------------- esquema

function mcmEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS cmstest_mensagens (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chave CHAR(32) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NOT NULL,
        enviado_em DATETIME NULL,
        formulario VARCHAR(60) NOT NULL DEFAULT '',
        nome VARCHAR(120) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        email_valido TINYINT(1) NOT NULL DEFAULT 0,
        telefone VARCHAR(40) NOT NULL DEFAULT '',
        empresa VARCHAR(150) NOT NULL DEFAULT '',
        assunto VARCHAR(150) NOT NULL DEFAULT '',
        servicos VARCHAR(300) NOT NULL DEFAULT '',
        objetivo TEXT NULL,
        orcamento VARCHAR(80) NOT NULL DEFAULT '',
        mensagem TEXT NULL,
        campos TEXT NULL,
        pagina VARCHAR(300) NOT NULL DEFAULT '',
        pagina_titulo VARCHAR(200) NOT NULL DEFAULT '',
        landing VARCHAR(300) NOT NULL DEFAULT '',
        referrer VARCHAR(300) NOT NULL DEFAULT '',
        origem VARCHAR(20) NOT NULL DEFAULT '',
        origem_detalhe VARCHAR(200) NOT NULL DEFAULT '',
        utm_source VARCHAR(200) NOT NULL DEFAULT '',
        utm_medium VARCHAR(200) NOT NULL DEFAULT '',
        utm_campaign VARCHAR(200) NOT NULL DEFAULT '',
        utm_term VARCHAR(200) NOT NULL DEFAULT '',
        utm_content VARCHAR(200) NOT NULL DEFAULT '',
        gclid VARCHAR(200) NOT NULL DEFAULT '',
        gbraid VARCHAR(200) NOT NULL DEFAULT '',
        wbraid VARCHAR(200) NOT NULL DEFAULT '',
        fbclid VARCHAR(200) NOT NULL DEFAULT '',
        msclkid VARCHAR(200) NOT NULL DEFAULT '',
        ttclid VARCHAR(200) NOT NULL DEFAULT '',
        toques TEXT NULL,
        jornada TEXT NULL,
        visitas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        paginas_vistas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        primeira_visita_em DATETIME NULL,
        ga_client_id VARCHAR(64) NOT NULL DEFAULT '',
        dispositivo VARCHAR(20) NOT NULL DEFAULT '',
        navegador VARCHAR(60) NOT NULL DEFAULT '',
        sistema VARCHAR(60) NOT NULL DEFAULT '',
        user_agent VARCHAR(300) NOT NULL DEFAULT '',
        idioma VARCHAR(24) NOT NULL DEFAULT '',
        fuso VARCHAR(64) NOT NULL DEFAULT '',
        tela VARCHAR(20) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        geo_cidade VARCHAR(120) NOT NULL DEFAULT '',
        geo_estado VARCHAR(120) NOT NULL DEFAULT '',
        geo_pais VARCHAR(2) NOT NULL DEFAULT '',
        geo_fonte VARCHAR(10) NOT NULL DEFAULT '',
        email_status VARCHAR(12) NOT NULL DEFAULT 'pendente',
        email_erro VARCHAR(255) NOT NULL DEFAULT '',
        email_enviado_em DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'novo',
        responsavel_id INT NULL,
        observacao VARCHAR(300) NOT NULL DEFAULT '',
        excluido_em DATETIME NULL,
        excluido_por INT NULL,
        KEY ix_msg_criado (criado_em),
        KEY ix_msg_enviado (enviado_em),
        KEY ix_msg_status (status, enviado_em),
        KEY ix_msg_email (email),
        UNIQUE KEY ux_msg_chave (chave),
        KEY ix_msg_ip (ip, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cmstest_mensagens_eventos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mensagem_id INT UNSIGNED NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        texto TEXT NULL,
        usuario_id INT NULL,
        criado_em DATETIME NOT NULL,
        KEY ix_ev_msg (mensagem_id, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Colunas acrescentadas depois da primeira versão entram aqui, no mesmo
    // padrão de cmsEnsureUserProfileColumns (SHOW COLUMNS + ADD COLUMN).
    $cols = $pdo->query('SHOW COLUMNS FROM cmstest_mensagens')->fetchAll(PDO::FETCH_COLUMN);
    $novas = [];
    foreach ($novas as $nome => $def) {
        if (!in_array($nome, $cols, true)) $pdo->exec("ALTER TABLE cmstest_mensagens ADD COLUMN $nome $def");
    }
}

// ------------------------------------------------------------ saneamento

/** Texto de uma linha: sem controle, sem CR/LF, espaços colapsados, cortado. */
function mc_linha($v, int $max): string
{
    if (is_array($v)) {
        $v = implode(', ', array_filter(array_map(static fn($x) => is_scalar($x) ? (string)$x : '', $v)));
    }
    if (!is_scalar($v)) return '';
    $v = (string)$v;
    if (!mb_check_encoding($v, 'UTF-8')) $v = mb_scrub($v, 'UTF-8');
    $v = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]+/u', ' ', $v) ?? '';
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
    $v = preg_replace('/[\x00-\x08\x0B-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]+/u', '', $v) ?? '';
    $v = preg_replace("/\n{3,}/", "\n\n", $v) ?? '';
    return trim(mb_substr($v, 0, $max, 'UTF-8'));
}

/** Caminho de página do próprio site: só o path, sem query, caracteres seguros. */
function mcmCaminho($v): string
{
    $v = mc_linha($v, 400);
    if ($v === '') return '';
    if ($v[0] !== '/') {
        $host = strtolower((string)(parse_url($v, PHP_URL_HOST) ?? ''));
        if (!in_array($host, MCM_SITE_HOSTS, true)) return '';
    }
    $caminho = (string)(parse_url($v, PHP_URL_PATH) ?? '');
    return preg_match('#^/[A-Za-z0-9/_\-.%]*$#', $caminho) ? mb_substr($caminho, 0, 300) : '';
}

/** Caminho com query (landing): mantém os parâmetros, mas só caracteres de URL. */
function mcmCaminhoComQuery($v): string
{
    $v = mc_linha($v, 400);
    if ($v === '' || $v[0] !== '/' || str_starts_with($v, '//') || str_contains($v, '\\')) return '';
    return preg_match('#^/[A-Za-z0-9/_\-.%?=&+~:,;@!$*\'()\[\]]*$#', $v) ? mb_substr($v, 0, 300) : '';
}

/** Referrer externo: só esquema https/http e host fora do site, cortado. */
function mcmReferrer($v): string
{
    $v = mc_linha($v, 400);
    if ($v === '') return '';
    $p = parse_url($v);
    if (!is_array($p) || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
        if (str_starts_with($v, 'android-app://')) return mb_substr($v, 0, 120);
        return '';
    }
    $host = strtolower($p['host']);
    if (in_array($host, MCM_SITE_HOSTS, true)) return '';
    return mb_substr($host . (isset($p['path']) ? rtrim($p['path'], '/') : ''), 0, 300);
}

/** Telefone em dígitos para o wa.me (com 55 na frente quando vier sem DDI). */
function mcmTelefoneWa(string $telefone): string
{
    $d = preg_replace('/\D+/', '', $telefone) ?? '';
    if ($d === '') return '';
    $d = ltrim($d, '0');
    if (strlen($d) >= 10 && strlen($d) <= 11) $d = '55' . $d;
    return (strlen($d) >= 12 && strlen($d) <= 15) ? $d : '';
}

// ---------------------------------------------------------- origem (UTM)

/** Um toque (primeiro ou último) vindo do navegador: limpa e valida. */
function mcmToqueLimpar($t): ?array
{
    if (!is_array($t)) return null;
    $out = [];
    foreach (MCM_TOQUE_CHAVES as $k) {
        $v = mc_linha($t[$k] ?? '', 200);
        if ($v !== '') $out[$k] = $v;
    }
    $ref = mcmReferrer($t['ref'] ?? '');
    if ($ref !== '') $out['ref'] = $ref;
    $landing = mcmCaminhoComQuery($t['landing'] ?? '');
    if ($landing !== '') $out['landing'] = $landing;
    $ts = $t['ts'] ?? null;
    $agora = time();
    if (is_numeric($ts) && (int)$ts >= ($agora - 400 * 86400) * 1000 && (int)$ts <= ($agora + 300) * 1000) {
        $out['ts'] = (int)$ts;
    }
    return $out ? $out : null;
}

/** {primeiro, ultimo} que o form-contato.js manda. */
function mcmToquesNormalizar($toques): ?array
{
    if (!is_array($toques)) return null;
    $out = [];
    foreach (['primeiro', 'ultimo'] as $k) {
        $c = mcmToqueLimpar($toques[$k] ?? null);
        if ($c) $out[$k] = $c;
    }
    return $out ? $out : null;
}

/** [origem, nome] a partir de um host de referência. */
function mcmRefTipo(string $ref): array
{
    if (str_starts_with($ref, 'android-app://')) return ['referencia', 'app ' . substr($ref, 14)];
    $h = strtolower(explode('/', $ref, 2)[0]);
    if ($h === '') return ['referencia', $ref];
    foreach (MCM_REF_HOSTS as [$trecho, $origem, $nome]) {
        $padrao = '/(^|\.)' . preg_quote($trecho, '/') . (str_ends_with($trecho, '.') ? '' : '$') . '/';
        if (preg_match($padrao, $h)) return [$origem, $nome];
    }
    return ['referencia', mb_substr($ref, 0, 80)];
}

/** Origem de UM toque: clique de anúncio > utm pago > utm > referência. null se não diz nada. */
function mcmOrigemDoToque(array $t): ?array
{
    $src = strtolower((string)($t['utm_source'] ?? ''));
    $med = strtolower((string)($t['utm_medium'] ?? ''));
    $camp = (string)($t['utm_campaign'] ?? '');
    $det = static fn(string $base): string => trim($base . ($camp !== '' ? ' · ' . $camp : ''), ' ·');

    if (!empty($t['gclid']) || !empty($t['gbraid']) || !empty($t['wbraid'])) return ['google_ads', $det('')];
    if (!empty($t['fbclid'])) return ['meta_ads', $det('')];
    if (!empty($t['ttclid'])) return ['tiktok_ads', $det('')];
    if (!empty($t['msclkid'])) return ['bing_ads', $det('')];

    $pago = in_array($med, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'paid-social', 'cpm', 'display', 'ads', 'ad'], true);
    if ($src !== '') {
        if ($pago && str_contains($src, 'google')) return ['google_ads', $det('')];
        if ($pago && preg_match('/facebook|instagram|meta|^fb$|^ig$/', $src)) return ['meta_ads', $det('')];
        if ($pago && str_contains($src, 'tiktok')) return ['tiktok_ads', $det('')];
        if ($pago && (str_contains($src, 'bing') || str_contains($src, 'microsoft'))) return ['bing_ads', $det('')];
        if ($pago) return ['pago', $det($src)];
        if (preg_match('/^(google|bing|duckduckgo|yahoo)$/', $src) && in_array($med, ['organic', 'seo', ''], true)) return ['busca', $det(ucfirst($src))];
        if (preg_match('/instagram|facebook|tiktok|youtube|whatsapp|pinterest|linkedin|twitter|^x$|threads|reddit/', $src)) return ['social', $det(ucfirst($src) . ($med !== '' ? ' / ' . $med : ''))];
        if (preg_match('/chatgpt|openai|perplexity|gemini|copilot|claude/', $src)) return ['ia', $det(ucfirst($src))];
        return ['campanha', $det($src . ($med !== '' ? ' / ' . $med : ''))];
    }
    if (!empty($t['ref'])) return mcmRefTipo((string)$t['ref']);
    return null;
}

/**
 * Origem do registro inteiro: primeiro toque, senão último, senão referrer
 * da sessão, senão UTM na query da landing. Nada = 'direto' quando houve
 * visita registrada, '' quando não há dado nenhum.
 */
function mcmOrigemDerivar(?array $toques, string $referrer, string $landing, bool $visitou): array
{
    $cands = [];
    if ($toques) {
        foreach (['primeiro', 'ultimo'] as $k) if (!empty($toques[$k])) $cands[] = $toques[$k];
    }
    if ($referrer !== '') $cands[] = ['ref' => $referrer];
    if ($landing !== '') {
        $q = (string)parse_url($landing, PHP_URL_QUERY);
        if ($q !== '') {
            parse_str($q, $p);
            $u = [];
            foreach (MCM_TOQUE_CHAVES as $k) { $v = mc_linha($p[$k] ?? '', 200); if ($v !== '') $u[$k] = $v; }
            if ($u) $cands[] = $u;
        }
    }
    foreach ($cands as $c) {
        $o = mcmOrigemDoToque($c);
        if ($o) return $o;
    }
    return $visitou ? ['direto', ''] : ['', ''];
}

// --------------------------------------------------------------- jornada

/** Lista de páginas vistas antes do envio (do localStorage), validada. */
function mcmJornadaNormalizar($j): ?array
{
    if (!is_array($j) || !isset($j['paginas']) || !is_array($j['paginas'])) return null;
    $agora = time() * 1000;
    $pags = [];
    foreach (array_slice($j['paginas'], -60) as $p) {
        if (!is_array($p)) continue;
        $caminho = mcmCaminho($p['p'] ?? '');
        $ts = $p['ts'] ?? null;
        if ($caminho === '' || !is_numeric($ts)) continue;
        $ts = (int)$ts;
        if ($ts < $agora - 400 * 86400000 || $ts > $agora + 300000) continue;
        $pags[] = ['p' => $caminho, 't' => mc_linha($p['t'] ?? '', 120), 'ts' => $ts];
    }
    if (!$pags) return null;
    usort($pags, static fn($a, $b) => $a['ts'] <=> $b['ts']);
    $out = ['paginas' => $pags];
    // "inicio" vem do localStorage: só vale dentro da mesma janela dos ts.
    $ini = $j['inicio'] ?? null;
    if (is_numeric($ini) && (int)$ini >= $agora - 400 * 86400000 && (int)$ini <= $pags[0]['ts']) $out['inicio'] = (int)$ini;
    return $out;
}

/** Visitas (intervalos de 30 min), páginas e primeira marca de tempo. */
function mcmJornadaResumo(?array $j): array
{
    $pags = $j['paginas'] ?? [];
    $n = count($pags);
    $visitas = $n ? 1 : 0;
    $ant = null;
    foreach ($pags as $p) {
        if ($ant !== null && $p['ts'] - $ant > 30 * 60 * 1000) $visitas++;
        $ant = $p['ts'];
    }
    $primeiro = $n ? (int)($j['inicio'] ?? $pags[0]['ts']) : 0;
    return ['visitas' => $visitas, 'paginas' => $n, 'primeiro' => $primeiro];
}

// ------------------------------------------------------------ dispositivo

/** [dispositivo, navegador, sistema] a partir do User-Agent. Só o básico. */
function mcmDispositivo(string $ua): array
{
    $u = strtolower($ua);
    if ($u === '') return ['', '', ''];
    $disp = 'computador';
    if (preg_match('/ipad|tablet|(android(?!.*mobile))/', $u)) $disp = 'tablet';
    elseif (preg_match('/mobile|iphone|android|ipod|windows phone/', $u)) $disp = 'celular';

    $nav = '';
    if (str_contains($u, 'instagram')) $nav = 'Instagram (app)';
    elseif (preg_match('/fban|fbav/', $u)) $nav = 'Facebook (app)';
    elseif (str_contains($u, 'edg/') || str_contains($u, 'edge/')) $nav = 'Edge';
    elseif (str_contains($u, 'samsungbrowser')) $nav = 'Samsung Internet';
    elseif (str_contains($u, 'opr/') || str_contains($u, 'opera')) $nav = 'Opera';
    elseif (str_contains($u, 'firefox/')) $nav = 'Firefox';
    elseif (str_contains($u, 'crios/')) $nav = 'Chrome (iOS)';
    elseif (str_contains($u, 'chrome/') || str_contains($u, 'chromium/')) $nav = 'Chrome';
    elseif (str_contains($u, 'safari/') && str_contains($u, 'version/')) $nav = 'Safari';
    elseif (preg_match('/bot|crawl|spider|curl|wget|python/', $u)) $nav = 'Robô';

    $sis = '';
    if (preg_match('/iphone|ipad|ipod/', $u)) $sis = 'iOS';
    elseif (str_contains($u, 'android')) $sis = 'Android';
    elseif (str_contains($u, 'windows')) $sis = 'Windows';
    elseif (str_contains($u, 'mac os x') || str_contains($u, 'macintosh')) $sis = 'macOS';
    elseif (str_contains($u, 'cros')) $sis = 'ChromeOS';
    elseif (str_contains($u, 'linux')) $sis = 'Linux';

    return [$disp, $nav, $sis];
}

// -------------------------------------------------------------------- geo

/** Pasta fora do public_html (o deploy faz rsync --delete lá dentro). */
function mcmGeoCaminho(): string
{
    return dirname(__DIR__, 2) . '/.mclair-geo/dbip-city-lite.mmdb';
}

/** Cidade, estado e país por IP com a base local da DB-IP, ou null. O IP não sai do servidor. */
function mcmGeo(string $ip): ?array
{
    if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mm)) $ip = $mm[1]; // IPv4 mapeado em IPv6
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return null;
    $arq = mcmGeoCaminho();
    if (!is_file($arq) || filesize($arq) < 64) return null;
    require_once __DIR__ . '/mmdb.php';
    try {
        $db = Mmdb::open($arq);
        $r = $db ? $db->lookup($ip) : null;
    } catch (Throwable $e) {
        error_log('mensagens: geo falhou: ' . $e->getMessage());
        return null;
    }
    if (!$r) return null;
    $cc = strtoupper(mc_linha($r['country']['iso_code'] ?? '', 2));
    if ($cc === '') return null;
    return [
        'cidade' => mc_linha($r['city']['names']['en'] ?? '', 120),
        'estado' => mc_linha($r['subdivisions'][0]['names']['en'] ?? '', 120),
        'pais'   => $cc,
        'fonte'  => 'dbip',
    ];
}

// ----------------------------------------------------------------- eventos

function mcmEvento(PDO $pdo, int $mensagemId, string $tipo, string $texto = '', ?int $usuarioId = null): void
{
    $st = $pdo->prepare('INSERT INTO cmstest_mensagens_eventos (mensagem_id, tipo, texto, usuario_id, criado_em) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
    $st->execute([$mensagemId, $tipo, $texto !== '' ? $texto : null, $usuarioId]);
}

/** Quantas mensagens novas (enviadas, status novo, fora da lixeira). Nunca derruba o painel. */
function mcmNovos(PDO $pdo): int
{
    try {
        mcmEnsureSchema($pdo);
        return (int)$pdo->query("SELECT COUNT(*) FROM cmstest_mensagens WHERE status = 'novo' AND enviado_em IS NOT NULL AND excluido_em IS NULL")->fetchColumn();
    } catch (Throwable $e) {
        error_log('mensagens: contagem falhou: ' . $e->getMessage());
        return 0;
    }
}

// ------------------------------------------------------------------- datas

function mcmDataLocal(?string $utc, string $fmt = 'd/m/Y H:i'): string
{
    if (!$utc) return '';
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(MCM_FUSO))->format($fmt);
    } catch (Throwable $e) {
        return '';
    }
}

function mcmTempoAtras(?string $utc): string
{
    if (!$utc) return '';
    $t = strtotime($utc . ' UTC');
    if ($t === false) return '';
    $d = time() - $t;
    if ($d < 60) return 'agora há pouco';
    if ($d < 3600) return 'há ' . intdiv($d, 60) . ' min';
    if ($d < 86400) { $h = intdiv($d, 3600); return 'há ' . $h . ($h === 1 ? ' hora' : ' horas'); }
    if ($d < 30 * 86400) { $n = intdiv($d, 86400); return 'há ' . $n . ($n === 1 ? ' dia' : ' dias'); }
    return 'em ' . mcmDataLocal($utc, 'd/m/Y');
}

// ------------------------------------------------------------------ e-mail

const MCM_DESTINO    = 'kelly@mclair.com.br';
// Cópia oculta (deixar '' para mandar só para a Kelly).
const MCM_COPIA      = '';
const MCM_REMETENTE  = 'site@mclair.com.br';
const MCM_NOME_REMET = 'Site Mclair';

function mc_h(string $t): string
{
    return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Monta e manda o e-mail de aviso para a Kelly a partir de um registro da
 * tabela. Devolve [ok, erro]. O texto que a pessoa digitou entra escapado
 * e rotulado: é dado, não instrução.
 */
function mcmEmailEnviar(array $m): array
{
    $rotuloForm = MCM_FORMULARIOS[$m['formulario']] ?? 'Formulário do site';
    $nome = (string)$m['nome'];
    $email = (string)$m['email'];
    $emailOk = (bool)$m['email_valido'];
    $telefone = (string)$m['telefone'];
    $empresa = (string)$m['empresa'];
    $assunto = (string)$m['assunto'];
    $servicos = (string)$m['servicos'];
    $orcamento = (string)$m['orcamento'];
    $objetivo = (string)($m['objetivo'] ?? '');
    $mensagem = (string)($m['mensagem'] ?? '');
    $caminho = (string)$m['pagina'];
    $id = (int)$m['id'];

    $assuntoEmail = 'Contato pelo site: ' . ($assunto !== '' ? $assunto : ($servicos !== '' ? $servicos : 'nova mensagem'));
    $assuntoEmail = mb_substr(mc_linha($assuntoEmail, 200), 0, 150, 'UTF-8');

    $quando = mcmDataLocal($m['enviado_em'] ?? $m['criado_em'], 'd/m/Y \à\s H:i');
    $paginaUrl = 'https://mclair.com.br' . ($caminho !== '' ? $caminho : '/');
    $painelUrl = 'https://mclair.com.br/acesso/mensagens?id=' . $id;

    $linhas = [
        'Nova mensagem pelo site da Mclair.',
        '',
        'Nome: ' . $nome,
        'E-mail: ' . ($emailOk ? $email : ($email !== '' ? $email . ' (formato inválido, conferir)' : '(não informado)')),
        'WhatsApp/telefone: ' . ($telefone !== '' ? $telefone : '(não informado)'),
        'Empresa: ' . ($empresa !== '' ? $empresa : '(não informada)'),
    ];
    if ($assunto !== '')   $linhas[] = 'Assunto: ' . $assunto;
    if ($servicos !== '')  $linhas[] = 'Serviços de interesse: ' . $servicos;
    if ($orcamento !== '') $linhas[] = 'Orçamento: ' . $orcamento;
    if ($objetivo !== '') { $linhas[] = ''; $linhas[] = 'O que quer divulgar:'; $linhas[] = $objetivo; }
    if ($mensagem !== '') { $linhas[] = ''; $linhas[] = 'Mensagem:'; $linhas[] = $mensagem; }
    $linhas[] = '';
    $linhas[] = '---';
    $linhas[] = 'Origem: ' . $rotuloForm;
    $linhas[] = 'Página: ' . $paginaUrl;
    $linhas[] = 'Enviado em: ' . $quando;
    $linhas[] = 'Ficha completa no painel: ' . $painelUrl;
    $linhas[] = 'A pessoa também foi levada ao WhatsApp com a mensagem pronta.';

    $linhaHtml = static function (string $rotulo, string $valor): string {
        return '<tr><td style="padding:10px 0;border-bottom:1px solid #EEECE8;width:150px;vertical-align:top;color:#8A847E;font-size:13px;">'
            . mc_h($rotulo) . '</td><td style="padding:10px 0;border-bottom:1px solid #EEECE8;vertical-align:top;color:#1C1A17;font-size:15px;font-weight:600;">'
            . $valor . '</td></tr>';
    };
    $blocoHtml = static function (string $titulo, string $texto): string {
        return '<p style="margin:28px 0 8px;color:#8A847E;font-size:13px;">' . mc_h($titulo) . '</p>'
            . '<div style="background:#F6F5F3;border-radius:10px;padding:16px 18px;color:#1C1A17;font-size:15px;line-height:1.6;">'
            . nl2br(mc_h($texto)) . '</div>';
    };

    $digitos = mcmTelefoneWa($telefone);
    $botoes = '';
    if ($digitos !== '') {
        $botoes .= '<a href="https://wa.me/' . $digitos . '" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 20px;border-radius:8px;margin:0 8px 8px 0;">Responder no WhatsApp</a>';
    }
    if ($emailOk) {
        $botoes .= '<a href="mailto:' . mc_h(rawurlencode($email)) . '" style="display:inline-block;background:#1C1A17;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 20px;border-radius:8px;margin:0 8px 8px 0;">Responder por e-mail</a>';
    }
    $botoes .= '<a href="' . mc_h($painelUrl) . '" style="display:inline-block;background:#ffffff;color:#1C1A17;border:1px solid #D9D5CF;text-decoration:none;font-weight:700;font-size:14px;padding:11px 20px;border-radius:8px;margin:0 8px 8px 0;">Abrir no painel</a>';

    $tabela = $linhaHtml('Nome', mc_h($nome))
        . $linhaHtml('E-mail', $emailOk ? '<a href="mailto:' . mc_h(rawurlencode($email)) . '" style="color:#C8102E;text-decoration:none;">' . mc_h($email) . '</a>' : ($email !== '' ? mc_h($email) . ' <span style="color:#C8102E;font-weight:400;">(formato inválido)</span>' : '<span style="color:#8A847E;font-weight:400;">não informado</span>'))
        . $linhaHtml('WhatsApp', $telefone !== '' ? mc_h($telefone) : '<span style="color:#8A847E;font-weight:400;">não informado</span>')
        . $linhaHtml('Empresa', $empresa !== '' ? mc_h($empresa) : '<span style="color:#8A847E;font-weight:400;">não informada</span>');
    if ($assunto !== '')   $tabela .= $linhaHtml('Assunto', mc_h($assunto));
    if ($servicos !== '')  $tabela .= $linhaHtml('Interesse', mc_h($servicos));
    if ($orcamento !== '') $tabela .= $linhaHtml('Verba', mc_h($orcamento));

    $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . mc_h($assuntoEmail) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F1EFEC;font-family:Lato,Helvetica,Arial,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1EFEC;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;">'
        . '<tr><td style="background:#151210;padding:26px 32px;">'
        . '<p style="margin:0;color:#ffffff;font-size:22px;font-weight:900;letter-spacing:-0.02em;">Mclair</p>'
        . '<p style="margin:6px 0 0;color:#C9C3BC;font-size:14px;">Novo contato pelo site</p></td></tr>'
        . '<tr><td style="height:4px;background:#C8102E;line-height:4px;font-size:0;">&nbsp;</td></tr>'
        . '<tr><td style="padding:30px 32px 8px;">'
        . '<p style="margin:0 0 4px;color:#1C1A17;font-size:24px;font-weight:800;line-height:1.25;">' . mc_h($nome) . ' quer conversar</p>'
        . '<p style="margin:0 0 22px;color:#6B6560;font-size:14px;">' . mc_h($rotuloForm) . ', ' . mc_h($quando) . '</p>'
        . '<div style="margin:0 0 18px;">' . $botoes . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $tabela . '</table>'
        . ($objetivo !== '' ? $blocoHtml('O que quer divulgar', $objetivo) : '')
        . ($mensagem !== '' ? $blocoHtml('Mensagem', $mensagem) : '')
        . '</td></tr>'
        . '<tr><td style="padding:24px 32px 30px;">'
        . '<p style="margin:0;color:#8A847E;font-size:13px;line-height:1.6;">Enviado da página <a href="' . mc_h($paginaUrl) . '" style="color:#8A847E;">' . mc_h($paginaUrl) . '</a>. A pessoa também foi levada ao WhatsApp da Mclair com a mensagem pronta. A ficha completa, com origem e histórico, fica em <a href="' . mc_h($painelUrl) . '" style="color:#8A847E;">Mensagens, no painel</a>.</p>'
        . '</td></tr></table>'
        . '<p style="margin:16px 0 0;color:#A29C96;font-size:12px;">Mclair Comunicação · mclair.com.br</p>'
        . '</td></tr></table></body></html>';

    $fronteira = 'mc_' . bin2hex(random_bytes(12));
    $corpo = '--' . $fronteira . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode(implode("\r\n", $linhas)), 76, "\r\n")) . "\r\n"
        . '--' . $fronteira . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($html), 76, "\r\n")) . "\r\n"
        . '--' . $fronteira . '--';

    $cabecalhos = [
        'From: ' . MCM_NOME_REMET . ' <' . MCM_REMETENTE . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $fronteira . '"',
        'X-Mailer: mclair-site',
    ];
    if (MCM_COPIA !== '') $cabecalhos[] = 'Bcc: ' . MCM_COPIA;
    // Sem Reply-To de propósito: com o e-mail de quem preencheu o Gmail
    // avisava "as respostas são enviadas para outro endereço".

    error_clear_last();
    $ok = @mail(
        MCM_DESTINO,
        '=?UTF-8?B?' . base64_encode($assuntoEmail) . '?=',
        $corpo,
        implode("\r\n", $cabecalhos),
        '-f' . MCM_REMETENTE
    );
    if ($ok) return [true, ''];
    $erro = error_get_last()['message'] ?? '';
    return [false, mc_linha($erro !== '' ? $erro : 'mail() retornou false', 250)];
}
