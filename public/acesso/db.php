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
    ];
}

/** Todos os autores, em ordem alfabética, com a contagem de posts de cada um. */
function cmsAuthorsAll(PDO $pdo, string $busca = ''): array {
    $sql = 'SELECT a.*, (SELECT COUNT(*) FROM cmstest_blog_posts p WHERE p.blog_author_id = a.id) AS post_count
            FROM cmstest_blog_authors a';
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
    $stmt = $pdo->prepare('SELECT a.*, (SELECT COUNT(*) FROM cmstest_blog_posts p WHERE p.blog_author_id = a.id) AS post_count FROM cmstest_blog_authors a WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? cmsAuthorDecode($row) : null;
}

// Signals the server-side cron (see ~/mclair-build/rebuild.sh) to rebuild and
// republish the static site. Just drops a flag file — actual build/deploy
// happens outside PHP since exec()/shell_exec() are disabled on this host.
function queueRebuild(): void {
    $dir = '/home/u229450165/mclair-build/queue';
    if (!is_dir($dir)) return;
    @touch($dir . '/pending');
}
