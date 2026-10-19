<?php
// Mensagens: todo lead que chega pelos formulários do site (gravado por
// /api/contato.php antes do e-mail sair), com busca, filtros, ficha
// completa, status, responsável, anotações e exportação em CSV.
// Rota: /acesso/mensagens (ver .htaccess). Só admin e editor.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/mensagens-lib.php';
cmsRequireRole(['admin', 'editor']);
$pdo = cmsDb();
mcmEnsureSchema($pdo);

function hh($v): string { return mc_h((string)$v); }
$meuId = (int)($_SESSION['cms_user_id'] ?? 0);
$souAdmin = cmsRole() === 'admin';

// Usuários que podem atender (para o campo "responsável").
$equipe = [];
foreach ($pdo->query("SELECT id, username, display_name FROM cmstest_users WHERE role IN ('admin','editor') ORDER BY display_name, username") as $u) {
    $equipe[(int)$u['id']] = $u['display_name'] ?: $u['username'];
}
function nomeUsuario(array $equipe, $id): string {
    return $id ? ($equipe[(int)$id] ?? ('usuário #' . (int)$id)) : '';
}

// ---------------------------------------------------------------- ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cmsCsrfValidate($_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
    }
    $id = (int)($_POST['id'] ?? 0);
    $acao = (string)($_POST['action'] ?? '');
    $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { http_response_code(404); die('Mensagem não encontrada.'); }
    $volta = 'mensagens?id=' . $id;

    if ($acao === 'status') {
        $novo = (string)($_POST['status'] ?? '');
        if (isset(MCM_STATUS[$novo]) && $novo !== $m['status']) {
            $pdo->prepare('UPDATE cmstest_mensagens SET status = ?, atualizado_em = UTC_TIMESTAMP() WHERE id = ?')->execute([$novo, $id]);
            mcmEvento($pdo, $id, 'status', 'Status mudou de "' . (MCM_STATUS[$m['status']] ?? $m['status']) . '" para "' . MCM_STATUS[$novo] . '"', $meuId);
        }
        header('Location: ' . $volta . '&ok=status');
        exit;
    }
    if ($acao === 'responsavel') {
        $resp = (int)($_POST['responsavel_id'] ?? 0);
        if ($resp !== 0 && !isset($equipe[$resp])) $resp = 0;
        if ($resp !== (int)$m['responsavel_id']) {
            $pdo->prepare('UPDATE cmstest_mensagens SET responsavel_id = ?, atualizado_em = UTC_TIMESTAMP() WHERE id = ?')->execute([$resp ?: null, $id]);
            mcmEvento($pdo, $id, 'responsavel', $resp ? 'Passou a ser atendida por ' . $equipe[$resp] : 'Ficou sem responsável', $meuId);
            // Quem assume uma mensagem nova já está atendendo.
            if ($resp && $m['status'] === 'novo') {
                $pdo->prepare("UPDATE cmstest_mensagens SET status = 'em_atendimento' WHERE id = ?")->execute([$id]);
                mcmEvento($pdo, $id, 'status', 'Status mudou de "Novo" para "Em atendimento"', $meuId);
            }
        }
        header('Location: ' . $volta . '&ok=responsavel');
        exit;
    }
    if ($acao === 'nota') {
        $texto = mc_texto($_POST['texto'] ?? '', 4000);
        if ($texto !== '') mcmEvento($pdo, $id, 'nota', $texto, $meuId);
        header('Location: ' . $volta . '&ok=nota#linha-do-tempo');
        exit;
    }
    if ($acao === 'excluir') {
        $pdo->prepare('UPDATE cmstest_mensagens SET excluido_em = UTC_TIMESTAMP(), excluido_por = ? WHERE id = ?')->execute([$meuId, $id]);
        mcmEvento($pdo, $id, 'excluido', 'Movida para a lixeira', $meuId);
        header('Location: mensagens?ok=excluida');
        exit;
    }
    if ($acao === 'restaurar') {
        $pdo->prepare('UPDATE cmstest_mensagens SET excluido_em = NULL, excluido_por = NULL WHERE id = ?')->execute([$id]);
        mcmEvento($pdo, $id, 'restaurado', 'Restaurada da lixeira', $meuId);
        header('Location: ' . $volta . '&ok=restaurada');
        exit;
    }
    if ($acao === 'apagar') {
        if (!$souAdmin || $m['excluido_em'] === null) { http_response_code(403); die('Só um administrador apaga de vez, e só o que já está na lixeira.'); }
        $pdo->prepare('DELETE FROM cmstest_mensagens_eventos WHERE mensagem_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cmstest_mensagens WHERE id = ?')->execute([$id]);
        header('Location: mensagens?lixeira=1&ok=apagada');
        exit;
    }
    if ($acao === 'reenviar') {
        if ($m['enviado_em'] === null) { http_response_code(400); die('Esta mensagem não foi enviada pela pessoa; não há aviso a reenviar.'); }
        [$ok, $erro] = mcmEmailEnviar($m);
        if ($ok) {
            $pdo->prepare("UPDATE cmstest_mensagens SET email_status = 'enviado', email_erro = '', email_enviado_em = UTC_TIMESTAMP() WHERE id = ?")->execute([$id]);
            mcmEvento($pdo, $id, 'reenviado', 'Aviso reenviado por e-mail para ' . MCM_DESTINO, $meuId);
        } else {
            $pdo->prepare("UPDATE cmstest_mensagens SET email_status = 'falhou', email_erro = ? WHERE id = ?")->execute([$erro, $id]);
            mcmEvento($pdo, $id, 'email_falhou', 'Reenvio falhou: ' . $erro, $meuId);
        }
        header('Location: ' . $volta . '&ok=' . ($ok ? 'reenviado' : 'reenvio_falhou'));
        exit;
    }
    http_response_code(400);
    die('Ação desconhecida.');
}

// ------------------------------------------------------- filtros da lista
$f = [
    'q' => mc_linha($_GET['q'] ?? '', 120),
    'status' => (string)($_GET['status'] ?? ''),
    'origem' => (string)($_GET['origem'] ?? ''),
    'formulario' => preg_replace('/[^a-z0-9\-]/', '', (string)($_GET['formulario'] ?? '')) ?? '',
    'tipo' => in_array($_GET['tipo'] ?? '', ['enviadas', 'abandonadas', 'todas'], true) ? $_GET['tipo'] : 'enviadas',
    'de' => dataValida((string)($_GET['de'] ?? '')),
    'ate' => dataValida((string)($_GET['ate'] ?? '')),
    'lixeira' => !empty($_GET['lixeira']),
];
if (!isset(MCM_STATUS[$f['status']])) $f['status'] = '';
if ($f['origem'] !== '' && $f['origem'] !== 'sem' && !isset(MCM_ORIGENS[$f['origem']])) $f['origem'] = '';

