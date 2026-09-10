<?php

require_once __DIR__ . '/../config/session.php';

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Se for requisição AJAX/JSON
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["sucesso" => true], JSON_UNESCAPED_UNICODE);
    exit;
}

header("Location: ../views/menu.php");
exit;
