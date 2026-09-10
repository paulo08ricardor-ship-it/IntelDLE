<?php
/**
 * Suíte de Testes Automatizados para a Validação de Respostas e Pontuação
 * Endpoints: api/verificar.php e api/salvar_pontos.php
 * 
 * Executável via PHP CLI: php tests/test_verificar.php
 */

define('TEST_MODE', true);

// Cores para saída CLI no terminal
$verde = "\033[32m";
$vermelho = "\033[31m";
$amarelo = "\033[33m";
$azul = "\033[36m";
$reset = "\033[0m";

echo "\n{$azul}==================================================================\n";
echo "   INTELDLE - SUÍTE DE TESTES: VERIFICAÇÃO DE RESPOSTAS & PONTUAÇÃO\n";
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


require_once __DIR__ . '/../config/database.php';
$db = (new Database())->conectar();

// Criação de usuário de teste dedicado para esta execução
$emailTeste = uniqid('teste_verificar_', true) . '@inteldle.com';
$senhaHash = password_hash('senha123', PASSWORD_DEFAULT);
$stmtUser = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('Player Teste QA', ?, ?, 'usuario')");
$stmtUser->execute([$emailTeste, $senhaHash]);
$usuarioIdTeste = (int) $db->lastInsertId();
$stmtUser->closeCursor();
$stmtUser = null;

/**
 * Executa requisição isolada ao endpoint api/verificar.php via subprocesso PHP
 */
