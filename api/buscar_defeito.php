<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$rawExcludeId = $_GET['exclude_id'] ?? $_POST['exclude_id'] ?? null;
$excludeId = filter_var($rawExcludeId, FILTER_VALIDATE_INT);

try {
    $db = (new Database())->conectar();

    $defeito = null;

    if ($excludeId !== false && $excludeId !== null && $excludeId > 0) {
        $stmt = $db->prepare("
            SELECT id, titulo, descricao, imagem, tipo
            FROM defeitos
            WHERE id != :exclude_id
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $stmt->execute([':exclude_id' => $excludeId]);
        $defeito = $stmt->fetch();
    }

    // Fallback se nenhum defeito foi encontrado com a exclusão ou exclude_id não foi informado
    if (!$defeito) {
        $sql = $db->query("
            SELECT id, titulo, descricao, imagem, tipo
            FROM defeitos
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $defeito = $sql->fetch();
    }

    echo json_encode($defeito ?: null, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erro ao buscar defeito: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao carregar cenário de defeito."], JSON_UNESCAPED_UNICODE);
}
