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

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Validação de campos vazios
if (empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Preencha o campo de e-mail."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($senha)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Preencha o campo de senha."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validação de formato de e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Formato de e-mail inválido."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = (new Database())->conectar();

    $sql = $db->prepare("
        SELECT id, nome, email, senha, tema, tipo
        FROM usuarios
        WHERE LOWER(email) = LOWER(?)
    ");
    $sql->execute([$email]);
    $user = $sql->fetch();

    // Validação com hash de senha seguro (password_verify)
    if (!$user || !password_verify($senha, $user['senha'])) {
        echo json_encode([
            "sucesso" => false,
            "erro" => "E-mail ou senha incorretos."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Regeneração de ID de sessão contra Session Fixation
    session_regenerate_id(true);

    $_SESSION['id'] = (int) $user['id'];
    $_SESSION['nome'] = $user['nome'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['tipo'] = $user['tipo'] ?? 'usuario';
    $_SESSION['tema'] = $user['tema'] ?? 'escuro';

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    setcookie('inteldle_tema', $_SESSION['tema'], [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    echo json_encode([
        "sucesso" => true,
        "tipo" => $_SESSION['tipo'],
        "nome" => $_SESSION['nome'],
        "tema" => $_SESSION['tema']
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erro no login: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Ocorreu um erro ao processar o login. Tente novamente."
    ], JSON_UNESCAPED_UNICODE);
}
