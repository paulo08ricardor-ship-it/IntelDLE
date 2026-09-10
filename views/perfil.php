<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!estaLogado()) {
    header("Location: menu.php");
    exit;
}

$db = (new Database())->conectar();
$usuarioId = (int) $_SESSION['id'];

$stmtUser = $db->prepare("SELECT id, nome, email, tipo, tema, criado_em FROM usuarios WHERE id = ?");
$stmtUser->execute([$usuarioId]);
$usuario = $stmtUser->fetch();

if (!$usuario) {
    header("Location: ../api/logout.php");
    exit;
}

// Estatísticas Pessoais
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) AS total_jogos,
        SUM(CASE WHEN acertou = 1 THEN 1 ELSE 0 END) AS total_acertos,
        (SELECT MAX(pontuacao) FROM ranking_diario WHERE usuario_id = :uid) AS recorde_diario,
        (SELECT MAX(pontuacao) FROM ranking_infinito WHERE usuario_id = :uid) AS recorde_infinito
    FROM historico
    WHERE usuario_id = :uid
");
$stmtStats->execute([':uid' => $usuarioId]);
$stats = $stmtStats->fetch();

$totalJogos = (int) ($stats['total_jogos'] ?? 0);
$totalAcertos = (int) ($stats['total_acertos'] ?? 0);
$taxaAcerto = $totalJogos > 0 ? round(($totalAcertos / $totalJogos) * 100, 1) : 0;

// Histórico de Partidas (3FN)
$stmtHist = $db->prepare("
    SELECT 
        h.id, h.acertou, h.pontos, h.modo, h.data_partida,
        d.titulo AS defeito_titulo, d.imagem AS defeito_imagem,
        c.nome AS componente_nome
    FROM historico h
    INNER JOIN defeitos d ON d.id = h.defeito_id
    LEFT JOIN componentes c ON c.id = h.componente_id
    WHERE h.usuario_id = ?
    ORDER BY h.id DESC
    LIMIT 30
");
$stmtHist->execute([$usuarioId]);
$historico = $stmtHist->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu Perfil & Histórico - Inteldle</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="<?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <header>
    <div class="logo">Inteldle</div>

    <div class="controles-header">
      <button class="btn-toggle-tema" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
      <a href="menu.php" class="botao" style="padding: 8px 18px; font-size: 15px;">← Voltar ao Menu</a>
      <a href="../api/logout.php" class="botao" style="padding: 8px 18px; font-size: 15px;">Sair</a>
    </div>
  </header>

  <div class="admin-container" style="margin-top: 30px;">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao" style="margin-top: 0;">
          👤 Perfil do Jogador: <?= htmlspecialchars($usuario['nome']) ?>
        </h1>
        <div class="admin-subtitulo-secao">
          E-mail: <strong><?= htmlspecialchars($usuario['email']) ?></strong> | 
          Membro desde: <strong><?= date('d/m/Y', strtotime($usuario['criado_em'])) ?></strong> | 
          Função: <span class="badge-tipo <?= $usuario['tipo'] === 'admin' ? 'badge-admin' : 'badge-usuario' ?>"><?= strtoupper($usuario['tipo']) ?></span>
        </div>
      </div>
      <?php if (ehAdmin()): ?>
        <a href="../admin/index.php" class="btn-admin-acao btn-admin-destaque">⚙️ Acessar Painel Admin</a>
      <?php endif; ?>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-icone">🎮</div>
        <div class="kpi-valor"><?= $totalJogos ?></div>
        <div class="kpi-rotulo">Partidas Jogadas</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">🎯</div>
        <div class="kpi-valor"><?= $taxaAcerto ?>%</div>
        <div class="kpi-rotulo">Taxa de Acertos (<?= $totalAcertos ?> / <?= $totalJogos ?>)</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">📅</div>
        <div class="kpi-valor"><?= (int)($stats['recorde_diario'] ?? 0) ?> pts</div>
        <div class="kpi-rotulo">Recorde Diário</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">🔥</div>
        <div class="kpi-valor"><?= (int)($stats['recorde_infinito'] ?? 0) ?> acertos</div>
        <div class="kpi-rotulo">Recorde Sequência (Infinito)</div>
      </div>
    </div>

    <!-- Tabela de Histórico de Partidas -->
    <div class="admin-header-secao" style="margin-top: 40px;">
      <h2 style="color: var(--cor-texto-destaque); margin: 0;">📜 Histórico Recente de Diagnósticos</h2>
    </div>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th>Data / Hora</th>
            <th>Modo</th>
            <th>Defeito Enfrentado</th>
            <th>Ação Escolhida</th>
            <th>Resultado</th>
            <th>Pontos / Streak</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($historico)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 30px; opacity: 0.7;">
                Você ainda não jogou nenhuma partida. Comece um desafio diário ou infinito!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($historico as $p): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($p['data_partida'])) ?></td>
                <td>
                  <span class="badge-status <?= $p['modo'] === 'diario' ? 'badge-hardware' : 'badge-software' ?>">
                    <?= strtoupper($p['modo']) ?>
                  </span>
                </td>
                <td>
                  <strong><?= htmlspecialchars($p['defeito_titulo']) ?></strong>
                </td>
                <td>
                  <?= !empty($p['componente_nome']) ? htmlspecialchars($p['componente_nome']) : '—' ?>
                </td>
                <td>
                  <?php if ((int)$p['acertou'] === 1): ?>
                    <span class="badge-status badge-correto">✓ Resolvido</span>
                  <?php else: ?>
                    <span class="badge-status badge-incorreto">✗ Erro</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color: var(--cor-texto-principal);">
                    <?= $p['modo'] === 'infinito' ? $p['pontos'] . ' 🔥' : $p['pontos'] . ' pts' ?>
                  </strong>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

</body>
</html>
