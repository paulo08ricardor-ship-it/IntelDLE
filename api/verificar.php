<?php
/**
 * API de Verificação de Diagnóstico e Validação Relacional
 * Inteldle - Versão Normalizada 3FN com Integridade Relacional Forte
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "resultado" => "erro",
        "mensagem" => "Método não permitido. Apenas requisições POST são aceitas."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Aceitar tanto FormData/POST tradicional quanto payload JSON (Content-Type: application/json)
$jsonRaw = file_get_contents('php://input');
if (!empty($jsonRaw)) {
    $dadosJson = json_decode($jsonRaw, true);
    if (is_array($dadosJson)) {
        $_POST = array_merge($dadosJson, $_POST);
    }
}

$modo = isset($_POST['modo']) && strtolower((string)$_POST['modo']) === 'infinito' ? 'infinito' : 'diario';
$usuarioId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;

// Reset opcional de pontuação da sessão para início de nova partida
if (isset($_POST['reset']) && ($_POST['reset'] === '1' || $_POST['reset'] === 'true' || $_POST['reset'] === true)) {
    if ($modo === 'infinito') {
        $_SESSION['streak_infinito'] = 0;
        $_SESSION['pontos_pendentes'] = 0;
    } else {
        $_SESSION['pontos_pendentes'] = 10000;
    }
}

$rawDefeito = $_POST['defeito_id'] ?? $_POST['defeito'] ?? null;
$defeitoId = filter_var($rawDefeito, FILTER_VALIDATE_INT);

// Priorizar componente_id (inteiro via FILTER_VALIDATE_INT)
$rawCompId = $_POST['componente_id'] ?? null;
$componenteIdInput = filter_var($rawCompId, FILTER_VALIDATE_INT);
$componenteNomeInput = trim((string)($_POST['componente'] ?? ''));

// Se componente_id não foi passado mas componente é numérico, utiliza como ID
if (($componenteIdInput === false || $componenteIdInput === null) && is_numeric($componenteNomeInput)) {
    $valId = filter_var($componenteNomeInput, FILTER_VALIDATE_INT);
    if ($valId !== false && $valId > 0) {
        $componenteIdInput = $valId;
    }
}

if ($defeitoId === false || $defeitoId === null || $defeitoId <= 0 || (($componenteIdInput === false || $componenteIdInput === null || $componenteIdInput <= 0) && $componenteNomeInput === '')) {
    http_response_code(200);
    echo json_encode([
        "resultado" => "erro",
        "mensagem" => "Dados da requisição incompletos ou inválidos.",
        "modo" => $modo
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Utilitário para remoção de acentos para busca flexível
 */
function removerAcentos(string $texto): string {
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
        'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
        'Ç'=>'c','Ñ'=>'n'
    ];
    return strtr($texto, $map);
}

/**
 * Normaliza string para comparação (sem acento, minúsculo, apenas alfanumérico)
 */
function simplificarTexto(string $str): string {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = removerAcentos($str);
    $str = preg_replace('/[^a-z0-9]/', '', $str);
    return $str;
}

