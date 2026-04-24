<?php

require_once __DIR__ . '/config/config.php';

if (estaLogado()) {
    redirecionar(SITE_URL . (perfil() === 'aluno' ? '/index.php' : '/dashboard.php'));
}

$erro = '';
$aba  = $_POST['aba'] ?? $_GET['aba'] ?? 'login';
$pdo  = conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aba === 'login') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (!$email || !$senha) {
        $erro = 'Preencha o e-mail e a senha.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if ($u && password_verify($senha, $u['senha'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $u['id'];
            $_SESSION['nome']       = $u['nome'];
            $_SESSION['email']      = $u['email'];
            $_SESSION['perfil']     = $u['perfil'];
            flash('sucesso', 'Bem-vindo(a), ' . $u['nome'] . '!');
            redirecionar(SITE_URL . ($u['perfil'] === 'aluno' ? '/index.php' : '/dashboard.php'));
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aba === 'cadastro') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email_cad'] ?? '');
    $senha = $_POST['senha_cad'] ?? '';
    $conf  = $_POST['confirmar'] ?? '';
    if (!$nome || !$email || !$senha || !$conf) { $erro = 'Preencha todos os campos.'; $aba='cadastro'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erro = 'E-mail inválido.'; $aba='cadastro'; }
    elseif (strlen($senha) < 6) { $erro = 'Senha deve ter ao menos 6 caracteres.'; $aba='cadastro'; }
    elseif ($senha !== $conf) { $erro = 'As senhas não coincidem.'; $aba='cadastro'; }
    else {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) { $erro = 'E-mail já cadastrado.'; $aba='cadastro'; }
        else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (nome,email,senha,perfil) VALUES (?,?,?,'aluno')")
                ->execute([$nome,$email,$hash]);
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email=?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $u['id'];
            $_SESSION['nome']       = $u['nome'];
            $_SESSION['email']      = $u['email'];
            $_SESSION['perfil']     = 'aluno';
            flash('sucesso', 'Conta criada! Bem-vindo(a), ' . $u['nome'] . '!');
            redirecionar(SITE_URL . '/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Sabor Etec</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
<style>
.login-page {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 480px;
}

.login-painel-esq {
    background: linear-gradient(155deg, var(--c1) 0%, var(--c2) 50%, var(--c3) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 48px;
    position: relative;
    overflow: hidden;
}

.login-painel-esq::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    border: 60px solid rgba(255,255,255,.04);
    top: -120px; right: -120px;
}

.login-painel-esq::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    border: 40px solid rgba(255,255,255,.05);
    bottom: -80px; left: -60px;
}

.esq-logo {
    width: 130px; height: 130px;
    border-radius: 28px;
    border: 3px solid rgba(255,255,255,.2);
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    margin-bottom: 28px;
    position: relative;
    z-index: 1;
}

.esq-nome {
    font-family: var(--titulo);
    font-size: 3rem;
    color: #fff;
    font-weight: 700;
    text-align: center;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}

.esq-sub {
    font-size: .9rem;
    color: rgba(255,255,255,.6);
    text-align: center;
    letter-spacing: .04em;
    text-transform: uppercase;
    position: relative;
    z-index: 1;
}

.esq-divisor {
    width: 48px; height: 2px;
    background: rgba(255,255,255,.25);
    margin: 24px auto;
    position: relative;
    z-index: 1;
}

.esq-desc {
    text-align: center;
    color: rgba(255,255,255,.55);
    font-size: .88rem;
    line-height: 1.7;
    max-width: 320px;
    position: relative;
    z-index: 1;
}

.login-painel-dir {
    background: var(--creme);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 44px;
}

.login-form-box {
    width: 100%;
    max-width: 380px;
}

.login-form-titulo {
    font-family: var(--titulo);
    font-size: 2rem;
    color: var(--c2);
    font-weight: 700;
    margin-bottom: 4px;
}

.login-form-sub {
    font-size: .84rem;
    color: var(--texto2);
    margin-bottom: 28px;
}

.abas {
    display: flex;
    background: var(--creme2);
    border-radius: var(--r-sm);
    padding: 4px;
    margin-bottom: 28px;
    border: 1px solid var(--borda);
}

.aba-btn {
    flex: 1;
    padding: 9px;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-family: var(--corpo);
    font-size: .88rem;
    font-weight: 600;
    color: var(--texto2);
    cursor: pointer;
    transition: var(--trans);
}

