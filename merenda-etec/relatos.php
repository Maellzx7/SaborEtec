<?php
// ============================================================
// ARQUIVO: relatos.php (raiz do projeto)
// DESCRIÇÃO: Sistema de relatos dos alunos — envio e visualização
//            Supervisor pode ver e responder todos os relatos
// Sistema Sabor Etec — ETEC de Peruíbe
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/_layout.php';

$pdo  = conectar();
$erro = '';

// ── Enviar relato (aluno ou visitante logado) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar_relato') {
    requerLogin();
    $tipo     = $_POST['tipo']     ?? 'sugestao';
    $titulo   = trim($_POST['titulo']   ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $anonimo  = isset($_POST['anonimo']) ? 1 : 0;

    $tiposValidos = ['reclamacao','restricao','sugestao','elogio','outro'];
    if (!in_array($tipo, $tiposValidos)) $tipo = 'sugestao';

    if (!$titulo || !$mensagem) {
        $erro = 'Preencha o título e a mensagem.';
    } else {
        $pdo->prepare("INSERT INTO relatos (usuario_id,tipo,titulo,mensagem,anonimo) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['usuario_id'], $tipo, $titulo, $mensagem, $anonimo]);
        flash('sucesso', 'Relato enviado! Obrigado pelo seu feedback.');
        redirecionar(SITE_URL . '/relatos.php');
    }
}

// ── Responder relato (supervisor) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'responder') {
    requerSupervisor();
    $id       = (int)($_POST['relato_id'] ?? 0);
    $resposta = trim($_POST['resposta'] ?? '');
    if ($id && $resposta) {
        $pdo->prepare("UPDATE relatos SET resposta=?, status='respondido',
                       respondido_por=?, respondido_em=NOW() WHERE id=?")
            ->execute([$resposta, $_SESSION['usuario_id'], $id]);
        flash('sucesso', 'Resposta enviada!');
    }
    redirecionar(SITE_URL . '/relatos.php');
}

// ── Marcar como lido ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_lido') {
    requerSupervisor();
    $id = (int)($_POST['relato_id'] ?? 0);
    $pdo->prepare("UPDATE relatos SET status='lido' WHERE id=? AND status='pendente'")->execute([$id]);
    redirecionar(SITE_URL . '/relatos.php');
}

