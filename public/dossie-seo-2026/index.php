<?php
/**
 * Dossiê de SEO 2026: página interna, fora dos buscadores.
 *
 * Aberta e editável por qualquer visitante, por decisão do cliente (23/09/2026).
 * Por isso duas salvaguardas que NÃO são controle de acesso e sim higiene básica:
 *   1. todo o conteúdo é escapado antes de virar HTML, senão um visitante
 *      poderia injetar script no domínio da agência;
 *   2. cada gravação guarda a versão anterior, senão uma edição errada
 *      apaga o dossiê sem volta.
 *
 * O texto vive no MySQL (não em arquivo) porque o deploy roda
 * `rsync -a --delete` sobre o public_html e levaria qualquer arquivo junto.
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../acesso/db.php';

const DOSSIE_SLUG      = 'seo-2026';
const DOSSIE_VERSOES   = 30;
const DOSSIE_MAX_BYTES = 400000;

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/dossie-seo-2026/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------- dados -- */

function dossieSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_doc (
        slug         VARCHAR(64) NOT NULL PRIMARY KEY,
        titulo       VARCHAR(190) NOT NULL,
        conteudo     LONGTEXT NOT NULL,
        atualizado_em DATETIME NOT NULL,
        atualizado_por VARCHAR(190) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossie_versao (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug         VARCHAR(64) NOT NULL,
        conteudo     LONGTEXT NOT NULL,
        salvo_em     DATETIME NOT NULL,
        salvo_por    VARCHAR(190) NULL,
        INDEX slug_time (slug, salvo_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function dossieCarrega(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT * FROM dossie_doc WHERE slug = ?');
    $stmt->execute([DOSSIE_SLUG]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    // Primeira visita: semeia com o markdown versionado no repositório.
    $seed = @file_get_contents(__DIR__ . '/seed.md');
    if ($seed === false) {
        $seed = "# Dossiê\n\nO conteúdo inicial não foi encontrado. Clique em Editar para escrever.";
    }
    $ins = $pdo->prepare(
        'INSERT INTO dossie_doc (slug, titulo, conteudo, atualizado_em, atualizado_por)
         VALUES (?, ?, ?, NOW(), ?)'
    );
    $ins->execute([DOSSIE_SLUG, 'Dossiê de concorrência: Mclair', $seed, 'carga inicial']);

    return [
        'slug'           => DOSSIE_SLUG,
        'titulo'         => 'Dossiê de concorrência: Mclair',
        'conteudo'       => $seed,
        'atualizado_em'  => date('Y-m-d H:i:s'),
        'atualizado_por' => 'carga inicial',
    ];
}

function dossieSalva(PDO $pdo, string $conteudo, string $autor, string $anterior): void
{
    $hist = $pdo->prepare(
        'INSERT INTO dossie_versao (slug, conteudo, salvo_em, salvo_por) VALUES (?, ?, NOW(), ?)'
    );
    $hist->execute([DOSSIE_SLUG, $anterior, $autor]);

    // Mantém só as N mais recentes; o resto sai para a tabela não crescer sem fim.
    $pdo->prepare(
        'DELETE FROM dossie_versao WHERE slug = ? AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM dossie_versao WHERE slug = ? ORDER BY id DESC LIMIT ' . DOSSIE_VERSOES . '
            ) recentes
        )'
    )->execute([DOSSIE_SLUG, DOSSIE_SLUG]);

    $upd = $pdo->prepare(
        'UPDATE dossie_doc SET conteudo = ?, atualizado_em = NOW(), atualizado_por = ? WHERE slug = ?'
    );
    $upd->execute([$conteudo, $autor, DOSSIE_SLUG]);
}

/* ------------------------------------------------------------- markdown -- */

/**
 * Converte markdown em HTML. Tudo é escapado ANTES de qualquer marcação ser
 * aplicada, então nada que um visitante digite vira tag: a única marcação que
 * sai daqui é a que este código produz.
 */
function mdInline(string $texto): string
{
    $t = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // `código`
    $t = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $t);
    // **negrito**
    $t = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $t);
    // [texto](url) — só http e https passam
    $t = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)\)/u',
        static function (array $m): string {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('#^https?://#i', $url)) {
                return $m[1];
            }
            $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<a href="' . $safe . '" rel="noopener noreferrer nofollow" target="_blank">' . $m[1] . '</a>';
        },
        $t
    );

    return $t;
}

