<?php
require_once __DIR__ . '/config.php';

function cmsDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

// Lazily adds the profile/force-password-change columns (same idempotent
// pattern as cmsRateLimitTable in auth.php -- no migration tooling here).
function cmsEnsureUserProfileColumns(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = $pdo->query("SHOW COLUMNS FROM cmstest_users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('display_name', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN display_name VARCHAR(190) NULL AFTER username");
    }
    if (!in_array('avatar_url', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER display_name");
    }
    if (!in_array('password_changed_at', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN password_changed_at DATETIME NULL");
        // Backfill existing accounts as "already changed" so the forced popup
        // only applies going forward, to newly-created or admin-reset accounts.
        $pdo->exec("UPDATE cmstest_users SET password_changed_at = NOW() WHERE password_changed_at IS NULL");
    }
    if (!in_array('login_count_since_reset', $cols, true)) {
        $pdo->exec("ALTER TABLE cmstest_users ADD COLUMN login_count_since_reset INT UNSIGNED NOT NULL DEFAULT 0");
    }
}

/**
 * Markdown do post: duas linhas de texto comum coladas viram dois parágrafos.
 *
 * O editor visual (Toast UI 3) grava "um Enter" como quebra simples ("a\nb"),
 * que para o markdown é a mesma frase; o post inteiro saía num único <p>.
 * O JS do editor já corrige antes de enviar; isto aqui é a rede de segurança
 * no servidor, com a MESMA regra de src/utils/markdown.ts (build) e do SQL
 * de reparo em db/repair/. Não mexe em lista, tabela, citação, título, HTML,
 * bloco de código, quebra forçada (dois espaços ou barra no fim) nem em
 * linha indentada. Só espaço e tab contam como linha em branco: NBSP é
 * texto para o CommonMark e os posts migrados dependem disso.
 */
function cmsNormalizarParagrafos(string $md): string {
    if ($md === '') return '';
    $linhas = explode("\n", (string) preg_replace("/\r\n?/", "\n", $md));
    $saida = [];
    $dentroDeCodigo = false;
    $especial = '/^(?:[-*+] |\d+[.)] |\||>|#|<|(?:=+|-+)[ \t]*$)/';
    $n = count($linhas);
    for ($i = 0; $i < $n; $i++) {
        $linha = $linhas[$i];
        if (preg_match('/^\s*(```|~~~)/', $linha)) { $dentroDeCodigo = !$dentroDeCodigo; $saida[] = $linha; continue; }
        if ($dentroDeCodigo) { $saida[] = $linha; continue; }
        if (preg_match('/^[ \t]+$/', $linha)) $linha = '';
        $saida[] = $linha;
        if (!isset($linhas[$i + 1])) continue;
        $proxima = $linhas[$i + 1];
        if ($linha === '' || preg_match('/^[ \t]*$/', $proxima)) continue;
        if (preg_match($especial, $linha) || preg_match($especial, $proxima)) continue;
        if (preg_match('/(  |\\\\)$/', $linha)) continue;
        if (preg_match('/^[ \t]/', $proxima)) continue;
        $saida[] = '';
    }
    return implode("\n", $saida);
}

/** Texto -> endereço: sem acento, sem pontuação, separado por hífen, até 80 caracteres. */
function cmsSlugify(string $texto): string {
    $t = trim($texto);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $t) ?: $t;
    } else {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    }
    $t = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($t));
    $t = trim((string) $t, '-');
    if (strlen($t) <= 80) return $t;
    // Corta no último hífen antes do limite, para não partir palavra no meio.
    $curto = substr($t, 0, 80);
    $ultimo = strrpos($curto, '-');
    return rtrim($ultimo > 40 ? substr($curto, 0, $ultimo) : $curto, '-');
}

// ---- Autores do blog ----
// A tabela e a coluna vêm de db/migrations/2026-09-25-blog-autores.sql, que é
// rodada à mão (aqui não se cria esquema sozinho de propósito: é o passo que
// o responsável aprova). Enquanto ela não existe, cada tela degrada com um
// aviso em vez de quebrar.
function cmsAuthorsReady(PDO $pdo): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $temTabela = (bool) $pdo->query("SHOW TABLES LIKE 'cmstest_blog_authors'")->fetchColumn();
        $temColuna = (bool) $pdo->query("SHOW COLUMNS FROM cmstest_blog_posts LIKE 'blog_author_id'")->fetchColumn();
        $ready = $temTabela && $temColuna;
    } catch (PDOException $e) {
        $ready = false;
    }
    return $ready;
}

const CMS_AUTHORS_MIGRATION_HINT = 'A área de autores ainda não foi ativada neste banco. Peça para rodar db/migrations/2026-09-25-blog-autores.sql (uma vez) e recarregue.';

