<?php
/**
 * API Administrativa: Gerenciamento de Componentes (CRUD)
 * Inteldle - Versão Normalizada 3FN com Integridade ACID
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Proteção RBAC estrita: Apenas administradores
exigirAdminApi();

$db = (new Database())->conectar();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';

try {
    switch ($acao) {
        case 'listar':
            $stmt = $db->query("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM solucoes s WHERE s.componente_id = c.id) AS total_vinculos,
                       (SELECT COUNT(*) FROM componentes_testes ct WHERE ct.componente_id = c.id) AS total_testes
                FROM componentes c 
                ORDER BY c.tipo ASC, c.nome ASC
            ");
            $componentes = $stmt->fetchAll();
            http_response_code(200);
            echo json_encode(["sucesso" => true, "dados" => $componentes], JSON_UNESCAPED_UNICODE);
            break;

        case 'obter':
            $idRaw = $_GET['id'] ?? $_POST['id'] ?? null;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmt = $db->prepare("SELECT * FROM componentes WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Componente não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(200);
            echo json_encode(["sucesso" => true, "dados" => $item], JSON_UNESCAPED_UNICODE);
            break;

        case 'salvar':
            $idRaw = $_POST['id'] ?? null;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                $id = null;
            }

            $nome = trim((string)($_POST['nome'] ?? ''));
            $tipo = trim((string)($_POST['tipo'] ?? 'hardware'));
            $icone = trim((string)($_POST['icone'] ?? 'cpu.png'));
            $descricao = trim((string)($_POST['descricao'] ?? ''));

            if (empty($nome)) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "O nome do componente é obrigatório."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!in_array($tipo, ['hardware', 'software'], true)) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "Tipo deve ser 'hardware' ou 'software'."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Início da Transação ACID
            $db->beginTransaction();

            if ($id !== null) {
                $stmtCheckExist = $db->prepare("SELECT id FROM componentes WHERE id = ?");
                $stmtCheckExist->execute([$id]);
                if (!$stmtCheckExist->fetch()) {
                    $db->rollBack();
                    http_response_code(404);
                    echo json_encode(["sucesso" => false, "erro" => "Componente não encontrado para atualização."], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Checagem de duplicidade: se $id for null ou int
            if ($id === null) {
                $check = $db->prepare("SELECT id FROM componentes WHERE LOWER(nome) = LOWER(?)");
                $check->execute([$nome]);
            } else {
                $check = $db->prepare("SELECT id FROM componentes WHERE LOWER(nome) = LOWER(?) AND id != ?");
                $check->execute([$nome, $id]);
            }

            if ($check->fetch()) {
                $db->rollBack();
                http_response_code(409);
                echo json_encode(["sucesso" => false, "erro" => "Já existe um componente cadastrado com este nome."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id !== null) {
                // Atualizar
                $stmt = $db->prepare("
                    UPDATE componentes 
                    SET nome = :nome, tipo = :tipo, icone = :icone, descricao = :descricao 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':icone' => $icone,
                    ':descricao' => $descricao,
                    ':id' => $id
                ]);
                $db->commit();
                http_response_code(200);
                echo json_encode(["sucesso" => true, "mensagem" => "Componente atualizado com sucesso!", "id" => $id], JSON_UNESCAPED_UNICODE);
            } else {
                // Criar
                $stmt = $db->prepare("
                    INSERT INTO componentes (nome, tipo, icone, descricao) 
                    VALUES (:nome, :tipo, :icone, :descricao)
                ");
                $stmt->execute([
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':icone' => $icone,
                    ':descricao' => $descricao
                ]);
                $novoId = (int) $db->lastInsertId();
                $db->commit();
                http_response_code(200);
                echo json_encode(["sucesso" => true, "mensagem" => "Componente criado com sucesso!", "id" => $novoId], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'excluir':
            $idRaw = $_POST['id'] ?? null;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido para exclusão."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Início da Transação ACID
            $db->beginTransaction();

            $stmtCheck = $db->prepare("SELECT id, nome FROM componentes WHERE id = ?");
            $stmtCheck->execute([$id]);
            $componente = $stmtCheck->fetch();

            if (!$componente) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Componente não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // a) Proteção RESTRICT: Verificar vínculos na tabela 'solucoes'
            $stmtSol = $db->prepare("
                SELECT COUNT(*) AS total,
                       COALESCE(SUM(CASE WHEN correto = 1 THEN 1 ELSE 0 END), 0) AS corretas
                FROM solucoes 
                WHERE componente_id = ?
            ");
            $stmtSol->execute([$id]);
            $solInfo = $stmtSol->fetch();
            $totalSolucoes = (int)($solInfo['total'] ?? 0);
            $totalCorretas = (int)($solInfo['corretas'] ?? 0);

            if ($totalSolucoes > 0) {
                $db->rollBack();
                http_response_code(409); // Conflict
                $msg = "Não é possível excluir o componente '{$componente['nome']}', pois ele está vinculado a {$totalSolucoes} solução(ões)";
                if ($totalCorretas > 0) {
                    $msg .= " (sendo a solução correta de {$totalCorretas} defeito(s))";
                }
                $msg .= ". Remova ou altere os vínculos na aba Soluções antes de excluí-lo.";
                echo json_encode(["sucesso" => false, "erro" => $msg], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // b) Proteção RESTRICT: Verificar vínculos na tabela 'componentes_testes'
            $stmtTestes = $db->prepare("SELECT COUNT(*) FROM componentes_testes WHERE componente_id = ?");
            $stmtTestes->execute([$id]);
            $totalTestes = (int)$stmtTestes->fetchColumn();

            if ($totalTestes > 0) {
                $db->rollBack();
                http_response_code(409); // Conflict
                echo json_encode([
                    "sucesso" => false,
                    "erro" => "Não é possível excluir o componente '{$componente['nome']}', pois existem {$totalTestes} teste(s) de diagnóstico associados a ele. Remova ou altere os vínculos antes de excluí-lo."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // c) Somente excluir se não houver dependências ativas
            $stmtDel = $db->prepare("DELETE FROM componentes WHERE id = ?");
            $stmtDel->execute([$id]);

            $db->commit();
            http_response_code(200);
            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Componente '{$componente['nome']}' excluído com sucesso!"
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Ação não suportada."], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("Erro em admin_componentes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Ocorreu um erro interno no servidor ao processar o componente."
    ], JSON_UNESCAPED_UNICODE);
}
