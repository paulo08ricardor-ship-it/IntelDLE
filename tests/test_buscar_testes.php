<?php
/**
 * Suíte de Testes Automatizados para a API de Testes de Diagnóstico
 * Endpoint: api/buscar_testes.php
 * Tabela: componentes_testes
 * 
 * Executável via PHP CLI: php tests/test_buscar_testes.php
 */

define('TEST_MODE', true);

// Cores para saída CLI no terminal
$verde = "\033[32m";
$vermelho = "\033[31m";
$amarelo = "\033[33m";
$azul = "\033[36m";
$reset = "\033[0m";

echo "\n{$azul}==================================================================\n";
echo "   INTELDLE - SUÍTE DE TESTES: CENTRAL DE DIAGNÓSTICO E TESTES\n";
echo "=================================================================={$reset}\n\n";

$passou = 0;
$falhou = 0;

function assertTeste($condicao, $titulo, $detalhe = '') {
    global $passou, $falhou, $verde, $vermelho, $reset;
    if ($condicao) {
        echo "  {$verde}[PASS]{$reset} {$titulo}\n";
        $passou++;
    } else {
        echo "  {$vermelho}[FAIL]{$reset} {$titulo}";
        if ($detalhe) {
            echo " ({$detalhe})";
        }
        echo "\n";
        $falhou++;
    }
}

// -------------------------------------------------------------
// GRUPO 1: VERIFICAÇÃO DE ESTRUTURA E INTEGRIDADE NO BANCO SQLite
// -------------------------------------------------------------
echo "{$amarelo}▶ GRUPO 1: Banco de Dados SQLite & Tabela componentes_testes{$reset}\n";

require_once __DIR__ . '/../config/database.php';
$db = (new Database())->conectar();

// 1.1 Existência da tabela
$stmtTabelas = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='componentes_testes'");
$tabelaExiste = (bool)$stmtTabelas->fetchColumn();
assertTeste($tabelaExiste, "Tabela 'componentes_testes' existe no banco SQLite");

// 1.2 Verificação de colunas obrigatórias
$colunasInfo = $db->query("PRAGMA table_info(componentes_testes)")->fetchAll(PDO::FETCH_ASSOC);
$nomesColunas = array_column($colunasInfo, 'name');
$colunasEsperadas = [
    'id',
    'componente_id',
    'nome',
    'tipo',
    'descricao',
    'icone',
    'comando_ou_acao',
    'resultado_esperado',
    'tempo_estimado',
    'resultado_normal',
    'resultado_anomalia'
];

foreach ($colunasEsperadas as $col) {
    assertTeste(in_array($col, $nomesColunas), "Coluna '{$col}' está presente na tabela componentes_testes");
}

// 1.3 Verificação de Constraint CHECK para as 5 categorias
$tableSql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='componentes_testes'")->fetchColumn();
$possuiCheckCategorias = str_contains($tableSql, "'hardware'") &&
                         str_contains($tableSql, "'software'") &&
                         str_contains($tableSql, "'rede'") &&
                         str_contains($tableSql, "'seguranca'") &&
                         str_contains($tableSql, "'geral'");
assertTeste($possuiCheckCategorias, "Constraint CHECK da tabela suporta as 5 categorias ('hardware', 'software', 'rede', 'seguranca', 'geral')");

// 1.4 Quantidade de testes cadastrados por categoria
$totalHW = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'hardware'")->fetchColumn();
assertTeste($totalHW >= 10, "Pelo menos 10 testes de Hardware cadastrados (Encontrados: {$totalHW})");

$totalSW = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'software'")->fetchColumn();
assertTeste($totalSW >= 4, "Pelo menos 4 testes de Software cadastrados (Encontrados: {$totalSW})");

$totalRede = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'rede'")->fetchColumn();
assertTeste($totalRede >= 2, "Pelo menos 2 testes de Rede cadastrados (Encontrados: {$totalRede})");

$totalSeg = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'seguranca'")->fetchColumn();
assertTeste($totalSeg >= 2, "Pelo menos 2 testes de Segurança cadastrados (Encontrados: {$totalSeg})");

$totalGeral = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'geral'")->fetchColumn();
assertTeste($totalGeral >= 2, "Pelo menos 2 testes Gerais cadastrados (Encontrados: {$totalGeral})");

$totalTodos = (int)$db->query("SELECT COUNT(*) FROM componentes_testes")->fetchColumn();
assertTeste($totalTodos >= 20, "Total geral de testes cadastrados >= 20 (Encontrados: {$totalTodos})");

