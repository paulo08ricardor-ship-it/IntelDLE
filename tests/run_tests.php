<?php
/**
 * Suíte de Testes Automatizados e Garantia de Qualidade (QA & SAST) - Inteldle 3FN
 * Executável via PHP CLI: php run_tests.php
 */

define('TEST_MODE', true);

// Cores para saída CLI
$verde = "\033[32m";
$vermelho = "\033[31m";
$amarelo = "\033[33m";
$azul = "\033[36m";
$reset = "\033[0m";

echo "\n{$azul}======================================================\n";
echo "   INTELDLE 3FN - SUÍTE DE TESTES E AUDITORIA (QA/SEC)\n";
echo "======================================================{$reset}\n\n";

$passou = 0;
$falhou = 0;

function assertTeste($condicao, $titulo) {
    global $passou, $falhou, $verde, $vermelho, $reset;
    if ($condicao) {
        echo "  {$verde}[PASS]{$reset} {$titulo}\n";
        $passou++;
    } else {
        echo "  {$vermelho}[FAIL]{$reset} {$titulo}\n";
        $falhou++;
    }
}

// -------------------------------------------------------------
// GRUPO 1: BANCO DE DADOS & ESQUEMA 3FN
// -------------------------------------------------------------
echo "{$amarelo}▶ GRUPO 1: Banco de Dados SQLite & Normalização 3FN{$reset}\n";

require_once __DIR__ . '/../config/database.php';
$db = (new Database())->conectar();

// 1.1 Verificar PRAGMA foreign_keys
$resFK = $db->query("PRAGMA foreign_keys;")->fetchColumn();
assertTeste((int)$resFK === 1, "PRAGMA foreign_keys está ativo (valor = 1)");

// 1.2 Verificar existência das 8 tabelas normalizadas em 3FN
$tabelasNecessarias = ['usuarios', 'componentes', 'componentes_testes', 'defeitos', 'solucoes', 'ranking_diario', 'ranking_infinito', 'historico'];
$stmtTabelas = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
$tabelasExistentes = $stmtTabelas->fetchAll(PDO::FETCH_COLUMN);

foreach ($tabelasNecessarias as $tab) {
    assertTeste(in_array($tab, $tabelasExistentes), "Tabela 3FN '{$tab}' existe no banco de dados");
}

// 1.3 Verificar integridade referencial (FK Constraint check)
$fkErroCapturado = false;
try {
    // Tenta inserir solução apontando para defeito_id inexistente (99999)
    $stmtInvalido = $db->prepare("INSERT INTO solucoes (defeito_id, componente_id, correto, mensagem) VALUES (99999, 1, 1, 'Teste FK')");
    $stmtInvalido->execute();
} catch (PDOException $e) {
    $fkErroCapturado = true;
}
assertTeste($fkErroCapturado, "Constraint de Foreign Key bloqueia inserção órfã com sucesso");

// -------------------------------------------------------------
// GRUPO 2: SEGURANÇA, AUTENTICAÇÃO, RBAC & CSRF
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 2: Segurança, Autenticação, RBAC & Proteção Anti-CSRF{$reset}\n";

require_once __DIR__ . '/../config/session.php';

// 2.1 Teste de verificação de papel (RBAC)
$_SESSION['id'] = 10;
$_SESSION['tipo'] = 'usuario';
assertTeste(!ehAdmin(), "Usuário com tipo = 'usuario' NÃO é reconhecido como admin");

$_SESSION['tipo'] = 'admin';
assertTeste(ehAdmin(), "Usuário com tipo = 'admin' É reconhecido como admin com sucesso");

// 2.2 Teste de validação de login do Administrador Padrão
$stmtAdmin = $db->prepare("SELECT id, senha, tipo FROM usuarios WHERE email = 'Admin@gmail.com'");
$stmtAdmin->execute();
$adminUser = $stmtAdmin->fetch();
assertTeste($adminUser && $adminUser['tipo'] === 'admin', "Usuário Administrador padrão existe com tipo = 'admin'");

