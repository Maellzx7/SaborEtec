<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/_layout.php';

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
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
</head>
<body>

<?php renderHeader('prato.php'); ?>

<main>
<div class="container">

    <div style="margin-bottom:20px">
        <a href="<?= SITE_URL ?>/index.php" style="font-size:0.88rem;color:var(--c4)">← Voltar ao cardápio</a>
    </div>

    <div class="prato-detalhe">

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

            <?php if ($prato['ingredientes']): ?>
                <div class="detalhe-secao">
                    <h3> Ingredientes</h3>
                    <p><?= nl2br(escape($prato['ingredientes'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($prato['modo_preparo']): ?>
                <div class="detalhe-secao">
                    <h3> Modo de preparo</h3>
                    <p><?= nl2br(escape($prato['modo_preparo'])) ?></p>
                </div>
            <?php endif; ?>

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

<?php renderFooter(); ?>

<script>
function toggleMenu() {
    document.getElementById('navMenu').classList.toggle('aberto');
}
</script>
</body>
</html>