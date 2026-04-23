<?php
// ============================================================
// ARQUIVO: prato.php (raiz do projeto)
// DESCRIÇÃO: Detalhe público de um prato — ingredientes,
//            valores nutricionais e modo de preparo
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirecionar(SITE_URL . '/index.php');

$pdo  = conectar();
$stmt = $pdo->prepare("SELECT * FROM pratos WHERE id=? AND ativo=1");
$stmt->execute([$id]);
$prato = $stmt->fetch();

if (!$prato) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;padding:40px">Prato não encontrado. <a href="' . SITE_URL . '/index.php">← Voltar</a></p>');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($prato['nome']) ?> — <?= SITE_NOME ?></title>
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

<main>
<div class="container">

    <div style="margin-bottom:20px">
        <a href="<?= SITE_URL ?>/index.php" style="font-size:0.88rem;color:var(--c4)">← Voltar ao cardápio</a>
    </div>

    <div class="prato-detalhe">

        <!-- Foto -->
        <?php if ($prato['foto'] && file_exists(UPLOAD_DIR . $prato['foto'])): ?>
            <img src="<?= UPLOAD_URL . escape($prato['foto']) ?>"
                 alt="<?= escape($prato['nome']) ?>" class="prato-detalhe-foto">
        <?php else: ?>
            <div class="prato-detalhe-foto-placeholder">🍲</div>
        <?php endif; ?>

        <div class="prato-detalhe-body">

            <h1><?= escape($prato['nome']) ?></h1>

            <?php if ($prato['descricao']): ?>
                <p style="color:#7a5050;font-size:0.95rem;margin-top:6px"><?= escape($prato['descricao']) ?></p>
            <?php endif; ?>

            <!-- Valores nutricionais -->
            <?php if ($prato['calorias'] || $prato['proteinas'] || $prato['carboidratos'] || $prato['gorduras']): ?>
                <div class="nutri-grid">
                    <?php if ($prato['calorias']): ?>
                        <div class="nutri-item">
                            <div class="nutri-valor"><?= $prato['calorias'] ?></div>
                            <div class="nutri-label">Calorias (kcal)</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($prato['proteinas']): ?>
                        <div class="nutri-item">
                            <div class="nutri-valor"><?= number_format($prato['proteinas'],1) ?>g</div>
                            <div class="nutri-label">Proteínas</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($prato['carboidratos']): ?>
                        <div class="nutri-item">
                            <div class="nutri-valor"><?= number_format($prato['carboidratos'],1) ?>g</div>
                            <div class="nutri-label">Carboidratos</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($prato['gorduras']): ?>
                        <div class="nutri-item">
                            <div class="nutri-valor"><?= number_format($prato['gorduras'],1) ?>g</div>
                            <div class="nutri-label">Gorduras</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Ingredientes -->
            <?php if ($prato['ingredientes']): ?>
                <div class="detalhe-secao">
                    <h3>🥕 Ingredientes</h3>
                    <p><?= nl2br(escape($prato['ingredientes'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Modo de preparo -->
            <?php if ($prato['modo_preparo']): ?>
                <div class="detalhe-secao">
                    <h3>👨‍🍳 Modo de preparo</h3>
                    <p><?= nl2br(escape($prato['modo_preparo'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Botões de ação (supervisor) -->
            <?php if (estaLogado() && perfil() !== 'aluno'): ?>
                <div style="margin-top:28px;padding-top:22px;border-top:2px solid var(--cinza-claro);display:flex;gap:12px">
                    <a href="<?= SITE_URL ?>/cardapio.php?acao=editar&id=<?= $prato['id'] ?>"
                       class="btn btn-editar">✏️ Editar este prato</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

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