// ── Buscar relatos ──
if (estaLogado() && in_array(perfil(), ['supervisor','sub_supervisor'])) {
    // Supervisor vê todos
    $relatos = $pdo->query("
        SELECT r.*, u.nome AS autor_nome, u.perfil AS autor_perfil,
               s.nome AS respondido_nome
        FROM relatos r
        JOIN usuarios u ON r.usuario_id = u.id
        LEFT JOIN usuarios s ON r.respondido_por = s.id
        ORDER BY r.criado_em DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (estaLogado()) {
    // Aluno vê apenas os seus
    $stmt = $pdo->prepare("
        SELECT r.*, u.nome AS autor_nome, u.perfil AS autor_perfil,
               s.nome AS respondido_nome
        FROM relatos r
        JOIN usuarios u ON r.usuario_id = u.id
        LEFT JOIN usuarios s ON r.respondido_por = s.id
        WHERE r.usuario_id = ?
        ORDER BY r.criado_em DESC
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $relatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $relatos = [];
}

$tiposLabel = [
    'reclamacao' => ['label'=>'Reclamação',    'cor'=>'#c0392b', 'bg'=>'#fde8e8'],
    'restricao'  => ['label'=>'Restrição',      'cor'=>'#8e5000', 'bg'=>'#fff3e0'],
    'sugestao'   => ['label'=>'Sugestão',        'cor'=>'#1a6a3a', 'bg'=>'#e8f5e9'],
    'elogio'     => ['label'=>'Elogio',          'cor'=>'#0d5c8f', 'bg'=>'#e3f2fd'],
    'outro'      => ['label'=>'Outro',           'cor'=>'#5a5a5a', 'bg'=>'#f0f0f0'],
];

$statusLabel = [
    'pendente'   => ['label'=>'Pendente',   'cor'=>'#8e5000', 'bg'=>'#fff3e0'],
    'lido'       => ['label'=>'Lido',       'cor'=>'#5a5a5a', 'bg'=>'#f0f0f0'],
    'respondido' => ['label'=>'Respondido', 'cor'=>'#1a6a3a', 'bg'=>'#e8f5e9'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatos — Sabor Etec</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
<style>
.page-hero {
    background: linear-gradient(135deg, var(--c1) 0%, var(--c2) 60%, var(--c3) 100%);
    padding: 48px 28px 52px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.page-hero h1 {
    font-family: var(--titulo);
    font-size: 2.6rem;
    color: #fff;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
}

.page-hero p {
    font-size: .95rem;
    color: rgba(255,255,255,.72);
    max-width: 520px;
    margin: 0 auto;
    position: relative;
}

/* Grid principal */
.relatos-layout {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 28px;
    align-items: start;
}

/* Card de envio */
.envio-card {
    background: var(--branco);
    border-radius: var(--r-lg);
    box-shadow: var(--sombra-s);
    border: 1px solid var(--borda);
    overflow: hidden;
    position: sticky;
    top: 88px;
}

.envio-card-top {
    background: linear-gradient(135deg, var(--c2), var(--c3));
    padding: 20px 24px;
}

.envio-card-top h2 {
    font-family: var(--titulo);
    font-size: 1.3rem;
    color: #fff;
    font-weight: 600;
}

.envio-card-top p { font-size: .78rem; color: rgba(255,255,255,.7); margin-top: 3px; }

.envio-card-body { padding: 24px; }

/* Seletor de tipo com ícones */
.tipo-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-bottom: 18px;
}

.tipo-opt {
    border: 1.5px solid var(--borda);
    border-radius: var(--r-sm);
    padding: 10px 6px;
    text-align: center;
    cursor: pointer;
    transition: var(--trans);
    background: var(--creme);
}

.tipo-opt:hover { border-color: var(--c4); background: #fff5f5; }

.tipo-opt.selecionado {
    border-color: var(--c3);
    background: #fde8ea;
}

.tipo-opt input { display: none; }

.tipo-opt-icon {
    font-size: 1.3rem;
    display: block;
    margin-bottom: 4px;
}

.tipo-opt-label {
    font-size: .62rem;
    font-weight: 600;
    color: var(--texto2);
    letter-spacing: .03em;
}

.tipo-opt.selecionado .tipo-opt-label { color: var(--c2); }

/* Checkbox anonimo */
.check-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: var(--creme2);
    border-radius: var(--r-sm);
    margin-bottom: 18px;
    cursor: pointer;
    transition: var(--trans);
    border: 1px solid transparent;
}
.check-row:hover { border-color: var(--borda); }

.check-row input[type=checkbox] {
    width: 16px; height: 16px;
    accent-color: var(--c3);
    cursor: pointer;
    flex-shrink: 0;
}

.check-row-txt { font-size: .8rem; color: var(--texto2); line-height: 1.4; }
.check-row-txt strong { color: var(--c2); display: block; font-size: .82rem; }

/* Lista de relatos */
.relatos-lista { display: flex; flex-direction: column; gap: 16px; }

.relato-card {
    background: var(--branco);
    border-radius: var(--r-lg);
    border: 1px solid var(--borda);
    box-shadow: var(--sombra-s);
    overflow: hidden;
    transition: var(--trans);
}

.relato-card:hover { box-shadow: var(--sombra-m); }

.relato-header {
    padding: 16px 20px 14px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--creme2);
}

.relato-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.relato-tipo-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
}

.relato-status-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .04em;
}

.relato-titulo {
    font-family: var(--titulo);
    font-size: 1.1rem;
    color: var(--c2);
    font-weight: 600;
    margin-bottom: 4px;
    line-height: 1.3;
}

.relato-autor {
    font-size: .74rem;
    color: var(--texto2);
}

.relato-body { padding: 16px 20px; }

.relato-mensagem {
    font-size: .88rem;
    color: var(--texto);
    line-height: 1.7;
    margin-bottom: 14px;
}

.relato-resposta {
    background: var(--creme2);
    border-left: 3px solid var(--c4);
    border-radius: 0 var(--r-sm) var(--r-sm) 0;
    padding: 12px 16px;
    margin-top: 12px;
}

.relato-resposta-titulo {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--c4);
    margin-bottom: 6px;
}

.relato-resposta-txt { font-size: .85rem; color: var(--texto); line-height: 1.65; }

/* Formulário de resposta (supervisor) */
.resp-form {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--creme2);
}

.resp-form textarea {
    width: 100%;
    padding: 10px 13px;
    border: 1.5px solid var(--borda);
    border-radius: var(--r-sm);
    font-family: var(--corpo);
    font-size: .88rem;
    color: var(--texto);
    background: var(--creme);
    outline: none;
    resize: vertical;
    min-height: 80px;
    transition: var(--trans);
    margin-bottom: 10px;
}
.resp-form textarea:focus { border-color: var(--c4); background: var(--branco); box-shadow: 0 0 0 3px rgba(174,40,49,.1); }

