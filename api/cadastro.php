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

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Validações
if (empty($nome)) {
    echo json_encode(["sucesso" => false, "erro" => "Preencha o campo de nome."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($email)) {
    echo json_encode(["sucesso" => false, "erro" => "Preencha o campo de e-mail."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($senha)) {
    echo json_encode(["sucesso" => false, "erro" => "Preencha o campo de senha."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($nome) < 2 || mb_strlen($nome) > 50) {
    echo json_encode(["sucesso" => false, "erro" => "O nome deve ter entre 2 e 50 caracteres."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    echo json_encode(["sucesso" => false, "erro" => "Formato de e-mail inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($senha) < 6 || strlen($senha) > 72) {
    echo json_encode(["sucesso" => false, "erro" => "A senha deve ter entre 6 e 72 caracteres."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = (new Database())->conectar();

    // Verificar e-mail duplicado
    $check = $db->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?)");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(["sucesso" => false, "erro" => "Este e-mail já está cadastrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Criptografia segura
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $sql = $db->prepare("
        INSERT INTO usuarios (nome, email, senha, tipo, tema)
        VALUES (?, ?, ?, 'usuario', 'escuro')
    ");

    $sql->execute([
        $nome,
        $email,
        $senhaHash
    ]);

    $id_usuario = (int) $db->lastInsertId();

    session_regenerate_id(true);

    $_SESSION['id'] = $id_usuario;
    $_SESSION['nome'] = $nome;
    $_SESSION['email'] = $email;
    $_SESSION['tipo'] = 'usuario';
    $_SESSION['tema'] = 'escuro';

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    setcookie('inteldle_tema', 'escuro', [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    echo json_encode([
        "sucesso" => true,
        "logado" => true,
        "tipo" => "usuario",
        "tema" => "escuro"
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erro no cadastro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Não foi possível concluir o cadastro no momento. Tente novamente."
    ], JSON_UNESCAPED_UNICODE);
}
