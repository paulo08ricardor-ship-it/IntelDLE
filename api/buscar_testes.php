<?php
/**
 * Endpoint da API: Buscar Testes e Diagnósticos
 * Central de Diagnóstico e Testes - Inteldle
 * 
 * Suporta GET com filtros opcionais:
 * - ?tipo=hardware|software|rede|seguranca|geral|todos (Validação estrita)
 * - ?id=N (Busca detalhada por ID específico)
 * - ?defeito_id=N (Simulação de diagnóstico realista baseado no defeito ativo)
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Validação de Método HTTP (Apenas GET é permitido)
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Método não permitido. Apenas o método GET é suportado."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawTipo = $_GET['tipo'] ?? null;
$rawId = $_GET['id'] ?? null;
$rawDefeitoId = $_GET['defeito_id'] ?? $_GET['defeito'] ?? null;

try {
    $db = (new Database())->conectar();

    // Validação do defeito_id (se fornecido)
    $defeitoId = null;
    $componenteDefeituosoId = null;

    if ($rawDefeitoId !== null) {
        $validDef = filter_var($rawDefeitoId, FILTER_VALIDATE_INT);
        if ($validDef === false || $validDef <= 0) {
            http_response_code(400);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Parâmetro 'defeito_id' inválido. Deve ser um número inteiro positivo."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $defeitoId = (int)$validDef;

        // Verifica se o defeito existe
        $stmtCheckDef = $db->prepare("SELECT id FROM defeitos WHERE id = :defeito_id");
        $stmtCheckDef->execute([':defeito_id' => $defeitoId]);
        if (!$stmtCheckDef->fetch()) {
            http_response_code(404);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Defeito informado não foi encontrado."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Descobre o componente defeituoso associado a esse defeito na tabela 3FN solucoes
        $stmtSol = $db->prepare("
            SELECT componente_id 
            FROM solucoes 
            WHERE defeito_id = :defeito_id AND correto = 1 
            LIMIT 1
        ");
        $stmtSol->execute([':defeito_id' => $defeitoId]);
        $compId = $stmtSol->fetchColumn();
        if ($compId !== false) {
            $componenteDefeituosoId = (int)$compId;
        }
    }

    /**
     * Função formatadora para enriquecer o teste com anomalia, status e resultado técnico
     */
    $formatarTeste = function (array $teste) use ($defeitoId, $componenteDefeituosoId): array {
        $testeCompId = isset($teste['componente_id']) && $teste['componente_id'] !== null ? (int)$teste['componente_id'] : null;
        
        $anomalia = false;
        $status = 'pronto';
        $resultadoTecnico = $teste['resultado_normal'] ?? ($teste['resultado_esperado'] ?? 'Operação nominal.');

        if ($defeitoId !== null) {
            if ($componenteDefeituosoId !== null && $testeCompId !== null && $testeCompId === $componenteDefeituosoId) {
                $anomalia = true;
                $status = 'alerta';
                $resultadoTecnico = $teste['resultado_anomalia'] ?? 'Anomalia detectada no componente.';
            } else {
                $anomalia = false;
                $status = 'ok';
                $resultadoTecnico = $teste['resultado_normal'] ?? ($teste['resultado_esperado'] ?? 'Componente saudável.');
            }
        }

        $teste['id'] = (int)$teste['id'];
        $teste['componente_id'] = $testeCompId;
        $teste['anomalia'] = $anomalia;
        $teste['status'] = $status;
        $teste['resultado_tecnico'] = $resultadoTecnico;
        $teste['status_sugerido'] = $anomalia ? 'anomalia' : 'saudavel';

        return $teste;
    };

    // Caso 1: Busca por ID específico de teste
    if ($rawId !== null) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Parâmetro 'id' inválido. Deve ser um número inteiro positivo."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, componente_id, nome, tipo, descricao, icone, comando_ou_acao, resultado_esperado, tempo_estimado, resultado_normal, resultado_anomalia
            FROM componentes_testes
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $teste = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teste) {
            http_response_code(404);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Teste de diagnóstico não encontrado para o ID informado."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $teste = $formatarTeste($teste);

        echo json_encode([
            "sucesso" => true,
            "dados" => $teste
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Caso 2: Filtro por tipo ('hardware', 'software', 'todos') ou listagem geral
    $tipo = null;
    if ($rawTipo !== null) {
        $tipo = trim(strtolower((string)$rawTipo));
        $tiposValidos = ['hardware', 'software', 'rede', 'seguranca', 'geral', 'todos'];
        if (!in_array($tipo, $tiposValidos, true)) {
            http_response_code(400);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Parâmetro 'tipo' inválido. Valores aceitos: 'hardware', 'software', 'rede', 'seguranca', 'geral' ou 'todos'."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($tipo !== null && $tipo !== 'todos') {
        $stmt = $db->prepare("
            SELECT id, componente_id, nome, tipo, descricao, icone, comando_ou_acao, resultado_esperado, tempo_estimado, resultado_normal, resultado_anomalia
            FROM componentes_testes
            WHERE tipo = :tipo
            ORDER BY id ASC
        ");
        $stmt->execute([':tipo' => $tipo]);
        $testes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 'todos' ou listagem sem filtro
        $stmt = $db->query("
            SELECT id, componente_id, nome, tipo, descricao, icone, comando_ou_acao, resultado_esperado, tempo_estimado, resultado_normal, resultado_anomalia
            FROM componentes_testes
            ORDER BY tipo ASC, id ASC
        ");
        $testes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Enriquece todos os testes da lista
    $testesFormatados = array_map($formatarTeste, $testes);

    echo json_encode([
        "sucesso" => true,
        "tipo" => $tipo ?? 'todos',
        "defeito_id" => $defeitoId,
        "total" => count($testesFormatados),
        "dados" => $testesFormatados
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("Erro em buscar_testes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro interno no servidor ao carregar testes de diagnóstico."
    ], JSON_UNESCAPED_UNICODE);
}
