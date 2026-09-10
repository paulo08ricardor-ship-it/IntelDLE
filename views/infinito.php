<?php
require_once '../config/session.php';
require_once '../config/database.php';

$id_usuario = $_SESSION['id'] ?? null;

$db = (new Database())->conectar();

$defeito = $db->query("SELECT * FROM defeitos ORDER BY RANDOM() LIMIT 1;")
              ->fetch(PDO::FETCH_ASSOC);

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
$recorde = 0;
if ($id_usuario !== null) {
    $stmtRecorde = $db->prepare("SELECT pontuacao FROM ranking_infinito WHERE usuario_id = ? ORDER BY pontuacao DESC LIMIT 1");
    $stmtRecorde->execute([$id_usuario]);
    $rowRecorde = $stmtRecorde->fetch(PDO::FETCH_ASSOC);
    if ($rowRecorde) {
        $recorde = (int) $rowRecorde['pontuacao'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modo Infinito - Inteldle</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/jogo.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="<?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>" data-modo="infinito" data-defeito="<?= htmlspecialchars($defeito['id'] ?? '') ?>" data-recorde="<?= $recorde ?>">

  <header class="header-jogo">
    <div class="header-jogo-esquerda">
      <a href="menu.php" class="botao-voltar">← Voltar ao Menu</a>
    </div>

    <div class="header-jogo-centro">
      <div class="hud-item hud-item-vidas" title="Vidas restantes no Desafio Infinito">
          <span class="hud-rotulo">Vidas:</span>
          <span class="hud-valor" id="hudVidas">❤️❤️❤️</span>
        </div>
      <div class="badge-modo">Modo Infinito</div>
      <div class="hud-container">
        
        <div class="hud-item" title="Sua sequência atual de acertos sem errar">
          <span class="hud-rotulo">Sequência:</span>
          <span class="hud-valor" id="hudStreak">0</span>
          <span class="hud-icone">🔥</span>
        </div>
        <div class="hud-item" title="Seu maior recorde de acertos consecutivos">
          <span class="hud-rotulo">Recorde:</span>
          <span class="hud-valor" id="hudRecorde"><?= $recorde ?></span>
          <span class="hud-icone">🏆</span>
        </div>
      </div>
    </div>

    <div class="header-jogo-direita">
      <button class="btn-toggle-tema-jogo" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
    </div>
  </header>

  <?php require_once __DIR__ . '/components/simulador_jogo.php'; ?>

  <script src="../assets/js/diagnostico.js"></script>
  <script src="../assets/js/jogo.js"></script>

</body>

</html>
