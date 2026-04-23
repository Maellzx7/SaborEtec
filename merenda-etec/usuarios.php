<?php
// ============================================================
// ARQUIVO: usuarios.php (raiz do projeto)
// DESCRIÇÃO: CRUD de usuários — alunos, supervisores e sub-supervisores
// Adicionar sub-supervisor: supervisor e sub_supervisor podem fazer
// Gerenciar todos os usuários: apenas supervisor pleno
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';
requerSupervisor();

$pdo  = conectar();
$acao = $_GET['acao'] ?? 'listar';
$erro = '';

// =====================================================
// PROCESSAR POST
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcao = $_POST['acao'] ?? '';

    // --- Criar usuário ---
    if ($postAcao === 'criar_usuario') {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'aluno';

        // Sub-supervisor só pode criar aluno ou sub_supervisor
        if (perfil() === 'sub_supervisor' && !in_array($perfil, ['aluno','sub_supervisor'])) {
            $erro = 'Você não tem permissão para criar esse tipo de usuário.';
        } elseif (!$nome || !$email || !$senha) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            // Verificar email único
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email=?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $erro = 'Este e-mail já está cadastrado.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nome,email,senha,perfil) VALUES (?,?,?,?)")
                    ->execute([$nome, $email, $hash, $perfil]);
                flash('sucesso', 'Usuário criado com sucesso!');
                redirecionar(SITE_URL . '/usuarios.php');
            }
        }
    }

    // --- Editar usuário ---
    if ($postAcao === 'editar_usuario') {
        requerSupervisorPleno(); // apenas supervisor pleno edita
        $id     = (int)($_POST['id'] ?? 0);
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $perfil = $_POST['perfil'] ?? 'aluno';
        $ativo  = isset($_POST['ativo']) ? 1 : 0;
        $novaSenha = $_POST['nova_senha'] ?? '';

        if (!$nome || !$email) {
            $erro = 'Nome e e-mail são obrigatórios.';
        } else {
            if ($novaSenha) {
                if (strlen($novaSenha) < 6) {
                    $erro = 'Nova senha deve ter ao menos 6 caracteres.';
                } else {
                    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nome=?,email=?,senha=?,perfil=?,ativo=? WHERE id=?")
                        ->execute([$nome,$email,$hash,$perfil,$ativo,$id]);
                }
            } else {
                $pdo->prepare("UPDATE usuarios SET nome=?,email=?,perfil=?,ativo=? WHERE id=?")
                    ->execute([$nome,$email,$perfil,$ativo,$id]);
            }
            if (!$erro) {
                flash('sucesso', 'Usuário atualizado!');
                redirecionar(SITE_URL . '/usuarios.php');
            }
        }
    }

    // --- Excluir usuário ---
    if ($postAcao === 'excluir_usuario') {
        requerSupervisorPleno();
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $_SESSION['usuario_id']) {
            flash('erro', 'Você não pode excluir a si mesmo.');
        } else {
            $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=?")->execute([$id]);
            flash('sucesso', 'Usuário desativado.');
        }
        redirecionar(SITE_URL . '/usuarios.php');
    }
}

// =====================================================
// DADOS PARA TELAS
// =====================================================
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY perfil, nome")->fetchAll();