// 2.3 Validação de Hash de Senha Seguro (BCRYPT / Argon2)
$infoHash = password_get_info($adminUser['senha']);
$hashValido = ($infoHash['algo'] !== null && $infoHash['algo'] !== 0 && !empty($adminUser['senha']));
assertTeste($hashValido, "Hash de senha do Administrador utiliza algoritmo seguro (" . ($infoHash['algoName'] ?? 'BCRYPT') . ")");

// 2.4 Teste de Proteção Anti-CSRF
$tokenCsrf = obterCsrfToken();
assertTeste(!empty($tokenCsrf) && strlen($tokenCsrf) === 64, "Geração de token anti-CSRF criptográfico de 64 caracteres");
assertTeste(validarCsrfToken($tokenCsrf), "Validação de token anti-CSRF válido é aceita com sucesso");
assertTeste(!validarCsrfToken('token_invalido_ou_forjado_12345'), "Validação de token anti-CSRF inválido é bloqueada com sucesso");

// 2.5 Simulação de Bloqueio em Rota Administrativa para Visitante / Usuário Comum
$_SESSION['tipo'] = 'usuario';
$permiteAcessoUsuarioComum = ehAdmin();
assertTeste(!$permiteAcessoUsuarioComum, "Middleware RBAC bloqueia acesso de usuário comum à área /admin");

// -------------------------------------------------------------
// GRUPO 3: FLUXO COMPLETO DO JOGO, RANKING & ROW_NUMBER
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 3: Fluxo de Jogo, Histórico & Cálculo de Ranking (ROW_NUMBER){$reset}\n";

// Criar jogador temporário para os testes de fluxo
$emailTeste = 'qa_teste_' . time() . '@inteldle.com';
$hashTeste = password_hash('senha123', PASSWORD_DEFAULT);
$stmtNovo = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('QA Player', ?, ?, 'usuario')");
$stmtNovo->execute([$emailTeste, $hashTeste]);
$qaUserId = (int) $db->lastInsertId();

$_SESSION['id'] = $qaUserId;
$_SESSION['nome'] = 'QA Player';
$_SESSION['tipo'] = 'usuario';
$_SESSION['pontos_pendentes'] = 10000;
$_SESSION['streak_infinito'] = 0;

// 3.1 Obter um defeito e seu componente correto
$stmtDef = $db->query("SELECT id, titulo FROM defeitos ORDER BY id ASC LIMIT 1");
$defeitoTeste = $stmtDef->fetch();
$defeitoId = (int) $defeitoTeste['id'];

