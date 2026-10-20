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

const MCM_ROTULO_EVENTO = [
    'rascunho' => 'Começou a preencher', 'enviado' => 'Formulário enviado', 'whatsapp' => 'Levada ao WhatsApp', 'wa_clique' => 'Clique em WhatsApp',
    'email_ok' => 'E-mail de aviso', 'email_falhou' => 'E-mail de aviso falhou', 'reenviado' => 'E-mail reenviado', 'ga4' => 'GA4',
    'status' => 'Status', 'responsavel' => 'Responsável', 'nota' => 'Anotação', 'editado' => 'Dados editados', 'criado' => 'Cadastrada à mão',
    'importado' => 'Importado', 'excluido' => 'Lixeira', 'restaurado' => 'Restaurada', 'anonimizado' => 'Dados apagados',
];

// Um item da linha do tempo. Usado na ficha e na resposta JSON das ações
// salvas sem recarregar (o JS acrescenta o HTML no fim da lista).
function tlItem(array $e, array $equipe): string {
    $h = '<li class="' . hh($e['tipo']) . '" data-ev="' . (int)$e['id'] . '"><span class="quando">' . hh(mcmDataLocal($e['criado_em'], 'd/m/Y')) . '<br>' . hh(mcmDataLocal($e['criado_em'], 'H:i')) . '</span>'
        . '<span><span class="tipo">' . hh(MCM_ROTULO_EVENTO[$e['tipo']] ?? $e['tipo']) . '</span>';
    if ((string)$e['texto'] !== '') $h .= '<div class="texto">' . hh($e['texto']) . '</div>';
    if ($e['usuario_id']) $h .= '<div class="quem">' . hh(nomeUsuario($equipe, $e['usuario_id'])) . '</div>';
    return $h . '</span></li>';
}
function textoResponsavel(array $m, array $equipe, string $desde): string {
    if (!$m['responsavel_id']) return '';
    return 'Atendida por ' . hh(nomeUsuario($equipe, $m['responsavel_id'])) . ($desde !== '' ? ' desde ' . hh(mcmDataLocal($desde)) : '') . '.';
}
// Atalho de status no topo da ficha ("Marcar em atendimento" / "Marcar como atendido").
function botaoStatusRapido(array $m, int $id): string {
    if ($m['enviado_em'] === null) return '';
    $alvo = $m['status'] === 'novo' ? ['em_atendimento', 'Marcar em atendimento'] : (in_array($m['status'], ['em_atendimento', 'proposta_enviada'], true) ? ['atendido', 'Marcar como atendido'] : null);
    if (!$alvo) return '';
    return '<form method="post" style="display:inline" data-ajax><input type="hidden" name="csrf" value="' . hh(cmsCsrfToken()) . '" /><input type="hidden" name="action" value="status" /><input type="hidden" name="id" value="' . $id . '" /><input type="hidden" name="status" value="' . $alvo[0] . '" /><button type="submit" class="btn secondary">' . $alvo[1] . '</button></form>';
}
function respondeJson(int $code, array $dados): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
// Erro de uma ação: JSON para o fetch, texto simples para o POST comum.
function falha(int $code, string $msg): void {
    global $ajax;
    if ($ajax) respondeJson($code, ['ok' => false, 'erro' => $msg]);
    http_response_code($code);
    die($msg);
}
// Depois de salvar via fetch: devolve o que mudou na ficha para o JS
// atualizar sem recarregar (status, responsável, atalho do topo e os
// eventos novos da linha do tempo a partir do último que a página já tinha).
function respostaFicha(PDO $pdo, int $id, array $equipe, string $msg, int $desde): void {
    $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    $ev = $pdo->prepare('SELECT * FROM cmstest_mensagens_eventos WHERE mensagem_id = ? AND id > ? ORDER BY criado_em, id');
    $ev->execute([$id, $desde]);
    $html = '';
    foreach ($ev as $e) $html .= tlItem($e, $equipe);
    $rd = $pdo->prepare("SELECT criado_em FROM cmstest_mensagens_eventos WHERE mensagem_id = ? AND tipo = 'responsavel' ORDER BY id DESC LIMIT 1");
    $rd->execute([$id]);
    $respDesde = (string)($rd->fetchColumn() ?: '');
    respondeJson(200, [
        'ok' => true, 'msg' => $msg,
        'status' => ['chave' => $m['status'], 'rotulo' => MCM_STATUS[$m['status']] ?? $m['status'], 'badge' => badgeStatus($m)],
        'responsavel' => ['id' => (int)$m['responsavel_id'], 'texto' => textoResponsavel($m, $equipe, $respDesde)],
        'acoes' => botaoStatusRapido($m, $id),
        'eventos' => $html,
    ]);
}

