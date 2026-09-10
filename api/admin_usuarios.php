<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Proteção RBAC estrita: Apenas administradores
exigirAdminApi();

$db = (new Database())->conectar();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';
$adminAtualId = (int) $_SESSION['id'];

try {
    switch ($acao) {
        case 'listar':
            $stmt = $db->query("
                SELECT u.id, u.nome, u.email, u.tipo, u.tema, u.criado_em,
                       (SELECT COUNT(*) FROM historico h WHERE h.usuario_id = u.id) AS total_partidas,
                       (SELECT MAX(pontuacao) FROM ranking_infinito ri WHERE ri.usuario_id = u.id) AS recorde_infinito,
                       (SELECT MAX(pontuacao) FROM ranking_diario rd WHERE rd.usuario_id = u.id) AS recorde_diario
                FROM usuarios u 
                ORDER BY u.id ASC
            ");
            $usuarios = $stmt->fetchAll();
            echo json_encode(["sucesso" => true, "dados" => $usuarios, "admin_logado_id" => $adminAtualId], JSON_UNESCAPED_UNICODE);
            break;

        case 'obter':
            $id = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("SELECT id, nome, email, tipo, tema, criado_em FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Usuário não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(["sucesso" => true, "dados" => $item], JSON_UNESCAPED_UNICODE);
            break;

        case 'salvar':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tipo = trim($_POST['tipo'] ?? 'usuario');
            $senha = $_POST['senha'] ?? '';

            if (empty($nome) || empty($email)) {
                echo json_encode(["sucesso" => false, "erro" => "Nome e E-mail são obrigatórios."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(["sucesso" => false, "erro" => "E-mail inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!in_array($tipo, ['usuario', 'admin'], true)) {
                echo json_encode(["sucesso" => false, "erro" => "Tipo de usuário deve ser 'usuario' ou 'admin'."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Impede que o próprio admin logado remova seu status de admin
            if ($id === $adminAtualId && $tipo !== 'admin') {
                echo json_encode(["sucesso" => false, "erro" => "Você não pode remover seu próprio privilégio de administrador."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verifica duplicidade de e-mail
            $check = $db->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) AND (? IS NULL OR id != ?)");
            $check->execute([$email, $id, $id]);
            if ($check->fetch()) {
                echo json_encode(["sucesso" => false, "erro" => "Este e-mail já está em uso por outro usuário."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id) {
                // Atualização
                if (!empty($senha)) {
                    if (strlen($senha) < 6) {
                        echo json_encode(["sucesso" => false, "erro" => "A nova senha deve ter no mínimo 6 caracteres."], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $tipo, $hash, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $tipo, $id]);
                }

                // Se o usuário atualizado for o próprio logado, atualiza dados na sessão
                if ($id === $adminAtualId) {
                    $_SESSION['nome'] = $nome;
                    $_SESSION['email'] = $email;
                    $_SESSION['tipo'] = $tipo;
                }

                echo json_encode(["sucesso" => true, "mensagem" => "Usuário atualizado com sucesso!", "id" => $id], JSON_UNESCAPED_UNICODE);
            } else {
                // Criação
                if (empty($senha) || strlen($senha) < 6) {
                    echo json_encode(["sucesso" => false, "erro" => "A senha inicial deve ter no mínimo 6 caracteres."], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, tema) VALUES (?, ?, ?, ?, 'escuro')");
                $stmt->execute([$nome, $email, $hash, $tipo]);
                $novoId = (int) $db->lastInsertId();
                echo json_encode(["sucesso" => true, "mensagem" => "Novo usuário cadastrado com sucesso!", "id" => $novoId], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'excluir':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(["sucesso" => false, "erro" => "ID inválido para exclusão."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Regra de segurança: O administrador logado não pode excluir a si mesmo
            if ($id === $adminAtualId) {
                echo json_encode(["sucesso" => false, "erro" => "Operação bloqueada: Você não pode excluir a sua própria conta de administrador."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["sucesso" => true, "mensagem" => "Usuário e registros vinculados excluídos com sucesso!"], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Ação não suportada."], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    error_log("Erro em admin_usuarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Ocorreu um erro interno no servidor ao processar o usuário."], JSON_UNESCAPED_UNICODE);
}
