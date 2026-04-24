<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/_layout.php';

$pdo = conectar();

$hoje       = new DateTime();
$dow        = (int)$hoje->format('N');
$diasAteSeg = ($dow === 7) ? 1 : (($dow === 6) ? 2 : $dow - 1);
$inicioSem  = (clone $hoje)->modify("-{$diasAteSeg} days")->format('Y-m-d');
$fimSem     = (clone $hoje)->modify("-{$diasAteSeg} days")->modify('+4 days')->format('Y-m-d');

$stmtC = $pdo->prepare("
    SELECT cs.dia_semana, cs.data_referencia,
           p.id, p.nome, p.descricao, p.calorias, p.foto
    FROM cardapio_semana cs
    JOIN pratos p ON cs.prato_id = p.id AND p.ativo = 1
    WHERE cs.data_referencia BETWEEN :ini AND :fim
    ORDER BY FIELD(cs.dia_semana,'segunda','terca','quarta','quinta','sexta')
");
$stmtC->execute([':ini' => $inicioSem, ':fim' => $fimSem]);
$cardapio = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$novidades = $pdo->query("
    SELECT n.titulo, n.mensagem, n.tipo, n.criado_em, u.nome AS autor
    FROM novidades n JOIN usuarios u ON n.usuario_id = u.id
    WHERE n.ativo = 1 ORDER BY n.criado_em DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$inicioMes = date('Y-m-01');
$fimMes    = date('Y-m-t');
$stmtM     = $pdo->prepare("
    SELECT cs.data_referencia, p.nome AS prato_nome, p.id AS prato_id
    FROM cardapio_semana cs JOIN pratos p ON cs.prato_id = p.id AND p.ativo = 1
    WHERE cs.data_referencia BETWEEN ? AND ?
");
$stmtM->execute([$inicioMes, $fimMes]);
$cardapioMes = [];
foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cardapioMes[$r['data_referencia']] = $r;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sabor Etec — Cardápio da Semana</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
<style>
.layout-outer {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: calc(100vh - 71px - 64px);
}

.col-esq {
    background: var(--branco);
    border-right: 1px solid var(--borda);
    display: flex;
    flex-direction: column;
}

.col-esq-top {
    background: linear-gradient(135deg, var(--c1), var(--c2));
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.col-esq-top h2 {
    font-family: var(--titulo);
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    letter-spacing: .02em;
}

.col-esq-icon {
    width: 28px; height: 28px;
    background: rgba(255,255,255,.15);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.col-esq-icon svg { width: 14px; height: 14px; fill: rgba(255,255,255,.9); }

.novidades-lista { flex: 1; overflow-y: auto; }

.nov-item {
    padding: 14px 16px;
    border-bottom: 1px solid var(--creme2);
    transition: background .15s;
    cursor: default;
}
.nov-item:hover { background: var(--creme2); }

.nov-tipo {
    font-size: .64rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    margin-bottom: 4px;
    display: flex; align-items: center; gap: 4px;
}

.t-mudanca { color: var(--c3); }
.t-aviso   { color: #9c6500; }
.t-info    { color: #1a6a3a; }

.nov-titulo {
    font-family: var(--titulo);
    font-size: .95rem;
    font-weight: 600;
    color: var(--c2);
    line-height: 1.3;
    margin-bottom: 4px;
}

.nov-texto { font-size: .76rem; color: var(--texto2); line-height: 1.5; }
.nov-data  { font-size: .67rem; color: #b09090; margin-top: 6px; }

.sem-nov {
    padding: 32px 16px;
    text-align: center;
    color: #c0a0a0;
    font-size: .82rem;
}

.col-dir {
    padding: 30px 34px 44px;
    display: flex;
    flex-direction: column;
    gap: 36px;
    background: var(--creme);
}

.pratos-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.pratos-header .sec-titulo { margin-bottom: 0; }

.semana-badge {
    background: var(--branco);
    border: 1px solid var(--borda);
    border-radius: 20px;
    padding: 6px 14px;
    font-size: .78rem;
    color: var(--texto2);
    font-weight: 500;
}

.pratos-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 18px;
}

.prato-card {
    background: var(--branco);
    border-radius: var(--r-lg);
    overflow: hidden;
    border: 1px solid var(--borda);
    box-shadow: var(--sombra-s);
    transition: var(--trans);
    cursor: pointer;
    position: relative;
    group: true;
}

.prato-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--sombra-m);
    border-color: var(--c4);
}

.prato-card:hover .prato-overlay { opacity: 1; }

.prato-thumb {
    width: 100%; height: 148px;
    object-fit: cover; display: block;
}

.prato-placeholder {
    width: 100%; height: 148px;
    background: linear-gradient(135deg, var(--c2) 0%, var(--c4) 100%);
    display: flex; align-items: center; justify-content: center;
}

.prato-placeholder svg { width: 44px; height: 44px; fill: rgba(255,255,255,.25); }

.prato-body { padding: 13px 15px 16px; }

.prato-dia {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--c4);
    margin-bottom: 3px;
}

.prato-nome {
    font-family: var(--titulo);
    font-size: 1.05rem;
    color: var(--c2);
    font-weight: 600;
    line-height: 1.3;
}

.prato-kcal {
    font-size: .72rem;
    color: var(--texto2);
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.prato-kcal svg { width: 11px; height: 11px; fill: var(--c4); }

.prato-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(155deg,rgba(56,5,14,.96),rgba(129,14,19,.92));
    opacity: 0;
    transition: opacity .26s;
    display: flex; flex-direction: column;
    justify-content: center; align-items: center;
    padding: 20px; text-align: center;
}

.prato-overlay h4 {
    font-family: var(--titulo);
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 8px;
    line-height: 1.3;
}

.prato-overlay p {
    font-size: .76rem;
    color: rgba(255,255,255,.8);
    line-height: 1.6;
    margin-bottom: 14px;
}

.prato-overlay a {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    padding: 7px 18px;
    border-radius: 20px;
    font-size: .76rem;
    font-weight: 600;
    transition: var(--trans);
    text-decoration: none;
}
.prato-overlay a:hover { background: rgba(255,255,255,.28); color: #fff; }

.vazio-box {
    grid-column: 1/-1;
    background: var(--branco);
    border: 1px dashed var(--borda);
    border-radius: var(--r-lg);
    padding: 52px 24px;
    text-align: center;
    color: var(--texto2);
}

.vazio-icon {
    width: 64px; height: 64px;
    background: var(--creme2);
    border-radius: 50%;
    margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
}
.vazio-icon svg { width: 30px; height: 30px; fill: var(--c4); opacity: .5; }

.cal-aluno-wrap {
    background: var(--branco);
    border-radius: var(--r-lg);
    box-shadow: var(--sombra-s);
    overflow: hidden;
    border: 1px solid var(--borda);
}

.cal-header-aluno {
    background: linear-gradient(135deg, var(--c2), var(--c3));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 22px;
}

.cal-header-aluno h3 {
    font-family: var(--titulo);
    font-size: 1.2rem;
    font-weight: 600;
}

.cal-dias-sem {
    display: grid;
    grid-template-columns: repeat(7,1fr);
    background: var(--c1);
}

.cal-dias-sem span {
    text-align: center;
    padding: 8px 2px;
    font-size: .64rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: rgba(255,255,255,.55);
}

.cal-aluno-grid { display: grid; grid-template-columns: repeat(7,1fr); }

.cal-cel {
    min-height: 64px;
    border-right: 1px solid var(--creme2);
    border-bottom: 1px solid var(--creme2);
    padding: 6px 7px;
}
.cal-cel:nth-child(7n) { border-right: none; }
.cal-cel.vazio   { background: #fafafa; }
.cal-cel.fds     { background: var(--creme2); }
.cal-cel.hoje-d  { background: #fff5f5; }

.cal-num {
    font-size: .76rem;
    font-weight: 600;
    color: var(--texto);
    line-height: 1;
    margin-bottom: 3px;
}

.cal-cel.hoje-d .cal-num {
    background: var(--c3);
    color: #fff;
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem;
}

.cal-cel.fds .cal-num  { color: #c0a0a0; }
.cal-cel.vazio .cal-num { color: #ddd; }

.cal-tag {
    background: linear-gradient(135deg, var(--c3), var(--c4));
    color: #fff;
    font-size: .58rem;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 4px;
    line-height: 1.4;
    display: block;
    cursor: pointer;
    transition: opacity .15s;
    text-decoration: none;
}
.cal-tag:hover { opacity: .8; color: #fff; }

.tip {
    display: none;
    position: fixed;
    background: var(--c1);
    color: #fff;
    padding: 9px 13px;
    border-radius: 10px;
    font-size: .78rem;
    max-width: 200px;
    z-index: 400;
    box-shadow: var(--sombra-m);
    pointer-events: none;
    font-family: var(--titulo);
    font-size: .9rem;
}
.tip.vis { display: block; }

.relatos-cta {
    background: linear-gradient(135deg, var(--c1), var(--c2));
    border-radius: var(--r-lg);
    padding: 28px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    box-shadow: var(--sombra-m);
}

.relatos-cta-txt h3 {
    font-family: var(--titulo);
    font-size: 1.5rem;
    color: #fff;
    margin-bottom: 5px;
}
.relatos-cta-txt p { font-size: .84rem; color: rgba(255,255,255,.7); }

.btn-cta {
    background: rgba(255,255,255,.12);
    border: 1.5px solid rgba(255,255,255,.35);
    color: #fff;
    padding: 12px 26px;
    border-radius: var(--r-md);
    font-size: .9rem;
    font-weight: 600;
    font-family: var(--corpo);
    cursor: pointer;
    transition: var(--trans);
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
}
.btn-cta:hover { background: rgba(255,255,255,.22); color: #fff; }

@media (max-width: 900px) {
    .layout-outer { grid-template-columns: 1fr; }
    .col-esq { border-right: none; border-bottom: 1px solid var(--borda); max-height: 220px; overflow-y: auto; }
    .pratos-grid { grid-template-columns: repeat(2,1fr); }
    .col-dir { padding: 22px 16px 36px; }
}
@media (max-width: 540px) {
    .pratos-grid { grid-template-columns: 1fr; }
    .relatos-cta { flex-direction: column; }
}
</style>
</head>
<body>
<?php renderHeader('index.php'); ?>

<main style="padding:0">
<div class="layout-outer">

    <aside class="col-esq">
        <div class="col-esq-top">
            <div class="col-esq-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            </div>
            <h2>Novidades</h2>
        </div>

        <div class="novidades-lista">
            <?php if (empty($novidades)): ?>
                <div class="sem-nov">
                    <svg viewBox="0 0 24 24" style="width:36px;height:36px;fill:var(--borda);margin:0 auto 8px"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>
                    <p>Nenhuma novidade</p>
                </div>
            <?php else: ?>
                <?php foreach ($novidades as $n):
                    $tc = 't-' . $n['tipo'];
                    $tl = match($n['tipo']) {
                        'mudanca' => '↺ Mudança', 'aviso' => '! Aviso', default => '» Info'
                    };
                ?>
                <div class="nov-item">
                    <div class="nov-tipo <?= $tc ?>"><?= $tl ?></div>
                    <div class="nov-titulo"><?= escape($n['titulo']) ?></div>
                    <div class="nov-texto"><?= escape(mb_substr($n['mensagem'],0,90)) ?><?= mb_strlen($n['mensagem'])>90?'…':'' ?></div>
                    <div class="nov-data"><?= date('d/m/Y', strtotime($n['criado_em'])) ?> · <?= escape($n['autor']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <div class="col-dir">

        <section>
            <div class="pratos-header">
                <div>
                    <div class="sec-titulo">Pratos da Semana</div>
                    <div class="sec-sub" style="margin-bottom:0">Passe o mouse sobre o prato para ver detalhes</div>
                </div>
                <span class="semana-badge"><?= date('d/m', strtotime($inicioSem)) ?> – <?= date('d/m/Y', strtotime($fimSem)) ?></span>
            </div>

            <div class="pratos-grid">
            <?php if (empty($cardapio)): ?>
                <div class="vazio-box">
                    <div class="vazio-icon">
                        <svg viewBox="0 0 24 24"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>
                    </div>
                    <p style="font-family:var(--titulo);font-size:1.2rem;color:var(--c2);margin-bottom:6px">Cardápio não cadastrado</p>
                    <p style="font-size:.84rem">O supervisor ainda não montou o cardápio desta semana.</p>
                    <?php if (!estaLogado()): ?>
                        <a href="<?= SITE_URL ?>/login.php" class="btn btn-primario" style="display:inline-flex;margin-top:18px;max-width:220px">Entrar como supervisor</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($cardapio as $item):
                    $fotoPath = UPLOAD_DIR . ($item['foto'] ?? '');
                    $temFoto  = !empty($item['foto']) && is_file($fotoPath);
                    $fotoUrl  = UPLOAD_URL . rawurlencode($item['foto'] ?? '');
                ?>
                <article class="prato-card" onclick="location.href='<?= SITE_URL ?>/prato.php?id=<?= (int)$item['id'] ?>'">
                    <?php if ($temFoto): ?>
                        <img src="<?= $fotoUrl ?>" alt="<?= escape($item['nome']) ?>" class="prato-thumb"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="prato-placeholder" style="display:none">
                            <svg viewBox="0 0 24 24"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>
                        </div>
                    <?php else: ?>
                        <div class="prato-placeholder">
                            <svg viewBox="0 0 24 24"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>
                        </div>
                    <?php endif; ?>

                    <div class="prato-body">
                        <div class="prato-dia"><?= diaSemanaLabel($item['dia_semana']) ?></div>
                        <div class="prato-nome"><?= escape($item['nome']) ?></div>
                        <?php if ($item['calorias']): ?>
                        <div class="prato-kcal">
                            <svg viewBox="0 0 24 24"><path d="M13.5 0.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>
                            <?= (int)$item['calorias'] ?> kcal
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="prato-overlay">
                        <h4><?= escape($item['nome']) ?></h4>
                        <p><?= escape(mb_substr($item['descricao'] ?? 'Clique para ver ingredientes e valores nutricionais completos.', 0, 100)) ?>…</p>
                        <a href="<?= SITE_URL ?>/prato.php?id=<?= (int)$item['id'] ?>" onclick="event.stopPropagation()">Ver detalhes</a>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </section>

        <section>
            <div class="sec-titulo">Calendário do Cardápio</div>
            <div class="sec-sub">Veja o que está programado para cada dia — clique no prato para mais detalhes</div>

            <div class="cal-aluno-wrap">
                <div class="cal-header-aluno">
                    <button class="cal-nav-btn" id="btnAntes">&#8592;</button>
                    <h3 id="calTitulo"></h3>
                    <button class="cal-nav-btn" id="btnDepois">&#8594;</button>
                </div>
                <div class="cal-dias-sem">
                    <span>Dom</span><span>Seg</span><span>Ter</span>
                    <span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
                </div>
                <div class="cal-aluno-grid" id="calGrid"></div>
            </div>
        </section>

        <div class="relatos-cta">
            <div class="relatos-cta-txt">
                <h3>Tem algo a dizer?</h3>
                <p>Envie sua sugestão, restrição alimentar, elogio ou reclamação.<br>Sua voz melhora nossa merenda!</p>
            </div>
            <a href="<?= SITE_URL ?>/relatos.php" class="btn-cta">Enviar relato</a>
        </div>

    </div>
</div>
</main>

<div class="tip" id="tip"></div>
<?php renderFooter(); ?>

<script>
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
let cardapioMes = <?= json_encode($cardapioMes) ?>;
let anoV = <?= date('Y') ?>, mesV = <?= date('n') - 1 ?>;

function pad(n){ return String(n).padStart(2,'0'); }
function ds(a,m,d){ return `${a}-${pad(m+1)}-${pad(d)}`; }

function renderCal() {
    document.getElementById('calTitulo').textContent = `${MESES[mesV]} ${anoV}`;
    const grid  = document.getElementById('calGrid');
    grid.innerHTML = '';
    const hoje  = new Date();
    const prim  = new Date(anoV, mesV, 1).getDay();
    const total = new Date(anoV, mesV+1, 0).getDate();

    for (let i = 0; i < prim; i++) {
        const c = document.createElement('div');
        c.className = 'cal-cel vazio';
        c.innerHTML = '<div class="cal-num"></div>';
        grid.appendChild(c);
    }

    for (let d = 1; d <= total; d++) {
        const dateStr = ds(anoV, mesV, d);
        const dow     = new Date(anoV, mesV, d).getDay();
        const isHoje  = d === hoje.getDate() && mesV === hoje.getMonth() && anoV === hoje.getFullYear();
        const isFds   = dow === 0 || dow === 6;

        const cel = document.createElement('div');
        cel.className = 'cal-cel' + (isFds?' fds':'') + (isHoje?' hoje-d':'');

        const num = document.createElement('div');
        num.className = 'cal-num';
        num.textContent = d;
        cel.appendChild(num);

        if (!isFds && cardapioMes[dateStr]) {
            const nome = cardapioMes[dateStr].prato_nome;
            const pid  = cardapioMes[dateStr].prato_id;
            const tag  = document.createElement('a');
            tag.className   = 'cal-tag';
            tag.href        = `<?= SITE_URL ?>/prato.php?id=${pid}`;
            tag.textContent = nome.length > 16 ? nome.substring(0,16)+'…' : nome;
            tag.title       = nome;
            tag.addEventListener('mouseenter', e => { const t=document.getElementById('tip'); t.textContent=nome; t.classList.add('vis'); moveTip(e); });
            tag.addEventListener('mousemove',  moveTip);
            tag.addEventListener('mouseleave', () => document.getElementById('tip').classList.remove('vis'));
            cel.appendChild(tag);
        }
        grid.appendChild(cel);
    }
}

function moveTip(e) {
    const t = document.getElementById('tip');
    t.style.left = (e.clientX+12)+'px';
    t.style.top  = (e.clientY-38)+'px';
}

document.getElementById('btnAntes').onclick = () => {
    mesV--; if (mesV<0){ mesV=11; anoV--; } carregarMes();
};
document.getElementById('btnDepois').onclick = () => {
    mesV++; if (mesV>11){ mesV=0; anoV++; } carregarMes();
};

function carregarMes() {
    const ini = ds(anoV, mesV, 1);
    const fim = ds(anoV, mesV, new Date(anoV, mesV+1, 0).getDate());
    fetch(`<?= SITE_URL ?>/ajax_cardapio.php?inicio=${ini}&fim=${fim}`)
        .then(r=>r.json()).then(d=>{ cardapioMes=d; renderCal(); })
        .catch(()=>{ cardapioMes={}; renderCal(); });
}

renderCal();
</script>
</body>
</html>