// 1.5 Todos os testes possuem componente_id válido associado a componentes(id)
$testesSemComp = (int)$db->query("
    SELECT COUNT(*) 
    FROM componentes_testes ct
    LEFT JOIN componentes c ON c.id = ct.componente_id
    WHERE ct.componente_id IS NULL OR c.id IS NULL
")->fetchColumn();
assertTeste($testesSemComp === 0, "Todos os 20 testes possuem vínculo de chave estrangeira válido em componentes(id)");

// 1.6 Todos os testes possuem mensagens de resultado_normal e resultado_anomalia preenchidas
$testesSemResultados = (int)$db->query("
    SELECT COUNT(*) 
    FROM componentes_testes 
    WHERE resultado_normal IS NULL OR TRIM(resultado_normal) = '' 
       OR resultado_anomalia IS NULL OR TRIM(resultado_anomalia) = ''
")->fetchColumn();
assertTeste($testesSemResultados === 0, "Todos os testes possuem 'resultado_normal' e 'resultado_anomalia' técnicos preenchidos");

// -------------------------------------------------------------
// GRUPO 2: TESTES BÁSICOS DO ENDPOINT api/buscar_testes.php
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 2: Validação Funcional Básica do Endpoint api/buscar_testes.php{$reset}\n";

/**
 * Executa requisição isolada ao endpoint api/buscar_testes.php via STDIN do processo PHP
 */
function simularRequisicaoApi(string $metodo = 'GET', array $paramsGet = []): array {
    $apiFile = str_replace('\\', '/', realpath(__DIR__ . '/../api/buscar_testes.php'));
    $phpExec = (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) 
        ? PHP_BINARY 
        : (file_exists('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');

    $code = "<?php
        register_shutdown_function(function() {
            \$status = http_response_code() ?: 200;
            echo \"\\n###HTTP_CODE###:\" . \$status;
        });
        \$_SERVER['REQUEST_METHOD'] = " . var_export(strtoupper($metodo), true) . ";
        \$_GET = " . var_export($paramsGet, true) . ";
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

// 2.1 Caso de Sucesso: Listar apenas testes de Hardware
$resHW = simularRequisicaoApi('GET', ['tipo' => 'hardware']);
assertTeste($resHW['code'] === 200, "GET ?tipo=hardware retorna HTTP 200 OK");
assertTeste(isset($resHW['json']['sucesso']) && $resHW['json']['sucesso'] === true, "GET ?tipo=hardware retorna campo 'sucesso' = true");
assertTeste(isset($resHW['json']['dados']) && is_array($resHW['json']['dados']), "GET ?tipo=hardware retorna array de 'dados'");
assertTeste(count($resHW['json']['dados'] ?? []) >= 10, "GET ?tipo=hardware retorna pelo menos 10 registros (" . count($resHW['json']['dados'] ?? []) . " encontrados)");

$apenasHW = true;
foreach ($resHW['json']['dados'] ?? [] as $item) {
    if (($item['tipo'] ?? '') !== 'hardware') {
        $apenasHW = false;
        break;
    }
}
assertTeste($apenasHW, "Todos os itens retornados por ?tipo=hardware possuem tipo === 'hardware'");

// 2.2 Caso de Sucesso: Listar apenas testes de Software
$resSW = simularRequisicaoApi('GET', ['tipo' => 'software']);
assertTeste($resSW['code'] === 200, "GET ?tipo=software retorna HTTP 200 OK");
assertTeste(isset($resSW['json']['sucesso']) && $resSW['json']['sucesso'] === true, "GET ?tipo=software retorna campo 'sucesso' = true");
assertTeste(isset($resSW['json']['dados']) && is_array($resSW['json']['dados']), "GET ?tipo=software retorna array de 'dados'");
assertTeste(count($resSW['json']['dados'] ?? []) >= 4, "GET ?tipo=software retorna pelo menos 4 registros (" . count($resSW['json']['dados'] ?? []) . " encontrados)");

$apenasSW = true;
foreach ($resSW['json']['dados'] ?? [] as $item) {
    if (($item['tipo'] ?? '') !== 'software') {
        $apenasSW = false;
        break;
    }
}
assertTeste($apenasSW, "Todos os itens retornados por ?tipo=software possuem tipo === 'software'");

// 2.3 Caso de Sucesso: Listar apenas testes de Rede
$resRede = simularRequisicaoApi('GET', ['tipo' => 'rede']);
assertTeste($resRede['code'] === 200, "GET ?tipo=rede retorna HTTP 200 OK");
assertTeste(isset($resRede['json']['sucesso']) && $resRede['json']['sucesso'] === true, "GET ?tipo=rede retorna campo 'sucesso' = true");
assertTeste(isset($resRede['json']['dados']) && is_array($resRede['json']['dados']), "GET ?tipo=rede retorna array de 'dados'");
assertTeste(count($resRede['json']['dados'] ?? []) >= 2, "GET ?tipo=rede retorna pelo menos 2 registros (" . count($resRede['json']['dados'] ?? []) . " encontrados)");

$apenasRede = true;
foreach ($resRede['json']['dados'] ?? [] as $item) {
    if (($item['tipo'] ?? '') !== 'rede') {
        $apenasRede = false;
        break;
    }
}
assertTeste($apenasRede, "Todos os itens retornados por ?tipo=rede possuem tipo === 'rede' (sem testes de hardware)");

// 2.4 Caso de Sucesso: Listar apenas testes de Segurança
$resSeg = simularRequisicaoApi('GET', ['tipo' => 'seguranca']);
assertTeste($resSeg['code'] === 200, "GET ?tipo=seguranca retorna HTTP 200 OK");
assertTeste(isset($resSeg['json']['sucesso']) && $resSeg['json']['sucesso'] === true, "GET ?tipo=seguranca retorna campo 'sucesso' = true");
assertTeste(isset($resSeg['json']['dados']) && is_array($resSeg['json']['dados']), "GET ?tipo=seguranca retorna array de 'dados'");
assertTeste(count($resSeg['json']['dados'] ?? []) >= 2, "GET ?tipo=seguranca retorna pelo menos 2 registros (" . count($resSeg['json']['dados'] ?? []) . " encontrados)");

$apenasSeg = true;
foreach ($resSeg['json']['dados'] ?? [] as $item) {
    if (($item['tipo'] ?? '') !== 'seguranca') {
        $apenasSeg = false;
        break;
    }
}
assertTeste($apenasSeg, "Todos os itens retornados por ?tipo=seguranca possuem tipo === 'seguranca' (sem testes de hardware)");

// 2.5 Caso de Sucesso: Listar apenas testes Gerais
$resGeral = simularRequisicaoApi('GET', ['tipo' => 'geral']);
assertTeste($resGeral['code'] === 200, "GET ?tipo=geral retorna HTTP 200 OK");
assertTeste(isset($resGeral['json']['sucesso']) && $resGeral['json']['sucesso'] === true, "GET ?tipo=geral retorna campo 'sucesso' = true");
assertTeste(isset($resGeral['json']['dados']) && is_array($resGeral['json']['dados']), "GET ?tipo=geral retorna array de 'dados'");
assertTeste(count($resGeral['json']['dados'] ?? []) >= 2, "GET ?tipo=geral retorna pelo menos 2 registros (" . count($resGeral['json']['dados'] ?? []) . " encontrados)");

$apenasGeral = true;
foreach ($resGeral['json']['dados'] ?? [] as $item) {
    if (($item['tipo'] ?? '') !== 'geral') {
        $apenasGeral = false;
        break;
    }
}
assertTeste($apenasGeral, "Todos os itens retornados por ?tipo=geral possuem tipo === 'geral' (sem testes de hardware)");

// 2.6 Caso de Sucesso: Listar todos os testes (sem parâmetro de tipo ou tipo=todos)
$resTodos = simularRequisicaoApi('GET', []);
assertTeste($resTodos['code'] === 200 && ($resTodos['json']['total'] ?? 0) >= 20, "GET sem parâmetros retorna todos os testes (Total: " . ($resTodos['json']['total'] ?? 0) . ")");
assertTeste(isset($resTodos['json']['dados'][0]['status']) && $resTodos['json']['dados'][0]['status'] === 'pronto', "GET sem defeito_id retorna status = 'pronto' e anomalia = false");

$resTodosParam = simularRequisicaoApi('GET', ['tipo' => 'todos']);
assertTeste($resTodosParam['code'] === 200 && ($resTodosParam['json']['total'] ?? 0) === ($resTodos['json']['total'] ?? 0), "GET ?tipo=todos retorna a mesma quantidade total de testes");

$tiposEncontradosEmTodos = array_unique(array_column($resTodos['json']['dados'] ?? [], 'tipo'));
$temTodasCategorias = in_array('hardware', $tiposEncontradosEmTodos) &&
                      in_array('software', $tiposEncontradosEmTodos) &&
                      in_array('rede', $tiposEncontradosEmTodos) &&
                      in_array('seguranca', $tiposEncontradosEmTodos) &&
                      in_array('geral', $tiposEncontradosEmTodos);
assertTeste($temTodasCategorias, "GET ?tipo=todos retorna itens de todas as 5 categorias (hardware, software, rede, seguranca, geral)");

// 2.7 Caso de Sucesso: Obter teste por ID específico
$resId1 = simularRequisicaoApi('GET', ['id' => '1']);
assertTeste($resId1['code'] === 200, "GET ?id=1 retorna HTTP 200 OK");
assertTeste(isset($resId1['json']['dados']['id']) && (int)$resId1['json']['dados']['id'] === 1, "GET ?id=1 retorna os dados do teste com ID 1");
assertTeste(!empty($resId1['json']['dados']['nome']), "GET ?id=1 contém o nome do teste ('" . ($resId1['json']['dados']['nome'] ?? '') . "')");

// 2.8 Caso de Erro: Parâmetro tipo inválido
$resTipoInvalido = simularRequisicaoApi('GET', ['tipo' => 'categoria_inexistente']);
assertTeste($resTipoInvalido['code'] === 400, "GET ?tipo=categoria_inexistente retorna HTTP 400 Bad Request");
assertTeste(isset($resTipoInvalido['json']['sucesso']) && $resTipoInvalido['json']['sucesso'] === false, "GET ?tipo=categoria_inexistente retorna 'sucesso' = false");

// 2.9 Caso de Erro: Tentativa de SQL Injection no parâmetro tipo
$resSqlInj = simularRequisicaoApi('GET', ['tipo' => "' OR '1'='1"]);
assertTeste($resSqlInj['code'] === 400, "Tentativa de injeção SQL no parâmetro tipo é sanitizada e bloqueada com HTTP 400");

// 2.10 Caso de Erro: ID inexistente
$resIdInexistente = simularRequisicaoApi('GET', ['id' => '99999']);
assertTeste($resIdInexistente['code'] === 404, "GET ?id=99999 retorna HTTP 404 Not Found");
assertTeste(isset($resIdInexistente['json']['sucesso']) && $resIdInexistente['json']['sucesso'] === false, "GET ?id=99999 retorna 'sucesso' = false");

// 2.11 Caso de Erro: ID com formato inválido (não inteiro)
$resIdNaoNumero = simularRequisicaoApi('GET', ['id' => 'abc']);
assertTeste($resIdNaoNumero['code'] === 400, "GET ?id=abc retorna HTTP 400 Bad Request");

// 2.12 Caso de Erro: Método HTTP não permitido (POST)
$resPost = simularRequisicaoApi('POST', []);
assertTeste($resPost['code'] === 405, "POST /buscar_testes.php retorna HTTP 405 Method Not Allowed");
assertTeste(isset($resPost['json']['sucesso']) && $resPost['json']['sucesso'] === false, "POST retorna 'sucesso' = false com mensagem de erro controlada");

// -------------------------------------------------------------
// GRUPO 3: TESTES COM DEFEITO ATIVO (DIAGNÓSTICO REALISTA POR CENÁRIO)
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 3: Diagnósticos Realistas Conforme Defeito Ativo (defeito_id){$reset}\n";

// 3.1 CENÁRIO DEFEITO #1: "Computador não liga" (Solução Correta: Fonte)
$resDef1 = simularRequisicaoApi('GET', ['defeito_id' => '1']);
assertTeste($resDef1['code'] === 200, "GET ?defeito_id=1 retorna HTTP 200 OK");
assertTeste(isset($resDef1['json']['sucesso']) && $resDef1['json']['sucesso'] === true, "GET ?defeito_id=1 retorna 'sucesso' = true");

$testeFonteHwDef1 = null;
$testePostGeralDef1 = null;
$testeRamDef1 = null;
$testeCpuDef1 = null;

foreach ($resDef1['json']['dados'] ?? [] as $t) {
    $nomeLower = mb_strtolower($t['nome'], 'UTF-8');
    if ($t['tipo'] === 'hardware' && (str_contains($nomeLower, 'fonte') || str_contains($nomeLower, 'psu'))) {
        $testeFonteHwDef1 = $t;
    }
    if ($t['tipo'] === 'geral' && (str_contains($nomeLower, 'post') || str_contains($nomeLower, 'barramentos'))) {
        $testePostGeralDef1 = $t;
    }
    if (str_contains($nomeLower, 'ram') || str_contains($nomeLower, 'memória')) {
        $testeRamDef1 = $t;
    }
    if (str_contains($nomeLower, 'cpu') || str_contains($nomeLower, 'processador')) {
        $testeCpuDef1 = $t;
    }
}

assertTeste($testeFonteHwDef1 !== null && $testeFonteHwDef1['anomalia'] === true, "Defeito #1 (Fonte): Teste de Hardware da Fonte retorna anomalia = true");
assertTeste($testeFonteHwDef1 !== null && $testeFonteHwDef1['status'] === 'alerta', "Defeito #1 (Fonte): Teste de Hardware da Fonte retorna status = 'alerta'");
assertTeste($testeFonteHwDef1 !== null && str_contains(mb_strtolower($testeFonteHwDef1['resultado_tecnico'], 'UTF-8'), 'falha'), "Defeito #1 (Fonte): Resultado técnico da Fonte detalha a falha de alimentação");

assertTeste($testePostGeralDef1 !== null && $testePostGeralDef1['anomalia'] === true, "Defeito #1 (Fonte): Teste Geral de POST retorna anomalia = true");
assertTeste($testePostGeralDef1 !== null && $testePostGeralDef1['status'] === 'alerta', "Defeito #1 (Fonte): Teste Geral de POST retorna status = 'alerta'");

assertTeste($testeRamDef1 !== null && $testeRamDef1['anomalia'] === false, "Defeito #1 (Fonte): Teste de RAM retorna anomalia = false");
assertTeste($testeRamDef1 !== null && $testeRamDef1['status'] === 'ok', "Defeito #1 (Fonte): Teste de RAM retorna status = 'ok'");
assertTeste($testeCpuDef1 !== null && $testeCpuDef1['anomalia'] === false, "Defeito #1 (Fonte): Teste de CPU retorna anomalia = false");

// 3.2 CENÁRIO DEFEITO #2: "Tela Azul" (Solução Correta: Reinstalar SO)
$resDef2 = simularRequisicaoApi('GET', ['defeito_id' => '2']);
assertTeste($resDef2['code'] === 200, "GET ?defeito_id=2 retorna HTTP 200 OK");

$testeSfcDef2 = null;
$testeServicosDef2 = null;
$testeRedeDef2 = null;
$testeBiosDef2 = null;
$testeHwDef2 = null;

foreach ($resDef2['json']['dados'] ?? [] as $t) {
    $nomeLower = mb_strtolower($t['nome'], 'UTF-8');
    if ($t['tipo'] === 'software' && (str_contains($nomeLower, 'sfc') || str_contains($nomeLower, 'integridade'))) {
        $testeSfcDef2 = $t;
    }
    if ($t['tipo'] === 'software' && str_contains($nomeLower, 'inicialização')) {
        $testeServicosDef2 = $t;
    }
    if ($t['tipo'] === 'rede' && str_contains($nomeLower, 'tcp/ip')) {
        $testeRedeDef2 = $t;
    }
    if ($t['tipo'] === 'software' && str_contains($nomeLower, 'bios')) {
        $testeBiosDef2 = $t;
    }
    if ($t['tipo'] === 'hardware' && (str_contains($nomeLower, 'fonte') || str_contains($nomeLower, 'ram'))) {
        $testeHwDef2 = $t;
    }
}

assertTeste($testeSfcDef2 !== null && $testeSfcDef2['anomalia'] === true, "Defeito #2 (Tela Azul): Teste de SFC/DISM retorna anomalia = true");
assertTeste($testeSfcDef2 !== null && $testeSfcDef2['status'] === 'alerta', "Defeito #2 (Tela Azul): Teste de SFC/DISM retorna status = 'alerta'");
assertTeste($testeServicosDef2 !== null && $testeServicosDef2['anomalia'] === true, "Defeito #2 (Tela Azul): Teste de Inicialização/Serviços retorna anomalia = true");
assertTeste($testeRedeDef2 !== null && $testeRedeDef2['anomalia'] === true, "Defeito #2 (Tela Azul): Teste de Pilha de Rede TCP/IP retorna anomalia = true");
assertTeste($testeBiosDef2 !== null && $testeBiosDef2['anomalia'] === false, "Defeito #2 (Tela Azul): Teste de BIOS retorna anomalia = false");
assertTeste($testeHwDef2 !== null && $testeHwDef2['anomalia'] === false, "Defeito #2 (Tela Azul): Testes de Hardware retornam anomalia = false e status = 'ok'");

// 3.3 CENÁRIO DEFEITO #3: "Superaquecimento" (Solução Correta: Cooler)
$resDef3 = simularRequisicaoApi('GET', ['defeito_id' => '3']);
assertTeste($resDef3['code'] === 200, "GET ?defeito_id=3 retorna HTTP 200 OK");

$testeCoolerDef3 = null;
$testeEstresseGeralDef3 = null;
$testeFonteDef3 = null;

foreach ($resDef3['json']['dados'] ?? [] as $t) {
    $nomeLower = mb_strtolower($t['nome'], 'UTF-8');
    if ($t['tipo'] === 'hardware' && (str_contains($nomeLower, 'cooler') || str_contains($nomeLower, 'térmico'))) {
        $testeCoolerDef3 = $t;
    }
    if ($t['tipo'] === 'geral' && (str_contains($nomeLower, 'estresse') || str_contains($nomeLower, 'carga'))) {
        $testeEstresseGeralDef3 = $t;
    }
    if ($t['tipo'] === 'hardware' && str_contains($nomeLower, 'fonte')) {
        $testeFonteDef3 = $t;
    }
}

assertTeste($testeCoolerDef3 !== null && $testeCoolerDef3['anomalia'] === true, "Defeito #3 (Superaquecimento): Teste de Cooler retorna anomalia = true");
assertTeste($testeCoolerDef3 !== null && $testeCoolerDef3['status'] === 'alerta', "Defeito #3 (Superaquecimento): Teste de Cooler retorna status = 'alerta'");
assertTeste($testeCoolerDef3 !== null && str_contains(mb_strtolower($testeCoolerDef3['resultado_tecnico'], 'UTF-8'), 'superaquecimento'), "Defeito #3 (Superaquecimento): Resultado técnico reporta temperatura crítica");

assertTeste($testeEstresseGeralDef3 !== null && $testeEstresseGeralDef3['anomalia'] === true, "Defeito #3 (Superaquecimento): Teste Geral de Estresse Térmico retorna anomalia = true");
assertTeste($testeEstresseGeralDef3 !== null && $testeEstresseGeralDef3['status'] === 'alerta', "Defeito #3 (Superaquecimento): Teste Geral de Estresse Térmico retorna status = 'alerta'");
assertTeste($testeFonteDef3 !== null && $testeFonteDef3['anomalia'] === false, "Defeito #3 (Superaquecimento): Teste de Fonte retorna anomalia = false");

// 3.4 CENÁRIO DEFEITO #4: "Travamento no sistema operacional" (Solução Correta: Procurar Vírus)
$resDef4 = simularRequisicaoApi('GET', ['defeito_id' => '4']);
assertTeste($resDef4['code'] === 200, "GET ?defeito_id=4 retorna HTTP 200 OK");

$testeVirusSegDef4 = null;
$testeRootkitsSegDef4 = null;
$testeSsdDef4 = null;

foreach ($resDef4['json']['dados'] ?? [] as $t) {
    $nomeLower = mb_strtolower($t['nome'], 'UTF-8');
    if ($t['tipo'] === 'seguranca' && (str_contains($nomeLower, 'varredura') || str_contains($nomeLower, 'antivírus'))) {
        $testeVirusSegDef4 = $t;
    }
    if ($t['tipo'] === 'seguranca' && (str_contains($nomeLower, 'rootkits') || str_contains($nomeLower, 'processos'))) {
        $testeRootkitsSegDef4 = $t;
    }
    if ($t['tipo'] === 'hardware' && (str_contains($nomeLower, 'ssd') || str_contains($nomeLower, 'disco'))) {
        $testeSsdDef4 = $t;
    }
}

assertTeste($testeVirusSegDef4 !== null && $testeVirusSegDef4['anomalia'] === true, "Defeito #4 (Vírus): Teste de Varredura Antivírus em Segurança retorna anomalia = true");
assertTeste($testeVirusSegDef4 !== null && $testeVirusSegDef4['status'] === 'alerta', "Defeito #4 (Vírus): Teste de Varredura Antivírus retorna status = 'alerta'");
assertTeste($testeRootkitsSegDef4 !== null && $testeRootkitsSegDef4['anomalia'] === true, "Defeito #4 (Vírus): Teste de Rootkits em Segurança retorna anomalia = true");
assertTeste($testeRootkitsSegDef4 !== null && $testeRootkitsSegDef4['status'] === 'alerta', "Defeito #4 (Vírus): Teste de Rootkits retorna status = 'alerta'");
assertTeste($testeSsdDef4 !== null && $testeSsdDef4['anomalia'] === false, "Defeito #4 (Vírus): Teste de Armazenamento/SSD retorna anomalia = false");

// 3.5 CENÁRIO DEFEITO #5: "A resolução de vídeo baixa" (Solução Correta: Atualização / Drivers)
$resDef5 = simularRequisicaoApi('GET', ['defeito_id' => '5']);
assertTeste($resDef5['code'] === 200, "GET ?defeito_id=5 retorna HTTP 200 OK");

$testeDriverDef5 = null;
$testeAdaptadorRedeDef5 = null;
$testeGpuFisicaDef5 = null;

foreach ($resDef5['json']['dados'] ?? [] as $t) {
    $nomeLower = mb_strtolower($t['nome'], 'UTF-8');
    if ($t['tipo'] === 'software' && (str_contains($nomeLower, 'driver') || str_contains($nomeLower, 'atualização'))) {
        $testeDriverDef5 = $t;
    }
    if ($t['tipo'] === 'rede' && (str_contains($nomeLower, 'adaptador') || str_contains($nomeLower, 'ethernet'))) {
        $testeAdaptadorRedeDef5 = $t;
    }
    if ($t['tipo'] === 'hardware' && (str_contains($nomeLower, 'gpu') || str_contains($nomeLower, 'vram') || str_contains($nomeLower, 'renderização'))) {
        $testeGpuFisicaDef5 = $t;
    }
}

assertTeste($testeDriverDef5 !== null && $testeDriverDef5['anomalia'] === true, "Defeito #5 (Drivers): Teste de Drivers/Atualização em Software retorna anomalia = true");
assertTeste($testeDriverDef5 !== null && $testeDriverDef5['status'] === 'alerta', "Defeito #5 (Drivers): Teste de Drivers retorna status = 'alerta'");
assertTeste($testeAdaptadorRedeDef5 !== null && $testeAdaptadorRedeDef5['anomalia'] === true, "Defeito #5 (Drivers): Teste de Adaptador de Rede em Rede retorna anomalia = true");
assertTeste($testeGpuFisicaDef5 !== null && $testeGpuFisicaDef5['anomalia'] === false, "Defeito #5 (Drivers): Teste físico de GPU em Hardware retorna anomalia = false (GPU saudável)");

// 3.6 Filtros Combinados de Categoria e Defeito Ativo (Isolamento Estrito)
// Filtro 1: tipo=seguranca & defeito_id=4
$resFiltroSeg = simularRequisicaoApi('GET', ['tipo' => 'seguranca', 'defeito_id' => '4']);
assertTeste($resFiltroSeg['code'] === 200, "GET ?tipo=seguranca&defeito_id=4 retorna HTTP 200 OK");
$apenasSegComAnomalia = true;
foreach ($resFiltroSeg['json']['dados'] ?? [] as $t) {
    if ($t['tipo'] !== 'seguranca' || $t['anomalia'] !== true) {
        $apenasSegComAnomalia = false;
        break;
    }
}
assertTeste($apenasSegComAnomalia && count($resFiltroSeg['json']['dados'] ?? []) >= 2, "Combinação ?tipo=seguranca&defeito_id=4 retorna apenas testes de segurança com anomalia = true");

// Filtro 2: tipo=rede & defeito_id=2
$resFiltroRede = simularRequisicaoApi('GET', ['tipo' => 'rede', 'defeito_id' => '2']);
assertTeste($resFiltroRede['code'] === 200, "GET ?tipo=rede&defeito_id=2 retorna HTTP 200 OK");
$apenasRedeValido = true;
$temAnomaliaPilha = false;
foreach ($resFiltroRede['json']['dados'] ?? [] as $t) {
    if ($t['tipo'] !== 'rede') $apenasRedeValido = false;
    if (str_contains(mb_strtolower($t['nome'], 'UTF-8'), 'tcp/ip') && $t['anomalia'] === true) {
        $temAnomaliaPilha = true;
    }
}
assertTeste($apenasRedeValido && $temAnomaliaPilha, "Combinação ?tipo=rede&defeito_id=2 retorna apenas testes de rede com anomalia na pilha TCP/IP");

// Filtro 3: tipo=geral & defeito_id=3
$resFiltroGeral = simularRequisicaoApi('GET', ['tipo' => 'geral', 'defeito_id' => '3']);
assertTeste($resFiltroGeral['code'] === 200, "GET ?tipo=geral&defeito_id=3 retorna HTTP 200 OK");
$apenasGeralValido = true;
$temAnomaliaEstresse = false;
foreach ($resFiltroGeral['json']['dados'] ?? [] as $t) {
    if ($t['tipo'] !== 'geral') $apenasGeralValido = false;
    if (str_contains(mb_strtolower($t['nome'], 'UTF-8'), 'estresse') && $t['anomalia'] === true) {
        $temAnomaliaEstresse = true;
    }
}
assertTeste($apenasGeralValido && $temAnomaliaEstresse, "Combinação ?tipo=geral&defeito_id=3 retorna apenas testes gerais com anomalia no estresse térmico");

// Filtro 4: tipo=hardware & defeito_id=1
$resFiltroHw = simularRequisicaoApi('GET', ['tipo' => 'hardware', 'defeito_id' => '1']);
assertTeste($resFiltroHw['code'] === 200, "GET ?tipo=hardware&defeito_id=1 retorna HTTP 200 OK");
$apenasHwValido = true;
$temAnomaliaFonte = false;
foreach ($resFiltroHw['json']['dados'] ?? [] as $t) {
    if ($t['tipo'] !== 'hardware') $apenasHwValido = false;
    if (str_contains(mb_strtolower($t['nome'], 'UTF-8'), 'fonte') && $t['anomalia'] === true) {
        $temAnomaliaFonte = true;
    }
}
assertTeste($apenasHwValido && $temAnomaliaFonte, "Combinação ?tipo=hardware&defeito_id=1 retorna apenas testes de hardware com anomalia na fonte");

// 3.7 Busca de ID Específico com defeito_id ativo
$idFonte = $db->query("SELECT id FROM componentes_testes WHERE tipo = 'hardware' AND nome LIKE '%Fonte%' LIMIT 1")->fetchColumn();
$resIdComDef = simularRequisicaoApi('GET', ['id' => (string)$idFonte, 'defeito_id' => '1']);
assertTeste($resIdComDef['code'] === 200 && ($resIdComDef['json']['dados']['anomalia'] ?? false) === true, "GET ?id={$idFonte}&defeito_id=1 retorna anomalia = true para o teste de Fonte");

$idRam = $db->query("SELECT id FROM componentes_testes WHERE tipo = 'hardware' AND nome LIKE '%RAM%' LIMIT 1")->fetchColumn();
$resIdRamComDef = simularRequisicaoApi('GET', ['id' => (string)$idRam, 'defeito_id' => '1']);
assertTeste($resIdRamComDef['code'] === 200 && ($resIdRamComDef['json']['dados']['anomalia'] ?? false) === false, "GET ?id={$idRam}&defeito_id=1 retorna anomalia = false para o teste de RAM");

// 3.8 Casos de Erro para defeito_id
$resDefInvalidoTexto = simularRequisicaoApi('GET', ['defeito_id' => 'abc']);
assertTeste($resDefInvalidoTexto['code'] === 400, "GET ?defeito_id=abc retorna HTTP 400 Bad Request");

$resDefInvalidoNegativo = simularRequisicaoApi('GET', ['defeito_id' => '-5']);
assertTeste($resDefInvalidoNegativo['code'] === 400, "GET ?defeito_id=-5 retorna HTTP 400 Bad Request");

$resDefInexistente = simularRequisicaoApi('GET', ['defeito_id' => '99999']);
assertTeste($resDefInexistente['code'] === 404, "GET ?defeito_id=99999 (inexistente) retorna HTTP 404 Not Found");

$resDefSqlInj = simularRequisicaoApi('GET', ['defeito_id' => '1 OR 1=1']);
assertTeste($resDefSqlInj['code'] === 400, "Tentativa de injeção SQL no parâmetro defeito_id é bloqueada com HTTP 400");

// -------------------------------------------------------------
// RELATÓRIO FINAL
// -------------------------------------------------------------
echo "\n{$azul}==================================================================\n";
echo "                   RELATÓRIO DE QA - TESTES                     \n";
echo "=================================================================={$reset}\n";
echo "  Total de Asserções Executadas: " . ($passou + $falhou) . "\n";
echo "  {$verde}Aprovadas: {$passou}{$reset}\n";
if ($falhou > 0) {
    echo "  {$vermelho}Falhas: {$falhou}{$reset}\n";
} else {
    echo "  {$verde}STATUS: 100% DOS TESTES DA CENTRAL DE DIAGNÓSTICO PASSARAM! 🎉{$reset}\n";
}
echo "==================================================================\n\n";

exit($falhou > 0 ? 1 : 0);