function dataValida(string $v): string {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : '';
}
function filtroSql(array $f): array {
    $w = [];
    $p = [];
    if ($f['lixeira']) { $w[] = 'excluido_em IS NOT NULL'; }
    else {
        $w[] = 'excluido_em IS NULL';
        if ($f['tipo'] === 'enviadas') $w[] = 'enviado_em IS NOT NULL';
        elseif ($f['tipo'] === 'abandonadas') $w[] = 'enviado_em IS NULL';
    }
    if ($f['status'] !== '') { $w[] = 'status = ?'; $p[] = $f['status']; }
    if ($f['origem'] === 'sem') { $w[] = "origem = ''"; }
    elseif ($f['origem'] !== '') { $w[] = 'origem = ?'; $p[] = $f['origem']; }
    if ($f['formulario'] !== '') { $w[] = 'formulario = ?'; $p[] = $f['formulario']; }
    $tz = new DateTimeZone(MCM_FUSO);
    if ($f['de'] !== '') { $w[] = 'COALESCE(enviado_em, criado_em) >= ?'; $p[] = (new DateTimeImmutable($f['de'] . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    if ($f['ate'] !== '') { $w[] = 'COALESCE(enviado_em, criado_em) <= ?'; $p[] = (new DateTimeImmutable($f['ate'] . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $w[] = '(nome LIKE ? OR email LIKE ? OR telefone LIKE ? OR empresa LIKE ? OR assunto LIKE ? OR servicos LIKE ? OR mensagem LIKE ? OR objetivo LIKE ?)';
        for ($i = 0; $i < 8; $i++) $p[] = $like;
    }
    return [' WHERE ' . implode(' AND ', $w), $p];
}

function rotuloInteresse(array $m): string {
    if ($m['assunto'] !== '') return $m['assunto'];
    if ($m['servicos'] !== '') return $m['servicos'];
    if (($m['objetivo'] ?? '') !== '') return mb_substr((string)$m['objetivo'], 0, 80);
    if (($m['mensagem'] ?? '') !== '') return mb_substr((string)$m['mensagem'], 0, 80);
    return '';
}
function iniciais(string $nome): string {
    $o = '';
    foreach (preg_split('/\s+/u', trim($nome)) ?: [] as $w) {
        if ($w !== '' && mb_strlen($o) < 2) $o .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $o !== '' ? $o : '?';
}
function origemTexto(array $m): string {
    $o = MCM_ORIGENS[$m['origem']] ?? MCM_ORIGENS[''];
    return $m['origem_detalhe'] !== '' ? $o . ' · ' . $m['origem_detalhe'] : $o;
}
function badgeStatus(array $m): string {
    if ($m['enviado_em'] === null) return '<span class="st st-abandonado">Não enviado</span>';
    $k = $m['status'];
    return '<span class="st st-' . hh($k) . '">' . hh(MCM_STATUS[$k] ?? $k) . '</span>';
}
function lugar(array $m): string {
    $partes = array_filter([$m['geo_cidade'], $m['geo_estado']]);
    $t = implode(', ', $partes);
    if ($m['geo_pais'] !== '' && $m['geo_pais'] !== 'BR') $t .= ($t !== '' ? ' · ' : '') . $m['geo_pais'];
    return $t;
}

// ----------------------------------------------------------- exportação
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    [$where, $params] = filtroSql($f);
    $st = $pdo->prepare('SELECT * FROM cmstest_mensagens' . $where . ' ORDER BY id DESC LIMIT 5001');
    $st->execute($params);
    $linhas = $st->fetchAll();
    $cortou = count($linhas) > 5000;
    if ($cortou) array_pop($linhas);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mensagens-mclair-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cab = ['id', 'criado_em', 'enviado_em', 'status', 'responsavel', 'formulario', 'nome', 'email', 'telefone', 'empresa', 'assunto', 'servicos', 'orcamento', 'objetivo', 'mensagem', 'pagina', 'pagina_entrada', 'referrer', 'origem', 'origem_detalhe', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ga_client_id', 'cidade', 'estado', 'pais', 'dispositivo', 'navegador', 'sistema', 'visitas', 'paginas_vistas', 'primeira_visita_em', 'email_aviso'];
    fputcsv($out, $cab, ';', '"', '');
    // Célula começando com = + - @ vira fórmula no Excel: neutraliza.
    $seg = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    foreach ($linhas as $m) {
        fputcsv($out, array_map($seg, [
            $m['id'], mcmDataLocal($m['criado_em']), mcmDataLocal($m['enviado_em']), $m['enviado_em'] === null ? 'nao_enviado' : $m['status'], nomeUsuario($equipe, $m['responsavel_id']),
            MCM_FORMULARIOS[$m['formulario']] ?? $m['formulario'], $m['nome'], $m['email'], $m['telefone'], $m['empresa'], $m['assunto'], $m['servicos'], $m['orcamento'], $m['objetivo'], $m['mensagem'],
            $m['pagina'], $m['landing'], $m['referrer'], MCM_ORIGENS[$m['origem']] ?? '', $m['origem_detalhe'],
            $m['utm_source'], $m['utm_medium'], $m['utm_campaign'], $m['utm_term'], $m['utm_content'], $m['gclid'], $m['gbraid'], $m['wbraid'], $m['fbclid'], $m['ga_client_id'],
            $m['geo_cidade'], $m['geo_estado'], $m['geo_pais'], $m['dispositivo'], $m['navegador'], $m['sistema'], $m['visitas'], $m['paginas_vistas'], mcmDataLocal($m['primeira_visita_em']), $m['email_status'],
        ]), ';', '"', '');
    }
    if ($cortou) fputcsv($out, ['A exportação para em 5000 linhas. Use o filtro de período para exportar o resto.'], ';', '"', '');
    fclose($out);
    exit;
}

// ------------------------------------------------------------------ ficha
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { http_response_code(404); die('Mensagem não encontrada.'); }
    $ev = $pdo->prepare('SELECT * FROM cmstest_mensagens_eventos WHERE mensagem_id = ? ORDER BY criado_em, id');
    $ev->execute([$id]);
    $eventos = $ev->fetchAll();
    $toques = json_decode((string)$m['toques'], true);
    $jornada = json_decode((string)$m['jornada'], true);
    $campos = json_decode((string)$m['campos'], true);
    if (!is_array($campos)) $campos = [];
    $waDigitos = mcmTelefoneWa((string)$m['telefone']);
    $abandonada = $m['enviado_em'] === null;
    $titulo = $m['nome'] !== '' ? $m['nome'] : 'Mensagem #' . $id;
    $okMsg = [
        'status' => 'Status atualizado.', 'responsavel' => 'Responsável atualizado.', 'nota' => 'Anotação guardada.',
        'restaurada' => 'Mensagem restaurada.', 'reenviado' => 'Aviso reenviado por e-mail.', 'reenvio_falhou' => 'O reenvio falhou. Veja o motivo em "E-mail de aviso".',
    ][$_GET['ok'] ?? ''] ?? null;

    // Texto para o botão "Copiar dados".
    $copiar = [];
    foreach ([['Nome', $m['nome']], ['E-mail', $m['email']], ['WhatsApp', $m['telefone']], ['Empresa', $m['empresa']], ['Assunto', $m['assunto']], ['Serviços', $m['servicos']], ['Verba', $m['orcamento']], ['O que quer divulgar', $m['objetivo']], ['Mensagem', $m['mensagem']], ['Formulário', MCM_FORMULARIOS[$m['formulario']] ?? $m['formulario']], ['Página', $m['pagina'] !== '' ? 'https://mclair.com.br' . $m['pagina'] : ''], ['Recebido em', mcmDataLocal($m['enviado_em'] ?? $m['criado_em'])], ['Origem', origemTexto($m)]] as [$r, $v]) {
        if ((string)$v !== '') $copiar[] = $r . ': ' . $v;
    }
    $rotuloEvento = [
        'rascunho' => 'Começou a preencher', 'enviado' => 'Formulário enviado', 'email_ok' => 'E-mail de aviso enviado', 'email_falhou' => 'E-mail de aviso falhou',
        'status' => 'Status', 'responsavel' => 'Responsável', 'nota' => 'Anotação', 'ga4' => 'GA4', 'importado' => 'Importado', 'excluido' => 'Lixeira', 'restaurado' => 'Restaurada', 'reenviado' => 'E-mail reenviado',
    ];

    adminLayoutTop('messages', $titulo, ['label' => 'Mensagens', 'href' => '/acesso/mensagens']);
    ?>
    <style>
      .mg-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; flex-wrap:wrap; margin-bottom:20px; }
      .mg-who { display:flex; align-items:center; gap:14px; min-width:0; }
      .mg-avatar { width:52px; height:52px; border-radius:50%; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0; }
      .mg-avatar.off { background:#9CA3AF; }
      .mg-who h2 { margin:0 0 4px; font-size:1.25rem; letter-spacing:-.01em; }
      .mg-who .sub { font-size:.84rem; color:var(--ink-3); font-weight:600; display:flex; flex-wrap:wrap; gap:4px 12px; }
      .mg-who .sub a { color:var(--ink); }
      .mg-who .sub a:hover { color:var(--red); }
      .mg-acoes { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
      .mg-acoes .btn { padding:9px 14px; font-size:.8rem; }
      .btn.wa { background:#1E7F4D; }
      .st { display:inline-block; padding:4px 10px; border-radius:6px; font-size:.72rem; font-weight:700; white-space:nowrap; background:var(--soft); color:var(--ink-3); }
      .st-novo { background:rgba(200,16,46,.1); color:var(--red); }
      .st-em_atendimento { background:#E8F0FB; color:#1D4ED8; }
      .st-atendido { background:#E6F4EC; color:#1E7F4D; }
      .st-proposta_enviada { background:#EFE9FB; color:#6D28D9; }
      .st-fechado { background:#1E7F4D; color:#fff; }
      .st-perdido { background:#F1F2F4; color:#6B7280; }
      .st-abandonado { background:#FFF4E5; color:#9A5B00; }
      .mg-grid { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:20px; align-items:start; }
      @media (max-width: 980px) { .mg-grid { grid-template-columns:1fr; } }
      .mg-card { background:var(--paper); border:1px solid var(--line); border-radius:12px; padding:20px 22px; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:18px; }
      .mg-card h3 { margin:0 0 14px; font-size:.95rem; }
      .mg-card h3 small { font-weight:600; color:var(--ink-3); font-size:.76rem; margin-left:8px; }
      dl.kv { margin:0; display:grid; grid-template-columns:150px minmax(0,1fr); gap:9px 14px; font-size:.86rem; }
      dl.kv dt { color:var(--ink-3); font-weight:600; }
      dl.kv dd { margin:0; word-break:break-word; }
      dl.kv dd.pre { white-space:pre-wrap; line-height:1.55; }
      dl.kv dd .muted, .muted { color:#9CA3AF; }
      @media (max-width: 480px) { dl.kv { grid-template-columns:1fr; gap:2px 0; } dl.kv dd { margin-bottom:8px; } }
      .mg-bloco { background:var(--soft); border-radius:10px; padding:14px 16px; white-space:pre-wrap; line-height:1.6; font-size:.9rem; margin:6px 0 14px; }
      .mg-aviso { border:1px solid var(--line); background:var(--soft); border-radius:10px; padding:12px 16px; font-size:.86rem; margin-bottom:18px; line-height:1.5; }
      .mg-aviso.atencao { background:#FFF4E5; border-color:#F3D9A4; color:#7A4A00; }
      .mg-aviso.falha { background:rgba(200,16,46,.07); border-color:rgba(200,16,46,.25); color:#8A0C20; }
      .mg-form-linha { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
      .mg-form-linha > div { flex:1; min-width:160px; }
      .mg-form-linha label { margin-top:0; }
      .mg-form-linha .btn { padding:10px 14px; }
      .tl { list-style:none; margin:0; padding:0; }
      .tl li { display:grid; grid-template-columns:120px minmax(0,1fr); gap:0 14px; padding:10px 0; border-top:1px solid var(--line); font-size:.85rem; }
      .tl li:first-child { border-top:none; padding-top:0; }
      .tl .quando { color:var(--ink-3); font-weight:600; font-size:.76rem; line-height:1.5; }
      .tl .tipo { font-weight:700; }
      .tl .texto { white-space:pre-wrap; line-height:1.5; margin-top:2px; }
      .tl .quem { color:var(--ink-3); font-size:.76rem; font-weight:600; margin-top:2px; }
      .tl li.nota .texto { background:var(--soft); border-radius:8px; padding:10px 12px; margin-top:6px; }
      @media (max-width: 480px) { .tl li { grid-template-columns:1fr; } }
      .pags { list-style:none; margin:0; padding:0; font-size:.82rem; }
      .pags li { display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-top:1px solid var(--line); }
      .pags li:first-child { border-top:none; }
      .pags li span:first-child { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
      .pags li span:last-child { color:var(--ink-3); font-weight:600; flex-shrink:0; }
      textarea.nota { min-height:90px; }
      .mg-rodape-acoes { display:flex; gap:14px; flex-wrap:wrap; align-items:center; margin-top:6px; font-size:.8rem; }
      .mg-rodape-acoes button.link { background:none; border:none; padding:0; color:#9a1f1f; text-decoration:underline; font-weight:700; cursor:pointer; font-family:inherit; font-size:.8rem; }
      .geo-atrib { font-size:.7rem; color:#9CA3AF; margin:10px 0 0; }
      .geo-atrib a { color:inherit; text-decoration:underline; }
    </style>

    <?php if ($okMsg): ?><div class="msg ok"><?= cmsCheckIcon() ?><span><?= hh($okMsg) ?></span></div><?php endif; ?>

    <div class="mg-head">
      <div class="mg-who">
        <div class="mg-avatar <?= $abandonada ? 'off' : '' ?>"><?= hh(iniciais((string)$m['nome'])) ?></div>
        <div>
          <h2><?= hh($m['nome'] !== '' ? $m['nome'] : 'Sem nome') ?> <span class="muted" style="font-weight:600;font-size:.9rem">#<?= $id ?></span></h2>
          <div class="sub">
            <?= badgeStatus($m) ?>
            <?php if ($m['email'] !== ''): ?><a href="mailto:<?= hh(rawurlencode($m['email'])) ?>"><?= hh($m['email']) ?></a><?= $m['email_valido'] ? '' : ' <span class="muted">(formato não conferido)</span>' ?><?php endif; ?>
            <?php if ($m['telefone'] !== ''): ?><span><?= hh($m['telefone']) ?></span><?php endif; ?>
            <?php if ($m['empresa'] !== ''): ?><span><?= hh($m['empresa']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="mg-acoes">
        <?php if ($waDigitos !== ''): ?><a class="btn wa" href="https://wa.me/<?= hh($waDigitos) ?>?text=<?= rawurlencode('Olá' . ($m['nome'] !== '' ? ' ' . explode(' ', trim((string)$m['nome']))[0] : '') . ', aqui é da Mclair. Recebemos a sua mensagem pelo site.') ?>" target="_blank" rel="noopener">Abrir WhatsApp</a><?php endif; ?>
        <?php if ($m['email_valido']): ?><a class="btn secondary" href="mailto:<?= hh(rawurlencode($m['email'])) ?>?subject=<?= rawurlencode('Re: ' . ($m['assunto'] !== '' ? $m['assunto'] : 'sua mensagem pelo site da Mclair')) ?>">Responder por e-mail</a><?php endif; ?>
        <button type="button" class="btn secondary" id="btnCopiar" data-texto="<?= hh(implode("\n", $copiar)) ?>">Copiar dados</button>
      </div>
    </div>

    <?php if ($m['excluido_em'] !== null): ?>
    <div class="mg-aviso atencao">Esta mensagem está na lixeira desde <?= hh(mcmDataLocal($m['excluido_em'])) ?><?= $m['excluido_por'] ? ' (' . hh(nomeUsuario($equipe, $m['excluido_por'])) . ')' : '' ?>. Ela não aparece na lista nem conta como nova.
      <form method="post" style="display:inline;margin-left:8px"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="restaurar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.78rem">Restaurar</button></form>
    </div>
    <?php endif; ?>
    <?php if ($abandonada): ?>
    <div class="mg-aviso atencao">A pessoa preencheu nome e contato mas <strong>não chegou a enviar</strong> o formulário (dados capturados em <?= hh(mcmDataLocal($m['criado_em'])) ?>, última alteração <?= hh(mcmDataLocal($m['atualizado_em'])) ?>). Nenhum e-mail foi mandado. Vale um contato leve, sem presumir que ela pediu retorno.</div>
    <?php elseif ($m['email_status'] === 'falhou'): ?>
    <div class="mg-aviso falha">O e-mail de aviso para <?= hh(MCM_DESTINO) ?> <strong>não saiu</strong> (<?= hh($m['email_erro'] ?: 'sem detalhe') ?>). A mensagem está completa aqui; responda pelos botões acima ou reenvie o aviso em "E-mail de aviso".</div>
    <?php endif; ?>
    <?php if ($m['observacao'] !== ''): ?><div class="mg-aviso"><?= hh($m['observacao']) ?></div><?php endif; ?>

    <div class="mg-grid">
      <div>
        <div class="mg-card">
          <h3>O que a pessoa escreveu <small><?= hh(MCM_FORMULARIOS[$m['formulario']] ?? ($m['formulario'] !== '' ? $m['formulario'] : 'formulário do site')) ?></small></h3>
          <dl class="kv">
            <?php if ($m['assunto'] !== ''): ?><dt>Assunto</dt><dd><?= hh($m['assunto']) ?></dd><?php endif; ?>
            <?php if ($m['servicos'] !== ''): ?><dt>Serviços de interesse</dt><dd><?= hh($m['servicos']) ?></dd><?php endif; ?>
            <?php if ($m['orcamento'] !== ''): ?><dt>Verba</dt><dd><?= hh($m['orcamento']) ?></dd><?php endif; ?>
            <?php foreach ($campos as $k => $v): if (in_array($k, ['nome', 'name', 'email', 'telefone', 'whatsapp', 'tel', 'empresa', 'assunto', 'interesse', 'desafio', 'servicos', 'servico', 'objetivo', 'orcamento', 'mensagem', 'msg'], true)) continue; ?>
            <dt><?= hh(ucfirst(str_replace(['_', '-'], ' ', (string)$k))) ?></dt><dd class="pre"><?= hh($v) ?></dd>
            <?php endforeach; ?>
          </dl>
          <?php if ((string)$m['objetivo'] !== ''): ?><p style="margin:16px 0 0;font-size:.78rem;font-weight:700;color:var(--ink-3)">O que quer divulgar</p><div class="mg-bloco"><?= hh($m['objetivo']) ?></div><?php endif; ?>
          <?php if ((string)$m['mensagem'] !== ''): ?><p style="margin:16px 0 0;font-size:.78rem;font-weight:700;color:var(--ink-3)">Mensagem</p><div class="mg-bloco"><?= hh($m['mensagem']) ?></div><?php endif; ?>
          <?php if ($m['assunto'] === '' && $m['servicos'] === '' && (string)$m['objetivo'] === '' && (string)$m['mensagem'] === '' && !$campos): ?><p class="muted" style="margin:0;font-size:.86rem">Só nome e contato até agora.</p><?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>Atendimento</h3>
          <form method="post" class="mg-form-linha">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="status" /><input type="hidden" name="id" value="<?= $id ?>" />
            <div><label>Status</label>
              <select name="status"><?php foreach (MCM_STATUS as $k => $v): ?><option value="<?= hh($k) ?>" <?= $k === $m['status'] ? 'selected' : '' ?>><?= hh($v) ?></option><?php endforeach; ?></select>
            </div>
            <button type="submit" class="btn">Salvar status</button>
          </form>
          <form method="post" class="mg-form-linha" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="responsavel" /><input type="hidden" name="id" value="<?= $id ?>" />
            <div><label>Quem atende</label>
              <select name="responsavel_id"><option value="0">Ninguém ainda</option><?php foreach ($equipe as $uid => $un): ?><option value="<?= $uid ?>" <?= $uid === (int)$m['responsavel_id'] ? 'selected' : '' ?>><?= hh($un) ?></option><?php endforeach; ?></select>
            </div>
            <button type="submit" class="btn secondary">Salvar</button>
            <?php if ((int)$m['responsavel_id'] !== $meuId && isset($equipe[$meuId])): ?><button type="submit" class="btn" name="responsavel_id" value="<?= $meuId ?>">Eu atendo</button><?php endif; ?>
          </form>
        </div>

        <div class="mg-card" id="linha-do-tempo">
          <h3>Linha do tempo</h3>
          <ul class="tl">
            <?php if ($m['primeira_visita_em']): ?>
            <li><span class="quando"><?= hh(mcmDataLocal($m['primeira_visita_em'], 'd/m/Y')) ?><br><?= hh(mcmDataLocal($m['primeira_visita_em'], 'H:i')) ?></span><span><span class="tipo">Primeira visita ao site</span><div class="texto muted"><?= hh(origemTexto($m)) ?><?= $m['landing'] !== '' ? ' · entrou por ' . hh($m['landing']) : '' ?></div></span></li>
            <?php endif; ?>
            <?php foreach ($eventos as $e): ?>
            <li class="<?= hh($e['tipo']) ?>"><span class="quando"><?= hh(mcmDataLocal($e['criado_em'], 'd/m/Y')) ?><br><?= hh(mcmDataLocal($e['criado_em'], 'H:i')) ?></span>
              <span><span class="tipo"><?= hh($rotuloEvento[$e['tipo']] ?? $e['tipo']) ?></span>
              <?php if ((string)$e['texto'] !== ''): ?><div class="texto"><?= hh($e['texto']) ?></div><?php endif; ?>
              <?php if ($e['usuario_id']): ?><div class="quem"><?= hh(nomeUsuario($equipe, $e['usuario_id'])) ?></div><?php endif; ?></span></li>
            <?php endforeach; ?>
          </ul>
          <form method="post" style="margin-top:16px">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="nota" /><input type="hidden" name="id" value="<?= $id ?>" />
            <label>Anotação interna</label>
            <textarea name="texto" class="nota" placeholder="Ligou e pediu proposta para dezembro, por exemplo. Só a equipe vê."></textarea>
            <button type="submit" class="btn secondary" style="margin-top:10px">Guardar anotação</button>
          </form>
        </div>
      </div>

      <div>
        <div class="mg-card">
          <h3>Origem e visita</h3>
          <dl class="kv" style="grid-template-columns:110px minmax(0,1fr)">
            <dt>Origem</dt><dd><?= hh(origemTexto($m)) ?></dd>
            <?php if ($m['utm_source'] !== '' || $m['utm_medium'] !== '' || $m['utm_campaign'] !== ''): ?>
            <dt>Campanha</dt><dd><?= hh(implode(' / ', array_filter([$m['utm_source'], $m['utm_medium'], $m['utm_campaign']]))) ?></dd>
            <?php endif; ?>
            <?php if ($m['utm_term'] !== ''): ?><dt>Termo</dt><dd><?= hh($m['utm_term']) ?></dd><?php endif; ?>
            <?php if ($m['utm_content'] !== ''): ?><dt>Conteúdo</dt><dd><?= hh($m['utm_content']) ?></dd><?php endif; ?>
            <?php foreach (['gclid' => 'gclid', 'gbraid' => 'gbraid', 'wbraid' => 'wbraid', 'fbclid' => 'fbclid', 'msclkid' => 'msclkid', 'ttclid' => 'ttclid'] as $k => $r): if ($m[$k] === '') continue; ?>
            <dt><?= $r ?></dt><dd style="font-family:ui-monospace,monospace;font-size:.78rem"><?= hh($m[$k]) ?></dd>
            <?php endforeach; ?>
            <?php if ($m['referrer'] !== ''): ?><dt>Veio de</dt><dd><?= hh($m['referrer']) ?></dd><?php endif; ?>
            <?php if ($m['landing'] !== ''): ?><dt>Entrou por</dt><dd><a href="https://mclair.com.br<?= hh($m['landing']) ?>" target="_blank" rel="noopener"><?= hh($m['landing']) ?></a></dd><?php endif; ?>
            <dt>Formulário em</dt><dd><?php if ($m['pagina'] !== ''): ?><a href="https://mclair.com.br<?= hh($m['pagina']) ?>" target="_blank" rel="noopener"><?= hh($m['pagina']) ?></a><?php else: ?><span class="muted">sem registro</span><?php endif; ?><?= $m['pagina_titulo'] !== '' ? '<div class="muted" style="font-size:.76rem">' . hh($m['pagina_titulo']) . '</div>' : '' ?></dd>
            <dt>Visitas</dt><dd><?= $m['visitas'] > 0 ? (int)$m['visitas'] . ($m['visitas'] == 1 ? ' visita' : ' visitas') . ', ' . (int)$m['paginas_vistas'] . ($m['paginas_vistas'] == 1 ? ' página' : ' páginas') : '<span class="muted">sem registro</span>' ?></dd>
            <?php if ($m['primeira_visita_em']): ?><dt>Primeira vez</dt><dd><?= hh(mcmDataLocal($m['primeira_visita_em'])) ?></dd><?php endif; ?>
            <?php if (is_array($toques) && !empty($toques['ultimo']) && !empty($toques['primeiro']) && $toques['ultimo'] !== $toques['primeiro']): $u = $toques['ultimo']; $ou = mcmOrigemDoToque($u); ?>
            <dt>Última vinda</dt><dd><?= hh($ou ? (MCM_ORIGENS[$ou[0]] ?? $ou[0]) . ($ou[1] !== '' ? ' · ' . $ou[1] : '') : 'Direto') ?><?= !empty($u['landing']) ? '<div class="muted" style="font-size:.76rem">' . hh($u['landing']) . '</div>' : '' ?></dd>
            <?php endif; ?>
          </dl>
          <?php if (is_array($jornada) && !empty($jornada['paginas'])): $pags = array_slice($jornada['paginas'], -15); ?>
          <p style="margin:16px 0 6px;font-size:.78rem;font-weight:700;color:var(--ink-3)">Páginas vistas<?= count($jornada['paginas']) > 15 ? ' (últimas 15 de ' . count($jornada['paginas']) . ')' : '' ?></p>
          <ul class="pags">
            <?php foreach ($pags as $p): ?><li><span title="<?= hh($p['t'] ?? '') ?>"><?= hh($p['p']) ?></span><span><?= hh(mcmDataLocal(gmdate('Y-m-d H:i:s', intdiv((int)$p['ts'], 1000)), 'd/m H:i')) ?></span></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>Dispositivo e local</h3>
          <dl class="kv" style="grid-template-columns:110px minmax(0,1fr)">
            <dt>Local</dt><dd><?= lugar($m) !== '' ? hh(lugar($m)) : '<span class="muted">não identificado</span>' ?></dd>
            <dt>Dispositivo</dt><dd><?= hh(implode(' · ', array_filter([$m['dispositivo'], $m['navegador'], $m['sistema']]))) ?: '<span class="muted">sem registro</span>' ?></dd>
            <?php if ($m['tela'] !== ''): ?><dt>Tela</dt><dd><?= hh($m['tela']) ?></dd><?php endif; ?>
            <?php if ($m['idioma'] !== ''): ?><dt>Idioma</dt><dd><?= hh($m['idioma']) ?></dd><?php endif; ?>
            <?php if ($m['fuso'] !== ''): ?><dt>Fuso</dt><dd><?= hh($m['fuso']) ?></dd><?php endif; ?>
            <dt>IP</dt><dd style="font-family:ui-monospace,monospace;font-size:.78rem"><?= $m['ip'] !== '' ? hh($m['ip']) : '<span class="muted">sem registro</span>' ?></dd>
            <?php if ($m['ga_client_id'] !== ''): ?><dt>GA4 client</dt><dd style="font-family:ui-monospace,monospace;font-size:.78rem"><?= hh($m['ga_client_id']) ?></dd><?php endif; ?>
          </dl>
          <?php if ($m['geo_fonte'] === 'dbip'): ?><p class="geo-atrib">Local aproximado pelo IP. <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a></p><?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>E-mail de aviso</h3>
          <dl class="kv" style="grid-template-columns:110px minmax(0,1fr)">
            <dt>Para</dt><dd><?= hh(MCM_DESTINO) ?></dd>
            <dt>Situação</dt><dd><?php if ($abandonada): ?><span class="muted">não se aplica (não enviou)</span><?php elseif ($m['email_status'] === 'enviado'): ?>Enviado em <?= hh(mcmDataLocal($m['email_enviado_em'])) ?><?php elseif ($m['email_status'] === 'falhou'): ?><span style="color:var(--red);font-weight:700">Falhou</span><?= $m['email_erro'] !== '' ? '<div class="muted" style="font-size:.76rem">' . hh($m['email_erro']) . '</div>' : '' ?><?php else: ?>Pendente<?php endif; ?></dd>
          </dl>
          <?php if (!$abandonada): ?>
          <form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="reenviar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="submit" class="btn secondary" style="padding:8px 14px;font-size:.78rem">Reenviar aviso por e-mail</button></form>
          <?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>Registro</h3>
          <dl class="kv" style="grid-template-columns:110px minmax(0,1fr)">
            <dt>Criado</dt><dd><?= hh(mcmDataLocal($m['criado_em'])) ?></dd>
            <dt>Enviado</dt><dd><?= $m['enviado_em'] ? hh(mcmDataLocal($m['enviado_em'])) : '<span class="muted">não enviou</span>' ?></dd>
            <dt>Atualizado</dt><dd><?= hh(mcmDataLocal($m['atualizado_em'])) ?></dd>
            <dt>Responsável</dt><dd><?= $m['responsavel_id'] ? hh(nomeUsuario($equipe, $m['responsavel_id'])) : '<span class="muted">ninguém ainda</span>' ?></dd>
          </dl>
          <div class="mg-rodape-acoes">
            <?php if ($m['excluido_em'] === null): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="excluir" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="button" class="link" data-confirm="Mover esta mensagem para a lixeira?" data-yes="sim, mover" onclick="askConfirm(this)">Mover para a lixeira</button></form>
            <?php elseif ($souAdmin): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="apagar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="button" class="link" data-confirm="Apagar de vez? Não dá para desfazer." data-yes="sim, apagar" onclick="askConfirm(this)">Apagar definitivamente</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.getElementById('btnCopiar').addEventListener('click', function () {
      var b = this, t = b.getAttribute('data-texto');
      function feito(ok) { var antes = b.textContent; b.textContent = ok ? 'Copiado' : 'Não deu para copiar'; setTimeout(function () { b.textContent = antes; }, 1800); }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(function () { feito(true); }, function () { feito(false); });
      else {
        var ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
        try { feito(document.execCommand('copy')); } catch (e) { feito(false); }
        ta.remove();
      }
    });
    </script>
    <?php
    adminLayoutBottom();
    exit;
}

// ------------------------------------------------------------------ lista
$perPage = 30;
$page = max(1, (int)($_GET['p'] ?? 1));
[$where, $params] = filtroSql($f);
$st = $pdo->prepare('SELECT COUNT(*) FROM cmstest_mensagens' . $where);
$st->execute($params);
$total = (int)$st->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $lastPage);
$st = $pdo->prepare('SELECT * FROM cmstest_mensagens' . $where . ' ORDER BY COALESCE(enviado_em, atualizado_em) DESC, id DESC LIMIT ? OFFSET ?');
$i = 1;
foreach ($params as $p) $st->bindValue($i++, $p);
$st->bindValue($i++, $perPage, PDO::PARAM_INT);
$st->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
$st->execute();
$lista = $st->fetchAll();

$novos = mcmNovos($pdo);
$semana = (int)$pdo->query('SELECT COUNT(*) FROM cmstest_mensagens WHERE enviado_em > (UTC_TIMESTAMP() - INTERVAL 7 DAY) AND excluido_em IS NULL')->fetchColumn();
$abandonadas7 = (int)$pdo->query('SELECT COUNT(*) FROM cmstest_mensagens WHERE enviado_em IS NULL AND criado_em > (UTC_TIMESTAMP() - INTERVAL 7 DAY) AND excluido_em IS NULL')->fetchColumn();
$falhas = (int)$pdo->query("SELECT COUNT(*) FROM cmstest_mensagens WHERE email_status = 'falhou' AND enviado_em IS NOT NULL AND excluido_em IS NULL")->fetchColumn();
$contagem = [
    'enviadas' => (int)$pdo->query('SELECT COUNT(*) FROM cmstest_mensagens WHERE enviado_em IS NOT NULL AND excluido_em IS NULL')->fetchColumn(),
    'abandonadas' => (int)$pdo->query('SELECT COUNT(*) FROM cmstest_mensagens WHERE enviado_em IS NULL AND excluido_em IS NULL')->fetchColumn(),
    'lixeira' => (int)$pdo->query('SELECT COUNT(*) FROM cmstest_mensagens WHERE excluido_em IS NOT NULL')->fetchColumn(),
];
$contagem['todas'] = $contagem['enviadas'] + $contagem['abandonadas'];
$formsUsados = $pdo->query("SELECT DISTINCT formulario FROM cmstest_mensagens WHERE formulario <> '' ORDER BY formulario")->fetchAll(PDO::FETCH_COLUMN);

function urlFiltro(array $f, array $mudar): string {
    $q = array_merge(['q' => $f['q'], 'status' => $f['status'], 'origem' => $f['origem'], 'formulario' => $f['formulario'], 'tipo' => $f['tipo'], 'de' => $f['de'], 'ate' => $f['ate'], 'lixeira' => $f['lixeira'] ? '1' : ''], $mudar);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null && $v !== false);
    if (($q['tipo'] ?? '') === 'enviadas') unset($q['tipo']);
    return 'mensagens' . ($q ? '?' . http_build_query($q) : '');
}
$filtrando = $f['q'] !== '' || $f['status'] !== '' || $f['origem'] !== '' || $f['formulario'] !== '' || $f['de'] !== '' || $f['ate'] !== '';
$okMsg = ['excluida' => 'Mensagem movida para a lixeira.', 'apagada' => 'Mensagem apagada.'][$_GET['ok'] ?? ''] ?? null;

adminLayoutTop('messages', 'Mensagens');
?>
<style>
  .st { display:inline-block; padding:4px 10px; border-radius:6px; font-size:.72rem; font-weight:700; white-space:nowrap; background:var(--soft); color:var(--ink-3); }
  .st-novo { background:rgba(200,16,46,.1); color:var(--red); }
  .st-em_atendimento { background:#E8F0FB; color:#1D4ED8; }
  .st-atendido { background:#E6F4EC; color:#1E7F4D; }
  .st-proposta_enviada { background:#EFE9FB; color:#6D28D9; }
  .st-fechado { background:#1E7F4D; color:#fff; }
  .st-perdido { background:#F1F2F4; color:#6B7280; }
  .st-abandonado { background:#FFF4E5; color:#9A5B00; }
  .abas { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:16px; }
  .abas a { padding:8px 14px; border-radius:8px; font-size:.82rem; font-weight:700; color:var(--ink-3); background:var(--paper); border:1px solid var(--line); }
  .abas a:hover { color:var(--ink); }
  .abas a.on { background:var(--ink); color:#fff; border-color:var(--ink); }
  .abas a span { opacity:.7; font-weight:600; margin-left:6px; }
  .filtros { display:grid; grid-template-columns:minmax(200px,2fr) repeat(5, minmax(120px,1fr)) auto; gap:8px; align-items:end; margin-bottom:18px; }
  .filtros label { margin:0 0 4px; }
  .filtros input, .filtros select { padding:9px 11px; font-size:.86rem; }
  .filtros .btn { padding:10px 14px; white-space:nowrap; }
  @media (max-width: 1100px) { .filtros { grid-template-columns:repeat(3, minmax(0,1fr)); } }
  @media (max-width: 600px) { .filtros { grid-template-columns:1fr 1fr; } }
  .quem { display:flex; align-items:center; gap:10px; min-width:0; }
  .quem .av { width:34px; height:34px; border-radius:50%; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.78rem; flex-shrink:0; }
  .quem .av.off { background:#9CA3AF; }
  .quem .nm { font-weight:700; display:block; }
  .quem .ct { font-size:.76rem; color:var(--ink-3); font-weight:600; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px; }
  td .sec { display:block; font-size:.74rem; color:var(--ink-3); font-weight:600; margin-top:2px; }
  table.dt td.int { max-width:280px; }
  table.dt td.int .pri { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  table.dt tr.linha { cursor:pointer; }
  table.dt tr.linha.nova .quem .nm { color:var(--red); }
  .vazio { padding:40px 20px; text-align:center; color:var(--ink-3); font-size:.9rem; }
  .vazio strong { display:block; color:var(--ink); font-size:1rem; margin-bottom:6px; }
</style>

<?php if ($okMsg): ?><div class="msg ok"><?= cmsCheckIcon() ?><span><?= hh($okMsg) ?></span></div><?php endif; ?>

<?php adminStatCards([
    ['num' => $novos, 'lbl' => 'Novas, sem atendimento', 'href' => $novos ? urlFiltro($f, ['status' => 'novo', 'tipo' => 'enviadas', 'lixeira' => '', 'q' => '', 'origem' => '', 'formulario' => '', 'de' => '', 'ate' => '']) : '', 'more' => 'Ver'],
    ['num' => $semana, 'lbl' => 'Recebidas nos últimos 7 dias'],
    ['num' => $abandonadas7, 'lbl' => 'Começaram e não enviaram (7 dias)', 'href' => $abandonadas7 ? urlFiltro($f, ['tipo' => 'abandonadas', 'status' => '', 'lixeira' => '']) : '', 'more' => 'Ver'],
    ['num' => $falhas, 'lbl' => 'Avisos por e-mail com falha'],
]); ?>

<div class="abas">
  <a href="<?= hh(urlFiltro($f, ['tipo' => 'enviadas', 'lixeira' => '', 'p' => ''])) ?>" class="<?= !$f['lixeira'] && $f['tipo'] === 'enviadas' ? 'on' : '' ?>">Enviadas<span><?= $contagem['enviadas'] ?></span></a>
  <a href="<?= hh(urlFiltro($f, ['tipo' => 'abandonadas', 'lixeira' => '', 'p' => ''])) ?>" class="<?= !$f['lixeira'] && $f['tipo'] === 'abandonadas' ? 'on' : '' ?>">Não enviadas<span><?= $contagem['abandonadas'] ?></span></a>
  <a href="<?= hh(urlFiltro($f, ['tipo' => 'todas', 'lixeira' => '', 'p' => ''])) ?>" class="<?= !$f['lixeira'] && $f['tipo'] === 'todas' ? 'on' : '' ?>">Todas<span><?= $contagem['todas'] ?></span></a>
  <a href="<?= hh(urlFiltro($f, ['lixeira' => '1', 'p' => ''])) ?>" class="<?= $f['lixeira'] ? 'on' : '' ?>">Lixeira<span><?= $contagem['lixeira'] ?></span></a>
</div>

<form method="get" class="filtros">
  <?php if ($f['tipo'] !== 'enviadas'): ?><input type="hidden" name="tipo" value="<?= hh($f['tipo']) ?>" /><?php endif; ?>
  <?php if ($f['lixeira']): ?><input type="hidden" name="lixeira" value="1" /><?php endif; ?>
  <div><label>Buscar</label><input type="text" name="q" value="<?= hh($f['q']) ?>" placeholder="nome, e-mail, telefone, empresa, texto" /></div>
  <div><label>Status</label><select name="status"><option value="">Todos</option><?php foreach (MCM_STATUS as $k => $v): ?><option value="<?= hh($k) ?>" <?= $k === $f['status'] ? 'selected' : '' ?>><?= hh($v) ?></option><?php endforeach; ?></select></div>
  <div><label>Origem</label><select name="origem"><option value="">Todas</option><?php foreach (MCM_ORIGENS as $k => $v): if ($k === 'manual') continue; ?><option value="<?= hh($k === '' ? 'sem' : $k) ?>" <?= ($k === '' ? 'sem' : $k) === $f['origem'] ? 'selected' : '' ?>><?= hh($v) ?></option><?php endforeach; ?></select></div>
  <div><label>Formulário</label><select name="formulario"><option value="">Todos</option><?php foreach ($formsUsados as $fm): ?><option value="<?= hh($fm) ?>" <?= $fm === $f['formulario'] ? 'selected' : '' ?>><?= hh(MCM_FORMULARIOS[$fm] ?? $fm) ?></option><?php endforeach; ?></select></div>
  <div><label>De</label><input type="date" name="de" value="<?= hh($f['de']) ?>" /></div>
  <div><label>Até</label><input type="date" name="ate" value="<?= hh($f['ate']) ?>" /></div>
  <div style="display:flex;gap:6px"><button type="submit" class="btn">Filtrar</button><?php if ($filtrando): ?><a class="btn secondary" href="<?= hh(urlFiltro($f, ['q' => '', 'status' => '', 'origem' => '', 'formulario' => '', 'de' => '', 'ate' => ''])) ?>">Limpar</a><?php endif; ?></div>
</form>

<div class="tablecard">
  <div class="tablecard-head">
    <div><strong><?= $f['lixeira'] ? 'Lixeira' : ($f['tipo'] === 'abandonadas' ? 'Começaram a preencher e não enviaram' : ($f['tipo'] === 'todas' ? 'Todas as mensagens' : 'Mensagens enviadas')) ?></strong><span class="count"><?= $total ?></span></div>
    <?php if ($total > 0): ?><a class="btn secondary" href="<?= hh(urlFiltro($f, ['export' => 'csv'])) ?>" style="padding:8px 14px;font-size:.78rem">Exportar CSV</a><?php endif; ?>
  </div>
  <?php if (!$lista): ?>
  <div class="vazio"><strong><?= $filtrando ? 'Nada com esses filtros' : ($f['tipo'] === 'abandonadas' ? 'Ninguém deixou formulário pela metade' : 'Nenhuma mensagem por aqui ainda') ?></strong><?= $filtrando ? 'Tente limpar a busca ou mudar o período.' : 'Cada formulário enviado no site aparece nesta lista e manda um e-mail para a Kelly.' ?></div>
  <?php else: ?>
  <table class="dt">
    <tr><th>Quem</th><th>Interesse</th><th>Origem</th><th class="s">Quando</th><th>Status</th><th>Ações</th></tr>
    <?php foreach ($lista as $m): $wa = mcmTelefoneWa((string)$m['telefone']); $abn = $m['enviado_em'] === null; $quando = $m['enviado_em'] ?? $m['atualizado_em']; ?>
    <tr class="linha <?= !$abn && $m['status'] === 'novo' ? 'nova' : '' ?>" data-href="mensagens?id=<?= (int)$m['id'] ?>">
      <td><div class="quem"><div class="av <?= $abn ? 'off' : '' ?>"><?= hh(iniciais((string)$m['nome'])) ?></div><div style="min-width:0"><a class="nm" href="mensagens?id=<?= (int)$m['id'] ?>"><?= hh($m['nome'] !== '' ? $m['nome'] : 'Sem nome') ?></a><span class="ct"><?= hh(implode(' · ', array_filter([$m['email'], $m['telefone'], $m['empresa']]))) ?: '<span class="muted">sem contato</span>' ?></span></div></div></td>
      <td class="int"><span class="pri"><?= rotuloInteresse($m) !== '' ? hh(rotuloInteresse($m)) : '<span style="color:#9CA3AF">sem assunto</span>' ?></span><span class="sec"><?= hh(MCM_FORMULARIOS[$m['formulario']] ?? ($m['formulario'] !== '' ? $m['formulario'] : 'formulário do site')) ?><?= $m['pagina'] !== '' ? ' · ' . hh($m['pagina']) : '' ?></span></td>
      <td><?= hh(MCM_ORIGENS[$m['origem']] ?? MCM_ORIGENS['']) ?><?= $m['origem_detalhe'] !== '' ? '<span class="sec">' . hh(mb_substr($m['origem_detalhe'], 0, 50)) . '</span>' : '' ?><?= lugar($m) !== '' ? '<span class="sec">' . hh(lugar($m)) . '</span>' : '' ?></td>
      <td title="<?= hh(mcmDataLocal($quando)) ?>"><?= hh(mcmTempoAtras($quando)) ?><span class="sec"><?= hh(mcmDataLocal($quando, 'd/m H:i')) ?></span></td>
      <td><?= badgeStatus($m) ?><?= $m['responsavel_id'] ? '<span class="sec">' . hh(nomeUsuario($equipe, $m['responsavel_id'])) . '</span>' : '' ?><?= !$abn && $m['email_status'] === 'falhou' ? '<span class="sec" style="color:var(--red)">e-mail falhou</span>' : '' ?></td>
      <td class="dt-actions"><a href="mensagens?id=<?= (int)$m['id'] ?>">abrir</a><?php if ($wa !== ''): ?><a href="https://wa.me/<?= hh($wa) ?>" target="_blank" rel="noopener" style="color:#1E7F4D">WhatsApp</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php if ($lastPage > 1): ?>
<div class="pagination">
  <?php for ($n = 1; $n <= $lastPage; $n++): ?>
    <a href="<?= hh(urlFiltro($f, ['p' => $n])) ?>" class="<?= $n === $page ? 'active' : '' ?>"><?= $n ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<script>
// Linha inteira abre a ficha; links e botões dentro dela continuam normais.
document.querySelectorAll('tr.linha').forEach(function (tr) {
  tr.addEventListener('click', function (e) {
    if (e.target.closest('a, button, form')) return;
    location.href = tr.getAttribute('data-href');
  });
});
</script>

<?php adminLayoutBottom(); ?>
