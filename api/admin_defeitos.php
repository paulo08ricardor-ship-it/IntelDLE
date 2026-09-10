<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Proteção RBAC estrita: Apenas administradores
exigirAdminApi();

$db = (new Database())->conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';

try {
    switch ($acao) {
        case 'listar':
            $stmt = $db->query("
                SELECT d.*, 
                       (SELECT COUNT(*) FROM solucoes s WHERE s.defeito_id = d.id) AS total_solucoes,
                       (SELECT COUNT(*) FROM solucoes s WHERE s.defeito_id = d.id AND s.correto = 1) AS solucoes_corretas
                FROM defeitos d 
                ORDER BY d.id ASC
            ");
            $defeitos = $stmt->fetchAll();
            echo json_encode(["sucesso" => true, "dados" => $defeitos], JSON_UNESCAPED_UNICODE);
            break;

        case 'obter':
            $id = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("SELECT * FROM defeitos WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Defeito não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(["sucesso" => true, "dados" => $item], JSON_UNESCAPED_UNICODE);
            break;

        case 'salvar':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $imagem = trim($_POST['imagem'] ?? 'monitor.png');
            $tipo = trim($_POST['tipo'] ?? 'hardware');

            if (empty($titulo) || empty($descricao)) {
                echo json_encode(["sucesso" => false, "erro" => "Título e descrição são obrigatórios."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!in_array($tipo, ['hardware', 'software'], true)) {
                echo json_encode(["sucesso" => false, "erro" => "Tipo deve ser 'hardware' ou 'software'."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id) {
                // Atualizar
                $stmt = $db->prepare("
                    UPDATE defeitos 
                    SET titulo = :titulo, descricao = :descricao, imagem = :imagem, tipo = :tipo 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':imagem' => $imagem,
                    ':tipo' => $tipo,
                    ':id' => $id
                ]);
                echo json_encode(["sucesso" => true, "mensagem" => "Defeito atualizado com sucesso!", "id" => $id], JSON_UNESCAPED_UNICODE);
            } else {
                // Criar
                $stmt = $db->prepare("
                    INSERT INTO defeitos (titulo, descricao, imagem, tipo) 
                    VALUES (:titulo, :descricao, :imagem, :tipo)
                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':imagem' => $imagem,
                    ':tipo' => $tipo
                ]);
                $novoId = (int) $db->lastInsertId();
                echo json_encode(["sucesso" => true, "mensagem" => "Defeito cadastrado com sucesso!", "id" => $novoId], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'excluir':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(["sucesso" => false, "erro" => "ID inválido para exclusão."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM defeitos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["sucesso" => true, "mensagem" => "Defeito e soluções vinculadas excluídos com sucesso!"], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Ação não suportada."], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    error_log("Erro em admin_defeitos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Ocorreu um erro interno no servidor ao processar o defeito."], JSON_UNESCAPED_UNICODE);
}
