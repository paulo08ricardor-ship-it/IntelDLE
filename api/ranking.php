<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? 'todos';
try {
    $db = (new Database())->conectar();
    $resposta = [];

    if ($tipo === 'diario' || $tipo === 'todos') {
        $sqlDiario = $db->query("
            SELECT 
                u.id AS usuario_id,
                u.nome, 
                r.pontuacao, 
                r.data_jogo,
                ROW_NUMBER() OVER (ORDER BY r.pontuacao DESC, r.id ASC) AS posicao
            FROM ranking_diario r
            INNER JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.data_jogo = DATE('now')
            ORDER BY r.pontuacao DESC, r.id ASC
            LIMIT 10
        ");
        $resposta['diario'] = $sqlDiario->fetchAll();
    }

    if ($tipo === 'infinito' || $tipo === 'todos') {
        $sqlInfinito = $db->query("
            SELECT 
                u.id AS usuario_id,
                u.nome, 
                r.pontuacao,
                ROW_NUMBER() OVER (ORDER BY r.pontuacao DESC, r.id ASC) AS posicao
            FROM ranking_infinito r
            INNER JOIN usuarios u ON u.id = r.usuario_id
            ORDER BY r.pontuacao DESC, r.id ASC
            LIMIT 10
        ");
        $resposta['infinito'] = $sqlInfinito->fetchAll();
    }

    if ($tipo === 'infinito') {
        echo json_encode($resposta['infinito'] ?? [], JSON_UNESCAPED_UNICODE);
    } elseif ($tipo === 'diario') {
        echo json_encode($resposta['diario'] ?? [], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar ranking: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Não foi possível carregar os rankings no momento."
    ], JSON_UNESCAPED_UNICODE);
}