try {
    $db = (new Database())->conectar();

    // 0. Bloqueio amigável no modo diário se o usuário já jogou hoje
    if ($modo === 'diario' && $usuarioId !== null) {
        $stmtBloqueio = $db->prepare("
            SELECT COUNT(*) AS total
            FROM ranking_diario
            WHERE usuario_id = ? AND data_jogo = DATE('now')
        ");
        $stmtBloqueio->execute([$usuarioId]);
        $bloqueioRow = $stmtBloqueio->fetch(PDO::FETCH_ASSOC);
        if ($bloqueioRow && (int)$bloqueioRow['total'] > 0) {
            http_response_code(200);
            echo json_encode([
                "resultado" => "bloqueado",
                "mensagem" => "Você já concluiu o desafio diário de hoje! Divirta-se no Modo Infinito.",
                "modo" => "diario"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 1. Busca relacional do componente no catálogo 'componentes'
    $compRow = null;
    if ($componenteIdInput !== false && $componenteIdInput !== null && $componenteIdInput > 0) {
        $stmtComp = $db->prepare("SELECT id, nome, tipo, descricao FROM componentes WHERE id = ? LIMIT 1");
        $stmtComp->execute([$componenteIdInput]);
        $compRow = $stmtComp->fetch(PDO::FETCH_ASSOC);
    }

    // Fallback por nome/string caso o ID não tenha sido fornecido diretamente
    if (!$compRow && !empty($componenteNomeInput)) {
        // Busca exata no banco (case-insensitive)
        $stmtCompNome = $db->prepare("SELECT id, nome, tipo, descricao FROM componentes WHERE LOWER(nome) = LOWER(?) LIMIT 1");
        $stmtCompNome->execute([$componenteNomeInput]);
        $compRow = $stmtCompNome->fetch(PDO::FETCH_ASSOC);

        // Busca exata na descrição no banco
        if (!$compRow) {
            $stmtCompDesc = $db->prepare("SELECT id, nome, tipo, descricao FROM componentes WHERE LOWER(descricao) = LOWER(?) LIMIT 1");
            $stmtCompDesc->execute([$componenteNomeInput]);
            $compRow = $stmtCompDesc->fetch(PDO::FETCH_ASSOC);
        }

        // Resolução flexível e dinâmica
        if (!$compRow) {
            $inputSimp = simplificarTexto($componenteNomeInput);
            $todosComps = $db->query("SELECT id, nome, tipo, descricao FROM componentes")->fetchAll(PDO::FETCH_ASSOC);

            // Comparação direta simplificada (sem acentos e sem pontuação)
            foreach ($todosComps as $c) {
                if (simplificarTexto($c['nome']) === $inputSimp) {
                    $compRow = $c;
                    break;
                }
            }

            // Mapeamento dinâmico de termos técnicos e sinônimos usuais
            if (!$compRow) {
                $aliasesComuns = [
                    'processador' => 'CPU',
                    'proc' => 'CPU',
                    'microprocessador' => 'CPU',
                    'intel' => 'CPU',
                    'amd' => 'CPU',
                    'memoria' => 'RAM',
                    'pente de memoria' => 'RAM',
                    'pente de memoria ram' => 'RAM',
                    'pente de memória' => 'RAM',
                    'pente de memória ram' => 'RAM',
                    'pente' => 'RAM',
                    'modulo ram' => 'RAM',
                    'módulo ram' => 'RAM',
                    'ddr' => 'RAM',
                    'ddr4' => 'RAM',
                    'ddr5' => 'RAM',
                    'placa de video' => 'GPU',
                    'placa de vídeo' => 'GPU',
                    'placadevideo' => 'GPU',
                    'placa grafica' => 'GPU',
                    'placa gráfica' => 'GPU',
                    'vga' => 'GPU',
                    'video' => 'GPU',
                    'vídeo' => 'GPU',
                    'geforce' => 'GPU',
                    'radeon' => 'GPU',
                    'disco rigido' => 'HD',
                    'disco rígido' => 'HD',
                    'hdd' => 'HD',
                    'hard disk' => 'HD',
                    'hard drive' => 'HD',
                    'disco solido' => 'SSD',
                    'disco sólido' => 'SSD',
                    'unidade de estado solido' => 'SSD',
                    'unidade de estado sólido' => 'SSD',
                    'nvme' => 'SSD',
                    'sata ssd' => 'SSD',
                    'm.2' => 'SSD',
                    'm2' => 'SSD',
                    'tela' => 'Monitor',
                    'display' => 'Monitor',
                    'cabo de video' => 'Monitor',
                    'cabo de vídeo' => 'Monitor',
                    'cabo hdmi' => 'Monitor',
                    'cabo displayport' => 'Monitor',
                    'hdmi' => 'Monitor',
                    'displayport' => 'Monitor',
                    'limpar contatos' => 'Limpeza',
                    'limpar poeira' => 'Limpeza',
                    'limpar' => 'Limpeza',
                    'poeira' => 'Limpeza',
                    'limpa contato' => 'Limpeza',
                    'alcool isopropilico' => 'Limpeza',
                    'álcool isopropílico' => 'Limpeza',
                    'alcool' => 'Limpeza',
                    'álcool' => 'Limpeza',
                    'pincel' => 'Limpeza',
                    'motherboard' => 'Placa-Mãe',
                    'mobo' => 'Placa-Mãe',
                    'mainboard' => 'Placa-Mãe',
                    'fan' => 'Cooler',
                    'ventoinha' => 'Cooler',
                    'dissipador' => 'Cooler',
                    'pasta termica' => 'Cooler',
                    'pasta térmica' => 'Cooler',
                    'water cooler' => 'Cooler',
                    'arrefecimento' => 'Cooler',
                    'psu' => 'Fonte',
                    'alimentador' => 'Fonte',
                    'power supply' => 'Fonte',
                    'fonte atx' => 'Fonte',
                    'uefi' => 'BIOS',
                    'setup' => 'BIOS',
                    'firmware' => 'BIOS',
                    'cmos' => 'BIOS',
                    'bateria cmos' => 'BIOS',
                    'atualizar drivers' => 'Atualização',
                    'atualizar driver' => 'Atualização',
                    'drivers' => 'Atualização',
                    'driver' => 'Atualização',
                    'update' => 'Atualização',
                    'windows update' => 'Atualização',
                    'reinstalar so' => 'Reinstalar SO',
                    'reinstalar sistema' => 'Reinstalar SO',
                    'reinstalar sistema operacional' => 'Reinstalar SO',
                    'formatar' => 'Reinstalar SO',
                    'formatar pc' => 'Reinstalar SO',
                    'formatar computador' => 'Reinstalar SO',
                    'reinstalar sistema' => 'Reinstalar SO',
                    'reinstalar windows' => 'Reinstalar SO',
                    'recuperar sistema' => 'Reinstalar SO',
                    'sfc' => 'Reinstalar SO',
                    'dism' => 'Reinstalar SO',
                    'antivirus' => 'Procurar Vírus',
                    'antivírus' => 'Procurar Vírus',
                    'antimalware' => 'Procurar Vírus',
                    'malware' => 'Procurar Vírus',
                    'virus' => 'Procurar Vírus',
                    'vírus' => 'Procurar Vírus',
                    'scan' => 'Procurar Vírus',
                    'virabot' => 'Procurar Vírus'
                ];

                foreach ($aliasesComuns as $alias => $nomeCanonico) {
                    if ($inputSimp === simplificarTexto($alias)) {
                        foreach ($todosComps as $c) {
                            if (simplificarTexto($c['nome']) === simplificarTexto($nomeCanonico)) {
                                $compRow = $c;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Busca por correspondência parcial na descrição cadastrada no banco
            if (!$compRow) {
                foreach ($todosComps as $c) {
                    $descSimp = simplificarTexto($c['descricao'] ?? '');
                    if ($descSimp !== '' && (str_contains($descSimp, $inputSimp) || str_contains($inputSimp, $descSimp))) {
                        $compRow = $c;
                        break;
                    }
                }
            }
        }
    }

    if (!$compRow) {
        http_response_code(200);
        echo json_encode([
            "resultado" => "erro",
            "mensagem" => "Componente não encontrado no catálogo do sistema.",
            "modo" => $modo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $componenteId = (int) $compRow['id'];
    $componenteNome = $compRow['nome'];

    // 2. Consulta da solução associada por Foreign Key relacional (3FN)
    $stmtSol = $db->prepare("
        SELECT correto, mensagem
        FROM solucoes
        WHERE defeito_id = :defeito_id AND componente_id = :componente_id
        LIMIT 1
    ");
    $stmtSol->execute([
        ':defeito_id' => $defeitoId,
        ':componente_id' => $componenteId
    ]);
    $resposta = $stmtSol->fetch(PDO::FETCH_ASSOC);

    if (!$resposta) {
        $resposta = [
            'correto' => 0,
            'mensagem' => "O componente {$componenteNome} não é a solução para esta falha."
        ];
    }

    $ehCorreto = ((int)$resposta['correto'] === 1);

    // 3. Processamento de Regras por Modo de Jogo com Transação ACID
    if ($modo === 'infinito') {
        $sequenciaAtual = isset($_SESSION['streak_infinito']) ? (int) $_SESSION['streak_infinito'] : 0;
        $pontosCalculados = 0;

        if ($ehCorreto) {
            $sequenciaAtual++;
            $_SESSION['streak_infinito'] = $sequenciaAtual;
            $pontosCalculados = $sequenciaAtual;
        } else {
            $sequenciaAtual = 0;
            $_SESSION['streak_infinito'] = 0;
            $pontosCalculados = 0;
        }

        // Gravação Atômica no Histórico se logado
        if ($usuarioId) {
            $db->beginTransaction();
            $stmtHist = $db->prepare("
                INSERT INTO historico (usuario_id, defeito_id, componente_id, acertou, pontos, modo)
                VALUES (?, ?, ?, ?, ?, 'infinito')
            ");
            $stmtHist->execute([
                $usuarioId,
                $defeitoId,
                $componenteId,
                $ehCorreto ? 1 : 0,
                $pontosCalculados
            ]);
            $db->commit();
        }

        http_response_code(200);
        echo json_encode([
            "resultado" => $ehCorreto ? "correto" : "erro",
            "mensagem" => $resposta['mensagem'],
            "pontos" => $pontosCalculados,
            "sequencia" => $sequenciaAtual,
            "modo" => "infinito",
            "componente" => $componenteNome,
            "componente_id" => $componenteId
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } else {
        // Modo Diário: Pontuação base de 10000 com penalidades
        $pontosAtuais = isset($_SESSION['pontos_pendentes']) ? (int) $_SESSION['pontos_pendentes'] : 10000;

        if ($ehCorreto) {
            $pontosFinais = max(0, $pontosAtuais);
            $_SESSION['pontos_pendentes'] = $pontosFinais;

            // Gravação Atômica no Histórico se logado
            if ($usuarioId) {
                $db->beginTransaction();
                $stmtHist = $db->prepare("
                    INSERT INTO historico (usuario_id, defeito_id, componente_id, acertou, pontos, modo)
                    VALUES (?, ?, ?, 1, ?, 'diario')
                ");
                $stmtHist->execute([
                    $usuarioId,
                    $defeitoId,
                    $componenteId,
                    $pontosFinais
                ]);
                $db->commit();
            }

            http_response_code(200);
            echo json_encode([
                "resultado" => "correto",
                "mensagem" => $resposta['mensagem'],
                "pontos" => $pontosFinais,
                "modo" => "diario",
                "componente" => $componenteNome,
                "componente_id" => $componenteId
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $pontosAtuais = max(0, $pontosAtuais - 200);
            $_SESSION['pontos_pendentes'] = $pontosAtuais;

            // Gravação Atômica de tentativa incorreta
            if ($usuarioId) {
                $db->beginTransaction();
                $stmtHist = $db->prepare("
                    INSERT INTO historico (usuario_id, defeito_id, componente_id, acertou, pontos, modo)
                    VALUES (?, ?, ?, 0, ?, 'diario')
                ");
                $stmtHist->execute([
                    $usuarioId,
                    $defeitoId,
                    $componenteId,
                    $pontosAtuais
                ]);
                $db->commit();
            }

            http_response_code(200);
            echo json_encode([
                "resultado" => "erro",
                "mensagem" => $resposta['mensagem'],
                "pontos" => $pontosAtuais,
                "modo" => "diario",
                "componente" => $componenteNome,
                "componente_id" => $componenteId
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro em verificar.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "erro",
        "mensagem" => "Ocorreu um erro interno ao processar a validação do componente.",
        "detalhe" => defined('TEST_MODE') ? $e->getMessage() : null,
        "modo" => $modo
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