/* Login CTA */
.login-cta {
    background: var(--branco);
    border: 1px solid var(--borda);
    border-radius: var(--r-lg);
    padding: 40px 32px;
    text-align: center;
    box-shadow: var(--sombra-s);
}

.login-cta h3 { font-family: var(--titulo); font-size: 1.5rem; color: var(--c2); margin-bottom: 8px; }
.login-cta p  { font-size: .88rem; color: var(--texto2); margin-bottom: 20px; }

/* Vazio */
.relatos-vazio {
    background: var(--branco);
    border: 1px dashed var(--borda);
    border-radius: var(--r-lg);
    padding: 48px 24px;
    text-align: center;
    color: var(--texto2);
}

@media (max-width: 860px) {
    .relatos-layout { grid-template-columns: 1fr; }
    .envio-card { position: static; }
    .tipo-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
</head>
<body>
<?php renderHeader('relatos.php'); ?>

<!-- Hero -->
<div class="page-hero">
    <h1>Sua Voz Importa</h1>
    <p>Envie sua sugestão, restrição alimentar, elogio ou reclamação. Juntos melhoramos a merenda da ETEC.</p>
</div>

<main>
<div class="container">
    <?= exibirFlash() ?>
    <?php if ($erro): ?>
        <div class="flash" style="background:#8b0000"><?= escape($erro) ?></div>
    <?php endif; ?>

    <div class="relatos-layout">

        <!-- ══ FORMULÁRIO DE ENVIO ══ -->
        <div>
            <?php if (!estaLogado()): ?>
                <div class="login-cta">
                    <svg viewBox="0 0 24 24" style="width:52px;height:52px;fill:var(--borda);margin:0 auto 16px"><path d="M12 1C8.676 1 6 3.676 6 7s2.676 6 6 6 6-2.676 6-6-2.676-6-6-6zm0 13c-4.004 0-12 2.011-12 6v2h24v-2c0-3.989-7.996-6-12-6z"/></svg>
                    <h3>Entre para enviar um relato</h3>
                    <p>Faça login ou crie sua conta gratuitamente para enviar sugestões, reclamações e muito mais.</p>
                    <a href="<?= SITE_URL ?>/login.php" class="btn btn-primario" style="display:inline-flex;max-width:220px">
                        Entrar / Criar conta
                    </a>
                </div>
            <?php else: ?>
                <div class="envio-card">
                    <div class="envio-card-top">
                        <h2>Enviar relato</h2>
                        <p>Seu feedback chega diretamente ao supervisor</p>
                    </div>
                    <div class="envio-card-body">
                        <form method="POST" id="formRelato">
                            <input type="hidden" name="acao" value="enviar_relato">
                            <input type="hidden" name="tipo" id="tipoHidden" value="sugestao">

                            <!-- Tipo com clique visual -->
                            <div style="font-size:.78rem;font-weight:600;color:var(--c2);text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">Tipo de relato</div>
                            <div class="tipo-grid">
                                <?php
                                $tiposOpts = [
                                    'reclamacao' => ['✗', 'Reclamação'],
                                    'restricao'  => ['⊘', 'Restrição'],
                                    'sugestao'   => ['✦', 'Sugestão'],
                                    'elogio'     => ['♥', 'Elogio'],
                                    'outro'      => ['…', 'Outro'],
                                ];
                                foreach ($tiposOpts as $val => [$ico, $lbl]):
                                ?>
                                <div class="tipo-opt <?= $val === 'sugestao' ? 'selecionado' : '' ?>"
                                     onclick="selecionarTipo('<?= $val ?>', this)">
                                    <span class="tipo-opt-icon"><?= $ico ?></span>
                                    <span class="tipo-opt-label"><?= $lbl ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-grupo">
                                <label>Título</label>
                                <input type="text" name="titulo" required
                                       placeholder="Resumo do seu relato">
                            </div>

                            <div class="form-grupo">
                                <label>Mensagem</label>
                                <textarea name="mensagem" required rows="4"
                                          placeholder="Descreva com detalhes..."></textarea>
                            </div>

                            <label class="check-row">
                                <input type="checkbox" name="anonimo" value="1">
                                <div class="check-row-txt">
                                    <strong>Enviar anonimamente</strong>
                                    Seu nome não será exibido ao supervisor
                                </div>
                            </label>

                            <button type="submit" class="btn btn-primario">
                                Enviar relato
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══ LISTA DE RELATOS ══ -->
        <div>
            <div class="sec-titulo" style="margin-bottom:4px">
                <?= (estaLogado() && in_array(perfil(),['supervisor','sub_supervisor'])) ? 'Todos os relatos' : 'Meus relatos' ?>
            </div>
            <div class="sec-sub">
                <?= count($relatos) ?> relato<?= count($relatos) !== 1 ? 's' : '' ?> encontrado<?= count($relatos) !== 1 ? 's' : '' ?>
            </div>

            <?php if (!estaLogado()): ?>
                <div class="relatos-vazio">
                    <p style="font-family:var(--titulo);font-size:1.1rem;color:var(--c2);margin-bottom:6px">
                        Faça login para ver seus relatos
                    </p>
                </div>
            <?php elseif (empty($relatos)): ?>
                <div class="relatos-vazio">
                    <svg viewBox="0 0 24 24" style="width:48px;height:48px;fill:var(--borda);margin:0 auto 12px"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    <p style="font-family:var(--titulo);font-size:1.1rem;color:var(--c2);margin-bottom:6px">Nenhum relato ainda</p>
                    <p style="font-size:.84rem">Use o formulário ao lado para enviar seu primeiro relato.</p>
                </div>
            <?php else: ?>
                <div class="relatos-lista">
                    <?php foreach ($relatos as $r):
                        $tp = $tiposLabel[$r['tipo']] ?? $tiposLabel['outro'];
                        $st = $statusLabel[$r['status']] ?? $statusLabel['pendente'];
                        $nomExib = $r['anonimo'] ? 'Anônimo' : escape($r['autor_nome']);
                    ?>
                    <div class="relato-card">
                        <div class="relato-header">
                            <div>
                                <div class="relato-meta" style="margin-bottom:6px">
                                    <span class="relato-tipo-badge"
                                          style="background:<?= $tp['bg'] ?>;color:<?= $tp['cor'] ?>">
                                        <?= $tp['label'] ?>
                                    </span>
                                    <span class="relato-status-badge"
                                          style="background:<?= $st['bg'] ?>;color:<?= $st['cor'] ?>">
                                        <?= $st['label'] ?>
                                    </span>
                                </div>
                                <div class="relato-titulo"><?= escape($r['titulo']) ?></div>
                                <div class="relato-autor">
                                    por <?= $nomExib ?> · <?= date('d/m/Y \à\s H:i', strtotime($r['criado_em'])) ?>
                                </div>
                            </div>

                            <!-- Marcar como lido (supervisor) -->
                            <?php if (in_array(perfil(),['supervisor','sub_supervisor']) && $r['status'] === 'pendente'): ?>
                            <form method="POST" style="flex-shrink:0">
                                <input type="hidden" name="acao" value="marcar_lido">
                                <input type="hidden" name="relato_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-secundario btn-sm">Marcar como lido</button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <div class="relato-body">
                            <div class="relato-mensagem"><?= nl2br(escape($r['mensagem'])) ?></div>

                            <!-- Resposta existente -->
                            <?php if ($r['resposta']): ?>
                            <div class="relato-resposta">
                                <div class="relato-resposta-titulo">
                                    Resposta do supervisor · <?= escape($r['respondido_nome'] ?? '') ?>
                                    · <?= $r['respondido_em'] ? date('d/m/Y', strtotime($r['respondido_em'])) : '' ?>
                                </div>
                                <div class="relato-resposta-txt"><?= nl2br(escape($r['resposta'])) ?></div>
                            </div>
                            <?php endif; ?>

                            <!-- Formulário de resposta (supervisor) -->
                            <?php if (in_array(perfil(),['supervisor','sub_supervisor']) && !$r['resposta']): ?>
                            <div class="resp-form">
                                <form method="POST">
                                    <input type="hidden" name="acao" value="responder">
                                    <input type="hidden" name="relato_id" value="<?= $r['id'] ?>">
                                    <textarea name="resposta" placeholder="Escreva uma resposta para este relato..." required></textarea>
                                    <button type="submit" class="btn btn-primario" style="width:auto;padding:9px 22px;font-size:.84rem">
                                        Enviar resposta
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>

<?php renderFooter(); ?>

<script>
function selecionarTipo(val, el) {
    document.querySelectorAll('.tipo-opt').forEach(o => o.classList.remove('selecionado'));
    el.classList.add('selecionado');
    document.getElementById('tipoHidden').value = val;
}
</script>
</body>
</html>