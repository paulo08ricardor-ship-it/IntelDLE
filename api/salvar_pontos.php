<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Método não permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Aceitar tanto FormData/POST tradicional quanto payload JSON
$jsonRaw = file_get_contents('php://input');
if (!empty($jsonRaw)) {
    $dadosJson = json_decode($jsonRaw, true);
    if (is_array($dadosJson)) {
        $_POST = array_merge($dadosJson, $_POST);
    }
}

$modo = isset($_POST['modo']) && strtolower((string)$_POST['modo']) === 'infinito' ? 'infinito' : 'diario';

if (!isset($_SESSION['id'])) {
    echo json_encode([
        'sucesso' => true,
        'visitante' => true,
        'modo' => $modo
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = (new Database())->conectar();
    $usuario_id = (int) $_SESSION['id'];

    if ($modo === 'infinito') {
        $pontos = 0;
        if (isset($_POST['pontos']) && is_numeric($_POST['pontos'])) {
            $pontos = (int) $_POST['pontos'];
        } elseif (isset($_SESSION['streak_infinito'])) {
            $pontos = (int) $_SESSION['streak_infinito'];
        }

        if ($pontos < 0) {
            $pontos = 0;
        }

        // SQLite UPSERT para ranking_infinito (3FN com chave única em usuario_id)
        $stmt = $db->prepare("
            INSERT INTO ranking_infinito (usuario_id, pontuacao)
            VALUES (?, ?)
            ON CONFLICT(usuario_id) DO UPDATE SET pontuacao = MAX(ranking_infinito.pontuacao, excluded.pontuacao)
        ");
        $stmt->execute([$usuario_id, $pontos]);

        // Buscar o recorde final consolidado
        $stmtRecorde = $db->prepare("SELECT pontuacao FROM ranking_infinito WHERE usuario_id = ?");
        $stmtRecorde->execute([$usuario_id]);
        $rowRecorde = $stmtRecorde->fetch();
        $recordeFinal = $rowRecorde ? (int)$rowRecorde['pontuacao'] : $pontos;

        echo json_encode([
            'sucesso' => true,
            'modo' => 'infinito',
            'sequencia' => $pontos,
            'recorde' => $recordeFinal
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Modo Diário
        $pontos = isset($_POST['pontos']) && is_numeric($_POST['pontos']) 
            ? (int) $_POST['pontos'] 
            : (isset($_SESSION['pontos_pendentes']) ? (int) $_SESSION['pontos_pendentes'] : 10000);
        
        if ($pontos < 0) {
            $pontos = 0;
        }

        // Verificar se o usuário já possui pontuação salva hoje
        $stmtCheck = $db->prepare("
            SELECT id, pontuacao 
            FROM ranking_diario 
            WHERE usuario_id = ? AND data_jogo = DATE('now')
            LIMIT 1
        ");
        $stmtCheck->execute([$usuario_id]);
        $jaExiste = $stmtCheck->fetch();

        if (!$jaExiste) {
            $stmt = $db->prepare("
                INSERT INTO ranking_diario (usuario_id, pontuacao, data_jogo)
                VALUES (?, ?, DATE('now'))
            ");
            $stmt->execute([$usuario_id, $pontos]);
            $pontuacaoFinal = $pontos;
        } else {
            $pontuacaoFinal = (int) $jaExiste['pontuacao'];
        }

        unset($_SESSION['pontos_pendentes']);

        echo json_encode([
            'sucesso' => true,
            'modo' => 'diario',
            'pontuacao' => $pontuacaoFinal
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    error_log("Erro ao salvar pontos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Não foi possível salvar sua pontuação no momento.',
        'detalhe' => defined('TEST_MODE') ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