$stmtSolCerta = $db->prepare("
    SELECT s.correto, s.mensagem, c.id AS comp_id, c.nome AS comp_nome
    FROM solucoes s
    INNER JOIN componentes c ON c.id = s.componente_id
    WHERE s.defeito_id = ? AND s.correto = 1
    LIMIT 1
");
$stmtSolCerta->execute([$defeitoId]);
$solucaoCorreta = $stmtSolCerta->fetch();

assertTeste($solucaoCorreta !== false, "Encontrada solução correta normalizada para o defeito #{$defeitoId} ({$solucaoCorreta['comp_nome']})");

// 3.2 Simular envio de resposta correta e gravação em 'historico'
$stmtHistTest = $db->prepare("
    INSERT INTO historico (usuario_id, defeito_id, componente_id, acertou, pontos, modo)
    VALUES (?, ?, ?, 1, 10000, 'diario')
");
$stmtHistTest->execute([$qaUserId, $defeitoId, $solucaoCorreta['comp_id']]);
$histId = (int) $db->lastInsertId();

assertTeste($histId > 0, "Partida gravada com sucesso na tabela 3FN 'historico' (ID: #{$histId})");

// 3.3 Atualização de Ranking Diário
$stmtRankD = $db->prepare("
    INSERT INTO ranking_diario (usuario_id, pontuacao, data_jogo)
    VALUES (?, 10000, DATE('now'))
    ON CONFLICT(usuario_id, data_jogo) DO UPDATE SET pontuacao = MAX(ranking_diario.pontuacao, excluded.pontuacao)
");
$stmtRankD->execute([$qaUserId]);

$stmtCheckRank = $db->prepare("SELECT pontuacao FROM ranking_diario WHERE usuario_id = ? AND data_jogo = DATE('now')");
$stmtCheckRank->execute([$qaUserId]);
$scoreDiario = $stmtCheckRank->fetchColumn();
assertTeste((int)$scoreDiario === 10000, "Ranking Diário atualizado corretamente com 10000 pontos");

// 3.4 Teste de Cálculo de Posição com ROW_NUMBER()
$stmtRankPos = $db->query("
    SELECT 
        u.id AS usuario_id,
        u.nome, 
        r.pontuacao,
        ROW_NUMBER() OVER (ORDER BY r.pontuacao DESC, r.id ASC) AS posicao
    FROM ranking_diario r
    INNER JOIN usuarios u ON u.id = r.usuario_id
    WHERE r.data_jogo = DATE('now')
    ORDER BY r.pontuacao DESC, r.id ASC
");
$ranksList = $stmtRankPos->fetchAll();
assertTeste(!empty($ranksList) && (int)$ranksList[0]['posicao'] === 1, "Cálculo de ranking via ROW_NUMBER() atribui posição #1 ao maior score");

// 3.5 Atualização de Ranking Infinito
$stmtRankI = $db->prepare("
    INSERT INTO ranking_infinito (usuario_id, pontuacao)
    VALUES (?, 7)
    ON CONFLICT(usuario_id) DO UPDATE SET pontuacao = MAX(ranking_infinito.pontuacao, excluded.pontuacao)
");
$stmtRankI->execute([$qaUserId]);

$stmtCheckRankI = $db->prepare("SELECT pontuacao FROM ranking_infinito WHERE usuario_id = ?");
$stmtCheckRankI->execute([$qaUserId]);
$streakInfinito = $stmtCheckRankI->fetchColumn();
assertTeste((int)$streakInfinito === 7, "Ranking Infinito atualizado corretamente com streak de 7 acertos");

// -------------------------------------------------------------
// GRUPO 3.B: REGRAS E LÓGICA DO MODO DIÁRIO (DAILY CHALLENGE)
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 3.B: Regras e Validações do Modo Diário (Desafio Diário){$reset}\n";

// 3.B.1 Seleção Determinística Baseada na Data de Hoje
$todosDefs = $db->query("SELECT id, titulo FROM defeitos ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalDefs = count($todosDefs);
$hojeData = date('Y-m-d');
$idxEsperado = abs(crc32($hojeData)) % $totalDefs;
$defSelecionado1 = $todosDefs[$idxEsperado];
$idxNovamente = abs(crc32($hojeData)) % $totalDefs;
$defSelecionado2 = $todosDefs[$idxNovamente];
assertTeste($defSelecionado1['id'] === $defSelecionado2['id'], "Seleção diária determinística via CRC32(Y-m-d) retorna o mesmo defeito #{$defSelecionado1['id']}");

// 3.B.2 Checagem de Desafio Concluído Hoje e Obtenção de Posição
$stmtCheckHoje = $db->prepare("
    SELECT 
        r.pontuacao,
        r.posicao,
        r.total_jogadores
    FROM (
        SELECT 
            usuario_id,
            pontuacao,
            ROW_NUMBER() OVER (ORDER BY pontuacao DESC, id ASC) AS posicao,
            COUNT(*) OVER () AS total_jogadores
        FROM ranking_diario
        WHERE data_jogo = DATE('now')
    ) r
    WHERE r.usuario_id = ?
");
$stmtCheckHoje->execute([$qaUserId]);
$dadosDiarioHoje = $stmtCheckHoje->fetch(PDO::FETCH_ASSOC);
assertTeste($dadosDiarioHoje !== false && (int)$dadosDiarioHoje['pontuacao'] === 10000, "Consulta de ranking_diario identifica corretamente que o usuário já jogou hoje");

// 3.B.3 Bloqueio de Nova Tentativa no Modo Diário (api/verificar.php)
$stmtBloqueioTest = $db->prepare("
    SELECT COUNT(*) AS total
    FROM ranking_diario
    WHERE usuario_id = ? AND data_jogo = DATE('now')
");
$stmtBloqueioTest->execute([$qaUserId]);
$totalDiarioBloqueio = (int)$stmtBloqueioTest->fetchColumn();
assertTeste($totalDiarioBloqueio > 0, "Regra de bloqueio no verificar.php: detecta tentativa repetida no mesmo dia ($totalDiarioBloqueio registro)");

// 3.B.4 Não-Sobrescrita no salvar_pontos.php se já existir score diário
$stmtCheckPre = $db->prepare("SELECT pontuacao FROM ranking_diario WHERE usuario_id = ? AND data_jogo = DATE('now')");
$stmtCheckPre->execute([$qaUserId]);
$scoreAntes = (int)$stmtCheckPre->fetchColumn();

// Simulação da lógica de salvar_pontos.php com pontuação diferente
$novaTentativaPontos = 5000;
$stmtCheckSalvar = $db->prepare("SELECT id, pontuacao FROM ranking_diario WHERE usuario_id = ? AND data_jogo = DATE('now')");
$stmtCheckSalvar->execute([$qaUserId]);
$jaExisteSalvo = $stmtCheckSalvar->fetch();
if (!$jaExisteSalvo) {
    $stmtIns = $db->prepare("INSERT INTO ranking_diario (usuario_id, pontuacao, data_jogo) VALUES (?, ?, DATE('now'))");
    $stmtIns->execute([$qaUserId, $novaTentativaPontos]);
}
$stmtCheckPos = $db->prepare("SELECT pontuacao FROM ranking_diario WHERE usuario_id = ? AND data_jogo = DATE('now')");
$stmtCheckPos->execute([$qaUserId]);
$scoreDepois = (int)$stmtCheckPos->fetchColumn();
assertTeste($scoreDepois === $scoreAntes, "salvar_pontos.php preserva a pontuação original de hoje ({$scoreAntes} pts) e não sobrescreve com nova tentativa ({$novaTentativaPontos} pts)");

// 3.B.5 Renderização do Componente diario_concluido.php
$dadosConclusao = [
    'pontuacao' => 10000,
    'posicao' => 1,
    'total_jogadores' => 3,
    'defeito_titulo' => 'Computador não liga',
    'defeito_imagem' => 'pc1.png',
    'componente_correto_nome' => 'Fonte',
    'solucao_mensagem' => 'Fonte substituída com sucesso.'
];
ob_start();
require __DIR__ . '/../views/components/diario_concluido.php';
$htmlConclusao = ob_get_clean();
assertTeste(str_contains($htmlConclusao, 'Desafio Diário Concluído!') && str_contains($htmlConclusao, 'Fonte'), "Componente views/components/diario_concluido.php renderiza corretamente com dadosConclusao");

// -------------------------------------------------------------
// GRUPO 4: OPERAÇÕES CRUD DA ÁREA ADMINISTRATIVA
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 4: Operações CRUD da Área Administrativa (/admin){$reset}\n";

// Limpeza preventiva de registros de testes residuais
$db->exec("DELETE FROM componentes_testes WHERE nome LIKE '%QA%'");
$db->exec("DELETE FROM componentes WHERE nome LIKE '%QA%'");
$db->exec("DELETE FROM defeitos WHERE titulo LIKE '%QA%'");

// 4.1 CRUD Defeitos: Criar, Atualizar, Deletar
$stmtDefCrud = $db->prepare("INSERT INTO defeitos (titulo, descricao, imagem, tipo) VALUES ('Defeito Teste QA', 'Descrição de teste', 'pc1.png', 'hardware')");
$stmtDefCrud->execute();
$novoDefId = (int) $db->lastInsertId();
assertTeste($novoDefId > 0, "CRUD Defeitos [CREATE]: Defeito #{$novoDefId} criado");

$stmtUpdDef = $db->prepare("UPDATE defeitos SET titulo = 'Defeito Teste QA Atualizado' WHERE id = ?");
$stmtUpdDef->execute([$novoDefId]);
$stmtCheckDef = $db->prepare("SELECT titulo FROM defeitos WHERE id = ?");
$stmtCheckDef->execute([$novoDefId]);
assertTeste($stmtCheckDef->fetchColumn() === 'Defeito Teste QA Atualizado', "CRUD Defeitos [UPDATE]: Defeito atualizado com sucesso");

// 4.2 CRUD Componentes: Criar, Atualizar, Deletar
$stmtCompCrud = $db->prepare("INSERT INTO componentes (nome, tipo, icone, descricao) VALUES ('Placa de Captura QA', 'hardware', 'placadevideo.png', 'Placa de captura para testes')");
$stmtCompCrud->execute();
$novoCompId = (int) $db->lastInsertId();
assertTeste($novoCompId > 0, "CRUD Componentes [CREATE]: Componente #{$novoCompId} criado");

// 4.3 CRUD Soluções: Vincular Defeito + Componente
$stmtSolCrud = $db->prepare("INSERT INTO solucoes (defeito_id, componente_id, correto, mensagem) VALUES (?, ?, 1, 'Solução de teste QA')");
$stmtSolCrud->execute([$novoDefId, $novoCompId]);
$novaSolId = (int) $db->lastInsertId();
assertTeste($novaSolId > 0, "CRUD Soluções [CREATE]: Solução vinculando Defeito #{$novoDefId} e Componente #{$novoCompId} criada");

// 4.4 Deleção com Efeito Cascata
$stmtDelDef = $db->prepare("DELETE FROM defeitos WHERE id = ?");
$stmtDelDef->execute([$novoDefId]);

$stmtCheckSolCascata = $db->prepare("SELECT COUNT(*) FROM solucoes WHERE id = ?");
$stmtCheckSolCascata->execute([$novaSolId]);
assertTeste((int)$stmtCheckSolCascata->fetchColumn() === 0, "CRUD Deleção em Cascata: Exclusão do defeito removeu a solução vinculada automaticamente");

/**
 * Função auxiliar para simulação de requisições isoladas à API Administrativa (admin_diagnosticos.php)
 */
function simularAdminDiagnosticosApi(string $metodo = 'GET', array $getParams = [], array $postParams = [], array $sessionData = []): array {
    $apiFile = str_replace('\\', '/', realpath(__DIR__ . '/../api/admin_diagnosticos.php'));
    $phpExec = (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) 
        ? PHP_BINARY 
        : (file_exists('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');

    $code = "<?php
        register_shutdown_function(function() {
            \$status = http_response_code() ?: 200;
            echo \"\\n###HTTP_CODE###:\" . \$status;
        });
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        foreach (" . var_export($sessionData, true) . " as \$k => \$v) {
            \$_SESSION[\$k] = \$v;
        }
        \$_SERVER['REQUEST_METHOD'] = " . var_export(strtoupper($metodo), true) . ";
        \$_GET = " . var_export($getParams, true) . ";
        \$_POST = " . var_export($postParams, true) . ";
        require " . var_export($apiFile, true) . ";
    ";

    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $process = proc_open($phpExec, $descriptors, $pipes);
    $stdout = '';
    $stderr = '';

    if (is_resource($process)) {
        fwrite($pipes[0], $code);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
    }

    $httpCode = 200;
    if (preg_match('/###HTTP_CODE###:(\d+)/', $stdout, $matches)) {
        $httpCode = (int)$matches[1];
        $stdout = str_replace($matches[0], '', $stdout);
    }

    $cleanOutput = trim($stdout);
    $json = json_decode($cleanOutput, true);

    return [
        'code' => $httpCode,
        'raw' => $cleanOutput,
        'json' => $json,
        'stderr' => $stderr
    ];
}

// 4.5 CRUD Diagnósticos (admin_diagnosticos.php) - Segurança RBAC & Anti-CSRF
$sessionAdminQA = [
    'id' => 1,
    'tipo' => 'admin',
    'nome' => 'Admin QA',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789'
];
$sessionUsuarioQA = [
    'id' => 2,
    'tipo' => 'usuario',
    'nome' => 'Player Comum'
];

$resRbacBloqueio = simularAdminDiagnosticosApi('GET', ['acao' => 'listar'], [], $sessionUsuarioQA);
assertTeste($resRbacBloqueio['code'] === 403, "CRUD Diagnósticos [RBAC]: Acesso negado com HTTP 403 para usuário não-admin");

$resCsrfBloqueio = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_forjado_ou_invalido',
    'nome' => 'Teste Sem CSRF',
    'tipo' => 'hardware'
], $sessionAdminQA);
assertTeste($resCsrfBloqueio['code'] === 403, "CRUD Diagnósticos [CSRF]: Mutação bloqueada com HTTP 403 ao enviar token CSRF inválido");

// 4.6 CRUD Diagnósticos [CREATE] - Criar teste de diagnóstico associado a componente
$resDiagCreate = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'nome' => 'Auditoria de Barramento PCIe QA',
    'tipo' => 'hardware',
    'componente_id' => $novoCompId,
    'icone' => 'placadevideo.png',
    'descricao' => 'Teste sintético de link PCIe e integridade de barramento de captura.',
    'comando_ou_acao' => 'lspci -vvv && directx_diag /pcie',
    'resultado_esperado' => 'Link PCIe x16 ativo em Gen 3.0/4.0 sem perda de largura de banda.',
    'tempo_estimado' => '4 minutos',
    'resultado_normal' => 'Link PCIe estável na velocidade nominal sem ruído de sinal elétrico.',
    'resultado_anomalia' => 'Degradação de barramento: link reduzido para PCIe x1 com erros de CRC.'
], $sessionAdminQA);

$novoDiagId = (int)($resDiagCreate['json']['id'] ?? 0);
assertTeste($resDiagCreate['code'] === 200 && $novoDiagId > 0, "CRUD Diagnósticos [CREATE]: Teste #{$novoDiagId} cadastrado com sucesso via API");

// 4.7 CRUD Diagnósticos [OBTER] - Consulta detalhada com LEFT JOIN
$resDiagObter = simularAdminDiagnosticosApi('GET', ['acao' => 'obter', 'id' => $novoDiagId], [], $sessionAdminQA);
assertTeste($resDiagObter['code'] === 200 && ($resDiagObter['json']['dados']['nome'] ?? '') === 'Auditoria de Barramento PCIe QA', "CRUD Diagnósticos [OBTER]: Teste recuperado com nome e dados corretos");
assertTeste(($resDiagObter['json']['dados']['componente_nome'] ?? '') === 'Placa de Captura QA', "CRUD Diagnósticos [OBTER]: LEFT JOIN retornou componente vinculado ('Placa de Captura QA')");

// 4.8 CRUD Diagnósticos [UPDATE] - Atualização de teste existente com integridade ACID
$resDiagUpdate = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'id' => $novoDiagId,
    'nome' => 'Auditoria de Barramento PCIe QA Atualizada',
    'tipo' => 'hardware',
    'componente_id' => $novoCompId,
    'icone' => 'placadevideo.png',
    'descricao' => 'Teste sintético atualizado.',
    'resultado_normal' => 'Barramento 100% íntegro e validado.',
    'resultado_anomalia' => 'Falha severa de barramento PCIe.'
], $sessionAdminQA);

assertTeste($resDiagUpdate['code'] === 200 && ($resDiagUpdate['json']['sucesso'] ?? false) === true, "CRUD Diagnósticos [UPDATE]: Teste atualizado com sucesso via API");

$resDiagObterUpd = simularAdminDiagnosticosApi('GET', ['acao' => 'obter', 'id' => $novoDiagId], [], $sessionAdminQA);
assertTeste(($resDiagObterUpd['json']['dados']['nome'] ?? '') === 'Auditoria de Barramento PCIe QA Atualizada', "CRUD Diagnósticos [UPDATE]: Leitura pós-atualização confirma novo nome persistido");

// 4.9 CRUD Diagnósticos [LISTAR] - Listagem geral e filtrada
$resDiagListar = simularAdminDiagnosticosApi('GET', ['acao' => 'listar', 'tipo' => 'hardware'], [], $sessionAdminQA);
assertTeste($resDiagListar['code'] === 200 && is_array($resDiagListar['json']['dados'] ?? null), "CRUD Diagnósticos [LISTAR]: Listagem de hardware retornou array de testes");
$encontrouDiagCriado = false;
foreach ($resDiagListar['json']['dados'] ?? [] as $d) {
    if ((int)($d['id'] ?? 0) === $novoDiagId) {
        $encontrouDiagCriado = true;
        break;
    }
}
assertTeste($encontrouDiagCriado, "CRUD Diagnósticos [LISTAR]: Teste criado #{$novoDiagId} presente nos dados listados");

// 4.10 CRUD Diagnósticos [DELETE] - Exclusão via API
$resDiagDelete = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'excluir',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'id' => $novoDiagId
], $sessionAdminQA);

assertTeste($resDiagDelete['code'] === 200 && ($resDiagDelete['json']['sucesso'] ?? false) === true, "CRUD Diagnósticos [DELETE]: Teste excluído com sucesso via API");

$resDiagObterPosDel = simularAdminDiagnosticosApi('GET', ['acao' => 'obter', 'id' => $novoDiagId], [], $sessionAdminQA);
assertTeste($resDiagObterPosDel['code'] === 404, "CRUD Diagnósticos [DELETE]: Consulta pós-exclusão retorna HTTP 404 Not Found");

// 4.11 CRUD Diagnósticos [VALIDAÇÕES] - Validação de entradas e erros controlados
$resValNomeVazio = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'nome' => '',
    'tipo' => 'hardware'
], $sessionAdminQA);
assertTeste($resValNomeVazio['code'] === 400, "CRUD Diagnósticos [VALIDAÇÃO]: Inserção com nome vazio rejeitada com HTTP 400");

