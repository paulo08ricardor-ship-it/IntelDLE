<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Método não permitido."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tema = $_POST['tema'] ?? 'escuro';

if (!in_array($tema, ['claro', 'escuro'], true)) {
    $tema = 'escuro';
}

$_SESSION['tema'] = $tema;

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

setcookie('inteldle_tema', $tema, [
    'expires' => time() + 31536000,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => false,
    'samesite' => 'Lax'
]);

if (isset($_SESSION['id'])) {
    try {
        $db = (new Database())->conectar();
        $stmt = $db->prepare("UPDATE usuarios SET tema = ? WHERE id = ?");
        $stmt->execute([$tema, $_SESSION['id']]);
    } catch (Exception $e) {
        // Falha no banco não impede a resposta
    }
}

echo json_encode([
    "sucesso" => true,
    "tema" => $tema
], JSON_UNESCAPED_UNICODE);
