<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Proteção RBAC estrita: Apenas administradores
exigirAdminApi();

$db = (new Database())->conectar();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';

try {
    switch ($acao) {
        case 'listar':
            $defeitoId = filter_var($_GET['defeito_id'] ?? $_POST['defeito_id'] ?? null, FILTER_VALIDATE_INT);
            if ($defeitoId) {
                $stmt = $db->prepare("
                    SELECT s.*, c.nome AS componente_nome, c.tipo AS componente_tipo, c.icone AS componente_icone, d.titulo AS defeito_titulo
                    FROM solucoes s
                    INNER JOIN componentes c ON c.id = s.componente_id
                    INNER JOIN defeitos d ON d.id = s.defeito_id
                    WHERE s.defeito_id = ?
                    ORDER BY s.correto DESC, c.nome ASC
                ");
                $stmt->execute([$defeitoId]);
            } else {
                $stmt = $db->query("
                    SELECT s.*, c.nome AS componente_nome, c.tipo AS componente_tipo, c.icone AS componente_icone, d.titulo AS defeito_titulo
                    FROM solucoes s
                    INNER JOIN componentes c ON c.id = s.componente_id
                    INNER JOIN defeitos d ON d.id = s.defeito_id
                    ORDER BY d.id ASC, s.correto DESC, c.nome ASC
                ");
            }
            $solucoes = $stmt->fetchAll();
            echo json_encode(["sucesso" => true, "dados" => $solucoes], JSON_UNESCAPED_UNICODE);
            break;

        case 'obter':
            $id = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("
                SELECT s.*, c.nome AS componente_nome, d.titulo AS defeito_titulo
                FROM solucoes s
                INNER JOIN componentes c ON c.id = s.componente_id
                INNER JOIN defeitos d ON d.id = s.defeito_id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Solução não encontrada."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(["sucesso" => true, "dados" => $item], JSON_UNESCAPED_UNICODE);
            break;

        case 'salvar':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $defeitoId = filter_var($_POST['defeito_id'] ?? 0, FILTER_VALIDATE_INT);
            $componenteId = filter_var($_POST['componente_id'] ?? 0, FILTER_VALIDATE_INT);
            $correto = (isset($_POST['correto']) && ($_POST['correto'] === '1' || $_POST['correto'] === 'true' || $_POST['correto'] === 1)) ? 1 : 0;
            $mensagem = trim($_POST['mensagem'] ?? '');

            if (!$defeitoId || !$componenteId) {
                echo json_encode(["sucesso" => false, "erro" => "Defeito e Componente são obrigatórios."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (empty($mensagem)) {
                echo json_encode(["sucesso" => false, "erro" => "A mensagem explicativa de feedback é obrigatória."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar se o par (defeito_id, componente_id) já existe em outro ID
            $check = $db->prepare("SELECT id FROM solucoes WHERE defeito_id = ? AND componente_id = ? AND (? IS NULL OR id != ?)");
            $check->execute([$defeitoId, $componenteId, $id, $id]);
            if ($check->fetch()) {
                echo json_encode(["sucesso" => false, "erro" => "Já existe uma solução cadastrada para esta combinação de Defeito e Componente."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id) {
                $stmt = $db->prepare("
                    UPDATE solucoes 
                    SET defeito_id = :defeito_id, componente_id = :componente_id, correto = :correto, mensagem = :mensagem 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':defeito_id' => $defeitoId,
                    ':componente_id' => $componenteId,
                    ':correto' => $correto,
                    ':mensagem' => $mensagem,
                    ':id' => $id
                ]);
                echo json_encode(["sucesso" => true, "mensagem" => "Solução atualizada com sucesso!", "id" => $id], JSON_UNESCAPED_UNICODE);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO solucoes (defeito_id, componente_id, correto, mensagem) 
                    VALUES (:defeito_id, :componente_id, :correto, :mensagem)
                ");
                $stmt->execute([
                    ':defeito_id' => $defeitoId,
                    ':componente_id' => $componenteId,
                    ':correto' => $correto,
                    ':mensagem' => $mensagem
                ]);
                $novoId = (int) $db->lastInsertId();
                echo json_encode(["sucesso" => true, "mensagem" => "Solução cadastrada com sucesso!", "id" => $novoId], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'excluir':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(["sucesso" => false, "erro" => "ID inválido para exclusão."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM solucoes WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["sucesso" => true, "mensagem" => "Solução excluída com sucesso!"], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Ação não suportada."], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    error_log("Erro em admin_solucoes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Ocorreu um erro interno no servidor ao processar a solução."], JSON_UNESCAPED_UNICODE);
}