// ---------------------------------------------------------------- ações
$ajax = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ajax=1 vem do JS da ficha (status, responsável e anotação salvos sem
    // recarregar). Sem JS o mesmo formulário faz POST comum e redireciona.
    $ajax = ($_POST['ajax'] ?? '') === '1';
    if (!cmsCsrfValidate($_POST['csrf'] ?? '')) {
        falha(403, 'Sessão expirada ou requisição inválida. Recarregue a página e tente de novo.');
    }
    $id = (int)($_POST['id'] ?? 0);
    $acao = (string)($_POST['action'] ?? '');
    $desde = (int)($_POST['ultimo_evento'] ?? 0);
    if ($acao === 'criar') {
        $nome = mc_linha($_POST['nome'] ?? '', 120);
        if (mb_strlen($nome) < 2) { http_response_code(422); die('Informe o nome.'); }
        $emailIn = mc_linha($_POST['email'] ?? '', 190);
        $canalManual = (string)($_POST['canal_manual'] ?? '');
        if (!isset(MCM_CANAIS_MANUAIS[$canalManual])) $canalManual = 'outro';
        $st = $pdo->prepare("INSERT INTO cmstest_mensagens (criado_em, atualizado_em, enviado_em, formulario, nome, email, email_valido, telefone, empresa, assunto, mensagem, origem, origem_detalhe, canal, email_status, status, responsavel_id)
            VALUES (UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'manual', ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?, 'nao_aplica', 'em_atendimento', ?)");
        $st->execute([$nome, $emailIn, $emailIn !== '' && filter_var($emailIn, FILTER_VALIDATE_EMAIL) ? 1 : 0, mc_linha($_POST['telefone'] ?? '', 40), mc_linha($_POST['empresa'] ?? '', 150),
            mc_linha($_POST['assunto'] ?? '', 150), mc_texto($_POST['mensagem'] ?? '', 4000), MCM_CANAIS_MANUAIS[$canalManual], $canalManual, $meuId ?: null]);
        $novoId = (int)$pdo->lastInsertId();
        mcmEvento($pdo, $novoId, 'criado', 'Cadastrada à mão; chegou por ' . mb_strtolower(MCM_CANAIS_MANUAIS[$canalManual]), $meuId);
        header('Location: mensagens?id=' . $novoId . '&ok=criada');
        exit;
    }
    $st = $pdo->prepare('SELECT * FROM cmstest_mensagens WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) falha(404, 'Mensagem não encontrada.');
    $volta = 'mensagens?id=' . $id;
    if ($acao === 'reenviar' && ($m['formulario'] === 'manual' || $m['anonimizado_em'] !== null)) { http_response_code(400); die('Cadastro à mão ou dados apagados: não há aviso a reenviar.'); }
    if (in_array($acao, ['nota', 'editar'], true) && $m['anonimizado_em'] !== null) falha(400, 'Dados apagados pela retenção; a ficha não recebe mais texto.');

    if ($acao === 'status') {
        $novo = (string)($_POST['status'] ?? '');
        if (!isset(MCM_STATUS[$novo])) falha(422, 'Status desconhecido.');
        if ($novo !== $m['status']) {
            $pdo->prepare('UPDATE cmstest_mensagens SET status = ?, atualizado_em = UTC_TIMESTAMP() WHERE id = ?')->execute([$novo, $id]);
            mcmEvento($pdo, $id, 'status', 'Status mudou de "' . (MCM_STATUS[$m['status']] ?? $m['status']) . '" para "' . MCM_STATUS[$novo] . '"', $meuId);
        }
        if ($ajax) respostaFicha($pdo, $id, $equipe, 'Salvo', $desde);
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
        if ($ajax) respostaFicha($pdo, $id, $equipe, 'Salvo', $desde);
        header('Location: ' . $volta . '&ok=responsavel');
        exit;
    }
    if ($acao === 'editar') {
        if ($m['anonimizado_em'] !== null) { http_response_code(400); die('Dados apagados pela retenção; não há o que editar.'); }
        $novo = [
            'nome' => mc_linha($_POST['nome'] ?? '', 120), 'email' => mc_linha($_POST['email'] ?? '', 190), 'telefone' => mc_linha($_POST['telefone'] ?? '', 40),
            'empresa' => mc_linha($_POST['empresa'] ?? '', 150), 'assunto' => mc_linha($_POST['assunto'] ?? '', 150),
        ];
        $mudou = [];
        foreach ($novo as $k => $v) if ($v !== (string)$m[$k]) $mudou[] = ['nome' => 'nome', 'email' => 'e-mail', 'telefone' => 'telefone', 'empresa' => 'empresa', 'assunto' => 'assunto'][$k] . ': "' . $m[$k] . '" virou "' . $v . '"';
        if ($mudou) {
            $pdo->prepare('UPDATE cmstest_mensagens SET nome = ?, email = ?, email_valido = ?, telefone = ?, empresa = ?, assunto = ?, atualizado_em = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$novo['nome'], $novo['email'], $novo['email'] !== '' && filter_var($novo['email'], FILTER_VALIDATE_EMAIL) ? 1 : 0, $novo['telefone'], $novo['empresa'], $novo['assunto'], $id]);
            mcmEvento($pdo, $id, 'editado', implode("\n", $mudou), $meuId);
        }
        header('Location: ' . $volta . '&ok=editada');
        exit;
    }
    if ($acao === 'nota') {
        $texto = mc_texto($_POST['texto'] ?? '', 4000);
        if ($texto === '') { if ($ajax) falha(422, 'Escreva a anotação antes de guardar.'); header('Location: ' . $volta . '#linha-do-tempo'); exit; }
        mcmEvento($pdo, $id, 'nota', $texto, $meuId);
        $pdo->prepare('UPDATE cmstest_mensagens SET atualizado_em = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
        if ($ajax) respostaFicha($pdo, $id, $equipe, 'Anotação guardada', $desde);
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
        [$ok, $erro, $meta] = mcmEmailEnviar($m);
        mcmEmailRegistrar($pdo, $id, $ok, $erro, $meta, 'Aviso reenviado', $meuId);
        header('Location: ' . $volta . '&ok=' . ($ok ? 'reenviado' : 'reenvio_falhou'));
        exit;
    }
    falha(400, 'Ação desconhecida.');
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
    $cab = ['id', 'criado_em', 'enviado_em', 'status', 'responsavel', 'formulario', 'nome', 'email', 'telefone', 'empresa', 'assunto', 'servicos', 'orcamento', 'objetivo', 'mensagem', 'pagina', 'pagina_entrada', 'referrer', 'origem', 'origem_detalhe', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ga_client_id', 'cidade', 'estado', 'pais', 'dispositivo', 'navegador', 'sistema', 'visitas', 'paginas_vistas', 'primeira_visita_em', 'email_aviso', 'email_para', 'email_message_id', 'canal', 'latitude', 'longitude', 'dados_apagados_em'];
    fputcsv($out, $cab, ';', '"', '');
    // Célula começando com = + - @ vira fórmula no Excel: neutraliza.
    $seg = static fn($v): string => (!is_numeric($v) && preg_match('/^[=+\-@\t\r]/', (string)$v)) ? "'" . $v : (string)$v;
    foreach ($linhas as $m) {
        fputcsv($out, array_map($seg, [
            $m['id'], mcmDataLocal($m['criado_em']), mcmDataLocal($m['enviado_em']), $m['enviado_em'] === null ? 'nao_enviado' : $m['status'], nomeUsuario($equipe, $m['responsavel_id']),
            MCM_FORMULARIOS[$m['formulario']] ?? $m['formulario'], $m['nome'], $m['email'], $m['telefone'], $m['empresa'], $m['assunto'], $m['servicos'], $m['orcamento'], $m['objetivo'], $m['mensagem'],
            $m['pagina'], $m['landing'], $m['referrer'], MCM_ORIGENS[$m['origem']] ?? '', $m['origem_detalhe'],
            $m['utm_source'], $m['utm_medium'], $m['utm_campaign'], $m['utm_term'], $m['utm_content'], $m['gclid'], $m['gbraid'], $m['wbraid'], $m['fbclid'], $m['ga_client_id'],
            $m['geo_cidade'], $m['geo_estado'], $m['geo_pais'], $m['dispositivo'], $m['navegador'], $m['sistema'], $m['visitas'], $m['paginas_vistas'], mcmDataLocal($m['primeira_visita_em']), $m['email_status'],
            $m['email_para'], $m['email_message_id'], $m['canal'], $m['geo_lat'], $m['geo_lng'], mcmDataLocal($m['anonimizado_em']),
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
    if (!is_array($toques)) $toques = null;
    $jornada = json_decode((string)$m['jornada'], true);
    if (!is_array($jornada) || empty($jornada['paginas'])) $jornada = null;
    $campos = json_decode((string)$m['campos'], true);
    if (!is_array($campos)) $campos = [];
    $waDigitos = mcmTelefoneWa((string)$m['telefone']);
    $abandonada = $m['enviado_em'] === null;
    $anonima = $m['anonimizado_em'] !== null;
    $manual = $m['formulario'] === 'manual';
    $local = mcmLocal($m);
    $horaLocal = mcmHoraLocal((string)$m['fuso']);
    $itens = mcmJornadaItens($jornada, (string)$m['fuso']);
    $resumoJ = mcmJornadaResumo($jornada);
    $primeiroToque = $toques['primeiro'] ?? null;
    $ultimoToque = $toques['ultimo'] ?? null;
    $logEmail = $m['email_message_id'] !== '' ? mcmEmailNoLog((string)$m['email_message_id']) : '';
    $foiWhats = false;
    $responsavelDesde = '';
    foreach ($eventos as $e) {
        if ($e['tipo'] === 'whatsapp') $foiWhats = true;
        if ($e['tipo'] === 'responsavel') $responsavelDesde = (string)$e['criado_em'];
    }
    $titulo = $m['nome'] !== '' ? $m['nome'] : ($anonima ? 'Dados apagados' : 'Mensagem #' . $id);
    $okMsg = [
        'status' => 'Status atualizado.', 'responsavel' => 'Responsável atualizado.', 'nota' => 'Anotação guardada.', 'editada' => 'Dados atualizados.', 'criada' => 'Mensagem cadastrada.',
        'restaurada' => 'Mensagem restaurada.', 'reenviado' => 'Aviso reenviado por e-mail (aceito pelo servidor).', 'reenvio_falhou' => 'O reenvio falhou. Veja o motivo em "E-mail de aviso".',
    ][$_GET['ok'] ?? ''] ?? null;

    // Texto para o botão "Copiar dados".
    $copiar = [];
    foreach ([['Nome', $m['nome']], ['E-mail', $m['email']], ['WhatsApp', $m['telefone']], ['Empresa', $m['empresa']], ['Assunto', $m['assunto']], ['Serviços', $m['servicos']], ['Verba', $m['orcamento']], ['O que quer divulgar', $m['objetivo']], ['Mensagem', $m['mensagem']], ['Formulário', MCM_FORMULARIOS[$m['formulario']] ?? $m['formulario']], ['Página', $m['pagina'] !== '' ? 'https://mclair.com.br' . $m['pagina'] : ''], ['Recebido em', mcmDataLocal($m['enviado_em'] ?? $m['criado_em'])], ['Origem', origemTexto($m)], ['Local', $local['rotulo'] ?? '']] as [$r, $v]) {
        if ((string)$v !== '') $copiar[] = $r . ': ' . $v;
    }
    $temGeo = $local !== null && $local['lat'] !== null && $local['lng'] !== null;

    adminLayoutTop('messages', $titulo, ['label' => 'Mensagens', 'href' => '/acesso/mensagens']);
    ?>
    <?php if ($temGeo): ?><link rel="stylesheet" href="/acesso/vendor/leaflet/leaflet.css" /><?php endif; ?>
    <style>
      .mg-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; flex-wrap:wrap; margin-bottom:16px; }
      .mg-who { display:flex; align-items:center; gap:14px; min-width:0; }
      .mg-avatar { width:52px; height:52px; border-radius:50%; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0; }
      .mg-avatar.off { background:#9CA3AF; }
      .mg-who h2 { margin:0 0 4px; font-size:1.25rem; letter-spacing:-.01em; }
      .mg-who .ask { font-size:.9rem; color:var(--ink); margin:0 0 6px; }
      .mg-who .sub { font-size:.8rem; color:var(--ink-3); font-weight:600; display:flex; flex-wrap:wrap; gap:6px 10px; align-items:center; }
      .mg-place { text-align:right; font-size:.84rem; color:var(--ink-3); min-width:0; }
      .mg-place strong { display:block; color:var(--ink); font-size:.92rem; }
      .mg-place .clock { font-size:1.3rem; font-weight:800; color:var(--ink); letter-spacing:-.01em; }
      .mg-acoes { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:18px; }
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
      .st-ok { background:#E6F4EC; color:#1E7F4D; }
      .st-falha { background:rgba(200,16,46,.1); color:var(--red); }
      .mg-grid { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:20px; align-items:start; }
      @media (max-width: 980px) { .mg-grid { grid-template-columns:1fr; } }
      .mg-card { background:var(--paper); border:1px solid var(--line); border-radius:12px; padding:20px 22px; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:18px; }
      .mg-card h3 { margin:0 0 14px; font-size:.95rem; }
      .mg-card h3 small { font-weight:600; color:var(--ink-3); font-size:.76rem; margin-left:8px; }
      .mg-card h4 { margin:18px 0 8px; font-size:.78rem; font-weight:700; color:var(--ink-3); text-transform:uppercase; letter-spacing:.04em; }
      dl.kv { margin:0; display:grid; grid-template-columns:140px minmax(0,1fr); gap:9px 14px; font-size:.86rem; }
      dl.kv.estreita { grid-template-columns:110px minmax(0,1fr); }
      dl.kv dt { color:var(--ink-3); font-weight:600; }
      dl.kv dd { margin:0; word-break:break-word; }
      dl.kv dd.pre { white-space:pre-wrap; line-height:1.55; }
      dl.kv dd.mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.78rem; }
      .muted { color:#9CA3AF; }
      @media (max-width: 480px) { dl.kv, dl.kv.estreita { grid-template-columns:1fr; gap:2px 0; } dl.kv dd { margin-bottom:8px; } }
      .mg-bloco { background:var(--soft); border-radius:10px; padding:14px 16px; white-space:pre-wrap; line-height:1.6; font-size:.9rem; margin:6px 0 14px; }
      .mg-aviso { border:1px solid var(--line); background:var(--soft); border-radius:10px; padding:12px 16px; font-size:.86rem; margin-bottom:14px; line-height:1.5; }
      .mg-aviso.atencao { background:#FFF4E5; border-color:#F3D9A4; color:#7A4A00; }
      .mg-aviso.falha { background:rgba(200,16,46,.07); border-color:rgba(200,16,46,.25); color:#8A0C20; }
      .mg-form-linha { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
      .mg-form-linha > div { flex:1; min-width:160px; }
      .mg-form-linha label { margin-top:0; }
      .mg-form-linha .btn { padding:10px 14px; }
      .mg-edit { display:grid; grid-template-columns:1fr 1fr; gap:0 14px; }
      @media (max-width: 600px) { .mg-edit { grid-template-columns:1fr; } }
      .mg-edit label { margin-top:10px; }
      .tl { list-style:none; margin:0; padding:0; }
      .tl li { display:grid; grid-template-columns:110px minmax(0,1fr); gap:0 14px; padding:10px 0; border-top:1px solid var(--line); font-size:.85rem; }
      .tl li:first-child { border-top:none; padding-top:0; }
      .tl .quando { color:var(--ink-3); font-weight:600; font-size:.76rem; line-height:1.5; }
      .tl .tipo { font-weight:700; }
      .tl .texto { white-space:pre-wrap; line-height:1.5; margin-top:2px; }
      .tl .quem { color:var(--ink-3); font-size:.76rem; font-weight:600; margin-top:2px; }
      .tl li.nota .texto { background:var(--soft); border-radius:8px; padding:10px 12px; margin-top:6px; }
      .tl li.whatsapp .tipo, .tl li.wa_clique .tipo { color:#1E7F4D; }
      .tl li.email_falhou .tipo { color:var(--red); }
      @media (max-width: 480px) { .tl li { grid-template-columns:1fr; } }
      .rec { list-style:none; margin:0; padding:0; font-size:.83rem; }
      .rec li { display:grid; grid-template-columns:72px minmax(0,1fr); gap:0 10px; padding:7px 0; border-top:1px solid var(--line); }
      .rec li:first-child { border-top:none; }
      .rec li.corte { display:block; padding:6px 0; color:var(--ink-3); font-weight:600; font-size:.76rem; text-align:center; background:var(--soft); border-radius:6px; margin:6px 0; border-top:none; }
      .rec .h { color:var(--ink-3); font-weight:600; font-size:.76rem; line-height:1.5; }
      .rec .t a { font-weight:700; }
      .rec .t a:hover { color:var(--red); }
      .rec .d { display:block; color:var(--ink-3); font-size:.74rem; font-weight:600; }
      .rec li.ultima .t a { color:var(--red); }
      .mg-mapa { height:220px; border-radius:10px; border:1px solid var(--line); background:var(--soft); overflow:hidden; margin-bottom:12px; z-index:0; }
      .mg-pino { width:16px; height:16px; border-radius:50%; background:var(--red); border:3px solid #fff; box-shadow:0 0 0 2px rgba(200,16,46,.45); }
      .mg-resumo { font-size:.84rem; margin:0 0 10px; }
      textarea.nota { min-height:90px; }
      /* Atendimento: status em botões segmentados (cada um é um submit: sem
         JS o clique faz POST comum; com JS salva por fetch), responsável
         salva ao trocar, anotação com um botão só. */
      .mg-status { display:flex; flex-wrap:wrap; gap:6px; }
      .mg-status button { font-family:inherit; cursor:pointer; padding:8px 13px; border-radius:8px; font-size:.8rem; font-weight:700; color:var(--ink-3); background:var(--paper); border:1px solid var(--line); }
      .mg-status button:hover { color:var(--ink); border-color:#C7CBD1; }
      .mg-status button.on { background:var(--ink); color:#fff; border-color:var(--ink); }
      .mg-status button:focus-visible { outline:2px solid rgba(200,16,46,.25); outline-offset:1px; }
      .mg-salvo { font-size:.78rem; font-weight:700; color:#1E7F4D; opacity:0; transition:opacity .2s; white-space:nowrap; }
      .mg-salvo.show { opacity:1; }
      .mg-salvo.erro { color:var(--red); }
      .mg-linha-salvo { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
      form.enviando .mg-status button, form.enviando select, form.enviando .btn, form.enviando textarea { opacity:.6; pointer-events:none; }
      .mg-resp { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
      .mg-resp select { width:auto; min-width:220px; flex:1; }
      .mg-resp .btn { padding:10px 14px; }
      .js .so-sem-js { display:none; }
      .mg-rodape-acoes { display:flex; gap:14px; flex-wrap:wrap; align-items:center; margin-top:6px; font-size:.8rem; }
      .mg-rodape-acoes button.link { background:none; border:none; padding:0; color:#9a1f1f; text-decoration:underline; font-weight:700; cursor:pointer; font-family:inherit; font-size:.8rem; }
      .geo-atrib { font-size:.7rem; color:#9CA3AF; margin:10px 0 0; }
      .geo-atrib a { color:inherit; text-decoration:underline; }
      details.mg-det > summary { cursor:pointer; font-weight:700; font-size:.85rem; color:var(--ink); list-style:none; display:flex; align-items:center; gap:8px; }
      details.mg-det > summary::-webkit-details-marker { display:none; }
      details.mg-det > summary::before { content:''; width:7px; height:7px; border-right:2px solid var(--ink-3); border-bottom:2px solid var(--ink-3); transform:rotate(-45deg); transition:transform .15s; }
      details.mg-det[open] > summary::before { transform:rotate(45deg); }
      details.mg-det > summary + * { margin-top:14px; }
      pre.mg-email { background:var(--soft); border-radius:8px; padding:12px 14px; font-size:.76rem; line-height:1.5; white-space:pre-wrap; word-break:break-word; margin:10px 0 0; max-height:360px; overflow:auto; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
    </style>
    <script>document.documentElement.classList.add('js');</script>

    <?php if ($okMsg): ?><div class="msg ok"><?= cmsCheckIcon() ?><span><?= hh($okMsg) ?></span></div><?php endif; ?>

    <div class="mg-head">
      <div class="mg-who">
        <div class="mg-avatar <?= $abandonada ? 'off' : '' ?>"><?= hh(iniciais((string)$m['nome'])) ?></div>
        <div>
          <h2><?= hh($m['nome'] !== '' ? $m['nome'] : ($anonima ? 'Dados apagados' : 'Sem nome')) ?> <span class="muted" style="font-weight:600;font-size:.9rem">#<?= $id ?></span></h2>
          <?php $interesse = rotuloInteresse($m); ?>
          <p class="ask"><?php if ($interesse !== ''): ?>Quer falar sobre <strong><?= hh($interesse) ?></strong><?php else: ?><?= hh(MCM_FORMULARIOS[$m['formulario']] ?? 'Formulário do site') ?><?php endif; ?><?= $m['orcamento'] !== '' ? ' · verba ' . hh($m['orcamento']) : '' ?><?= $m['empresa'] !== '' ? ' · ' . hh($m['empresa']) : '' ?></p>
          <div class="sub">
            <span id="mgBadge"><?= badgeStatus($m) ?></span>
            <?php if (!$abandonada): ?><?php if ($m['email_status'] === 'enviado'): ?><span class="st st-ok">aviso por e-mail aceito</span><?php elseif ($m['email_status'] === 'falhou'): ?><span class="st st-falha">aviso por e-mail falhou</span><?php elseif ($m['email_status'] === 'nao_aplica'): ?><?php else: ?><span class="st">aviso pendente</span><?php endif; ?><?php endif; ?>
            <?php if ($foiWhats): ?><span class="st st-ok">foi ao WhatsApp</span><?php endif; ?>
            <span><?= hh($manual ? 'Cadastrada à mão' : (MCM_FORMULARIOS[$m['formulario']] ?? ($m['formulario'] !== '' ? $m['formulario'] : 'formulário do site'))) ?> · <span title="<?= hh(mcmDataLocal($m['enviado_em'] ?? $m['criado_em'])) ?> (Brasília)"><?= hh(mcmTempoAtras($m['enviado_em'] ?? $m['criado_em'])) ?></span></span>
          </div>
        </div>
      </div>
      <?php if ($local || $horaLocal): ?>
      <div class="mg-place">
        <?php if ($local): ?><strong><?= hh($local['rotulo']) ?></strong><span><?= hh($local['fonte_rotulo']) ?></span><?php endif; ?>
        <?php if ($horaLocal): ?><div style="margin-top:6px">Agora são <span class="clock" data-hora-local data-fuso="<?= hh($m['fuso']) ?>"><?= hh($horaLocal['hora']) ?></span> para a pessoa<?= $horaLocal['diferenca'] !== 'mesma hora de Brasília' ? '<br>' . hh($horaLocal['diferenca']) : '' ?><?= ($horaLocal['h'] < 8 || $horaLocal['h'] >= 21) ? '<br>melhor esperar para ligar' : '' ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="mg-acoes">
      <?php if ($waDigitos !== '' && !$anonima): ?><a class="btn wa" href="https://wa.me/<?= hh($waDigitos) ?>?text=<?= rawurlencode('Olá' . ($m['nome'] !== '' ? ' ' . explode(' ', trim((string)$m['nome']))[0] : '') . ', aqui é da Mclair. Recebemos a sua mensagem pelo site.') ?>" target="_blank" rel="noopener">Abrir WhatsApp</a><?php endif; ?>
      <?php if ($m['email_valido'] && !$anonima): ?><a class="btn secondary" href="mailto:<?= hh(rawurlencode($m['email'])) ?>?subject=<?= rawurlencode('Re: ' . ($m['assunto'] !== '' ? $m['assunto'] : 'sua mensagem pelo site da Mclair')) ?>">Responder por e-mail</a><?php endif; ?>
      <button type="button" class="btn secondary" id="btnCopiar" data-texto="<?= hh(implode("\n", $copiar)) ?>">Copiar dados</button>
      <span id="mgAcaoStatus" style="display:contents"><?= botaoStatusRapido($m, $id) ?></span>
    </div>

    <?php if ($m['excluido_em'] !== null): ?>
    <div class="mg-aviso atencao">Esta mensagem está na lixeira desde <?= hh(mcmDataLocal($m['excluido_em'])) ?><?= $m['excluido_por'] ? ' (' . hh(nomeUsuario($equipe, $m['excluido_por'])) . ')' : '' ?>. Ela não aparece na lista nem conta como nova.
      <form method="post" style="display:inline;margin-left:8px"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="restaurar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.78rem">Restaurar</button></form>
    </div>
    <?php endif; ?>
    <?php if ($anonima): ?>
    <div class="mg-aviso">Dados pessoais apagados em <?= hh(mcmDataLocal($m['anonimizado_em'], 'd/m/Y')) ?> (retenção de 24 meses sem movimento para mensagens não fechadas). Ficaram formulário, data, assunto, origem, campanha, estado, país e o histórico de status.</div>
    <?php endif; ?>
    <?php if ($abandonada): ?>
    <div class="mg-aviso atencao">A pessoa preencheu nome e contato mas <strong>não chegou a enviar</strong> o formulário (dados capturados em <?= hh(mcmDataLocal($m['criado_em'])) ?>, última alteração <?= hh(mcmDataLocal($m['atualizado_em'])) ?>). Nenhum e-mail foi mandado e ela não foi ao WhatsApp por aqui. Vale um contato leve, sem presumir que ela pediu retorno.</div>
    <?php elseif ($m['email_status'] === 'falhou'): ?>
    <div class="mg-aviso falha">O e-mail de aviso para <?= hh($m['email_para'] ?: mcmDestino()) ?> <strong>não saiu</strong> (<?= hh($m['email_erro'] ?: 'sem detalhe') ?>). A mensagem está completa aqui; responda pelos botões acima ou reenvie o aviso em "E-mail de aviso".</div>
    <?php endif; ?>
    <?php if (!$abandonada && !$anonima && !$manual && $m['email'] === ''): ?>
    <div class="mg-aviso atencao">Esta pessoa <strong>não deixou e-mail</strong>. <?= $waDigitos !== '' ? 'Responda pelo WhatsApp.' : ($m['telefone'] !== '' ? 'O telefone ' . hh($m['telefone']) . ' não tem DDD completo: confira antes de ligar.' : 'Não deixou nenhum contato.') ?></div>
    <?php endif; ?>
    <?php if ($foiWhats && !$abandonada): ?>
    <div class="mg-aviso"><strong>Também foi ao WhatsApp.</strong> No mesmo clique de enviar, a pessoa foi levada ao WhatsApp da Mclair com a mensagem pronta. Não dá para saber se ela chegou a enviar lá; se não apareceu conversa no WhatsApp, responda pelos botões acima.</div>
    <?php endif; ?>
    <?php if ($m['observacao'] !== ''): ?><div class="mg-aviso"><?= hh($m['observacao']) ?></div><?php endif; ?>

    <div class="mg-grid">
      <div>
        <div class="mg-card">
          <h3>O que a pessoa escreveu <small><?= hh($manual ? 'cadastro à mão' : (MCM_FORMULARIOS[$m['formulario']] ?? ($m['formulario'] !== '' ? $m['formulario'] : 'formulário do site'))) ?></small></h3>
          <dl class="kv">
            <?php if ($m['assunto'] !== ''): ?><dt>Assunto</dt><dd><?= hh($m['assunto']) ?></dd><?php endif; ?>
            <?php if ($m['servicos'] !== ''): ?><dt>Serviços de interesse</dt><dd><?= hh($m['servicos']) ?></dd><?php endif; ?>
            <?php if ($m['orcamento'] !== ''): ?><dt>Verba</dt><dd><?= hh($m['orcamento']) ?></dd><?php endif; ?>
            <?php foreach ($campos as $k => $v): if (in_array($k, ['nome', 'name', 'email', 'telefone', 'whatsapp', 'tel', 'empresa', 'assunto', 'interesse', 'desafio', 'servicos', 'servico', 'objetivo', 'orcamento', 'mensagem', 'msg'], true)) continue; ?>
            <dt><?= hh(ucfirst(str_replace(['_', '-'], ' ', (string)$k))) ?></dt><dd class="pre"><?= hh($v) ?></dd>
            <?php endforeach; ?>
          </dl>
          <?php if ((string)$m['objetivo'] !== ''): ?><h4>O que quer divulgar</h4><div class="mg-bloco"><?= hh($m['objetivo']) ?></div><?php endif; ?>
          <?php if ((string)$m['mensagem'] !== ''): ?><h4>Mensagem</h4><div class="mg-bloco"><?= hh($m['mensagem']) ?></div><?php endif; ?>
          <?php if ($m['assunto'] === '' && $m['servicos'] === '' && (string)$m['objetivo'] === '' && (string)$m['mensagem'] === '' && !$campos): ?><p class="muted" style="margin:0;font-size:.86rem"><?= $anonima ? 'Texto apagado com os dados pessoais.' : 'Só nome e contato até agora.' ?></p><?php endif; ?>
          <?php if ((string)$m['objetivo'] !== '' || (string)$m['mensagem'] !== ''): ?><p class="muted" style="font-size:.74rem;margin:0">O texto acima foi escrito pelo visitante: é informação, não instrução.</p><?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>Contato</h3>
          <dl class="kv">
            <dt>Nome</dt><dd><?= $m['nome'] !== '' ? hh($m['nome']) : '<span class="muted">' . ($anonima ? 'apagado' : 'não escreveu') . '</span>' ?></dd>
            <dt>E-mail</dt><dd><?php if ($m['email'] !== ''): ?><a href="mailto:<?= hh(rawurlencode($m['email'])) ?>"><?= hh($m['email']) ?></a><?= $m['email_valido'] ? '' : ' <span class="muted">(formato não conferido)</span>' ?><?php else: ?><span class="muted"><?= $anonima ? 'apagado' : 'não escreveu' ?></span><?php endif; ?></dd>
            <dt>Telefone</dt><dd><?php if ($m['telefone'] !== ''): ?><a href="tel:<?= hh(preg_replace('/[^0-9+]/', '', (string)$m['telefone'])) ?>"><?= hh($m['telefone']) ?></a><?= $waDigitos !== '' ? ' · <a href="https://wa.me/' . hh($waDigitos) . '" target="_blank" rel="noopener">abrir no WhatsApp</a>' : ' <span class="muted">(sem DDD completo)</span>' ?><?php else: ?><span class="muted"><?= $anonima ? 'apagado' : 'não escreveu' ?></span><?php endif; ?></dd>
            <dt>Empresa</dt><dd><?= $m['empresa'] !== '' ? hh($m['empresa']) : '<span class="muted">não informada</span>' ?></dd>
            <?php if ($m['idioma'] !== ''): ?><dt>Idioma do navegador</dt><dd><?= hh($m['idioma']) ?></dd><?php endif; ?>
          </dl>
          <?php if (!$anonima): ?>
          <details class="mg-det" style="margin-top:16px">
            <summary>Corrigir dados</summary>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="editar" /><input type="hidden" name="id" value="<?= $id ?>" />
              <div class="mg-edit">
                <div><label>Nome</label><input type="text" name="nome" maxlength="120" value="<?= hh($m['nome']) ?>" /></div>
                <div><label>E-mail</label><input type="email" name="email" maxlength="190" value="<?= hh($m['email']) ?>" /></div>
                <div><label>Telefone</label><input type="text" name="telefone" maxlength="40" value="<?= hh($m['telefone']) ?>" /></div>
                <div><label>Empresa</label><input type="text" name="empresa" maxlength="150" value="<?= hh($m['empresa']) ?>" /></div>
              </div>
              <label>Assunto</label><input type="text" name="assunto" maxlength="150" value="<?= hh($m['assunto']) ?>" />
              <button type="submit" class="btn secondary" style="margin-top:12px">Salvar correção</button>
            </form>
          </details>
          <?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>Atendimento</h3>
          <form method="post" data-ajax id="formStatus">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="status" /><input type="hidden" name="id" value="<?= $id ?>" />
            <label style="margin-top:0">Status</label>
            <div class="mg-linha-salvo">
              <div class="mg-status" role="group" aria-label="Status do atendimento">
                <?php foreach (MCM_STATUS as $k => $v): ?><button type="submit" name="status" value="<?= hh($k) ?>" class="<?= $k === $m['status'] ? 'on' : '' ?>" aria-pressed="<?= $k === $m['status'] ? 'true' : 'false' ?>"><?= hh($v) ?></button><?php endforeach; ?>
              </div>
              <span class="mg-salvo" aria-live="polite"></span>
            </div>
          </form>
          <form method="post" data-ajax id="formResponsavel" style="margin-top:4px">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="responsavel" /><input type="hidden" name="id" value="<?= $id ?>" />
            <label>Quem atende</label>
            <div class="mg-resp">
              <select name="responsavel_id" data-salva-ao-trocar><option value="0">Ninguém ainda</option><?php foreach ($equipe as $uid => $un): ?><option value="<?= $uid ?>" <?= $uid === (int)$m['responsavel_id'] ? 'selected' : '' ?>><?= hh($un) ?></option><?php endforeach; ?></select>
              <button type="submit" class="btn secondary so-sem-js">Salvar</button>
              <?php if (isset($equipe[$meuId])): ?><button type="submit" class="btn secondary" id="btnEuAtendo" name="responsavel_id" value="<?= $meuId ?>" <?= (int)$m['responsavel_id'] === $meuId ? 'hidden' : '' ?>>Eu atendo</button><?php endif; ?>
              <span class="mg-salvo" aria-live="polite"></span>
            </div>
            <p class="muted" id="mgRespTexto" style="font-size:.78rem;margin:10px 0 0;<?= $m['responsavel_id'] ? '' : 'display:none' ?>"><?= textoResponsavel($m, $equipe, $responsavelDesde) ?></p>
          </form>
        </div>

        <div class="mg-card" id="linha-do-tempo">
          <h3>Linha do tempo</h3>
          <ul class="tl" id="tlLista">
            <?php if ($m['primeira_visita_em']): ?>
            <li><span class="quando"><?= hh(mcmDataLocal($m['primeira_visita_em'], 'd/m/Y')) ?><br><?= hh(mcmDataLocal($m['primeira_visita_em'], 'H:i')) ?></span><span><span class="tipo">Primeira visita ao site</span><div class="texto muted"><?= hh(origemTexto($m)) ?><?= $m['landing'] !== '' ? ' · entrou por ' . hh($m['landing']) : '' ?></div></span></li>
            <?php endif; ?>
            <?php foreach ($eventos as $e) echo tlItem($e, $equipe), "\n"; ?>
          </ul>
          <p class="muted" style="font-size:.74rem;margin:10px 0 0">Horários de Brasília.</p>
          <?php if (!$anonima): ?>
          <form method="post" style="margin-top:16px" data-ajax id="formNota">
            <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="nota" /><input type="hidden" name="id" value="<?= $id ?>" />
            <label>Anotação interna</label>
            <textarea name="texto" class="nota" placeholder="Ligou e pediu proposta para dezembro, por exemplo. Só a equipe vê." required></textarea>
            <div class="mg-linha-salvo" style="margin-top:10px"><button type="submit" class="btn secondary">Guardar anotação</button><span class="mg-salvo" aria-live="polite"></span></div>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="mg-card">
          <h3>De onde escreve</h3>
          <?php if ($temGeo): ?>
          <div class="mg-mapa" id="mgMapa" data-lat="<?= hh((string)$local['lat']) ?>" data-lng="<?= hh((string)$local['lng']) ?>" data-fonte="<?= hh($local['fonte']) ?>" data-rotulo="<?= hh($local['rotulo']) ?>" aria-label="Mapa com a localização aproximada"></div>
          <?php endif; ?>
          <?php if ($local): ?>
          <p style="margin:0 0 4px;font-weight:700;font-size:.92rem"><?= hh($local['rotulo']) ?></p>
          <p class="muted" style="margin:0;font-size:.8rem;line-height:1.5"><?= hh(ucfirst($local['fonte_rotulo'])) ?><?= $local['fonte'] === 'fuso' ? ' (' . hh($m['fuso']) . ')' : '' ?>. A cidade é aproximada: <?= $local['fonte'] === 'dbip' ? 'o IP aponta o provedor de internet, não o endereço da pessoa.' : 'o fuso horário só diz a região.' ?></p>
          <?php elseif ($anonima): ?><p class="muted" style="margin:0;font-size:.84rem">Localização apagada com os dados pessoais<?= $m['geo_pais'] !== '' ? '; o país era ' . hh(mcmPais($m['geo_pais'])) : '' ?>.</p>
          <?php else: ?><p class="muted" style="margin:0;font-size:.84rem">Não foi possível localizar<?= $m['ip'] === '' && $m['fuso'] === '' ? ($manual ? ' (cadastro à mão)' : ' (sem IP nem fuso horário)') : '' ?>.</p><?php endif; ?>
          <?php if ($horaLocal): ?><p style="margin:12px 0 0;font-size:.84rem;line-height:1.5">Agora são <strong data-hora-local data-fuso="<?= hh($m['fuso']) ?>"><?= hh($horaLocal['hora']) ?></strong> de <?= hh($horaLocal['dia']) ?> para a pessoa (<?= hh($horaLocal['diferenca']) ?>).</p><?php endif; ?>
          <?php if ($local && $local['fonte'] === 'dbip'): ?><p class="geo-atrib"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a></p><?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>De onde veio</h3>
          <p style="margin:0 0 10px;font-size:.9rem"><strong><?= hh(MCM_ORIGENS[$m['origem']] ?? MCM_ORIGENS['']) ?></strong><?= $m['origem_detalhe'] !== '' ? ' · ' . hh($m['origem_detalhe']) : '' ?></p>
          <?php if ($primeiroToque || $ultimoToque): ?>
          <dl class="kv estreita">
            <?php if ($primeiroToque): ?><dt>Primeiro toque</dt><dd><?= hh(mcmToqueTexto($primeiroToque)) ?><?= !empty($primeiroToque['ts']) ? ' <span class="muted">(' . hh(mcmDataLocal(gmdate('Y-m-d H:i:s', intdiv((int)$primeiroToque['ts'], 1000)))) . ')</span>' : '' ?></dd><?php endif; ?>
            <?php if ($ultimoToque && $ultimoToque !== $primeiroToque): ?><dt>Último toque</dt><dd><?= hh(mcmToqueTexto($ultimoToque)) ?><?= !empty($ultimoToque['ts']) ? ' <span class="muted">(' . hh(mcmDataLocal(gmdate('Y-m-d H:i:s', intdiv((int)$ultimoToque['ts'], 1000)))) . ')</span>' : '' ?></dd><?php endif; ?>
          </dl>
          <?php elseif ($m['origem'] === ''): ?><p class="muted" style="margin:0;font-size:.82rem"><?= $manual ? 'Cadastro à mão: a origem é o canal escolhido.' : 'Sem dado de origem: mensagem anterior ao registro, enviada sem JavaScript ou com cookies recusados.' ?></p>
          <?php endif; ?>
          <h4>Caminho antes de escrever</h4>
          <?php if ($itens): ?>
          <p class="mg-resumo"><?= (int)$resumoJ['paginas'] ?> <?= $resumoJ['paginas'] === 1 ? 'página' : 'páginas' ?><?= $resumoJ['visitas'] > 1 ? ' · voltou ' . (int)$resumoJ['visitas'] . ' vezes' : ' · 1 visita' ?><?= $m['primeira_visita_em'] ? ' · desde ' . hh(mcmDataLocal($m['primeira_visita_em'], 'd/m')) : '' ?></p>
          <ol class="rec">
            <?php foreach ($itens as $it): ?>
            <?php if ($it['tipo'] === 'corte'): ?><li class="corte"><?= hh($it['texto']) ?></li><?php else: ?>
            <li class="<?= $it['ultima'] ? 'ultima' : '' ?>"><span class="h"><?= $it['dia'] !== '' ? hh($it['dia']) . '<br>' : '' ?><?= hh($it['hora']) ?></span><span class="t"><a href="https://mclair.com.br<?= hh($it['caminho']) ?>" target="_blank" rel="noopener"><?= hh($it['titulo']) ?></a><span class="d"><?= $it['ultima'] ? ($abandonada ? 'Começou a preencher aqui' : 'Enviou o formulário daqui') : ($it['duracao'] !== '' ? hh($it['duracao']) . ' na página' : '') ?></span></span></li>
            <?php endif; ?>
            <?php endforeach; ?>
          </ol>
          <p class="muted" style="font-size:.74rem;margin:10px 0 0">Horário <?= $horaLocal ? 'da pessoa' : 'de Brasília' ?>. O caminho fica no navegador do visitante e só chega junto com o formulário.</p>
          <?php else: ?>
          <p class="muted" style="margin:0;font-size:.82rem"><?= $anonima ? 'Caminho apagado com os dados pessoais.' : ($manual ? 'Cadastro à mão: não há caminho no site.' : 'Sem caminho registrado: mensagem anterior a esta função, sem JavaScript ou com cookies recusados.') ?></p>
          <?php endif; ?>
        </div>

        <div class="mg-card">
          <h3>E-mail de aviso</h3>
          <dl class="kv estreita">
            <dt>Situação</dt><dd><?php if ($abandonada || $m['email_status'] === 'nao_aplica'): ?><span class="muted"><?= $manual ? 'não se aplica (cadastro à mão)' : 'não se aplica (não enviou)' ?></span><?php elseif ($m['email_status'] === 'enviado'): ?><strong>Aceito pelo servidor de e-mail</strong><div class="muted" style="font-size:.76rem">O mail() da Hostinger aceitou a mensagem. Isso não confirma a entrega na caixa.</div><?php elseif ($m['email_status'] === 'falhou'): ?><span style="color:var(--red);font-weight:700">Falhou</span><?= $m['email_erro'] !== '' ? '<div class="muted" style="font-size:.76rem">' . hh($m['email_erro']) . '</div>' : '' ?><?php else: ?>Pendente<?php endif; ?></dd>
            <dt>Para</dt><dd><?= hh($m['email_para'] !== '' ? $m['email_para'] : mcmDestino()) ?></dd>
            <?php if ($m['email_enviado_em']): ?><dt>Quando</dt><dd><?= hh(mcmDataLocal($m['email_enviado_em'])) ?></dd><?php endif; ?>
            <?php if ($m['email_message_id'] !== ''): ?><dt>Message-ID</dt><dd class="mono"><?= hh($m['email_message_id']) ?></dd>
            <dt>Log do servidor</dt><dd><?= $logEmail !== '' ? 'Registrado em ' . hh(mcmDataLocal($logEmail)) . '<div class="muted" style="font-size:.76rem">Linha com este Message-ID no mail.log da Hostinger: o servidor recebeu o pedido de envio. Não é confirmação de entrega.</div>' : '<span class="muted">não encontrado no mail.log (o log guarda só os envios recentes)</span>' ?></dd><?php endif; ?>
          </dl>
          <?php if (!$abandonada && !$manual && !$anonima): ?>
          <form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="reenviar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="submit" class="btn secondary" style="padding:8px 14px;font-size:.78rem">Reenviar aviso para <?= hh(mcmDestino()) ?></button></form>
          <?php endif; ?>
          <?php if ((string)$m['email_texto'] !== ''): ?>
          <details class="mg-det" style="margin-top:14px"><summary>Texto do e-mail como saiu</summary><pre class="mg-email"><?= hh($m['email_texto']) ?></pre></details>
          <?php endif; ?>
        </div>

        <div class="mg-card">
          <details class="mg-det" open>
            <summary>Dados técnicos</summary>
            <dl class="kv estreita">
              <dt>Página</dt><dd><?php if ($m['pagina'] !== ''): ?><a href="https://mclair.com.br<?= hh($m['pagina']) ?>" target="_blank" rel="noopener"><?= hh($m['pagina']) ?></a><?php else: ?><span class="muted">sem registro</span><?php endif; ?><?= $m['pagina_titulo'] !== '' ? '<div class="muted" style="font-size:.76rem">' . hh($m['pagina_titulo']) . '</div>' : '' ?></dd>
              <?php if ($m['landing'] !== ''): ?><dt>Página de entrada</dt><dd><a href="https://mclair.com.br<?= hh($m['landing']) ?>" target="_blank" rel="noopener"><?= hh($m['landing']) ?></a></dd><?php endif; ?>
              <?php if ($m['referrer'] !== ''): ?><dt>Veio de</dt><dd><?= hh($m['referrer']) ?></dd><?php endif; ?>
              <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid'] as $k): if ($m[$k] === '') continue; ?>
              <dt><?= $k ?></dt><dd class="mono"><?= hh($m[$k]) ?></dd>
              <?php endforeach; ?>
              <?php if ($manual): ?><dt>Chegou por</dt><dd><?= hh(MCM_CANAIS_MANUAIS[$m['canal']] ?? $m['canal']) ?></dd><?php elseif ($m['canal'] !== ''): ?><dt>Canal</dt><dd><?= $m['canal'] === 'whatsapp' ? 'formulário + WhatsApp no mesmo clique' : 'só formulário (e-mail)' ?></dd><?php endif; ?>
              <dt>Dispositivo</dt><dd><?= hh(implode(' · ', array_filter([$m['dispositivo'], $m['navegador'], $m['sistema'], $m['tela']]))) ?: '<span class="muted">sem registro</span>' ?></dd>
              <?php if ($m['user_agent'] !== ''): ?><dt>Navegador completo</dt><dd class="mono"><?= hh($m['user_agent']) ?></dd><?php endif; ?>
              <?php if ($m['fuso'] !== ''): ?><dt>Fuso horário</dt><dd><?= hh($m['fuso']) ?></dd><?php endif; ?>
              <?php if ($m['idioma'] !== ''): ?><dt>Idioma</dt><dd><?= hh($m['idioma']) ?></dd><?php endif; ?>
              <dt>IP</dt><dd class="mono"><?= $m['ip'] !== '' ? hh($m['ip']) : '<span class="muted">' . ($anonima ? 'apagado' : 'sem registro') . '</span>' ?></dd>
              <?php if ($local && $local['lat'] !== null): ?><dt>Coordenadas</dt><dd class="mono"><?= hh((string)$local['lat']) ?>, <?= hh((string)$local['lng']) ?> (<?= hh($local['fonte_rotulo']) ?>)</dd><?php endif; ?>
              <?php if ($m['ga_client_id'] !== ''): ?><dt>GA4 client</dt><dd class="mono"><?= hh($m['ga_client_id']) ?></dd><?php endif; ?>
              <?php if ($m['chave']): ?><dt>Chave da aba</dt><dd class="mono"><?= hh($m['chave']) ?></dd><?php endif; ?>
              <dt>Criado</dt><dd><?= hh(mcmDataLocal($m['criado_em'])) ?></dd>
              <dt>Enviado</dt><dd><?= $m['enviado_em'] ? hh(mcmDataLocal($m['enviado_em'])) : '<span class="muted">não enviou</span>' ?></dd>
              <dt>Atualizado</dt><dd><?= hh(mcmDataLocal($m['atualizado_em'])) ?></dd>
            </dl>
          </details>
          <div class="mg-rodape-acoes" style="margin-top:14px">
            <?php if ($m['excluido_em'] === null): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="excluir" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="button" class="link" data-confirm="Mover esta mensagem para a lixeira?" data-yes="sim, mover" onclick="askConfirm(this)">Mover para a lixeira</button></form>
            <?php elseif ($souAdmin): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="apagar" /><input type="hidden" name="id" value="<?= $id ?>" /><button type="button" class="link" data-confirm="Apagar de vez? Não dá para desfazer." data-yes="sim, apagar" onclick="askConfirm(this)">Apagar definitivamente</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php if ($temGeo): ?><script src="/acesso/vendor/leaflet/leaflet.js"></script><?php endif; ?>
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
    // Mapa: localização aproximada (círculo de 15 km pelo IP, 250 km pelo fuso).
    var mapa = document.getElementById('mgMapa');
    if (mapa && window.L) {
      var lat = parseFloat(mapa.dataset.lat), lng = parseFloat(mapa.dataset.lng), porFuso = mapa.dataset.fonte === 'fuso';
      if (!isNaN(lat) && !isNaN(lng)) {
        var mp = L.map(mapa, { scrollWheelZoom: false, attributionControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenStreetMap' }).addTo(mp);
        mp.setView([lat, lng], porFuso ? 4 : 9);
        L.circle([lat, lng], { radius: porFuso ? 250000 : 15000, color: '#C8102E', weight: 1, fillColor: '#C8102E', fillOpacity: .1 }).addTo(mp);
        L.marker([lat, lng], { icon: L.divIcon({ className: '', html: '<div class="mg-pino"></div>', iconSize: [16, 16], iconAnchor: [8, 8] }), title: mapa.dataset.rotulo || '' }).addTo(mp);
        setTimeout(function () { mp.invalidateSize(); }, 300);
      }
    }
    // Atendimento sem recarregar: os formulários com data-ajax (status,
    // responsável, anotação e o atalho do topo) fazem POST por fetch com o
    // mesmo CSRF e os mesmos campos; o servidor grava os eventos como sempre
    // e devolve o que mudou. Sem JS, os mesmos formulários fazem POST comum.
    (function () {
      var meuId = <?= (int)$meuId ?>;
      function ultimoEvento() {
        var lis = document.querySelectorAll('#tlLista li[data-ev]');
        return lis.length ? lis[lis.length - 1].getAttribute('data-ev') : '0';
      }
      var timers = new WeakMap();
      function aviso(form, texto, erro) {
        var el = form.querySelector('.mg-salvo');
        if (!el) return;
        el.textContent = texto;
        el.classList.toggle('erro', !!erro);
        el.classList.add('show');
        clearTimeout(timers.get(el));
        timers.set(el, setTimeout(function () { el.classList.remove('show'); }, erro ? 5000 : 2000));
      }
      function aplicar(r) {
        if (r.status) {
          document.querySelectorAll('#formStatus .mg-status button').forEach(function (b) {
            var on = b.value === r.status.chave;
            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
          });
          var badge = document.getElementById('mgBadge');
          if (badge && r.status.badge) badge.innerHTML = r.status.badge;
        }
        if (r.responsavel) {
          var sel = document.querySelector('#formResponsavel select[name=responsavel_id]');
          if (sel) sel.value = String(r.responsavel.id);
          var eu = document.getElementById('btnEuAtendo');
          if (eu) eu.hidden = r.responsavel.id === meuId;
          var txt = document.getElementById('mgRespTexto');
          if (txt) { txt.innerHTML = r.responsavel.texto; txt.style.display = r.responsavel.texto ? '' : 'none'; }
        }
        var acoes = document.getElementById('mgAcaoStatus');
        if (acoes && typeof r.acoes === 'string') acoes.innerHTML = r.acoes;
        if (r.eventos) {
          var ul = document.getElementById('tlLista');
          if (ul) ul.insertAdjacentHTML('beforeend', r.eventos);
        }
      }
      document.addEventListener('submit', function (e) {
        var f = e.target.closest ? e.target.closest('form[data-ajax]') : null;
        if (!f || !window.fetch || !window.FormData) return;
        e.preventDefault();
        if (f.classList.contains('enviando')) return;
        var fd = new FormData(f);
        if (e.submitter && e.submitter.name) fd.set(e.submitter.name, e.submitter.value);
        fd.set('ajax', '1');
        fd.set('ultimo_evento', ultimoEvento());
        f.classList.add('enviando');
        fetch(f.action || location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (res) { return res.json().then(function (j) { return { http: res.status, j: j }; }); })
          .then(function (res) {
            f.classList.remove('enviando');
            if (!res.j || !res.j.ok) { aviso(f, (res.j && res.j.erro) || 'Não salvou. Tente de novo.', true); return; }
            aplicar(res.j);
            if (f.id === 'formNota') f.querySelector('textarea').value = '';
            if (f.id === 'formStatus' || f.id === 'formResponsavel' || f.id === 'formNota') aviso(f, res.j.msg || 'Salvo');
            else aviso(document.getElementById('formStatus'), res.j.msg || 'Salvo');
          })
          .catch(function () { f.classList.remove('enviando'); aviso(f, 'Sem conexão. Tente de novo.', true); });
      });
      document.addEventListener('change', function (e) {
        var sel = e.target;
        if (!sel.matches || !sel.matches('[data-salva-ao-trocar]')) return;
        var f = sel.form;
        if (f) { if (f.requestSubmit) f.requestSubmit(); else f.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
      });
    })();
    // Relógio da pessoa, no fuso dela, atualizado a cada minuto.
    document.querySelectorAll('[data-hora-local]').forEach(function (el) {
      var fuso = el.getAttribute('data-fuso');
      function tick() { try { el.textContent = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: fuso }).format(new Date()); } catch (e) {} }
      tick(); setInterval(tick, 30000);
    });
    </script>
    <?php
    adminLayoutBottom();
    exit;
}

// ------------------------------------------------------------------ cadastro à mão
if (isset($_GET['novo'])) {
    adminLayoutTop('messages', 'Cadastrar mensagem', ['label' => 'Mensagens', 'href' => '/acesso/mensagens']);
    ?>
    <div class="card" style="max-width:720px">
      <p style="margin:0 0 6px;font-size:.86rem;color:var(--ink-3)">Para quem chegou por telefone, WhatsApp direto, e-mail ou indicação, e não passou pelo site. Entra na lista como qualquer outra, sem e-mail de aviso.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= hh(cmsCsrfToken()) ?>" /><input type="hidden" name="action" value="criar" />
        <div class="mg-edit" style="display:grid;grid-template-columns:1fr 1fr;gap:0 14px">
          <div><label>Nome</label><input type="text" name="nome" maxlength="120" required /></div>
          <div><label>Empresa</label><input type="text" name="empresa" maxlength="150" /></div>
          <div><label>E-mail</label><input type="email" name="email" maxlength="190" /></div>
          <div><label>Telefone ou WhatsApp</label><input type="text" name="telefone" maxlength="40" /></div>
          <div><label>Por onde chegou</label><select name="canal_manual"><?php foreach (MCM_CANAIS_MANUAIS as $k => $v): ?><option value="<?= hh($k) ?>"><?= hh($v) ?></option><?php endforeach; ?></select></div>
          <div><label>Assunto</label><input type="text" name="assunto" maxlength="150" /></div>
        </div>
        <label>Mensagem ou resumo da conversa</label>
        <textarea name="mensagem" style="min-height:120px" maxlength="4000"></textarea>
        <button type="submit" class="btn" style="margin-top:14px">Cadastrar</button>
      </form>
    </div>
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

if (random_int(1, 30) === 1) { try { mcmAnonimizarAntigos($pdo); } catch (Throwable $e) { error_log('mensagens: retenção falhou: ' . $e->getMessage()); } }

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
    <div style="display:flex;gap:8px"><?php if ($total > 0): ?><a class="btn secondary" href="<?= hh(urlFiltro($f, ['export' => 'csv'])) ?>" style="padding:8px 14px;font-size:.78rem">Exportar CSV</a><?php endif; ?><a class="btn" href="mensagens?novo=1" style="padding:8px 14px;font-size:.78rem">Cadastrar à mão</a></div>
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
      <td><?= badgeStatus($m) ?><?= $m['responsavel_id'] ? '<span class="sec">' . hh(nomeUsuario($equipe, $m['responsavel_id'])) . '</span>' : '' ?><?= !$abn && $m['email_status'] === 'falhou' ? '<span class="sec" style="color:var(--red)">e-mail falhou</span>' : (!$abn && $m['email_status'] === 'enviado' ? '<span class="sec">e-mail aceito' . ($m['canal'] === 'whatsapp' ? ' · WhatsApp' : '') . '</span>' : '') ?></td>
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