function simularRequisicaoVerificar(string $metodo = 'POST', array $post = [], array $session = [], string $rawBody = ''): array {
    $apiFile = str_replace('\\', '/', realpath(__DIR__ . '/../api/verificar.php'));
    $phpExec = PHP_BINARY ?: (file_exists('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');

    $sessionExport = var_export($session, true);
    $postExport = var_export($post, true);
    $methodExport = var_export(strtoupper($metodo), true);
    $apiFileExport = var_export($apiFile, true);
    $rawBodyExport = var_export($rawBody, true);

    $code = "<?php
        define('TEST_MODE', true);
        session_start();
        \$_SESSION = {$sessionExport};
        \$_SERVER['REQUEST_METHOD'] = {$methodExport};
        \$_POST = {$postExport};
        if (!empty({$rawBodyExport})) {
            // Mock de php://input para payload JSON
        }
        register_shutdown_function(function() {
            \$status = http_response_code() ?: 200;
            echo \"\\n###HTTP_CODE###:\" . \$status;
            echo \"\\n###SESSION_DATA###:\" . json_encode(\$_SESSION);
        });
        require {$apiFileExport};
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

    $returnedSession = [];
    if (preg_match('/###SESSION_DATA###:(.+)/s', $stdout, $matches)) {
        $returnedSession = json_decode(trim($matches[1]), true) ?? [];
        $stdout = str_replace($matches[0], '', $stdout);
    }

    $cleanOutput = trim($stdout);
    $json = json_decode($cleanOutput, true);

    return [
        'code' => $httpCode,
        'raw' => $cleanOutput,
        'json' => $json,
        'session' => $returnedSession,
        'stderr' => $stderr
    ];
}

/**
 * Executa requisição isolada ao endpoint api/salvar_pontos.php via subprocesso PHP
 */
function simularRequisicaoSalvarPontos(string $metodo = 'POST', array $post = [], array $session = []): array {
    $apiFile = str_replace('\\', '/', realpath(__DIR__ . '/../api/salvar_pontos.php'));
    $phpExec = PHP_BINARY ?: (file_exists('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');

    $sessionExport = var_export($session, true);
    $postExport = var_export($post, true);
    $methodExport = var_export(strtoupper($metodo), true);
    $apiFileExport = var_export($apiFile, true);

    $code = "<?php
        define('TEST_MODE', true);
        session_start();
        \$_SESSION = {$sessionExport};
        \$_SERVER['REQUEST_METHOD'] = {$methodExport};
        \$_POST = {$postExport};
        register_shutdown_function(function() {
            \$status = http_response_code() ?: 200;
            echo \"\\n###HTTP_CODE###:\" . \$status;
            echo \"\\n###SESSION_DATA###:\" . json_encode(\$_SESSION);
        });
        require {$apiFileExport};
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

    $returnedSession = [];
    if (preg_match('/###SESSION_DATA###:(.+)/s', $stdout, $matches)) {
        $returnedSession = json_decode(trim($matches[1]), true) ?? [];
        $stdout = str_replace($matches[0], '', $stdout);
    }

    $cleanOutput = trim($stdout);
    $json = json_decode($cleanOutput, true);

    return [
        'code' => $httpCode,
        'raw' => $cleanOutput,
        'json' => $json,
        'session' => $returnedSession,
        'stderr' => $stderr
    ];
}

// -------------------------------------------------------------
// GRUPO 1: VALIDAÇÃO DE RESPOSTAS PARA TODOS OS 5 DEFEITOS
// -------------------------------------------------------------
echo "{$amarelo}▶ GRUPO 1: Validação de Respostas Corretas e Incorretas (Defeitos 1 a 5){$reset}\n";

$defeitosTestes = [
    1 => [
        'nome' => 'Defeito 1 (Computador não liga)',
        'correto' => 'Fonte',
        'incorretos' => ['RAM', 'CPU', 'GPU', 'HD', 'Cooler', 'Reinstalar SO']
    ],
    2 => [
        'nome' => 'Defeito 2 (Tela Azul)',
        'correto' => 'Reinstalar SO',
        'incorretos' => ['Fonte', 'RAM', 'SSD', 'Placa-Mãe', 'Procurar Vírus']
    ],
    3 => [
        'nome' => 'Defeito 3 (Superaquecimento)',
        'correto' => 'Cooler',
        'incorretos' => ['Fonte', 'HD', 'Monitor', 'BIOS', 'RAM']
    ],
    4 => [
        'nome' => 'Defeito 4 (Travamento no SO / Malware)',
        'correto' => 'Procurar Vírus',
        'incorretos' => ['RAM', 'Fonte', 'Placa-Mãe', 'Cooler', 'Limpeza']
    ],
    5 => [
        'nome' => 'Defeito 5 (Resolução de vídeo baixa)',
        'correto' => 'Atualização',
        'incorretos' => ['GPU', 'RAM', 'Monitor', 'Fonte', 'HD']
    ]
];

foreach ($defeitosTestes as $defId => $info) {
    // Teste de Resposta Correta
    $resCerta = simularRequisicaoVerificar('POST', [
        'defeito' => $defId,
        'componente' => $info['correto'],
        'modo' => 'infinito'
    ]);

    assertTeste(
        $resCerta['code'] === 200 && ($resCerta['json']['resultado'] ?? '') === 'correto',
        "{$info['nome']}: Resposta CORRETA com '{$info['correto']}' retorna resultado = 'correto'"
    );
    assertTeste(
        !empty($resCerta['json']['mensagem']),
        "{$info['nome']}: Resposta correta contém mensagem explicativa da solução"
    );

    // Teste de Respostas Incorretas
    foreach ($info['incorretos'] as $inc) {
        $resErro = simularRequisicaoVerificar('POST', [
            'defeito' => $defId,
            'componente' => $inc,
            'modo' => 'infinito'
        ]);
        assertTeste(
            $resErro['code'] === 200 && ($resErro['json']['resultado'] ?? '') === 'erro',
            "{$info['nome']}: Resposta INCORRETA com '{$inc}' retorna resultado = 'erro'"
        );
    }
}

// -------------------------------------------------------------
// GRUPO 2: DICIONÁRIO COMPLETO DE ALIASES / SINÔNIMOS (14 COMPONENTES)
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 2: Dicionário Case-Insensitive & Aliases para todos os 14 Componentes{$reset}\n";

$aliasesTestes = [
    // 1. CPU
    'CPU' => ['cpu', 'CPU', 'Processador', 'processador', 'PROCESSADOR', 'proc', 'Processador Central'],
    // 2. RAM
    'RAM' => ['ram', 'RAM', 'Memória RAM', 'memoria ram', 'Memoria RAM', 'MEMÓRIA RAM', 'pente de memoria'],
    // 3. GPU
    'GPU' => ['gpu', 'GPU', 'Placa de Vídeo', 'placa de video', 'placa de vídeo', 'PLACA DE VIDEO', 'vga', 'placa grafica'],
    // 4. HD
    'HD' => ['hd', 'HD', 'Disco Rígido', 'disco rigido', 'disco rígido', 'DISCO RIGIDO', 'hdd', 'hard disk'],
    // 5. SSD
    'SSD' => ['ssd', 'SSD', 'disco solido', 'disco sólido', 'unidade de estado solido', 'nvme', 'sata ssd'],
    // 6. Monitor
    'Monitor' => ['monitor', 'Monitor', 'MONITOR', 'tela', 'display', 'cabo de video', 'cabo hdmi'],
    // 7. Limpeza
    'Limpeza' => ['limpeza', 'Limpeza', 'LIMPEZA', 'limpeza de contatos', 'limpar poeira', 'alcool isopropilico', 'limpa contato'],
    // 8. Placa-Mãe
    'Placa-Mãe' => ['placa-mãe', 'Placa-Mãe', 'placa-mae', 'placa mae', 'placa mãe', 'PLACA MAE', 'motherboard', 'mobo'],
    // 9. Cooler
    'Cooler' => ['cooler', 'Cooler', 'COOLER', 'cooler e pasta termica', 'pasta termica', 'pasta térmica', 'fan', 'ventoinha'],
    // 10. Fonte
    'Fonte' => ['fonte', 'Fonte', 'FONTE', 'psu', 'PSU', 'fonte de alimentacao', 'fonte de alimentação', 'fonte atx'],
    // 11. BIOS
    'BIOS' => ['bios', 'BIOS', 'uefi', 'setup', 'firmware', 'bateria cmos'],
    // 12. Atualização
    'Atualização' => ['atualizacao', 'atualização', 'Atualização', 'ATUALIZACAO', 'atualizar drivers', 'drivers', 'driver', 'update'],
    // 13. Reinstalar SO
    'Reinstalar SO' => ['reinstalar so', 'Reinstalar SO', 'REINSTALAR SO', 'reinstalar sistema', 'formatar', 'formatar pc', 'recuperar sistema'],
    // 14. Procurar Vírus
    'Procurar Vírus' => ['procurar virus', 'procurar vírus', 'Procurar Vírus', 'antivirus', 'antivírus', 'Antivírus', 'antimalware', 'virus']
];

foreach ($aliasesTestes as $compCanonico => $variacoes) {
    // Escolhe um defeito para testar o reconhecimento (ex: Defeito 1)
    // Se compCanonico for Fonte, resultado é correto; caso contrário erro, mas o componente é reconhecido sem falhar no catálogo
    $defId = 1;

    foreach ($variacoes as $var) {
        $resVar = simularRequisicaoVerificar('POST', [
            'defeito' => $defId,
            'componente' => $var,
            'modo' => 'infinito'
        ]);

        $resultadoValido = in_array($resVar['json']['resultado'] ?? '', ['correto', 'erro']);
        $naoDeuErroCatalogo = ($resVar['json']['mensagem'] ?? '') !== 'Componente não encontrado no catálogo do sistema.';
        $componenteRetornado = $resVar['json']['componente'] ?? '';

        assertTeste(
            $resultadoValido && $naoDeuErroCatalogo && $componenteRetornado === $compCanonico,
            "Alias '{$var}' normalizado com sucesso para '{$compCanonico}' (Resultado: " . ($resVar['json']['resultado'] ?? 'null') . ")"
        );
    }
}

// -------------------------------------------------------------
// GRUPO 3: MODO INFINITO, SEQUÊNCIA & HISTÓRICO
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 3: Modo Infinito, Sequência de Acertos & Histórico{$reset}\n";

// 3.1 Reset de sessão para Modo Infinito
$resResetInf = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'Fonte',
    'modo' => 'infinito',
    'reset' => '1'
], ['id' => $usuarioIdTeste, 'streak_infinito' => 10]);

