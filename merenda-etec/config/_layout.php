<?php
// ============================================================
// ARQUIVO: config/_layout.php
// DESCRIÇÃO: Funções de header e footer reutilizáveis
// Sistema Sabor Etec — ETEC de Peruíbe
// ============================================================

function renderHeader(string $paginaAtiva = '') {
    $url  = SITE_URL;
    $nome = SITE_NOME;
    $nav  = '';

    $itens = [
        'index.php'    => 'Cardápio',
        'relatos.php'  => 'Relatos',
    ];

    if (estaLogado() && perfil() !== 'aluno') {
        $itens['dashboard.php'] = 'Painel';
        $itens['cardapio.php']  = 'Gerenciar';
        $itens['usuarios.php']  = 'Usuários';
    }

    foreach ($itens as $arq => $label) {
        $ativo = (basename($paginaAtiva) === $arq) ? ' class="ativo"' : '';
        $nav .= "<li><a href=\"{$url}/{$arq}\"{$ativo}>{$label}</a></li>";
    }

    if (estaLogado()) {
        $nav .= '<li><a href="' . $url . '/logout.php">Sair</a></li>';
        $nav .= '<li><span class="nav-user">' . escape($_SESSION['nome']) . '</span></li>';
    } else {
        $nav .= '<li><a href="' . $url . '/login.php">Entrar</a></li>';
    }

    echo <<<HTML
<header>
  <nav class="navbar">
    <a href="{$url}/index.php" class="logo">
      <img src="{$url}/assets/uploads/logo.png" alt="" class="logo-img" width="36" height="36" style="width:36px;height:36px;min-width:36px;flex-shrink:0">
      <div class="logo-texto">
        <span class="nome">Sabor Etec</span>
        
      </div>
    </a>
    <button class="hamburger" onclick="document.getElementById('navMenu').classList.toggle('aberto')" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navMenu">{$nav}</ul>
  </nav>
</header>
HTML;
}

function renderFooter() {
    $url = SITE_URL;
    $ano = date('Y');
    echo <<<HTML
<footer>
  <div class="footer-inner">
    <div class="footer-logo">
<img src="{$url}/assets/uploads/logo.png" alt="" class="logo-img" width="36" height="36">   <h1>Sabor Etec</h1>
    </div>
    <span>ETEC de Peruíbe &copy; {$ano} &mdash; Sistema de Gestão da Merenda Escolar</span>
  </div>
</footer>
HTML;
}