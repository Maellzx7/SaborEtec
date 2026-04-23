<?php
// ============================================================
// ARQUIVO: login.php (raiz do projeto)
// DESCRIÇÃO: Autenticação de alunos e supervisores
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';

// Se já está logado, redireciona
if (estaLogado()) {
    redirecionar(SITE_URL . (perfil() === 'aluno' ? '/index.php' : '/dashboard.php'));
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha o e-mail e a senha.';
    } else {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nome']       = $usuario['nome'];
            $_SESSION['email']      = $usuario['email'];
            $_SESSION['perfil']     = $usuario['perfil'];
            flash('sucesso', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
            redirecionar(SITE_URL . ($usuario['perfil'] === 'aluno' ? '/index.php' : '/dashboard.php'));
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SITE_NOME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <div class="icone">🍽️</div>
            <h1><?= SITE_NOME ?></h1>
            <p>Sistema de Gestão da Merenda Escolar</p>
        </div>

        <?php if ($erro): ?>
            <div class="flash" style="background:#8b0000"><?= escape($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grupo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= escape($_POST['email'] ?? '') ?>"
                       placeholder="seu@email.com" required autofocus>
            </div>
            <div class="form-grupo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha"
                       placeholder="••••••••" required>
            </div>
            <div style="margin-top:24px">
                <button type="submit" class="btn btn-primario">Entrar no sistema</button>
            </div>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:0.83rem;color:#9a7070">
            <a href="<?= SITE_URL ?>/index.php">← Ver cardápio sem login</a>
        </p>
    </div>
</div>
</body>
</html>