assertTeste($resResetInf['code'] === 200, "Reset de pontuação no Modo Infinito retorna HTTP 200");
assertTeste(($resResetInf['json']['sequencia'] ?? 0) === 1, "Acerto inicial pós-reset incrementa sequência para 1");

// 3.2 Continuação de sequência (streak = 1 -> streak = 2)
$resStreak2 = simularRequisicaoVerificar('POST', [
    'defeito' => 3,
    'componente' => 'Cooler',
    'modo' => 'infinito'
], ['id' => $usuarioIdTeste, 'streak_infinito' => 1]);

assertTeste(($resStreak2['json']['sequencia'] ?? 0) === 2, "Segundo acerto consecutivo incrementa sequência para 2");

// 3.3 Erro no modo infinito (zera a sequência)
$resErroInf = simularRequisicaoVerificar('POST', [
    'defeito' => 3,
    'componente' => 'RAM',
    'modo' => 'infinito'
], ['id' => $usuarioIdTeste, 'streak_infinito' => 2]);

assertTeste(($resErroInf['json']['resultado'] ?? '') === 'erro', "Resposta incorreta no Modo Infinito retorna resultado = 'erro'");
assertTeste(($resErroInf['json']['sequencia'] ?? -1) === 0, "Resposta incorreta no Modo Infinito zera a sequência (sequencia = 0)");