function mdLinhaTabela(string $linha): array
{
    $linha = trim($linha);
    $linha = preg_replace('/^\||\|$/', '', $linha);
    return array_map('trim', explode('|', $linha));
}

function mdParaHtml(string $md): string
{
    $linhas = preg_split('/\r\n|\r|\n/', $md);
    $out    = [];
    $i      = 0;
    $total  = count($linhas);

    while ($i < $total) {
        $linha = $linhas[$i];

        if (trim($linha) === '') {
            $i++;
            continue;
        }

        // Título
        if (preg_match('/^(#{1,4})\s+(.*)$/u', $linha, $m)) {
            $nivel = strlen($m[1]);
            $out[] = '<h' . $nivel . '>' . mdInline($m[2]) . '</h' . $nivel . '>';
            $i++;
            continue;
        }

        // Tabela: linha de cabeçalho seguida da linha de separação
        if (str_contains($linha, '|')
            && $i + 1 < $total
            && preg_match('/^\s*\|?[\s:-]*-[\s:|-]*\|?\s*$/', $linhas[$i + 1])) {

            $cabecalho = mdLinhaTabela($linha);
            $i += 2;

            $corpo = [];
            while ($i < $total && trim($linhas[$i]) !== '' && str_contains($linhas[$i], '|')) {
                $corpo[] = mdLinhaTabela($linhas[$i]);
                $i++;
            }

            $html = '<div class="rolagem"><table><thead><tr>';
            foreach ($cabecalho as $celula) {
                $html .= '<th>' . mdInline($celula) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($corpo as $linhaCorpo) {
                $html .= '<tr>';
                foreach ($linhaCorpo as $celula) {
                    $html .= '<td>' . mdInline($celula) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
            $out[] = $html;
            continue;
        }

        // Lista com marcador
        if (preg_match('/^[-*]\s+/', $linha)) {
            $itens = [];
            while ($i < $total && preg_match('/^[-*]\s+(.*)$/u', $linhas[$i], $m)) {
                $itens[] = '<li>' . mdInline($m[1]) . '</li>';
                $i++;
            }
            $out[] = '<ul>' . implode('', $itens) . '</ul>';
            continue;
        }

        // Lista numerada
        if (preg_match('/^\d+\.\s+/', $linha)) {
            $itens = [];
            while ($i < $total && preg_match('/^\d+\.\s+(.*)$/u', $linhas[$i], $m)) {
                $itens[] = '<li>' . mdInline($m[1]) . '</li>';
                $i++;
            }
            $out[] = '<ol>' . implode('', $itens) . '</ol>';
            continue;
        }

        // Parágrafo: junta linhas até a próxima linha em branco
        $paragrafo = [];
        while ($i < $total
               && trim($linhas[$i]) !== ''
               && !preg_match('/^(#{1,4})\s+/', $linhas[$i])
               && !preg_match('/^[-*]\s+/', $linhas[$i])
               && !preg_match('/^\d+\.\s+/', $linhas[$i])) {
            $paragrafo[] = $linhas[$i];
            $i++;
        }
        if ($paragrafo) {
            $out[] = '<p>' . mdInline(implode(' ', $paragrafo)) . '</p>';
        }
    }

    return implode("\n", $out);
}

/* ------------------------------------------------------------- execução -- */

$erro    = null;
$aviso   = null;
$editar  = isset($_GET['editar']);
$pdo     = cmsDb();

dossieSchema($pdo);

// Segunda parte da página: o Plano de 12 meses. As ações ?plano=... (estado,
// salvar, responder, apagar, baixar) terminam dentro de planoInicia().
define('DOSSIE_PLANO', true);
require __DIR__ . '/plano.php';
$plano = planoInicia($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], (string) $token)) {
        $erro   = 'A sessão expirou. Recarregue a página e tente salvar de novo.';
        $editar = true;
    } else {
        $novo  = (string) ($_POST['conteudo'] ?? '');
        $autor = trim((string) ($_POST['autor'] ?? ''));
        $autor = $autor !== '' ? mb_substr($autor, 0, 180) : 'anônimo';

        if (trim($novo) === '') {
            $erro   = 'O dossiê ficaria vazio. Nada foi salvo.';
            $editar = true;
        } elseif (strlen($novo) > DOSSIE_MAX_BYTES) {
            $erro   = 'O texto passou do limite de ' . number_format(DOSSIE_MAX_BYTES / 1000, 0, ',', '.') . ' mil caracteres. Nada foi salvo.';
            $editar = true;
        } else {
            $atual = dossieCarrega($pdo);
            dossieSalva($pdo, $novo, $autor, (string) $atual['conteudo']);
            header('Location: /dossie-seo-2026/?salvo=1');
            exit;
        }
    }
}

