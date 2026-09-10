<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

$id_usuario = $_SESSION['id'] ?? null;
$db = (new Database())->conectar();

// 1. Seleção diária determinística do defeito baseada na data de hoje
$todosDefeitos = $db->query("SELECT * FROM defeitos ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalDefeitos = count($todosDefeitos);
$defeito = null;

if ($totalDefeitos > 0) {
    $hojeStr = date('Y-m-d');
    $indiceHoje = abs(crc32($hojeStr)) % $totalDefeitos;
    $defeito = $todosDefeitos[$indiceHoje];
}

$ja_jogou = false;
$dadosConclusao = null;

if ($id_usuario !== null) {
    // 2. Consulta posição, pontuação e total de jogadores de hoje
    $stmtRank = $db->prepare("
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
        WHERE r.usuario_id = :id_do_usuario
    ");
    $stmtRank->execute([':id_do_usuario' => $id_usuario]);
    $rankInfo = $stmtRank->fetch(PDO::FETCH_ASSOC);

    if ($rankInfo) {
        $ja_jogou = true;

        // Buscar informações do defeito resolvido e solução no histórico de hoje
        $stmtHist = $db->prepare("
            SELECT 
                h.pontos,
                d.titulo AS defeito_titulo,
                d.imagem AS defeito_imagem,
                c.nome AS componente_correto_nome,
                s.mensagem AS solucao_mensagem
            FROM historico h
            INNER JOIN defeitos d ON d.id = h.defeito_id
            LEFT JOIN componentes c ON c.id = h.componente_id
            LEFT JOIN solucoes s ON s.defeito_id = h.defeito_id AND s.componente_id = h.componente_id
            WHERE h.usuario_id = :id_usuario
              AND h.modo = 'diario'
              AND DATE(h.data_partida) = DATE('now')
              AND h.acertou = 1
            ORDER BY h.id DESC
            LIMIT 1
        ");
        $stmtHist->execute([':id_usuario' => $id_usuario]);
        $histInfo = $stmtHist->fetch(PDO::FETCH_ASSOC);

        $defeitoTitulo = $histInfo['defeito_titulo'] ?? ($defeito['titulo'] ?? 'Defeito do Dia');
        $defeitoImagem = $histInfo['defeito_imagem'] ?? ($defeito['imagem'] ?? 'monitor.png');
        $componenteNome = $histInfo['componente_correto_nome'] ?? '';
        $solucaoMensagem = $histInfo['solucao_mensagem'] ?? '';

        // Fallback: se não estiver no histórico detalhado, busca a solução oficial do defeito do dia
        if (empty($componenteNome) && !empty($defeito['id'])) {
            $stmtSol = $db->prepare("
                SELECT c.nome, s.mensagem
                FROM solucoes s
                INNER JOIN componentes c ON c.id = s.componente_id
                WHERE s.defeito_id = ? AND s.correto = 1
                LIMIT 1
            ");
            $stmtSol->execute([$defeito['id']]);
            $solRow = $stmtSol->fetch(PDO::FETCH_ASSOC);
            if ($solRow) {
                $componenteNome = $solRow['nome'];
                $solucaoMensagem = $solRow['mensagem'];
            }
        }

        $dadosConclusao = [
            'pontuacao' => (int) $rankInfo['pontuacao'],
            'posicao' => (int) $rankInfo['posicao'],
            'total_jogadores' => (int) $rankInfo['total_jogadores'],
            'defeito_titulo' => $defeitoTitulo,
            'defeito_imagem' => $defeitoImagem,
            'componente_correto_nome' => $componenteNome,
            'solucao_mensagem' => $solucaoMensagem
        ];
    }
}

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
if ($id_usuario !== null) {
    $stmtTema = $db->prepare("SELECT tema FROM usuarios WHERE id = ?");
    $stmtTema->execute([$id_usuario]);
    $userRow = $stmtTema->fetch(PDO::FETCH_ASSOC);
    if (!empty($userRow['tema'])) {
        $temaAtual = $userRow['tema'];
        $_SESSION['tema'] = $temaAtual;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Desafio Diário - Inteldle</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/jogo.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="<?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>" data-modo="diario" data-defeito="<?= htmlspecialchars($defeito['id'] ?? '') ?>" data-ja-jogou="<?= $ja_jogou ? 'true' : 'false' ?>">

  <header class="header-jogo">
    <div class="header-jogo-esquerda">
      <a href="menu.php" class="botao-voltar">← Voltar ao Menu</a>
    </div>

    <div class="header-jogo-centro">
      <div class="badge-modo">Modo Diário</div>
    </div>

    <div class="header-jogo-direita">
      <button class="btn-toggle-tema-jogo" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
    </div>
  </header>

  <?php if ($ja_jogou): ?>
    <?php require_once __DIR__ . '/components/diario_concluido.php'; ?>
  <?php else: ?>
    <?php require_once __DIR__ . '/components/simulador_jogo.php'; ?>
  <?php endif; ?>

  <script src="../assets/js/diagnostico.js"></script>
  <script src="../assets/js/jogo.js"></script>
</body>

</html>