// 3.4 Verificar gravação no histórico para modo infinito
$stmtCheckHistInf = $db->prepare("
    SELECT COUNT(*) FROM historico 
    WHERE usuario_id = ? AND modo = 'infinito'
");
$stmtCheckHistInf->execute([$usuarioIdTeste]);
$totalHistInf = (int)$stmtCheckHistInf->fetchColumn();
$stmtCheckHistInf->closeCursor();
$stmtCheckHistInf = null;
assertTeste($totalHistInf >= 3, "Partidas do Modo Infinito foram gravadas na tabela 'historico' (Total: {$totalHistInf})");

// 3.5 Integração com api/salvar_pontos.php no Modo Infinito
$resSalvarInf = simularRequisicaoSalvarPontos('POST', [
    'modo' => 'infinito',
    'pontos' => 5
], ['id' => $usuarioIdTeste]);

assertTeste($resSalvarInf['code'] === 200 && ($resSalvarInf['json']['sucesso'] ?? false) === true, "api/salvar_pontos.php salva recorde infinito com sucesso", $resSalvarInf['json']['detalhe'] ?? ($resSalvarInf['json']['erro'] ?? $resSalvarInf['raw']));
assertTeste(($resSalvarInf['json']['recorde'] ?? 0) >= 5, "Recorde salvo em ranking_infinito é consolidado (Recorde: " . ($resSalvarInf['json']['recorde'] ?? 0) . ")", $resSalvarInf['raw']);

// -------------------------------------------------------------
// GRUPO 4: MODO DIÁRIO, REGRAS DE PONTUAÇÃO & BLOQUEIO AMIGÁVEL
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 4: Modo Diário, Penalidades de Pontuação & Bloqueio Amigável{$reset}\n";

// Criar outro usuário limpo para testar o fluxo diário completo
$emailDiario = uniqid('teste_diario_', true) . '@inteldle.com';
$stmtUserD = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('Player Diario QA', ?, ?, 'usuario')");
$stmtUserD->execute([$emailDiario, $senhaHash]);
$usuarioDiarioId = (int) $db->lastInsertId();
$stmtUserD->closeCursor();
$stmtUserD = null;

// 4.1 Erro no modo diário aplica penalidade de 200 pontos (10000 -> 9800)
$resErroD1 = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'RAM',
    'modo' => 'diario'
], ['id' => $usuarioDiarioId, 'pontos_pendentes' => 10000]);

assertTeste($resErroD1['code'] === 200 && ($resErroD1['json']['resultado'] ?? '') === 'erro', "Primeiro erro no Modo Diário retorna resultado = 'erro'", $resErroD1['json']['detalhe'] ?? $resErroD1['raw']);
assertTeste(($resErroD1['json']['pontos'] ?? 0) === 9800, "Primeiro erro aplica penalidade de 200 pontos (Pontuação: 9800 pts)", $resErroD1['raw']);

// 4.2 Segundo erro no modo diário (9800 -> 9600)
$resErroD2 = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'GPU',
    'modo' => 'diario'
], ['id' => $usuarioDiarioId, 'pontos_pendentes' => 9800]);

assertTeste(($resErroD2['json']['pontos'] ?? 0) === 9600, "Segundo erro aplica penalidade de 200 pontos (Pontuação: 9600 pts)", $resErroD2['raw']);

// 4.3 Acerto no modo diário mantém pontuação final e grava histórico
$resAcertoD = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'Fonte',
    'modo' => 'diario'
], ['id' => $usuarioDiarioId, 'pontos_pendentes' => 9600]);

assertTeste($resAcertoD['code'] === 200 && ($resAcertoD['json']['resultado'] ?? '') === 'correto', "Acerto no Modo Diário retorna resultado = 'correto'", $resAcertoD['raw']);
assertTeste(($resAcertoD['json']['pontos'] ?? 0) === 9600, "Acerto preserva a pontuação calculada final (9600 pts)", $resAcertoD['raw']);

// 4.4 Salvar pontuação diária via api/salvar_pontos.php
$resSalvarD = simularRequisicaoSalvarPontos('POST', [
    'modo' => 'diario',
    'pontos' => 9600
], ['id' => $usuarioDiarioId]);