/** Tipos de link aceitos na assinatura, na ordem em que aparecem no formulário. */
function cmsAuthorSocialTypes(): array {
    return [
        'linkedin'  => 'LinkedIn',
        'instagram' => 'Instagram',
        'x'         => 'X (Twitter)',
        'facebook'  => 'Facebook',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'site'      => 'Site',
        'outro'     => 'Outro link',
    ];
}

/** Linha do banco -> array pronto para JSON (colunas JSON decodificadas, tipos certos). */
function cmsAuthorDecode(array $row): array {
    $topics = json_decode((string) ($row['topics'] ?? ''), true);
    $links = json_decode((string) ($row['social_links'] ?? ''), true);
    return [
        'id'           => (int) $row['id'],
        'slug'         => (string) $row['slug'],
        'name'         => (string) $row['name'],
        'job_title'    => (string) ($row['job_title'] ?? ''),
        'photo_url'    => (string) ($row['photo_url'] ?? ''),
        'bio_short'    => (string) ($row['bio_short'] ?? ''),
        'bio_long'     => (string) ($row['bio_long'] ?? ''),
        'topics'       => is_array($topics) ? array_values($topics) : [],
        'social_links' => is_array($links) ? array_values($links) : [],
        'post_count'   => (int) ($row['post_count'] ?? 0),
        // Colunas da segunda migração; antes dela, todo autor é pessoa sem tratamento definido.
        'kind'         => ($row['kind'] ?? 'pessoa') === 'organizacao' ? 'organizacao' : 'pessoa',
        'gender'       => in_array($row['gender'] ?? null, ['m', 'f'], true) ? $row['gender'] : null,
    ];
}

// Segunda etapa (db/migrations/2026-09-26-blog-autores-v2.sql): tipo,
// tratamento e vários autores por post. Sem ela, vale um autor por post.
function cmsAuthorsV2Ready(PDO $pdo): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $temKind = (bool) $pdo->query("SHOW COLUMNS FROM cmstest_blog_authors LIKE 'kind'")->fetchColumn();
        $temGender = (bool) $pdo->query("SHOW COLUMNS FROM cmstest_blog_authors LIKE 'gender'")->fetchColumn();
        $temJoin = (bool) $pdo->query("SHOW TABLES LIKE 'cmstest_blog_post_authors'")->fetchColumn();
        $ready = $temKind && $temGender && $temJoin;
    } catch (PDOException $e) {
        $ready = false;
    }
    return $ready;
}

const CMS_AUTHORS_V2_HINT = 'Vários autores por post, tipo (pessoa/organização) e tratamento (autor/autora) dependem de db/migrations/2026-09-26-blog-autores-v2.sql. Até lá, só o primeiro autor é gravado.';

/** "Autor", "Autora" ou "Organização", conforme o cadastro. */
function cmsAuthorLabel(array $a): string {
    if (($a['kind'] ?? 'pessoa') === 'organizacao') return 'Organização';
    return ($a['gender'] ?? null) === 'f' ? 'Autora' : 'Autor';
}

/** Subconsulta de contagem de posts do autor, pela tabela de vários autores quando ela existe. */
function cmsAuthorPostCountSql(PDO $pdo): string {
    return cmsAuthorsV2Ready($pdo)
        ? '(SELECT COUNT(*) FROM cmstest_blog_post_authors pa WHERE pa.author_id = a.id)'
        : '(SELECT COUNT(*) FROM cmstest_blog_posts p WHERE p.blog_author_id = a.id)';
}

/** Ids dos autores do post, na ordem. Sem a tabela de vários autores, é só o blog_author_id. */
function cmsPostAuthorIds(PDO $pdo, string $slug): array {
    if (cmsAuthorsV2Ready($pdo)) {
        $stmt = $pdo->prepare('SELECT author_id FROM cmstest_blog_post_authors WHERE post_slug = ? ORDER BY position, author_id');
        $stmt->execute([$slug]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) return $ids;
    }
    $stmt = $pdo->prepare('SELECT blog_author_id FROM cmstest_blog_posts WHERE slug = ?');
    $stmt->execute([$slug]);
    $id = (int) $stmt->fetchColumn();
    return $id ? [$id] : [];
}

/** Nomes ligados em português: "A", "A e B", "A, B e C". */
function cmsJoinNames(array $nomes): string {
    $n = count($nomes);
    if ($n === 0) return '';
    if ($n === 1) return $nomes[0];
    return implode(', ', array_slice($nomes, 0, -1)) . ' e ' . $nomes[$n - 1];
}

/**
 * Grava os autores de um post: tabela de vários autores (quando existe),
 * blog_author_id = primeiro autor, e o nome de exibição (coluna `author`)
 * = nomes ligados. Ids inexistentes são ignorados. Lista vazia = sem autor
 * cadastrado; nesse caso o nome de texto informado ($nomeLivre) é mantido.
 */
