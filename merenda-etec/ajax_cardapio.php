<?php
// ============================================================
// ARQUIVO: ajax_cardapio.php (raiz do projeto)
// DESCRIÇÃO: Endpoint AJAX — retorna cardápio de um período.
//            Usado pelo calendário do aluno (público) e do
//            supervisor (dashboard). Sem restrição de perfil.
// Sistema de Merenda - ETEC de Peruíbe
// ============================================================

require_once __DIR__ . '/config/config.php';

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-t');

// Valida formato YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$pdo  = conectar();
$stmt = $pdo->prepare("
    SELECT cs.data_referencia, cs.prato_id, p.nome AS prato_nome
    FROM cardapio_semana cs
    JOIN pratos p ON cs.prato_id = p.id AND p.ativo = 1
    WHERE cs.data_referencia BETWEEN ? AND ?
    ORDER BY cs.data_referencia
");
$stmt->execute([$inicio, $fim]);

$resultado = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $resultado[$row['data_referencia']] = [
        'prato_id'   => $row['prato_id'],
        'prato_nome' => $row['prato_nome'],
    ];
}

header('Content-Type: application/json');
echo json_encode($resultado);