assertTeste($resSalvarD['code'] === 200 && ($resSalvarD['json']['sucesso'] ?? false) === true, "api/salvar_pontos.php registra pontuação diária em ranking_diario", $resSalvarD['raw']);
assertTeste(($resSalvarD['json']['pontuacao'] ?? 0) === 9600, "Pontuação diária registrada com 9600 pts", $resSalvarD['raw']);

// 4.5 Bloqueio Amigável: Nova tentativa no mesmo dia deve retornar HTTP 200 com resultado='bloqueado'
$resBloqueioD = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'Fonte',
    'modo' => 'diario'
], ['id' => $usuarioDiarioId]);

assertTeste($resBloqueioD['code'] === 200, "Tentativa de usuário já concluído no Modo Diário retorna HTTP 200 OK (Sem quebrar frontend)", $resBloqueioD['raw']);
assertTeste(($resBloqueioD['json']['resultado'] ?? '') === 'bloqueado', "Retorno possui resultado = 'bloqueado'", $resBloqueioD['raw']);
assertTeste(
    str_contains($resBloqueioD['json']['mensagem'] ?? '', 'já concluiu o desafio diário de hoje'),
    "Mensagem amigável de bloqueio diário informando Modo Infinito ('" . ($resBloqueioD['json']['mensagem'] ?? '') . "')",
    $resBloqueioD['raw']
);

// -------------------------------------------------------------
// GRUPO 5: VALIDAÇÕES HTTP, VISITANTES & TRATAMENTO DE ERROS
// -------------------------------------------------------------
echo "\n{$amarelo}▶ GRUPO 5: Métodos HTTP, Visitantes & Tratamento de Erros{$reset}\n";

// 5.1 GET em api/verificar.php (405 Method Not Allowed)
$resGetVerif = simularRequisicaoVerificar('GET', []);
assertTeste($resGetVerif['code'] === 405, "GET /api/verificar.php retorna HTTP 405 Method Not Allowed");
assertTeste(($resGetVerif['json']['resultado'] ?? '') === 'erro', "GET /api/verificar.php retorna resultado = 'erro'");

// 5.2 POST sem parâmetros em api/verificar.php
$resVazioVerif = simularRequisicaoVerificar('POST', []);
assertTeste($resVazioVerif['code'] === 200, "POST sem parâmetros retorna HTTP 200");
assertTeste(($resVazioVerif['json']['resultado'] ?? '') === 'erro', "POST sem parâmetros retorna resultado = 'erro'");

// 5.3 Componente inexistente em api/verificar.php
$resInexistente = simularRequisicaoVerificar('POST', [
    'defeito' => 1,
    'componente' => 'Peca_Desconhecida_XYZ'
]);
assertTeste(($resInexistente['json']['resultado'] ?? '') === 'erro', "Componente não catalogado retorna resultado = 'erro'");

// 5.4 GET em api/salvar_pontos.php (405 Method Not Allowed)
$resGetSalvar = simularRequisicaoSalvarPontos('GET', []);
assertTeste($resGetSalvar['code'] === 405, "GET /api/salvar_pontos.php retorna HTTP 405 Method Not Allowed");

// 5.5 Salvar pontos como Visitante (sem sessão logada)
$resSalvarVisitante = simularRequisicaoSalvarPontos('POST', ['modo' => 'infinito', 'pontos' => 3], []);
assertTeste($resSalvarVisitante['code'] === 200, "Salvar pontos de visitante retorna HTTP 200");
assertTeste(($resSalvarVisitante['json']['visitante'] ?? false) === true, "Salvar pontos de visitante retorna flag visitante = true");

// -------------------------------------------------------------
// LIMPEZA FINAL DOS DADOS DE TESTE
// -------------------------------------------------------------
$db->exec("DELETE FROM usuarios WHERE id IN ({$usuarioIdTeste}, {$usuarioDiarioId})");

// -------------------------------------------------------------
// RELATÓRIO FINAL
// -------------------------------------------------------------
echo "\n{$azul}==================================================================\n";
echo "                   RELATÓRIO DE QA - VERIFICAÇÃO                \n";
echo "=================================================================={$reset}\n";
echo "  Total de Asserções Executadas: " . ($passou + $falhou) . "\n";
echo "  {$verde}Aprovadas: {$passou}{$reset}\n";
if ($falhou > 0) {
    echo "  {$vermelho}Falhas: {$falhou}{$reset}\n";
} else {
    echo "  {$verde}STATUS: 100% DOS TESTES DE VALIDAÇÃO PASSARAM COM SUCESSO! 🎉{$reset}\n";
}
echo "==================================================================\n\n";

exit($falhou > 0 ? 1 : 0);
