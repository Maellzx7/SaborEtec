<?php
// ============================================================
// ARQUIVO: index.php (raiz do projeto)
// DESCRIÇÃO: Página pública — novidades à esquerda,
//            pratos da semana à direita + calendário do aluno
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';

$pdo = conectar();

// Semana atual: segunda a sexta
$hoje         = new DateTime();
$diaSemana    = (int)$hoje->format('N'); // 1=seg ... 7=dom
$diasAteSeg   = $diaSemana === 7 ? 1 : ($diaSemana === 6 ? 2 : $diaSemana - 1);
$inicioSemana = (clone $hoje)->modify("-{$diasAteSeg} days")->format('Y-m-d');
$fimSemana    = (clone $hoje)->modify("-{$diasAteSeg} days")->modify('+4 days')->format('Y-m-d');

// Busca todos os pratos do cardápio desta semana
$stmtCardapio = $pdo->prepare("
    SELECT cs.dia_semana, cs.data_referencia,
           p.id, p.nome, p.descricao, p.calorias, p.foto
    FROM cardapio_semana cs
    JOIN pratos p ON cs.prato_id = p.id AND p.ativo = 1
    WHERE cs.data_referencia BETWEEN :ini AND :fim
    ORDER BY FIELD(cs.dia_semana,'segunda','terca','quarta','quinta','sexta')
");
$stmtCardapio->execute([':ini' => $inicioSemana, ':fim' => $fimSemana]);
$cardapio = $stmtCardapio->fetchAll(PDO::FETCH_ASSOC);

// Novidades ativas
$novidades = $pdo->query("
    SELECT n.titulo, n.mensagem, n.tipo, n.criado_em, u.nome AS autor
    FROM novidades n
    JOIN usuarios u ON n.usuario_id = u.id
    WHERE n.ativo = 1
    ORDER BY n.criado_em DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Cardápio do mês para o calendário do aluno
$inicioMes = date('Y-m-01');
$fimMes    = date('Y-m-t');
$stmtMes   = $pdo->prepare("
    SELECT cs.data_referencia, p.nome AS prato_nome, p.id AS prato_id
    FROM cardapio_semana cs
    JOIN pratos p ON cs.prato_id = p.id AND p.ativo = 1
    WHERE cs.data_referencia BETWEEN ? AND ?
");
$stmtMes->execute([$inicioMes, $fimMes]);
$cardapioMes = [];
foreach ($stmtMes->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cardapioMes[$row['data_referencia']] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NOME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
    <style>
        body { background: var(--cinza-claro); }

        /* ── Layout principal (wireframe) ── */
        .layout-outer {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: calc(100vh - 64px - 56px);
        }

        /* ── Coluna esquerda: Novidades ── */
        .col-esq {
            background: #fff;
            border-right: 2px solid var(--cinza-medio);
            display: flex;
            flex-direction: column;
        }

        .col-esq-header {
            background: var(--c1);
            color: #fff;
            padding: 13px 16px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .nov-item {
            padding: 13px 15px;
            border-bottom: 1px solid var(--cinza-claro);
            transition: background .15s;
        }
        .nov-item:hover { background: #fdf3f3; }

        .nov-tipo {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            margin-bottom: 3px;
        }
        .t-mudanca { color: var(--c3); }
        .t-aviso   { color: #a06000; }
        .t-info    { color: #1a6a3a; }

        .nov-titulo {
            font-size: .82rem;
            font-weight: 600;
            color: var(--c2);
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .nov-texto {
            font-size: .75rem;
            color: #7a5050;
            line-height: 1.5;
        }

        .nov-data {
            font-size: .68rem;
            color: #b09090;
            margin-top: 5px;
        }

        .sem-nov {
            padding: 28px 14px;
            text-align: center;
            color: #c0a0a0;
            font-size: .8rem;
        }

        /* ── Coluna direita ── */
        .col-dir {
            padding: 28px 32px 40px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* ── Título da seção ── */
        .sec-titulo {
            font-family: var(--fonte-titulo);
            font-size: 1.4rem;
            color: var(--c2);
            font-weight: 700;
            margin-bottom: 3px;
        }
        .sec-sub {
            font-size: .8rem;
            color: #9a7070;
            margin-bottom: 18px;
        }

        /* ── Grid 3 colunas ── */
        .pratos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        /* ── Card do prato ── */
        .prato-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(56,5,14,.09);
            overflow: hidden;
            border: 1.5px solid transparent;
            transition: transform .22s, box-shadow .22s, border-color .22s;
            cursor: pointer;
            position: relative;
        }
        .prato-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(56,5,14,.18);
            border-color: var(--c4);
        }
        .prato-card:hover .prato-overlay { opacity: 1; }

        .prato-thumb {
            width: 100%;
            height: 140px;
            object-fit: cover;
            display: block;
        }
        .prato-placeholder {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, var(--c2), var(--c4));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            color: rgba(255,255,255,.3);
        }

        .prato-body {
            padding: 12px 14px 14px;
        }
        .prato-dia {
            font-size: .66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--c4);
            margin-bottom: 2px;
        }
        .prato-nome {
            font-family: var(--fonte-titulo);
            font-size: .95rem;
            color: var(--c2);
            font-weight: 600;
            line-height: 1.3;
        }
        .prato-kcal {
            font-size: .72rem;
            color: #9a7070;
            margin-top: 5px;
        }

        /* hover overlay */
        .prato-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(155deg,rgba(56,5,14,.95),rgba(128,14,19,.9));
            opacity: 0;
            transition: opacity .24s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 18px;
            text-align: center;
        }
        .prato-overlay h4 {
            font-family: var(--fonte-titulo);
            font-size: .95rem;
            color: #fff;
            margin-bottom: 7px;
            line-height: 1.3;
        }
        .prato-overlay p {
            font-size: .74rem;
            color: rgba(255,255,255,.82);
            line-height: 1.55;
            margin-bottom: 12px;
        }
        .prato-overlay a {
            background: var(--c4);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: .76rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }
        .prato-overlay a:hover { background: #fff; color: var(--c2); }

        /* ── Vazio ── */
        .vazio-box {
            grid-column: 1 / -1;
            background: #fff;
            border-radius: 14px;
            padding: 48px 24px;
            text-align: center;
            color: #9a7070;
            box-shadow: 0 2px 8px rgba(56,5,14,.07);
        }

        /* ── Calendário do aluno ── */
        .cal-aluno-wrap {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(56,5,14,.08);
            overflow: hidden;
        }

        .cal-aluno-header {
            background: var(--c2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
        }

        .cal-aluno-header h3 {
            font-family: var(--fonte-titulo);
            font-size: 1rem;
            font-weight: 600;
        }

        .cal-nav {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff;
            width: 30px; height: 30px;
            border-radius: 7px;
            cursor: pointer;
            font-size: .95rem;
            transition: background .15s;
            display: flex; align-items: center; justify-content: center;
        }
        .cal-nav:hover { background: rgba(255,255,255,.28); }

        .cal-dias-sem {
            display: grid;
            grid-template-columns: repeat(7,1fr);
            background: var(--c1);
        }
        .cal-dias-sem span {
            text-align: center;
            padding: 7px 2px;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: rgba(255,255,255,.65);
        }

        .cal-aluno-grid {
            display: grid;
            grid-template-columns: repeat(7,1fr);
        }

        .cal-cel {
            min-height: 68px;
            border-right: 1px solid var(--cinza-claro);
            border-bottom: 1px solid var(--cinza-claro);
            padding: 6px 7px;
        }
        .cal-cel:nth-child(7n) { border-right: none; }
        .cal-cel.vazio   { background: #fafafa; }
        .cal-cel.fds     { background: #f9f4f4; }
        .cal-cel.hoje-d  { background: #fff8f8; }

        .cal-num {
            font-size: .78rem;
            font-weight: 600;
            color: var(--cinza-texto);
            line-height: 1;
            margin-bottom: 4px;
        }
        .cal-cel.hoje-d .cal-num {
            background: var(--c3);
            color: #fff;
            width: 22px; height: 22px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .72rem;
        }
        .cal-cel.fds .cal-num  { color: #cca0a0; }
        .cal-cel.vazio .cal-num { color: #ddd; }

        .cal-prato-tag {
            background: var(--c4);
            color: #fff;
            font-size: .6rem;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 4px;
            line-height: 1.35;
            display: block;
            cursor: pointer;
            transition: background .15s;
        }
        .cal-prato-tag:hover { background: var(--c2); }

        /* ── Tooltip popup ── */
        .tooltip-pop {
            display: none;
            position: fixed;
            background: var(--c1);
            color: #fff;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: .8rem;
            max-width: 220px;
            z-index: 300;
            box-shadow: 0 8px 24px rgba(0,0,0,.3);
            pointer-events: none;
        }
        .tooltip-pop.vis { display: block; }

        @media (max-width: 860px) {
            .layout-outer { grid-template-columns: 1fr; }
            .col-esq { border-right: none; border-bottom: 2px solid var(--cinza-medio); max-height: 240px; overflow-y: auto; }
            .pratos-grid { grid-template-columns: repeat(2,1fr); }
            .col-dir { padding: 20px 16px 32px; }
        }
        @media (max-width: 540px) {
            .pratos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header>
    <nav class="navbar">
        <a href="<?= SITE_URL ?>/index.php" class="logo">
            <div class="logo-icon">🍽️</div>
            <?= SITE_NOME ?>
        </a>
        <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <ul class="nav-links" id="navMenu">
            <li><a href="<?= SITE_URL ?>/index.php" class="ativo">Cardápio</a></li>
            <?php if (estaLogado()): ?>
                <?php if (perfil() !== 'aluno'): ?>
                    <li><a href="<?= SITE_URL ?>/dashboard.php">Painel</a></li>
                    <li><a href="<?= SITE_URL ?>/cardapio.php">Gerenciar Cardápio</a></li>
                    <li><a href="<?= SITE_URL ?>/usuarios.php">Usuários</a></li>
                <?php endif; ?>
                <li><a href="<?= SITE_URL ?>/logout.php">Sair</a></li>
                <li><span class="nav-user">👤 <?= escape($_SESSION['nome']) ?></span></li>
            <?php else: ?>
                <li><a href="<?= SITE_URL ?>/login.php">Entrar</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main style="padding:0">
<div class="layout-outer">

    <!-- ══ ESQUERDA: NOVIDADES ══ -->
    <aside class="col-esq">
        <div class="col-esq-header">📢 Novidades</div>

        <?php if (empty($novidades)): ?>
            <div class="sem-nov">
                <div style="font-size:1.6rem;margin-bottom:6px">📋</div>
                Nenhuma novidade
            </div>
        <?php else: ?>
            <?php foreach ($novidades as $n): ?>
                <?php
                $tc = 't-' . $n['tipo'];
                $tl = match($n['tipo']) {
                    'mudanca' => '🔄 Mudança', 'aviso' => '⚠️ Aviso', default => '📢 Info'
                };
                ?>
                <div class="nov-item">
                    <div class="nov-tipo <?= $tc ?>"><?= $tl ?></div>
                    <div class="nov-titulo"><?= escape($n['titulo']) ?></div>
                    <div class="nov-texto"><?= escape(mb_substr($n['mensagem'],0,95)) ?><?= mb_strlen($n['mensagem'])>95?'…':'' ?></div>
                    <div class="nov-data"><?= date('d/m/Y', strtotime($n['criado_em'])) ?> · <?= escape($n['autor']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>

    <!-- ══ DIREITA: PRATOS + CALENDÁRIO ══ -->
    <div class="col-dir">

        <!-- Pratos da semana -->
        <section>
            <div class="sec-titulo">Pratos da Semana</div>
            <div class="sec-sub">
                <?= date('d/m', strtotime($inicioSemana)) ?> a <?= date('d/m/Y', strtotime($fimSemana)) ?>
                · Passe o mouse para ver detalhes
            </div>

            <div class="pratos-grid">
                <?php if (empty($cardapio)): ?>
                    <div class="vazio-box">
                        <div style="font-size:2.5rem;margin-bottom:12px">🍽️</div>
                        <p style="font-size:.95rem;font-weight:600;color:var(--c2);margin-bottom:6px">
                            Cardápio não cadastrado para esta semana
                        </p>
                        <p style="font-size:.84rem">O supervisor ainda não montou o cardápio.</p>
                        <?php if (!estaLogado()): ?>
                            <a href="<?= SITE_URL ?>/login.php" class="btn btn-primario"
                               style="display:inline-flex;margin-top:16px;max-width:240px">
                                Entrar como supervisor
                            </a>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primario"
                               style="display:inline-flex;margin-top:16px;max-width:240px">
                                Ir ao painel
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($cardapio as $item): ?>
                        <article class="prato-card"
                                 onclick="location.href='<?= SITE_URL ?>/prato.php?id=<?= (int)$item['id'] ?>'">

                            <?php
                            // Monta caminho real da imagem para verificar existência
                            $fotoNome = $item['foto'] ?? '';
                            $fotoPath = UPLOAD_DIR . $fotoNome;
                            $fotoUrl  = UPLOAD_URL . rawurlencode($fotoNome);
                            $temFoto  = $fotoNome && is_file($fotoPath);
                            ?>

                            <?php if ($temFoto): ?>
                                <img src="<?= $fotoUrl ?>"
                                     alt="<?= escape($item['nome']) ?>"
                                     class="prato-thumb"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="prato-placeholder" style="display:none">🍲</div>
                            <?php else: ?>
                                <div class="prato-placeholder">🍲</div>
                            <?php endif; ?>

                            <div class="prato-body">
                                <div class="prato-dia"><?= diaSemanaLabel($item['dia_semana']) ?></div>
                                <div class="prato-nome"><?= escape($item['nome']) ?></div>
                                <?php if ($item['calorias']): ?>
                                    <div class="prato-kcal">🔥 <?= (int)$item['calorias'] ?> kcal</div>
                                <?php endif; ?>
                            </div>

                            <div class="prato-overlay">
                                <h4><?= escape($item['nome']) ?></h4>
                                <p><?= escape(mb_substr($item['descricao'] ?? 'Clique para ver ingredientes e valores nutricionais.', 0, 100)) ?>…</p>
                                <a href="<?= SITE_URL ?>/prato.php?id=<?= (int)$item['id'] ?>"
                                   onclick="event.stopPropagation()">Ver detalhes →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Calendário informativo do aluno -->
        <section>
            <div class="sec-titulo">Calendário do Cardápio</div>
            <div class="sec-sub">Veja o que está programado para cada dia do mês</div>

            <div class="cal-aluno-wrap">
                <div class="cal-aluno-header">
                    <button class="cal-nav" id="btnAntes">&#8592;</button>
                    <h3 id="calTitulo"></h3>
                    <button class="cal-nav" id="btnDepois">&#8594;</button>
                </div>
                <div class="cal-dias-sem">
                    <span>Dom</span><span>Seg</span><span>Ter</span>
                    <span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
                </div>
                <div class="cal-aluno-grid" id="calGrid"></div>
            </div>
        </section>

    </div>
</div>
</main>

<footer>
    <strong><?= SITE_NOME ?></strong> · ETEC de Peruíbe · Sistema de Gestão da Merenda Escolar
</footer>

<!-- Tooltip flutuante -->
<div class="tooltip-pop" id="tooltip"></div>

<script>
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// Cardápio do mês atual vindo do PHP
let cardapioAtual = <?= json_encode($cardapioMes) ?>;

let anoVis = <?= date('Y') ?>;
let mesVis = <?= date('n') - 1 ?>; // 0-indexed

function pad(n){ return String(n).padStart(2,'0'); }
function dataStr(a,m,d){ return `${a}-${pad(m+1)}-${pad(d)}`; }

function renderCal() {
    document.getElementById('calTitulo').textContent = `${MESES[mesVis]} ${anoVis}`;
    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';

    const hoje      = new Date();
    const primDia   = new Date(anoVis, mesVis, 1).getDay(); // 0=dom
    const totalDias = new Date(anoVis, mesVis + 1, 0).getDate();

    // Células vazias
    for (let i = 0; i < primDia; i++) {
        const c = document.createElement('div');
        c.className = 'cal-cel vazio';
        c.innerHTML = '<div class="cal-num"></div>';
        grid.appendChild(c);
    }

    for (let d = 1; d <= totalDias; d++) {
        const ds     = dataStr(anoVis, mesVis, d);
        const dow    = new Date(anoVis, mesVis, d).getDay();
        const isHoje = d === hoje.getDate() && mesVis === hoje.getMonth() && anoVis === hoje.getFullYear();
        const isFds  = dow === 0 || dow === 6;

        const cel = document.createElement('div');
        cel.className = 'cal-cel' + (isFds?' fds':'') + (isHoje?' hoje-d':'');

        const num = document.createElement('div');
        num.className = 'cal-num';
        num.textContent = d;
        cel.appendChild(num);

        // Se tem prato cadastrado para este dia
        if (cardapioAtual[ds]) {
            const nome  = cardapioAtual[ds].prato_nome;
            const pid   = cardapioAtual[ds].prato_id;
            const tag   = document.createElement('a');
            tag.className = 'cal-prato-tag';
            tag.href      = `<?= SITE_URL ?>/prato.php?id=${pid}`;
            tag.textContent = nome.length > 18 ? nome.substring(0,18)+'…' : nome;
            tag.title       = nome;
            // Tooltip
            tag.addEventListener('mouseenter', e => mostrarTooltip(e, nome));
            tag.addEventListener('mousemove',  e => moverTooltip(e));
            tag.addEventListener('mouseleave', esconderTooltip);
            cel.appendChild(tag);
        }

        grid.appendChild(cel);
    }
}

function mostrarTooltip(e, texto) {
    const t = document.getElementById('tooltip');
    t.textContent = texto;
    t.classList.add('vis');
    moverTooltip(e);
}
function moverTooltip(e) {
    const t = document.getElementById('tooltip');
    t.style.left = (e.clientX + 12) + 'px';
    t.style.top  = (e.clientY - 36) + 'px';
}
function esconderTooltip() {
    document.getElementById('tooltip').classList.remove('vis');
}

// Navegação entre meses
document.getElementById('btnAntes').onclick = () => {
    mesVis--;
    if (mesVis < 0) { mesVis = 11; anoVis--; }
    carregarMes();
};
document.getElementById('btnDepois').onclick = () => {
    mesVis++;
    if (mesVis > 11) { mesVis = 0; anoVis++; }
    carregarMes();
};

function carregarMes() {
    const ini = dataStr(anoVis, mesVis, 1);
    const tot = new Date(anoVis, mesVis + 1, 0).getDate();
    const fim = dataStr(anoVis, mesVis, tot);

    fetch(`<?= SITE_URL ?>/ajax_cardapio.php?inicio=${ini}&fim=${fim}`)
        .then(r => r.json())
        .then(data => { cardapioAtual = data; renderCal(); })
        .catch(() => { cardapioAtual = {}; renderCal(); });
}

renderCal();

function toggleMenu() {
    document.getElementById('navMenu').classList.toggle('aberto');
}
</script>
</body>
</html>