if (isset($_GET['restaurar'])) {
    $id = (int) $_GET['restaurar'];
    if (hash_equals($_SESSION['csrf'], (string) ($_GET['csrf'] ?? ''))) {
        $stmt = $pdo->prepare('SELECT conteudo FROM dossie_versao WHERE id = ? AND slug = ?');
        $stmt->execute([$id, DOSSIE_SLUG]);
        $versao = $stmt->fetchColumn();
        if ($versao !== false) {
            $atual = dossieCarrega($pdo);
            dossieSalva($pdo, (string) $versao, 'restauração da versão ' . $id, (string) $atual['conteudo']);
            header('Location: /dossie-seo-2026/?salvo=1');
            exit;
        }
        $erro = 'Essa versão não existe mais.';
    } else {
        $erro = 'Link de restauração inválido. Recarregue a página.';
    }
}

$doc = dossieCarrega($pdo);

$versoes = $pdo->prepare(
    'SELECT id, salvo_em, salvo_por, CHAR_LENGTH(conteudo) AS tamanho
     FROM dossie_versao WHERE slug = ? ORDER BY id DESC LIMIT ' . DOSSIE_VERSOES
);
$versoes->execute([DOSSIE_SLUG]);
$historico = $versoes->fetchAll();

if (isset($_GET['salvo'])) {
    $aviso = 'Alterações salvas.';
}

$atualizado = new DateTimeImmutable((string) $doc['atualizado_em']);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
<meta name="googlebot" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $editar ? 'Editando o dossiê: ' : '' ?>SEO da Mclair: plano e acompanhamento</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;1,400&display=swap" rel="stylesheet">
<style>
:root{
  --cream:#FAFAF8; --cream-2:#F3F1EE; --cream-3:#ECEAE6;
  --red:#C8102E; --teal:#1B8A76;
  --ink:#1C1A17; --ink-2:#3D3A36; --ink-3:#6B6560; --ink-4:#9C9590;
  --line:#E5E2DD; --line-2:#D5D1CB;
  --font:'Lato',system-ui,-apple-system,sans-serif;
  --max-w:860px; --gutter:clamp(20px,5vw,48px);
}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{
  margin:0; background:var(--cream); color:var(--ink);
  font-family:var(--font); font-size:17px; line-height:1.65;
  padding:0 var(--gutter) 96px;
  padding-top:env(safe-area-inset-top,0px);
  padding-bottom:calc(96px + env(safe-area-inset-bottom,0px));
}
.folha{max-width:var(--max-w); margin:0 auto}

/* Cabeçalho */
.topo{
  display:flex; flex-wrap:wrap; gap:16px; align-items:baseline;
  justify-content:space-between;
  padding:40px 0 8px; border-bottom:1px solid var(--line); margin-bottom:40px;
}
.marca{font-weight:700; letter-spacing:-0.01em; color:var(--red); text-decoration:none; font-size:19px}
.reservado{font-size:13px; color:var(--ink-4)}

