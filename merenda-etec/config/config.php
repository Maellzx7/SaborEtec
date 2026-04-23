<?php
// ============================================================
// ARQUIVO: config/config.php
// DESCRIÇÃO: Constantes e funções globais do sistema
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

// Exibe erros durante desenvolvimento — remova em produção
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/database.php';

define('SITE_NOME', 'Merenda ETEC Peruíbe');

// Detecta automaticamente o caminho base — funciona no XAMPP sem configuração manual
$_protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_raiz      = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$_docroot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$_subpath   = str_replace($_docroot, '', $_raiz);
$_subpath   = '/' . trim(str_replace('\\', '/', $_subpath), '/');

define('SITE_URL',   $_protocolo . '://' . $_host . $_subpath);
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

unset($_protocolo, $_host, $_raiz, $_docroot, $_subpath);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function estaLogado(): bool {
    return isset($_SESSION['usuario_id']);
}

function requerLogin(): void {
    if (!estaLogado()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requerSupervisor(): void {
    requerLogin();
    if (!in_array($_SESSION['perfil'], ['supervisor', 'sub_supervisor'])) {
        header('Location: ' . SITE_URL . '/index.php?acesso=negado');
        exit;
    }
}

function requerSupervisorPleno(): void {
    requerLogin();
    if ($_SESSION['perfil'] !== 'supervisor') {
        header('Location: ' . SITE_URL . '/dashboard.php?acesso=negado');
        exit;
    }
}

function perfil(): string {
    return $_SESSION['perfil'] ?? '';
}

function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $mensagem): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function exibirFlash(): string {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cor = $f['tipo'] === 'sucesso' ? '#2e7d32' : ($f['tipo'] === 'erro' ? '#8b0000' : '#4a3900');
    return '<div class="flash" style="background:' . $cor . '">' . escape($f['msg']) . '</div>';
}

function uploadFoto(array $arquivo): ?string {
    if ($arquivo['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return null;
    $nome = uniqid('prato_', true) . '.' . $ext;
    $destino = UPLOAD_DIR . $nome;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (move_uploaded_file($arquivo['tmp_name'], $destino)) return $nome;
    return null;
}

function diaSemanaLabel(string $dia): string {
    return match($dia) {
        'segunda' => 'Segunda-feira',
        'terca'   => 'Terça-feira',
        'quarta'  => 'Quarta-feira',
        'quinta'  => 'Quinta-feira',
        'sexta'   => 'Sexta-feira',
        default   => ucfirst($dia),
    };
}