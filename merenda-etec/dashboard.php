<?php
// ============================================================
// ARQUIVO: dashboard.php (raiz do projeto)
// DESCRIÇÃO: Painel do supervisor com calendário interativo
//            para montar o cardápio por dia
// Acesso: supervisor e sub_supervisor
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/_layout.php';
requerSupervisor();

$pdo = conectar();

// --- POST: publicar novidade ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcao = $_POST['acao'] ?? '';

    if ($postAcao === 'nova_novidade') {
        $titulo   = trim($_POST['titulo'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $tipo     = $_POST['tipo'] ?? 'info';
        if ($titulo && $mensagem) {
            $pdo->prepare("INSERT INTO novidades (titulo,mensagem,tipo,usuario_id) VALUES (?,?,?,?)")
                ->execute([$titulo, $mensagem, $tipo, $_SESSION['usuario_id']]);
            flash('sucesso', 'Aviso publicado!');
        } else {
            flash('erro', 'Preencha título e mensagem.');
        }
        redirecionar(SITE_URL . '/dashboard.php');
    }

    if ($postAcao === 'desativar_novidade') {
        $id = (int)($_POST['novidade_id'] ?? 0);
        $pdo->prepare("UPDATE novidades SET ativo=0 WHERE id=?")->execute([$id]);
        flash('sucesso', 'Aviso removido.');
        redirecionar(SITE_URL . '/dashboard.php');
    }

    // Salvar prato num dia via calendário (AJAX ou POST normal)
    if ($postAcao === 'salvar_dia_cardapio') {
        $dataRef  = $_POST['data_ref'] ?? '';
        $pratoId  = (int)($_POST['prato_id'] ?? 0);
        $dia      = $_POST['dia_semana'] ?? '';
        $diasValidos = ['segunda','terca','quarta','quinta','sexta'];
        if ($dataRef && $dia && in_array($dia, $diasValidos)) {
            if ($pratoId > 0) {
                $pdo->prepare("INSERT INTO cardapio_semana (dia_semana,prato_id,data_referencia) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE prato_id=?")
                    ->execute([$dia, $pratoId, $dataRef, $pratoId]);
            } else {
                $pdo->prepare("DELETE FROM cardapio_semana WHERE dia_semana=? AND data_referencia=?")
                    ->execute([$dia, $dataRef]);
            }
        }
        // Resposta JSON para AJAX
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        flash('sucesso', 'Cardápio atualizado!');
        redirecionar(SITE_URL . '/dashboard.php');
    }
}

// Estatísticas
$totalPratos    = $pdo->query("SELECT COUNT(*) FROM pratos WHERE ativo=1")->fetchColumn();
$totalUsuarios  = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo=1")->fetchColumn();
$totalNovidades = $pdo->query("SELECT COUNT(*) FROM novidades WHERE ativo=1")->fetchColumn();

// Todos os pratos para o select do calendário
$todosPratos = $pdo->query("SELECT id, nome FROM pratos WHERE ativo=1 ORDER BY nome")->fetchAll();

// Cardápio do mês atual (para marcar dias no calendário)
$mesAtual   = date('Y-m');
$inicioMes  = $mesAtual . '-01';
$fimMes     = date('Y-m-t');
$stmtMes    = $pdo->prepare("SELECT dia_semana, prato_id, data_referencia, p.nome AS prato_nome
    FROM cardapio_semana cs JOIN pratos p ON cs.prato_id = p.id
    WHERE cs.data_referencia BETWEEN ? AND ?");
$stmtMes->execute([$inicioMes, $fimMes]);
$cardapioMes = [];
foreach ($stmtMes->fetchAll() as $row) {
    $cardapioMes[$row['data_referencia']] = $row;
}

// Novidades ativas
$novidades = $pdo->query("
    SELECT n.*, u.nome AS autor
    FROM novidades n JOIN usuarios u ON n.usuario_id = u.id
    WHERE n.ativo=1 ORDER BY n.criado_em DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel — <?= SITE_NOME ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
    <style>
        /* Calendário */
        .calendario-wrap {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(56,5,14,.10);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .cal-header {
            background: var(--c1);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
        }

        .cal-header h2 {
            font-family: var(--fonte-titulo);
            font-size: 1.15rem;
            font-weight: 600;
        }

        .cal-nav-btn {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff;
            width: 34px; height: 34px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background .18s;
            display: flex; align-items: center; justify-content: center;
        }
        .cal-nav-btn:hover { background: rgba(255,255,255,.28); }

        .cal-grid-cabecalho {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: var(--c2);
        }

        .cal-grid-cabecalho span {
            text-align: center;
            padding: 9px 4px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255,255,255,.75);
        }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
        }

        .cal-dia {
            min-height: 80px;
            border-right: 1px solid var(--cinza-claro);
            border-bottom: 1px solid var(--cinza-claro);
            padding: 7px 8px 8px;
            position: relative;
            transition: background .15s;
        }

        .cal-dia:nth-child(7n) { border-right: none; }

        .cal-dia.vazio { background: #fafafa; }
        .cal-dia.fds   { background: #f9f4f4; }
        .cal-dia.hoje  { background: #fff8f8; }

        .cal-dia.util {
            cursor: pointer;
        }
        .cal-dia.util:hover { background: #fdf0f0; }

        .cal-dia-num {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--cinza-texto);
            margin-bottom: 4px;
            line-height: 1;
        }

        .cal-dia.hoje .cal-dia-num {
            background: var(--c3);
            color: #fff;
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem;
        }

        .cal-dia.fds .cal-dia-num { color: #bba0a0; }
        .cal-dia.vazio .cal-dia-num { color: #ddd; }

        .cal-prato-badge {
            background: var(--c4);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 6px;
            border-radius: 5px;
            line-height: 1.3;
            cursor: pointer;
            display: block;
            margin-top: 2px;
            transition: background .15s;
        }

        .cal-prato-badge:hover { background: var(--c2); }

        .cal-adicionar {
            font-size: 0.68rem;
            color: #c0a0a0;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .cal-adicionar:hover { color: var(--c4); }

        /* Modal de seleção de prato */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30,5,10,.55);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.aberto { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 18px;
            padding: 32px 36px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            animation: popIn .22s cubic-bezier(.34,1.56,.64,1);
        }

        @keyframes popIn {
            from { opacity:0; transform: scale(.92) translateY(12px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }

        .modal-titulo {
            font-family: var(--fonte-titulo);
            font-size: 1.2rem;
            color: var(--c2);
            margin-bottom: 4px;
        }

        .modal-sub {
            font-size: 0.82rem;
            color: #9a7070;
            margin-bottom: 20px;
        }

        .modal-select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--cinza-medio);
            border-radius: 10px;
            font-family: var(--fonte-corpo);
            font-size: 0.92rem;
            color: var(--cinza-texto);
            background: var(--branco);
            margin-bottom: 18px;
            outline: none;
            transition: border .18s;
        }

        .modal-select:focus { border-color: var(--c4); box-shadow: 0 0 0 3px rgba(174,40,49,.12); }

        .modal-acoes { display: flex; gap: 10px; }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(56,5,14,.08);
            border-left: 4px solid var(--c3);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .stat-num {
            font-family: var(--fonte-titulo);
            font-size: 1.8rem;
            color: var(--c2);
            font-weight: 700;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #9a7070;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 3px;
        }

        /* Seção de avisos */
        .avisos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width:800px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .avisos-grid { grid-template-columns: 1fr; }
            .modal-box { margin: 16px; padding: 24px 20px; }
        }
    </style>
</head>
<body>

<?php renderHeader('dashboard.php'); ?>

<main>
<div class="container">

    <?= exibirFlash() ?>

    <h1 class="secao-titulo">Painel de Controle</h1>
    <p class="secao-sub">Olá, <strong><?= escape($_SESSION['nome']) ?></strong> · <?= ucfirst(str_replace('_',' ', perfil())) ?></p>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">🍲</div>
            <div>
                <div class="stat-num"><?= $totalPratos ?></div>
                <div class="stat-label">Pratos cadastrados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div>
                <div class="stat-num"><?= $totalUsuarios ?></div>
                <div class="stat-label">Usuários ativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📢</div>
            <div>
                <div class="stat-num"><?= $totalNovidades ?></div>
                <div class="stat-label">Avisos ativos</div>
            </div>
        </div>
    </div>

    <!-- CALENDÁRIO -->
    <div class="calendario-wrap">
        <div class="cal-header">
            <button class="cal-nav-btn" onclick="mudarMes(-1)">&#8592;</button>
            <h2 id="cal-titulo"></h2>
            <button class="cal-nav-btn" onclick="mudarMes(1)">&#8594;</button>
        </div>
        <div class="cal-grid-cabecalho">
            <span>Dom</span><span>Seg</span><span>Ter</span>
            <span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
        </div>
        <div class="cal-grid" id="calGrid"></div>
    </div>

    <!-- AVISOS -->
    <div class="avisos-grid">

        <!-- Publicar aviso -->
        <div class="card-form" style="margin:0">
            <h2 style="font-family:var(--fonte-titulo);font-size:1.15rem;color:var(--c2);margin-bottom:18px">
                📢 Publicar aviso
            </h2>
            <form method="POST">
                <input type="hidden" name="acao" value="nova_novidade">
                <div class="form-grupo">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="info">📢 Informação</option>
                        <option value="mudanca">🔄 Mudança de cardápio</option>
                        <option value="aviso">⚠️ Aviso importante</option>
                    </select>
                </div>
                <div class="form-grupo">
                    <label>Título</label>
                    <input type="text" name="titulo" required placeholder="Ex: Mudança no cardápio de quinta">
                </div>
                <div class="form-grupo">
                    <label>Mensagem</label>
                    <textarea name="mensagem" required rows="3" placeholder="Descreva o aviso…"></textarea>
                </div>
                <button type="submit" class="btn btn-primario" style="width:auto;padding:10px 24px">
                    Publicar
                </button>
            </form>
        </div>

        <!-- Lista de avisos -->
        <div>
            <h2 style="font-family:var(--fonte-titulo);font-size:1.15rem;color:var(--c2);margin-bottom:14px">
                Avisos publicados
            </h2>
            <?php if (empty($novidades)): ?>
                <p style="color:#9a7070;font-size:.88rem">Nenhum aviso ativo.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($novidades as $nov): ?>
                        <div style="background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(56,5,14,.07);display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                            <div>
                                <div style="font-weight:600;font-size:.88rem;color:var(--c2)"><?= escape($nov['titulo']) ?></div>
                                <div style="font-size:.78rem;color:#9a7070;margin-top:3px"><?= escape(mb_substr($nov['mensagem'],0,70)) ?>… · <?= date('d/m/Y', strtotime($nov['criado_em'])) ?></div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Remover?')">
                                <input type="hidden" name="acao" value="desativar_novidade">
                                <input type="hidden" name="novidade_id" value="<?= $nov['id'] ?>">
                                <button type="submit" class="btn btn-perigo btn-sm" style="white-space:nowrap">Remover</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px">
                <a href="<?= SITE_URL ?>/cardapio.php" class="btn btn-secundario">🍲 Gerenciar pratos</a>
                <a href="<?= SITE_URL ?>/usuarios.php" class="btn btn-secundario">👥 Usuários</a>
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-secundario">🌐 Ver site</a>
            </div>
        </div>
    </div>

</div>
</main>

<?php renderFooter(); ?>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="fecharModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-titulo" id="modalTitulo">Selecionar prato</div>
        <div class="modal-sub" id="modalSub"></div>
        <select class="modal-select" id="modalSelect">
            <option value="0">— sem prato —</option>
            <?php foreach ($todosPratos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= escape($p['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="modal-acoes">
            <button class="btn btn-primario" style="flex:1" onclick="salvarDia()">Salvar</button>
            <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
// Dados do cardápio vindo do PHP (data => {pratoId, pratoNome})
const cardapioExistente = <?= json_encode($cardapioMes) ?>;

const DIAS_SEMANA_MAP = {1:'segunda',2:'terca',3:'quarta',4:'quinta',5:'sexta'};
const MESES_PT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

let anoAtual  = <?= date('Y') ?>;
let mesAtual  = <?= date('n') - 1 ?>; // 0-indexed
let diaAberto = null;

function formatarData(ano, mes, dia) {
    return `${ano}-${String(mes+1).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
}

function renderizarCalendario() {
    const titulo = document.getElementById('cal-titulo');
    titulo.textContent = `${MESES_PT[mesAtual]} ${anoAtual}`;

    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';

    const primeiroDia = new Date(anoAtual, mesAtual, 1).getDay(); // 0=dom
    const totalDias   = new Date(anoAtual, mesAtual + 1, 0).getDate();
    const hoje        = new Date();

    // Células vazias antes do primeiro dia
    for (let i = 0; i < primeiroDia; i++) {
        const cel = document.createElement('div');
        cel.className = 'cal-dia vazio';
        grid.appendChild(cel);
    }

    for (let d = 1; d <= totalDias; d++) {
        const dataStr  = formatarData(anoAtual, mesAtual, d);
        const diaSem   = new Date(anoAtual, mesAtual, d).getDay(); // 0=dom,6=sab
        const isHoje   = (d === hoje.getDate() && mesAtual === hoje.getMonth() && anoAtual === hoje.getFullYear());
        const isUtil   = diaSem >= 1 && diaSem <= 5;
        const isFds    = diaSem === 0 || diaSem === 6;

        const cel = document.createElement('div');
        cel.className = 'cal-dia' + (isFds ? ' fds' : '') + (isHoje ? ' hoje' : '') + (isUtil ? ' util' : '');

        // Número do dia
        const numEl = document.createElement('div');
        numEl.className = 'cal-dia-num';
        numEl.textContent = d;
        cel.appendChild(numEl);

        if (isUtil) {
            const prato = cardapioExistente[dataStr];
            if (prato) {
                const badge = document.createElement('span');
                badge.className = 'cal-prato-badge';
                badge.textContent = prato.prato_nome.length > 22
                    ? prato.prato_nome.substring(0, 22) + '…'
                    : prato.prato_nome;
                badge.title = prato.prato_nome;
                badge.onclick = (e) => { e.stopPropagation(); abrirModal(dataStr, diaSem, d, prato.prato_id); };
                cel.appendChild(badge);
            } else {
                const add = document.createElement('span');
                add.className = 'cal-adicionar';
                add.innerHTML = '<span style="font-size:1rem">＋</span> Adicionar prato';
                add.onclick = () => abrirModal(dataStr, diaSem, d, 0);
                cel.appendChild(add);
            }
            cel.onclick = () => abrirModal(dataStr, diaSem, d, prato ? prato.prato_id : 0);
        }

        grid.appendChild(cel);
    }
}

function mudarMes(delta) {
    mesAtual += delta;
    if (mesAtual < 0)  { mesAtual = 11; anoAtual--; }
    if (mesAtual > 11) { mesAtual = 0;  anoAtual++; }

    // Recarregar cardápio do mês via AJAX
    const inicio = formatarData(anoAtual, mesAtual, 1);
    const fim    = formatarData(anoAtual, mesAtual, new Date(anoAtual, mesAtual+1, 0).getDate());

    fetch(`<?= SITE_URL ?>/ajax_cardapio.php?inicio=${inicio}&fim=${fim}`)
        .then(r => r.json())
        .then(data => {
            Object.keys(cardapioExistente).forEach(k => delete cardapioExistente[k]);
            Object.assign(cardapioExistente, data);
            renderizarCalendario();
        })
        .catch(() => renderizarCalendario());
}

function abrirModal(dataStr, diaSem, diaNum, pratoIdAtual) {
    diaAberto = { dataStr, diaSem, diaNum };
    const nomeDia = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'][diaSem];
    document.getElementById('modalTitulo').textContent = nomeDia;
    document.getElementById('modalSub').textContent = `${String(diaNum).padStart(2,'0')}/${String(mesAtual+1).padStart(2,'0')}/${anoAtual} — escolha o prato do dia`;
    document.getElementById('modalSelect').value = pratoIdAtual || 0;
    document.getElementById('modalOverlay').classList.add('aberto');
}

function fecharModal(e) {
    if (!e || e.target === document.getElementById('modalOverlay')) {
        document.getElementById('modalOverlay').classList.remove('aberto');
        diaAberto = null;
    }
}

function salvarDia() {
    if (!diaAberto) return;
    const pratoId   = document.getElementById('modalSelect').value;
    const diaNome   = DIAS_SEMANA_MAP[diaAberto.diaSem] || '';
    const formData  = new FormData();
    formData.append('acao',       'salvar_dia_cardapio');
    formData.append('data_ref',   diaAberto.dataStr);
    formData.append('prato_id',   pratoId);
    formData.append('dia_semana', diaNome);
    formData.append('ajax',       '1');

    fetch('<?= SITE_URL ?>/dashboard.php', { method:'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const pSelecionado = document.getElementById('modalSelect');
                const pNome = pSelecionado.options[pSelecionado.selectedIndex].text;
                if (parseInt(pratoId) > 0) {
                    cardapioExistente[diaAberto.dataStr] = { prato_id: pratoId, prato_nome: pNome };
                } else {
                    delete cardapioExistente[diaAberto.dataStr];
                }
                renderizarCalendario();
                document.getElementById('modalOverlay').classList.remove('aberto');
            }
        })
        .catch(() => alert('Erro ao salvar. Tente novamente.'));
}

// ESC fecha modal
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

// Init
renderizarCalendario();

function toggleMenu() {
    document.getElementById('navMenu').classList.toggle('aberto');
}
</script>
</body>
</html>