/* Tipografia do documento */
h1,h2,h3,h4{line-height:1.2; letter-spacing:-0.015em; text-wrap:balance; margin:0}
h1{font-size:clamp(30px,5vw,42px); font-weight:700; margin-bottom:8px}
:where(.dossie) h2{font-size:clamp(23px,3.2vw,29px); font-weight:700; margin:52px 0 14px; padding-top:20px; border-top:1px solid var(--line)}
:where(.dossie) h3{font-size:19px; font-weight:700; margin:34px 0 10px; color:var(--ink-2)}
:where(.dossie) h4{font-size:17px; font-weight:700; margin:26px 0 8px; color:var(--ink-2)}
p{margin:0}
:where(.dossie) p{margin:0 0 17px; text-wrap:pretty}
:where(.dossie) ul,:where(.dossie) ol{margin:0 0 20px; padding-left:22px}
:where(.dossie) li{margin-bottom:7px; text-wrap:pretty}
strong{font-weight:700}
a{color:var(--red); text-underline-offset:2px}
a:hover{color:var(--ink)}
:where(.dossie) code{
  font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:0.87em;
  background:var(--cream-3); padding:1px 5px; border-radius:3px; word-break:break-word;
}

/* Tabelas */
.rolagem{overflow-x:auto; margin:0 0 26px; -webkit-overflow-scrolling:touch}
:where(.dossie) table{border-collapse:collapse; width:100%; font-size:15px; min-width:460px}
:where(.dossie) thead th{
  text-align:left; font-weight:700; font-size:13px; text-transform:uppercase;
  letter-spacing:0.04em; color:var(--ink-3);
  border-bottom:2px solid var(--line-2); padding:0 14px 9px 0; vertical-align:bottom;
}
:where(.dossie) tbody td{
  border-bottom:1px solid var(--line); padding:11px 14px 11px 0;
  vertical-align:top; text-wrap:pretty;
}
:where(.dossie) tbody tr:last-child td{border-bottom:none}
:where(.dossie) thead th:last-child,:where(.dossie) tbody td:last-child{padding-right:0}