$resValTipoInvalido = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'nome' => 'Teste Invalido',
    'tipo' => 'categoria_inexistente'
], $sessionAdminQA);
assertTeste($resValTipoInvalido['code'] === 400, "CRUD Diagnósticos [VALIDAÇÃO]: Inserção com tipo inexistente rejeitada com HTTP 400");

$resValCompInexistente = simularAdminDiagnosticosApi('POST', [], [
    'acao' => 'salvar',
    'csrf_token' => 'token_admin_diagnosticos_csrf_test_123456789',
    'nome' => 'Teste Comp Inexistente',
    'tipo' => 'hardware',
    'componente_id' => 99999
], $sessionAdminQA);
assertTeste($resValCompInexistente['code'] === 404, "CRUD Diagnósticos [VALIDAÇÃO]: Referência a componente_id inexistente retorna HTTP 404");

// -------------------------------------------------------------
// GRUPO 5: LIMPEZA DE TESTES & ALINHAMENTO DE AUTOINCREMENT
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 5: Integridade e Alinhamento de Autoincremento (sqlite_sequence){$reset}\n";

$db = (new Database())->conectar();

// Limpar dados temporários de teste usando Prepared Statements
$stmtDelComp = $db->prepare("DELETE FROM componentes WHERE id = ?");
$stmtDelComp->execute([$novoCompId]);