.aba-btn.ativa {
    background: var(--branco);
    color: var(--c2);
    box-shadow: 0 2px 8px rgba(56,5,14,.10);
}

.aba-painel { display: none; }
.aba-painel.ativa { display: block; }

.login-rodape {
    text-align: center;
    margin-top: 20px;
    font-size: .78rem;
    color: var(--texto2);
}

.login-rodape a { color: var(--c4); font-weight: 600; }

@media (max-width: 820px) {
    .login-page { grid-template-columns: 1fr; }
    .login-painel-esq { display: none; }
    .login-painel-dir { padding: 40px 24px; min-height: 100vh; }
}
</style>
</head>
<body>
<div class="login-page">

    <div class="login-painel-esq">
        <img src="<?= SITE_URL ?>/assets/uploads/logo.png" alt="Sabor Etec" class="esq-logo">
        <div class="esq-nome">Sabor Etec</div>
        <div class="esq-sub">ETEC de Peruíbe</div>
        <div class="esq-divisor"></div>
        <div class="esq-desc">Sistema de gestão da merenda escolar. Cardápio da semana, valores nutricionais e muito mais.</div>
    </div>

    <div class="login-painel-dir">
        <div class="login-form-box">

            <div class="login-form-titulo">Bem-vindo</div>
            <div class="login-form-sub">Acesse sua conta ou crie uma nova</div>

            <?php if ($erro): ?>
                <div class="flash" style="background:#8b0000;margin-bottom:20px"><?= escape($erro) ?></div>
            <?php endif; ?>

            <div class="abas">
                <button class="aba-btn <?= $aba==='login'    ?'ativa':'' ?>" onclick="trocarAba('login',this)">Entrar</button>
                <button class="aba-btn <?= $aba==='cadastro' ?'ativa':'' ?>" onclick="trocarAba('cadastro',this)">Criar conta</button>
            </div>

            <div class="aba-painel <?= $aba==='login'?'ativa':'' ?>" id="painel-login">
                <form method="POST">
                    <input type="hidden" name="aba" value="login">
                    <div class="form-grupo">
                        <label>E-mail</label>
                        <input type="email" name="email"
                               value="<?= $aba==='login'?escape($_POST['email']??''):'' ?>"
                               placeholder="seu@email.com" autocomplete="email">
                    </div>
                    <div class="form-grupo">
                        <label>Senha</label>
                        <input type="password" name="senha" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <div style="margin-top:22px">
                        <button type="submit" class="btn btn-primario">Entrar</button>
                    </div>
                </form>
            </div>

            <div class="aba-painel <?= $aba==='cadastro'?'ativa':'' ?>" id="painel-cadastro">
                <form method="POST">
                    <input type="hidden" name="aba" value="cadastro">
                    <div class="form-grupo">
                        <label>Nome completo</label>
                        <input type="text" name="nome"
                               value="<?= $aba==='cadastro'?escape($_POST['nome']??''):'' ?>"
                               placeholder="Seu nome" autocomplete="name">
                    </div>
                    <div class="form-grupo">
                        <label>E-mail</label>
                        <input type="email" name="email_cad"
                               value="<?= $aba==='cadastro'?escape($_POST['email_cad']??''):'' ?>"
                               placeholder="seu@email.com" autocomplete="email">
                    </div>
                    <div class="form-grupo">
                        <label>Senha</label>
                        <input type="password" name="senha_cad" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                    </div>
                    <div class="form-grupo">
                        <label>Confirmar senha</label>
                        <input type="password" name="confirmar" placeholder="Repita a senha" autocomplete="new-password">
                    </div>
                    <div style="margin-top:22px">
                        <button type="submit" class="btn btn-primario">Criar minha conta</button>
                    </div>
                    <p style="font-size:.72rem;color:#b09090;margin-top:12px;text-align:center">
                        Conta criada como <strong>aluno</strong>. Supervisores são cadastrados pelo painel.
                    </p>
                </form>
            </div>

            <div class="login-rodape">
                <a href="<?= SITE_URL ?>/index.php">← Ver cardápio sem login</a>
            </div>
        </div>
    </div>
</div>

<script>
function trocarAba(aba, btn) {
    document.querySelectorAll('.aba-btn').forEach(b => b.classList.remove('ativa'));
    document.querySelectorAll('.aba-painel').forEach(p => p.classList.remove('ativa'));
    document.getElementById('painel-' + aba).classList.add('ativa');
    btn.classList.add('ativa');
}
</script>
</body>
</html>