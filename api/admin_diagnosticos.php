<?php
/**
 * API Administrativa: Gerenciamento de Diagnósticos e Testes (CRUD)
 * Inteldle - Versão Normalizada 3FN com Integridade ACID
 * 
 * Ações Suportadas:
 * - 'listar'  : Consulta todos os testes com LEFT JOIN componentes c ON c.id = ct.componente_id ordenados por ct.tipo ASC, ct.id ASC
 * - 'obter'   : Retorna os dados completos do teste pelo ID com LEFT JOIN componentes
 * - 'salvar'  : Insere (se ID ausente/vazio) ou atualiza teste existente em transação ACID
 * - 'excluir' : Exclui o teste pelo ID com Prepared Statements em transação ACID
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Proteção RBAC estrita: Apenas administradores com validação anti-CSRF para mutações POST
exigirAdminApi();

$db = (new Database())->conectar();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';

try {
    switch ($acao) {
        case 'listar':
            $filtroTipo = trim((string)($_GET['tipo'] ?? $_POST['tipo'] ?? ''));
            $filtroComp = filter_var($_GET['componente_id'] ?? $_POST['componente_id'] ?? null, FILTER_VALIDATE_INT);

            $sql = "
                SELECT ct.*, 
                       c.nome AS componente_nome, 
                       c.tipo AS componente_tipo, 
                       c.icone AS componente_icone
                FROM componentes_testes ct
                LEFT JOIN componentes c ON c.id = ct.componente_id
            ";
            $condicoes = [];
            $params = [];

            if (!empty($filtroTipo) && in_array($filtroTipo, ['hardware', 'software', 'rede', 'seguranca', 'geral'], true)) {
                $condicoes[] = "ct.tipo = ?";
                $params[] = $filtroTipo;
            }

            if ($filtroComp && $filtroComp > 0) {
                $condicoes[] = "ct.componente_id = ?";
                $params[] = $filtroComp;
            }

            if (!empty($condicoes)) {
                $sql .= " WHERE " . implode(" AND ", $condicoes);
            }

            $sql .= " ORDER BY ct.tipo ASC, ct.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $testes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode(["sucesso" => true, "dados" => $testes], JSON_UNESCAPED_UNICODE);
            break;

        case 'obter':
            $idRaw = $_GET['id'] ?? $_POST['id'] ?? null;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("
                SELECT ct.*, 
                       c.nome AS componente_nome, 
                       c.tipo AS componente_tipo, 
                       c.icone AS componente_icone
                FROM componentes_testes ct
                LEFT JOIN componentes c ON c.id = ct.componente_id
                WHERE ct.id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Teste de diagnóstico não encontrado."], JSON_UNESCAPED_UNICODE);
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
            $icone = trim((string)($_POST['icone'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $comandoOuAcao = trim((string)($_POST['comando_ou_acao'] ?? ''));
            $resultadoEsperado = trim((string)($_POST['resultado_esperado'] ?? ''));
            $tempoEstimado = trim((string)($_POST['tempo_estimado'] ?? '5 minutos'));
            $resultadoNormal = trim((string)($_POST['resultado_normal'] ?? ''));
            $resultadoAnomalia = trim((string)($_POST['resultado_anomalia'] ?? ''));

            // Validação de campos obrigatórios
            if (empty($nome)) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "O nome do teste de diagnóstico é obrigatório."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $tiposValidos = ['hardware', 'software', 'rede', 'seguranca', 'geral'];
            if (!in_array($tipo, $tiposValidos, true)) {
                http_response_code(400);
                echo json_encode([
                    "sucesso" => false, 
                    "erro" => "Tipo inválido. Os tipos aceitos são: 'hardware', 'software', 'rede', 'seguranca', 'geral'."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Validação de chave estrangeira opcional (componente_id)
            $componenteId = null;
            $componenteIdRaw = $_POST['componente_id'] ?? null;
            if ($componenteIdRaw !== null && $componenteIdRaw !== '' && $componenteIdRaw !== '0') {
                $compValid = filter_var($componenteIdRaw, FILTER_VALIDATE_INT);
                if ($compValid === false || $compValid <= 0) {
                    http_response_code(400);
                    echo json_encode(["sucesso" => false, "erro" => "ID do componente inválido."], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $stmtCompCheck = $db->prepare("SELECT id, icone FROM componentes WHERE id = ?");
                $stmtCompCheck->execute([$compValid]);
                $compFound = $stmtCompCheck->fetch(PDO::FETCH_ASSOC);

                if (!$compFound) {
                    http_response_code(404);
                    echo json_encode(["sucesso" => false, "erro" => "Componente referenciado não foi encontrado."], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $componenteId = (int)$compValid;
                if (empty($icone) && !empty($compFound['icone'])) {
                    $icone = $compFound['icone'];
                }
            }

            // Preenchimento de padrões coerentes caso omitidos
            if (empty($icone)) {
                $icone = match ($tipo) {
                    'software'  => 'reinstall.png',
                    'rede'      => 'update.png',
                    'seguranca' => 'Virabot_shell.webp',
                    default     => 'cpu.png',
                };
            }

            if (empty($descricao)) {
                $descricao = "Procedimento de diagnóstico e teste para subsistema de {$tipo}: {$nome}";
            }

            if (empty($resultadoNormal)) {
                $resultadoNormal = "Operação nominal, barramento íntegro e conformidade técnica atestada.";
            }

            if (empty($resultadoAnomalia)) {
                $resultadoAnomalia = "Anomalia técnica detectada durante a execução do teste de diagnóstico.";
            }

            // Início da Transação Atômica ACID
            $db->beginTransaction();

            if ($id !== null) {
                // Verificar se o registro existe para atualização
                $stmtCheckExist = $db->prepare("SELECT id FROM componentes_testes WHERE id = ?");
                $stmtCheckExist->execute([$id]);
                if (!$stmtCheckExist->fetch()) {
                    $db->rollBack();
                    http_response_code(404);
                    echo json_encode(["sucesso" => false, "erro" => "Teste de diagnóstico não encontrado para atualização."], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // Atualizar registro existente
                $stmt = $db->prepare("
                    UPDATE componentes_testes 
                    SET componente_id = :componente_id,
                        nome = :nome,
                        tipo = :tipo,
                        descricao = :descricao,
                        icone = :icone,
                        comando_ou_acao = :comando_ou_acao,
                        resultado_esperado = :resultado_esperado,
                        tempo_estimado = :tempo_estimado,
                        resultado_normal = :resultado_normal,
                        resultado_anomalia = :resultado_anomalia
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':componente_id'      => $componenteId,
                    ':nome'               => $nome,
                    ':tipo'               => $tipo,
                    ':descricao'          => $descricao,
                    ':icone'              => $icone,
                    ':comando_ou_acao'    => $comandoOuAcao ?: null,
                    ':resultado_esperado' => $resultadoEsperado ?: null,
                    ':tempo_estimado'     => $tempoEstimado ?: null,
                    ':resultado_normal'   => $resultadoNormal,
                    ':resultado_anomalia' => $resultadoAnomalia,
                    ':id'                 => $id
                ]);

                $db->commit();
                http_response_code(200);
                echo json_encode([
                    "sucesso" => true,
                    "mensagem" => "Teste de diagnóstico atualizado com sucesso!",
                    "id" => $id
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // Inserir novo teste
                $stmt = $db->prepare("
                    INSERT INTO componentes_testes (
                        componente_id, nome, tipo, descricao, icone,
                        comando_ou_acao, resultado_esperado, tempo_estimado,
                        resultado_normal, resultado_anomalia
                    ) VALUES (
                        :componente_id, :nome, :tipo, :descricao, :icone,
                        :comando_ou_acao, :resultado_esperado, :tempo_estimado,
                        :resultado_normal, :resultado_anomalia
                    )
                ");
                $stmt->execute([
                    ':componente_id'      => $componenteId,
                    ':nome'               => $nome,
                    ':tipo'               => $tipo,
                    ':descricao'          => $descricao,
                    ':icone'              => $icone,
                    ':comando_ou_acao'    => $comandoOuAcao ?: null,
                    ':resultado_esperado' => $resultadoEsperado ?: null,
                    ':tempo_estimado'     => $tempoEstimado ?: null,
                    ':resultado_normal'   => $resultadoNormal,
                    ':resultado_anomalia' => $resultadoAnomalia
                ]);

                $novoId = (int)$db->lastInsertId();
                $db->commit();
                http_response_code(200);
                echo json_encode([
                    "sucesso" => true,
                    "mensagem" => "Teste de diagnóstico criado com sucesso!",
                    "id" => $novoId
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'excluir':
            $idRaw = $_POST['id'] ?? $_GET['id'] ?? null;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                http_response_code(400);
                echo json_encode(["sucesso" => false, "erro" => "ID inválido para exclusão."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Início da Transação Atômica ACID
            $db->beginTransaction();

            $stmtCheck = $db->prepare("SELECT id, nome FROM componentes_testes WHERE id = ?");
            $stmtCheck->execute([$id]);
            $teste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$teste) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Teste de diagnóstico não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmtDel = $db->prepare("DELETE FROM componentes_testes WHERE id = ?");
            $stmtDel->execute([$id]);

            $db->commit();
            http_response_code(200);
            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Teste de diagnóstico '{$teste['nome']}' excluído com sucesso!"
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Ação não suportada."], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro em admin_diagnosticos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Ocorreu um erro interno no servidor ao processar o teste de diagnóstico."
    ], JSON_UNESCAPED_UNICODE);
}