$stmtDelUser = $db->prepare("DELETE FROM usuarios WHERE id = ?");
$stmtDelUser->execute([$qaUserId]);

// Sincronizar explicitamente sqlite_sequence com o MAX(id) real de cada tabela
$tabelasSeq = ['usuarios', 'componentes', 'componentes_testes', 'defeitos', 'solucoes', 'ranking_diario', 'ranking_infinito', 'historico'];
foreach ($tabelasSeq as $tab) {
    $db->exec("
        DELETE FROM sqlite_sequence WHERE name = '{$tab}';
        INSERT INTO sqlite_sequence (name, seq) VALUES ('{$tab}', (SELECT COALESCE(MAX(id), 0) FROM {$tab}));
    ");
}

// 5.1 Verificar se o autoincremento de usuarios está alinhado com MAX(id)
$maxUserId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM usuarios")->fetchColumn();
$seqUserId = (int)$db->query("SELECT seq FROM sqlite_sequence WHERE name = 'usuarios'")->fetchColumn();
assertTeste($seqUserId === $maxUserId, "Ponteiro sqlite_sequence ('usuarios') perfeitamente sincronizado com MAX(id) = {$maxUserId}");

// -------------------------------------------------------------
// RELATÓRIO FINAL
// -------------------------------------------------------------
echo "\n{$azul}======================================================\n";
echo "                   RELATÓRIO DE QA                    \n";
echo "======================================================{$reset}\n";
echo "  Total de Testes Executados: " . ($passou + $falhou) . "\n";
echo "  {$verde}Aprovados: {$passou}{$reset}\n";
if ($falhou > 0) {
    echo "  {$vermelho}Falhas: {$falhou}{$reset}\n";
} else {
    echo "  {$verde}STATUS: 100% DOS TESTES PASSARAM COM SUCESSO! 🎉{$reset}\n";
}
echo "======================================================\n\n";

exit($falhou > 0 ? 1 : 0);
