<?php
/**
 * Gerenciamento Seguro de Sessões e Controle de Acesso Baseado em Funções (RBAC)
 * Inteldle - Versão Atualizada 3FN
 */

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}

// Cabeçalhos de Segurança HTTP Globais
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

/**
 * Retorna ou gera um token anti-CSRF exclusivo para a sessão ativa.
 */
function obterCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida se o token CSRF enviado na requisição corresponde ao da sessão.
 */
function validarCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Extrai o token CSRF recebido via POST ou cabeçalho HTTP.
 */
function extrairCsrfRequisicao(): ?string
{
    if (!empty($_POST['csrf_token'])) {
        return (string)$_POST['csrf_token'];
    }
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return null;
}

/**
 * Retorna se há um usuário autenticado na sessão.
 */
function estaLogado(): bool
{
    return isset($_SESSION['id']) && !empty($_SESSION['id']);
}

/**
 * Retorna se o usuário logado possui privilégios de administrador.
 */
function ehAdmin(): bool
{
    return estaLogado() && isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';
}

/**
 * Retorna os dados do usuário logado na sessão ou null.
 */
function obterUsuarioLogado(): ?array
{
    if (!estaLogado()) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['id'],
        'nome' => $_SESSION['nome'] ?? 'Usuário',
        'email' => $_SESSION['email'] ?? '',
        'tipo' => $_SESSION['tipo'] ?? 'usuario',
        'tema' => $_SESSION['tema'] ?? 'escuro'
    ];
}

/**
 * Middleware para páginas WEB do painel administrativo (/admin).
 * Bloqueia visitantes e usuários comuns (tipo != 'admin').
 */
function exigirAdminWeb(): void
{
    if (!ehAdmin()) {
        http_response_code(403);
        $tema = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Acesso Negado | Inteldle Admin</title>
            <link rel="stylesheet" href="../assets/css/global.css">
            <style>
                body {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    background-color: var(--cor-fundo-corpo);
                    color: var(--cor-texto-principal);
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    text-align: center;
                    padding: 20px;
                    box-sizing: border-box;
                }
                .card-erro {
                    background: var(--cor-fundo-modal);
                    border: 2px solid var(--cor-borda-erro);
                    padding: 40px;
                    border-radius: 12px;
                    max-width: 500px;
                    box-shadow: 0 8px 30px rgba(255, 68, 68, 0.2);
                }
                .codigo {
                    font-size: 72px;
                    font-weight: 900;
                    color: var(--cor-texto-erro);
                    margin: 0 0 10px;
                }
                .titulo {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 15px;
                    color: var(--cor-texto-destaque);
                }
                .desc {
                    font-size: 16px;
                    color: var(--cor-texto-principal);
                    margin-bottom: 30px;
                    line-height: 1.5;
                }
                .btn-voltar {
                    display: inline-block;
                    padding: 12px 30px;
                    background: var(--cor-btn-bg);
                    border: 2px solid var(--cor-borda-principal);
                    color: var(--cor-texto-principal);
                    text-decoration: none;
                    font-weight: bold;
                    border-radius: 6px;
                    transition: all 0.3s ease;
                }
                .btn-voltar:hover {
                    background: var(--cor-btn-hover-bg);
                    color: var(--cor-btn-hover-texto);
                }
            </style>
        </head>
        <body class="<?= $tema === 'claro' ? 'tema-claro' : '' ?>">
            <div class="card-erro">
                <div class="codigo">403</div>
                <div class="titulo">ACESSO RESTRITO</div>
                <div class="desc">
                    Esta área é restrita exclusivamente a administradores do Inteldle.<br>
                    <?= estaLogado() ? 'Sua conta atual não possui permissões administrativas.' : 'Você precisa efetuar login com uma conta administrativa.' ?>
                </div>
                <a href="../views/menu.php" class="btn-voltar">← Voltar ao Menu Inicial</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Middleware para ENDPOINTS DE API (/api/admin_*).
 * Retorna JSON com HTTP 403 caso o solicitante não seja admin,
 * e HTTP 403 caso a requisição modificadora falhe na validação anti-CSRF.
 *
 * @param bool $validarCsrf Se true, valida o token CSRF para métodos que alteram estado (POST)
 */
function exigirAdminApi(bool $validarCsrf = true): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (!ehAdmin()) {
        http_response_code(403);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Acesso não autorizado. Esta API requer privilégios de administrador (tipo = 'admin').",
            "codigo" => 403
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($validarCsrf && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';
        // Validar CSRF para ações que criam, alteram ou excluem dados
        if (in_array($acao, ['salvar', 'excluir', 'criar', 'editar'], true)) {
            $tokenRecebido = extrairCsrfRequisicao();
            if (!validarCsrfToken($tokenRecebido)) {
                http_response_code(403);
                echo json_encode([
                    "sucesso" => false,
                    "erro" => "Token de proteção anti-CSRF inválido ou expirado. Recarregue a página e tente novamente.",
                    "codigo" => 403
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}