function cmsPostAuthorsSave(PDO $pdo, string $slug, array $ids, ?string $nomeLivre = null): void {
    $autores = [];
    foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
        if ($id > 0 && ($a = cmsAuthorById($pdo, $id))) $autores[] = $a;
    }
    $v2 = cmsAuthorsV2Ready($pdo);
    if (!$v2) $autores = array_slice($autores, 0, 1);

    $primeiro = $autores[0]['id'] ?? null;
    $nome = $autores ? cmsJoinNames(array_column($autores, 'name')) : ($nomeLivre !== null && trim($nomeLivre) !== '' ? trim($nomeLivre) : null);
    $pdo->prepare('UPDATE cmstest_blog_posts SET blog_author_id = ?, author = ? WHERE slug = ?')->execute([$primeiro, $nome, $slug]);

    if ($v2) {
        $pdo->prepare('DELETE FROM cmstest_blog_post_authors WHERE post_slug = ?')->execute([$slug]);
        $ins = $pdo->prepare('INSERT INTO cmstest_blog_post_authors (post_slug, author_id, position) VALUES (?, ?, ?)');
        foreach ($autores as $i => $a) $ins->execute([$slug, $a['id'], $i]);
    }
}

/** Depois de remover/reatribuir um autor: refaz blog_author_id e o nome de exibição dos posts afetados. */
function cmsPostAuthorsResync(PDO $pdo, array $slugs): void {
    foreach (array_unique($slugs) as $slug) {
        $ids = cmsPostAuthorIds($pdo, $slug);
        $stmt = $pdo->prepare('SELECT author FROM cmstest_blog_posts WHERE slug = ?');
        $stmt->execute([$slug]);
        cmsPostAuthorsSave($pdo, $slug, $ids, (string) $stmt->fetchColumn());
    }
}

/** Todos os autores, em ordem alfabética, com a contagem de posts de cada um. */
function cmsAuthorsAll(PDO $pdo, string $busca = ''): array {
    $sql = 'SELECT a.*, ' . cmsAuthorPostCountSql($pdo) . ' AS post_count FROM cmstest_blog_authors a';
    $params = [];
    if ($busca !== '') {
        $sql .= ' WHERE a.name LIKE ? OR a.job_title LIKE ?';
        $params = ['%' . $busca . '%', '%' . $busca . '%'];
    }
    $sql .= ' ORDER BY a.name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('cmsAuthorDecode', $stmt->fetchAll());
}

function cmsAuthorById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT a.*, ' . cmsAuthorPostCountSql($pdo) . ' AS post_count FROM cmstest_blog_authors a WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? cmsAuthorDecode($row) : null;
}

// ---- Data e hora do post ----
// post_date nasceu como DATE; db/migrations/2026-09-26-blog-post-date-datetime.sql
// passa para DATETIME (hora de Brasília, sem fuso no banco). Até lá, só o dia.
function cmsPostDateHasTime(PDO $pdo): bool {
    static $tem = null;
    if ($tem !== null) return $tem;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM cmstest_blog_posts LIKE 'post_date'")->fetch();
        $tem = $col ? stripos((string) $col['Type'], 'datetime') !== false : false;
    } catch (PDOException $e) {
        $tem = false;
    }
    return $tem;
}

/**
 * "AAAA-MM-DD" ou "AAAA-MM-DD HH:MM" -> valor para o banco. Data no futuro
 * (mais de 5 min) vira agora, porque não existe agendamento e um post com
 * data futura confundiria leitor e Google; $ajustada avisa o editor.
 */
function cmsNormalizarPostDate(string $entrada, bool $comHora, bool &$ajustada): ?string {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $entrada = trim($entrada);
    if ($entrada === '') return null;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2})(?::\d{2})?)?$/', $entrada, $m)) return null;
    $data = $m[1];
    $hora = $comHora ? ($m[2] ?? '09:00') : '00:00';
    try {
        $dt = new DateTime("$data $hora:00", $tz);
    } catch (Exception $e) {
        return null;
    }
    $agora = new DateTime('now', $tz);
    if ($dt->getTimestamp() > $agora->getTimestamp() + 300) {
        $ajustada = true;
        $dt = $agora;
    }
    return $comHora ? $dt->format('Y-m-d H:i:00') : $dt->format('Y-m-d');
}

// Signals the server-side cron (see ~/mclair-build/rebuild.sh) to rebuild and
// republish the static site. Just drops a flag file — actual build/deploy
// happens outside PHP since exec()/shell_exec() are disabled on this host.
// Pasta do build no servidor. config.php pode sobrescrever (o teste local
// aponta para uma pasta de mentira e simula o cron).
if (!defined('CMS_BUILD_DIR')) define('CMS_BUILD_DIR', '/home/u229450165/mclair-build');

function queueRebuild(): void {
    $dir = CMS_BUILD_DIR . '/queue';
    if (!is_dir($dir)) return;
    @touch($dir . '/pending');
}