/* Barra de ações */
.acoes{
  display:flex; flex-wrap:wrap; gap:10px; align-items:center;
  margin:14px 0 44px; font-size:14px; color:var(--ink-3);
}
.btn{
  display:inline-block; font:inherit; font-size:14px; font-weight:700;
  padding:9px 18px; border-radius:6px; cursor:pointer; text-decoration:none;
  border:1px solid var(--line-2); background:#fff; color:var(--ink);
}
.btn:hover{border-color:var(--ink-4); color:var(--ink)}
.btn-forte{background:var(--ink); border-color:var(--ink); color:var(--cream)}
.btn-forte:hover{background:#000; color:#fff}
.quando{margin-left:auto; text-align:right; font-size:13px; color:var(--ink-4)}

/* Editor */
:where(.dossie) textarea{
  width:100%; min-height:62vh; font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:14px; line-height:1.7; padding:18px; border:1px solid var(--line-2);
  border-radius:8px; background:#fff; color:var(--ink); resize:vertical;
}
textarea:focus,input:focus{outline:2px solid var(--teal); outline-offset:1px}
:where(.dossie) input[type=text]{
  font:inherit; font-size:14px; padding:9px 12px; border:1px solid var(--line-2);
  border-radius:6px; background:#fff; max-width:260px;
}
:where(.dossie) label{font-size:14px; color:var(--ink-3)}
.dica{font-size:13px; color:var(--ink-4); margin:10px 0 18px}

/* Recados */
.recado{padding:12px 16px; border-radius:6px; margin:0 0 24px; font-size:15px}
.recado-ok{background:var(--teal); color:#fff}
.recado-erro{background:var(--red); color:#fff}

/* Histórico */
:where(.dossie) details{margin-top:56px; border-top:1px solid var(--line); padding-top:22px}
:where(.dossie) summary{cursor:pointer; font-weight:700; font-size:15px; color:var(--ink-2)}
:where(.dossie) details table{font-size:14px; margin-top:16px; min-width:0}
/* Anexo: o dossiê de setembro, recolhido */
details.anexo{margin-top:64px; padding-top:28px; border-top:1px solid var(--line-2); scroll-margin-top:12px}
details.anexo > summary{cursor:pointer; list-style:none; display:grid; gap:4px}
details.anexo > summary::-webkit-details-marker{display:none}
.anexo-tit{font-size:clamp(24px,3.4vw,30px); font-weight:700; letter-spacing:-0.015em; line-height:1.2}
.anexo-sub{font-size:15px; color:var(--ink-3)}
.anexo-acao{color:var(--red); font-weight:700; text-decoration:underline; text-underline-offset:3px}
details.anexo > summary:hover .anexo-tit{color:var(--red)}
details.anexo > summary:focus-visible{outline:2px solid var(--teal); outline-offset:4px; border-radius:4px}
details.anexo[open] .anexo-abrir, details.anexo:not([open]) .anexo-fechar{display:none}
details.anexo > .dossie{margin-top:28px}
details.anexo article > h1:first-child{font-size:clamp(24px,3.4vw,30px)}
.rodape{margin-top:56px; padding-top:20px; border-top:1px solid var(--line); font-size:13px; color:var(--ink-4)}
@media (max-width:560px){
  body{font-size:16px}
  .quando{margin-left:0; text-align:left; width:100%}
}
<?php readfile(__DIR__ . '/plano.css'); ?>
</style>
</head>
<body>
<div class="folha">

  <header class="topo">
    <a class="marca" href="https://mclair.com.br/">Mclair</a>
    <span class="reservado">Documento interno, fora dos buscadores</span>
  </header>

  <?php if ($aviso): ?>
    <p class="recado recado-ok"><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?> O texto está no <a href="#dossie">anexo do diagnóstico de setembro</a>.</p>
  <?php endif; ?>
  <?php if ($erro && !$editar): ?>
    <p class="recado recado-erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <?php if ($editar): ?>

  <div class="dossie" id="dossie">
    <?php if ($erro): ?>
      <p class="recado recado-erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <h1>Editando o dossiê</h1>
    <p class="dica">O texto usa markdown: <code>##</code> para título de seção, <code>|</code> para tabelas,
       <code>-</code> para listas, <code>**negrito**</code> e <code>[texto](endereço)</code> para links.
       A versão anterior fica guardada e dá para voltar atrás.</p>

    <form method="post" action="/dossie-seo-2026/">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
      <textarea name="conteudo" spellcheck="true"><?= htmlspecialchars((string) $doc['conteudo'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <div class="acoes">
        <label for="autor">Quem está editando</label>
        <input type="text" id="autor" name="autor" placeholder="seu nome" maxlength="180">
        <button type="submit" class="btn btn-forte">Salvar</button>
        <a class="btn" href="/dossie-seo-2026/">Cancelar</a>
      </div>
    </form>
  </div>

  <?php else: ?>

  <?php planoSecao($plano); ?>

  <details class="anexo" id="dossie"<?= ($aviso || $erro) ? ' open' : '' ?>>
    <summary>
      <span class="anexo-tit">Anexo: diagnóstico de setembro</span>
      <span class="anexo-sub">O dossiê de concorrência de 23 de setembro de 2026, de onde saiu o plano. <span class="anexo-acao anexo-abrir">Abrir o diagnóstico</span><span class="anexo-acao anexo-fechar">Fechar</span></span>
    </summary>
    <div class="dossie">

    <div class="acoes">
      <a class="btn btn-forte" href="/dossie-seo-2026/?editar=1">Editar</a>
      <span class="quando">
        Atualizado em <?= $atualizado->format('d/m/Y') ?> às <?= $atualizado->format('H:i') ?>
        <?php if (!empty($doc['atualizado_por'])): ?>
          por <?= htmlspecialchars((string) $doc['atualizado_por'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </span>
    </div>

    <article><?= mdParaHtml((string) $doc['conteudo']) ?></article>

    <?php if ($historico): ?>
    <details>
      <summary>Versões anteriores (<?= count($historico) ?>)</summary>
      <div class="rolagem">
      <table>
        <thead><tr><th>Quando</th><th>Quem</th><th>Tamanho</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($historico as $v):
            $quando = new DateTimeImmutable((string) $v['salvo_em']); ?>
          <tr>
            <td><?= $quando->format('d/m/Y H:i') ?></td>
            <td><?= htmlspecialchars((string) ($v['salvo_por'] ?? 'sem nome'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int) $v['tamanho'], 0, ',', '.') ?> car.</td>
            <td><a href="/dossie-seo-2026/?restaurar=<?= (int) $v['id'] ?>&amp;csrf=<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">restaurar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </details>
    <?php endif; ?>

    </div>
  </details>

  <?php endif; ?>

  <p class="rodape">
    Esta página pede aos buscadores que não a listem, mas o endereço continua público:
    quem tiver o link consegue abrir e editar.
  </p>

</div>
</body>
</html>
