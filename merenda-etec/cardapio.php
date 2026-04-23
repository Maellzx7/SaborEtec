<?php
// ============================================================
// ARQUIVO: cardapio.php (raiz do projeto)
// DESCRIÇÃO: CRUD de pratos + montagem do cardápio semanal
// Acesso: supervisor e sub_supervisor
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';
requerSupervisor();

$pdo = conectar();
$acao = $_GET['acao'] ?? 'listar';
$erro = '';
$sucesso = '';

// =====================================================
// PROCESSAR POST
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcao = $_POST['acao'] ?? '';

    // --- Salvar prato (criar ou editar) ---
    if ($postAcao === 'salvar_prato') {
        $id          = (int)($_POST['id'] ?? 0);
        $nome        = trim($_POST['nome'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $ingredientes= trim($_POST['ingredientes'] ?? '');
        $modo_preparo= trim($_POST['modo_preparo'] ?? '');
        $calorias    = (int)($_POST['calorias'] ?? 0) ?: null;
        $proteinas   = (float)($_POST['proteinas'] ?? 0) ?: null;
        $carboidratos= (float)($_POST['carboidratos'] ?? 0) ?: null;
        $gorduras    = (float)($_POST['gorduras'] ?? 0) ?: null;

        if (!$nome) {
            $erro = 'O nome do prato é obrigatório.';
        } else {
            $foto = null;
            if (!empty($_FILES['foto']['name'])) {
                $foto = uploadFoto($_FILES['foto']);
                if (!$foto) $erro = 'Formato de imagem inválido. Use JPG, PNG ou WebP.';
            }

            if (!$erro) {
                if ($id > 0) {
                    // Editar
                    $fotoSql = $foto ? ', foto = ?' : '';
                    $params  = [$nome, $descricao, $ingredientes, $modo_preparo, $calorias, $proteinas, $carboidratos, $gorduras];
                    if ($foto) $params[] = $foto;
                    $params[] = $id;
                    $pdo->prepare("UPDATE pratos SET nome=?, descricao=?, ingredientes=?, modo_preparo=?, calorias=?, proteinas=?, carboidratos=?, gorduras=?{$fotoSql} WHERE id=?")
                        ->execute($params);
                    flash('sucesso', 'Prato atualizado com sucesso!');
                } else {
                    // Criar
                    $pdo->prepare("INSERT INTO pratos (nome,descricao,ingredientes,modo_preparo,calorias,proteinas,carboidratos,gorduras,foto) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$nome,$descricao,$ingredientes,$modo_preparo,$calorias,$proteinas,$carboidratos,$gorduras,$foto]);
                    flash('sucesso', 'Prato cadastrado com sucesso!');
                }
                redirecionar(SITE_URL . '/cardapio.php');
            }
        }
    }

    // --- Excluir prato ---
    if ($postAcao === 'excluir_prato') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE pratos SET ativo=0 WHERE id=?")->execute([$id]);
        flash('sucesso', 'Prato removido.');
        redirecionar(SITE_URL . '/cardapio.php');
    }

    // --- Salvar cardápio semanal ---
    if ($postAcao === 'salvar_cardapio') {
        $semana = $_POST['semana'] ?? date('Y-m-d', strtotime('monday this week'));
        $dias   = ['segunda','terca','quarta','quinta','sexta'];
        $count  = 0;
        foreach ($dias as $dia) {
            $pratoId = (int)($_POST["dia_$dia"] ?? 0);
            $dataRef = date('Y-m-d', strtotime("$semana +$count days"));
            if ($pratoId > 0) {
                $pdo->prepare("INSERT INTO cardapio_semana (dia_semana, prato_id, data_referencia)
                               VALUES (?,?,?) ON DUPLICATE KEY UPDATE prato_id=?")
                    ->execute([$dia, $pratoId, $dataRef, $pratoId]);
            } else {
                $pdo->prepare("DELETE FROM cardapio_semana WHERE dia_semana=? AND data_referencia=?")
                    ->execute([$dia, $dataRef]);
            }
            $count++;
        }
        flash('sucesso', 'Cardápio da semana salvo!');
        redirecionar(SITE_URL . '/cardapio.php?acao=semana&semana=' . $semana);
    }
}

// =====================================================
// DADOS PARA TELAS
// =====================================================
$pratos = $pdo->query("SELECT * FROM pratos WHERE ativo=1 ORDER BY nome")->fetchAll();

// Editar prato
$pratoEdit = null;
if ($acao === 'editar' && isset($_GET['id'])) {
    $s = $pdo->prepare("SELECT * FROM pratos WHERE id=? AND ativo=1");
    $s->execute([(int)$_GET['id']]);
    $pratoEdit = $s->fetch();
    if (!$pratoEdit) redirecionar(SITE_URL . '/cardapio.php');
}

// Cardápio da semana
$semanaAtual   = $_GET['semana'] ?? date('Y-m-d', strtotime('monday this week'));
$inicioSemana  = date('Y-m-d', strtotime($semanaAtual));
$fimSemana     = date('Y-m-d', strtotime($semanaAtual . ' +4 days'));
$cardapioSemana = [];
if ($acao === 'semana' || $acao === 'listar') {
    $stmtCs = $pdo->prepare("
        SELECT cs.dia_semana, cs.prato_id, cs.data_referencia
        FROM cardapio_semana cs
        WHERE cs.data_referencia BETWEEN ? AND ?
    ");
    $stmtCs->execute([$inicioSemana, $fimSemana]);
    foreach ($stmtCs->fetchAll() as $row) {
        $cardapioSemana[$row['dia_semana']] = $row['prato_id'];
    }
}

function navUrl(string $a, string $extra = ''): string {
    return SITE_URL . '/cardapio.php?acao=' . $a . $extra;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio — <?= SITE_NOME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
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
            <li><a href="<?= SITE_URL ?>/index.php">Cardápio</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">Painel</a></li>
            <li><a href="<?= SITE_URL ?>/cardapio.php" class="ativo">Gerenciar Cardápio</a></li>
            <li><a href="<?= SITE_URL ?>/usuarios.php">Usuários</a></li>
            <li><a href="<?= SITE_URL ?>/logout.php">Sair</a></li>
            <li><span class="nav-user">👤 <?= escape($_SESSION['nome']) ?></span></li>
        </ul>
    </nav>
</header>

<main>
<div class="container">

    <?= exibirFlash() ?>
    <?php if ($erro): ?>
        <div class="flash" style="background:#8b0000"><?= escape($erro) ?></div>
    <?php endif; ?>

    <!-- Tabs de navegação -->
    <div style="margin-bottom:8px">
        <h1 class="secao-titulo">Gerenciar Cardápio</h1>
    </div>
    <div class="tabs" style="max-width:500px;margin-bottom:28px">
        <button class="tab-btn <?= in_array($acao,['listar','editar']) ? 'ativo' : '' ?>"
                onclick="location.href='<?= navUrl('listar') ?>'">
            🍲 Pratos cadastrados
        </button>
        <button class="tab-btn <?= $acao === 'novo' ? 'ativo' : '' ?>"
                onclick="location.href='<?= navUrl('novo') ?>'">
            ➕ Novo prato
        </button>
        <button class="tab-btn <?= $acao === 'semana' ? 'ativo' : '' ?>"
                onclick="location.href='<?= navUrl('semana') ?>'">
            📅 Semana
        </button>
    </div>

    <!-- ===================== LISTAR PRATOS ===================== -->
    <?php if ($acao === 'listar'): ?>

        <h2 style="font-family:var(--fonte-titulo);color:var(--c2);font-size:1.2rem;margin-bottom:16px">
            Pratos cadastrados (<?= count($pratos) ?>)
        </h2>

        <?php if (empty($pratos)): ?>
            <p style="color:#9a7070">Nenhum prato cadastrado ainda.</p>
        <?php else: ?>
            <div class="tabela-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Prato</th>
                            <th>Calorias</th>
                            <th>Proteínas</th>
                            <th>Foto</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pratos as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($p['nome']) ?></strong>
                                    <?php if ($p['descricao']): ?>
                                        <br><span style="font-size:0.8rem;color:#9a7070"><?= escape(mb_substr($p['descricao'],0,70)) ?>…</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $p['calorias'] ? $p['calorias'] . ' kcal' : '—' ?></td>
                                <td><?= $p['proteinas'] ? $p['proteinas'] . 'g' : '—' ?></td>
                                <td><?= $p['foto'] ? '✅' : '—' ?></td>
                                <td style="white-space:nowrap">
                                    <a href="<?= navUrl('editar') ?>&id=<?= $p['id'] ?>" class="btn btn-editar btn-sm">Editar</a>
                                    <a href="<?= SITE_URL ?>/prato.php?id=<?= $p['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
                                    <form method="POST" style="display:inline"
                                          onsubmit="return confirm('Excluir este prato?')">
                                        <input type="hidden" name="acao" value="excluir_prato">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-perigo btn-sm">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ===================== FORM PRATO ===================== -->
    <?php elseif ($acao === 'novo' || $acao === 'editar'): ?>

        <div class="card-form card-form-wide" style="margin:0">
            <h2 class="form-titulo" style="text-align:left;margin-bottom:22px">
                <?= $pratoEdit ? '✏️ Editar prato' : '➕ Novo prato' ?>
            </h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="salvar_prato">
                <input type="hidden" name="id" value="<?= $pratoEdit['id'] ?? 0 ?>">

                <div class="form-grupo">
                    <label>Nome do prato *</label>
                    <input type="text" name="nome" required
                           value="<?= escape($pratoEdit['nome'] ?? '') ?>"
                           placeholder="Ex: Arroz com feijão e frango grelhado">
                </div>
                <div class="form-grupo">
                    <label>Descrição curta</label>
                    <textarea name="descricao" rows="2"
                              placeholder="Breve descrição exibida no card"><?= escape($pratoEdit['descricao'] ?? '') ?></textarea>
                </div>
                <div class="form-grupo">
                    <label>Ingredientes</label>
                    <textarea name="ingredientes" rows="3"
                              placeholder="Liste os ingredientes separados por vírgula"><?= escape($pratoEdit['ingredientes'] ?? '') ?></textarea>
                </div>
                <div class="form-grupo">
                    <label>Modo de preparo</label>
                    <textarea name="modo_preparo" rows="4"
                              placeholder="Descreva o passo a passo do preparo"><?= escape($pratoEdit['modo_preparo'] ?? '') ?></textarea>
                </div>

                <p style="font-size:0.82rem;font-weight:600;color:var(--c4);text-transform:uppercase;letter-spacing:.06em;margin:20px 0 12px">
                    Valores nutricionais (por porção)
                </p>
                <div class="form-row" style="grid-template-columns:repeat(4,1fr)">
                    <div class="form-grupo">
                        <label>Calorias (kcal)</label>
                        <input type="number" name="calorias" min="0"
                               value="<?= escape($pratoEdit['calorias'] ?? '') ?>"
                               placeholder="Ex: 520">
                    </div>
                    <div class="form-grupo">
                        <label>Proteínas (g)</label>
                        <input type="number" name="proteinas" step="0.1" min="0"
                               value="<?= escape($pratoEdit['proteinas'] ?? '') ?>"
                               placeholder="Ex: 35.5">
                    </div>
                    <div class="form-grupo">
                        <label>Carboidratos (g)</label>
                        <input type="number" name="carboidratos" step="0.1" min="0"
                               value="<?= escape($pratoEdit['carboidratos'] ?? '') ?>"
                               placeholder="Ex: 68.0">
                    </div>
                    <div class="form-grupo">
                        <label>Gorduras (g)</label>
                        <input type="number" name="gorduras" step="0.1" min="0"
                               value="<?= escape($pratoEdit['gorduras'] ?? '') ?>"
                               placeholder="Ex: 8.2">
                    </div>
                </div>

                <div class="form-grupo">
                    <label>Foto do prato</label>
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($pratoEdit['foto'])): ?>
                        <small style="color:#9a7070">Foto atual: <?= escape($pratoEdit['foto']) ?> (envie nova para substituir)</small>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:12px;margin-top:10px">
                    <button type="submit" class="btn btn-primario" style="width:auto;padding:11px 28px">
                        <?= $pratoEdit ? 'Salvar alterações' : 'Cadastrar prato' ?>
                    </button>
                    <a href="<?= navUrl('listar') ?>" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </div>

    <!-- ===================== CARDÁPIO DA SEMANA ===================== -->
    <?php elseif ($acao === 'semana'): ?>

        <div class="card-form card-form-wide" style="margin:0">
            <h2 style="font-family:var(--fonte-titulo);color:var(--c2);font-size:1.2rem;margin-bottom:6px">
                📅 Montar cardápio da semana
            </h2>

            <!-- Navegação de semanas -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:22px">
                <?php
                $semanaAnterior = date('Y-m-d', strtotime($inicioSemana . ' -7 days'));
                $proximaSemana  = date('Y-m-d', strtotime($inicioSemana . ' +7 days'));
                ?>
                <a href="<?= navUrl('semana') ?>&semana=<?= $semanaAnterior ?>" class="btn btn-secundario btn-sm">← Semana anterior</a>
                <span style="font-size:0.88rem;color:#9a7070">
                    <?= date('d/m', strtotime($inicioSemana)) ?> a <?= date('d/m/Y', strtotime($fimSemana)) ?>
                </span>
                <a href="<?= navUrl('semana') ?>&semana=<?= $proximaSemana ?>" class="btn btn-secundario btn-sm">Próxima semana →</a>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="salvar_cardapio">
                <input type="hidden" name="semana" value="<?= escape($inicioSemana) ?>">

                <?php
                $dias = ['segunda','terca','quarta','quinta','sexta'];
                foreach ($dias as $i => $dia):
                    $dataRef = date('d/m', strtotime($inicioSemana . " +$i days"));
                    $pratoAtual = $cardapioSemana[$dia] ?? 0;
                ?>
                    <div class="form-grupo" style="display:flex;align-items:center;gap:14px">
                        <label style="min-width:160px;margin:0">
                            <?= diaSemanaLabel($dia) ?><br>
                            <span style="font-size:0.78rem;font-weight:400;color:#9a7070"><?= $dataRef ?></span>
                        </label>
                        <select name="dia_<?= $dia ?>" style="flex:1">
                            <option value="0">— sem prato —</option>
                            <?php foreach ($pratos as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $pratoAtual == $p['id'] ? 'selected' : '' ?>>
                                    <?= escape($p['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top:18px">
                    <button type="submit" class="btn btn-primario" style="width:auto;padding:11px 28px">
                        Salvar cardápio da semana
                    </button>
                </div>
            </form>
        </div>

    <?php endif; ?>

</div>
</main>

<footer>
    <strong><?= SITE_NOME ?></strong> · ETEC de Peruíbe · Sistema de Gestão da Merenda Escolar
</footer>

<script>
function toggleMenu() {
    document.getElementById('navMenu').classList.toggle('aberto');
}
</script>
</body>
</html>