$usuarioEdit = null;
if ($acao === 'editar' && isset($_GET['id'])) {
    requerSupervisorPleno();
    $s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $usuarioEdit = $s->fetch();
    if (!$usuarioEdit) redirecionar(SITE_URL . '/usuarios.php');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários — <?= SITE_NOME ?></title>
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
            <li><a href="<?= SITE_URL ?>/cardapio.php">Gerenciar Cardápio</a></li>
            <li><a href="<?= SITE_URL ?>/usuarios.php" class="ativo">Usuários</a></li>
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

    <h1 class="secao-titulo">Gerenciar Usuários</h1>

    <!-- Tabs -->
    <div class="tabs" style="max-width:420px;margin-bottom:28px">
        <button class="tab-btn <?= in_array($acao,['listar','editar']) ? 'ativo' : '' ?>"
                onclick="location.href='<?= SITE_URL ?>/usuarios.php'">
            👥 Todos os usuários
        </button>
        <button class="tab-btn <?= $acao === 'novo' ? 'ativo' : '' ?>"
                onclick="location.href='<?= SITE_URL ?>/usuarios.php?acao=novo'">
            ➕ Adicionar usuário
        </button>
    </div>

    <!-- ===================== LISTAR ===================== -->
    <?php if ($acao === 'listar'): ?>

        <div class="tabela-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <?php if (perfil() === 'supervisor'): ?>
                            <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td>
                                <?= escape($u['nome']) ?>
                                <?php if ($u['id'] === $_SESSION['usuario_id']): ?>
                                    <span style="font-size:0.75rem;color:var(--c4)">(você)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= escape($u['email']) ?></td>
                            <td>
                                <?php
                                $badges = [
                                    'supervisor'     => '<span class="badge badge-supervisor">Supervisor</span>',
                                    'sub_supervisor' => '<span class="badge badge-sub">Sub-supervisor</span>',
                                    'aluno'          => '<span class="badge badge-aluno">Aluno</span>',
                                ];
                                echo $badges[$u['perfil']] ?? escape($u['perfil']);
                                ?>
                            </td>
                            <td>
                                <?php if ($u['ativo']): ?>
                                    <span class="badge badge-ativo">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inativo">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <?php if (perfil() === 'supervisor'): ?>
                                <td style="white-space:nowrap">
                                    <a href="<?= SITE_URL ?>/usuarios.php?acao=editar&id=<?= $u['id'] ?>"
                                       class="btn btn-editar btn-sm">Editar</a>
                                    <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                                        <form method="POST" style="display:inline"
                                              onsubmit="return confirm('Desativar este usuário?')">
                                            <input type="hidden" name="acao" value="excluir_usuario">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-perigo btn-sm">Desativar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <!-- ===================== CRIAR ===================== -->
    <?php elseif ($acao === 'novo'): ?>

        <div class="card-form" style="margin:0">
            <h2 class="form-titulo" style="text-align:left;margin-bottom:22px">➕ Adicionar usuário</h2>

            <?php if (perfil() === 'sub_supervisor'): ?>
                <div class="flash" style="background:#4a3900;margin-bottom:20px">
                    ℹ️ Como sub-supervisor, você pode adicionar alunos e outros sub-supervisores.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="criar_usuario">
                <div class="form-grupo">
                    <label>Nome completo *</label>
                    <input type="text" name="nome" required placeholder="Ex: Maria Silva">
                </div>
                <div class="form-grupo">
                    <label>E-mail *</label>
                    <input type="email" name="email" required placeholder="usuario@etec.sp.gov.br">
                </div>
                <div class="form-grupo">
                    <label>Senha *</label>
                    <input type="password" name="senha" required placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                <div class="form-grupo">
                    <label>Perfil *</label>
                    <select name="perfil">
                        <option value="aluno">Aluno</option>
                        <option value="sub_supervisor">Sub-supervisor</option>
                        <?php if (perfil() === 'supervisor'): ?>
                            <option value="supervisor">Supervisor</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div style="display:flex;gap:12px;margin-top:12px">
                    <button type="submit" class="btn btn-primario" style="width:auto;padding:11px 28px">
                        Criar usuário
                    </button>
                    <a href="<?= SITE_URL ?>/usuarios.php" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </div>

    <!-- ===================== EDITAR ===================== -->
    <?php elseif ($acao === 'editar' && $usuarioEdit): ?>

        <div class="card-form" style="margin:0">
            <h2 class="form-titulo" style="text-align:left;margin-bottom:22px">✏️ Editar usuário</h2>
            <form method="POST" action="">
                <input type="hidden" name="acao" value="editar_usuario">
                <input type="hidden" name="id" value="<?= $usuarioEdit['id'] ?>">
                <div class="form-grupo">
                    <label>Nome completo *</label>
                    <input type="text" name="nome" required value="<?= escape($usuarioEdit['nome']) ?>">
                </div>
                <div class="form-grupo">
                    <label>E-mail *</label>
                    <input type="email" name="email" required value="<?= escape($usuarioEdit['email']) ?>">
                </div>
                <div class="form-grupo">
                    <label>Nova senha <span style="font-weight:400;color:#9a7070">(deixe em branco para manter)</span></label>
                    <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                <div class="form-grupo">
                    <label>Perfil *</label>
                    <select name="perfil">
                        <option value="aluno"          <?= $usuarioEdit['perfil']==='aluno'           ? 'selected' : '' ?>>Aluno</option>
                        <option value="sub_supervisor" <?= $usuarioEdit['perfil']==='sub_supervisor'  ? 'selected' : '' ?>>Sub-supervisor</option>
                        <option value="supervisor"     <?= $usuarioEdit['perfil']==='supervisor'      ? 'selected' : '' ?>>Supervisor</option>
                    </select>
                </div>
                <div class="form-grupo">
                    <label style="display:flex;align-items:center;gap:10px;font-weight:400">
                        <input type="checkbox" name="ativo" <?= $usuarioEdit['ativo'] ? 'checked' : '' ?>>
                        Usuário ativo
                    </label>
                </div>
                <div style="display:flex;gap:12px;margin-top:12px">
                    <button type="submit" class="btn btn-primario" style="width:auto;padding:11px 28px">
                        Salvar alterações
                    </button>
                    <a href="<?= SITE_URL ?>/usuarios.php" class="btn btn-secundario">Cancelar</a>
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