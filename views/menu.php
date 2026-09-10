<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

$rankingDiario = [];
$rankingInfinito = [];
$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
$diarioConcluidoHoje = false;
$usuario_id = $_SESSION['id'] ?? null;

try {
    $db = (new Database())->conectar();

    $rankingDiario = $db->query("
        SELECT 
            u.id AS usuario_id,
            u.nome, 
            r.pontuacao,
            ROW_NUMBER() OVER (ORDER BY r.pontuacao DESC, r.id ASC) AS posicao
        FROM ranking_diario r
        INNER JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.data_jogo = DATE('now')
        ORDER BY r.pontuacao DESC, r.id ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rankingInfinito = $db->query("
        SELECT 
            u.id AS usuario_id,
            u.nome, 
            r.pontuacao,
            ROW_NUMBER() OVER (ORDER BY r.pontuacao DESC, r.id ASC) AS posicao
        FROM ranking_infinito r
        INNER JOIN usuarios u ON u.id = r.usuario_id
        ORDER BY r.pontuacao DESC, r.id ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($usuario_id !== null) {
        $stmtTema = $db->prepare("SELECT tema FROM usuarios WHERE id = ?");
        $stmtTema->execute([$usuario_id]);
        $userRow = $stmtTema->fetch(PDO::FETCH_ASSOC);
        if (!empty($userRow['tema'])) {
            $temaAtual = $userRow['tema'];
            $_SESSION['tema'] = $temaAtual;
        }

        // Verificar se o jogador já concluiu o desafio diário hoje
        $stmtDiario = $db->prepare("
            SELECT COUNT(*) AS total 
            FROM ranking_diario 
            WHERE usuario_id = ? AND data_jogo = DATE('now')
        ");
        $stmtDiario->execute([$usuario_id]);
        $diarioRow = $stmtDiario->fetch(PDO::FETCH_ASSOC);
        $diarioConcluidoHoje = ($diarioRow && (int)$diarioRow['total'] > 0);
    }
} catch (Exception $e) {
    error_log("Erro ao carregar dados do menu: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inteldle</title>

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="<?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <header>
    <div class="logo">Inteldle</div>

    <div class="controles-header">
      <button class="btn-toggle-tema" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
      
      <?php if (isset($_SESSION['id'])): ?>
        <div class="usuario-logado">
          Olá, <?= htmlspecialchars($_SESSION['nome']) ?>
          <a href="perfil.php" class="botao" style="padding: 5px 14px; font-size: 14px;" title="Ver Perfil e Histórico">Meu Perfil</a>
          <?php if (ehAdmin()): ?>
            <a href="../admin/index.php" class="botao" style="padding: 5px 14px; font-size: 14px;" title="Área de Administração">Painel Admin</a>
          <?php endif; ?>
          <a href="../api/logout.php" class="botao" style="padding: 5px 14px; font-size: 14px;">Sair</a>
        </div>
      <?php else: ?>
        <button class="botao"
          onclick="abrirModal('modalLogin')">
          Login / Cadastro
        </button>
      <?php endif; ?>
    </div>
  </header>

  <h1>Simulador de Erros</h1>

  <div class="container">

    <div class="painel-tecnicos">

      <p class="titulo-painel">
        Técnicos Diários
      </p>

      <ul class="lista-scores">

        <?php if (empty($rankingDiario)): ?>
          <li class="item-score item-score-vazio">
            <span>Nenhum score hoje</span>
          </li>
        <?php else: ?>
          <?php foreach ($rankingDiario as $player): ?>
            <li class="item-score">
              <span class="jogador-info">
                <strong class="posicao-rank">#<?= (int)$player['posicao'] ?></strong>
                <span class="nome-jogador" title="<?= htmlspecialchars($player['nome']) ?>"><?= htmlspecialchars($player['nome']) ?></span>
              </span>
              <span class="score score-diario">
                <?= (int)$player['pontuacao'] ?> pts
              </span>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>

      </ul>

    </div>

    <div class="menu-central">

      <a href="diario.php" class="botao botao-menu-diario <?= $diarioConcluidoHoje ? 'diario-concluido' : '' ?>">
        Defeito Diário
        <?php if ($diarioConcluidoHoje): ?>
          <span class="badge-diario-concluido">✓ Concluído hoje</span>
        <?php endif; ?>
      </a>

      <a href="infinito.php" class="botao">
        Defeitos Infinitos
      </a>

      <a href="Creditos.html" class="botao">
        Créditos
      </a>

      <a href="tutorial.php" class="botao">
        Como Jogar
      </a>

    </div>

    <div class="painel-tecnicos">

      <p class="titulo-painel">
        Técnicos Infinitos
      </p>

      <ul class="lista-scores">

        <?php if (empty($rankingInfinito)): ?>
          <li class="item-score item-score-vazio">
            <span>Nenhum score ainda</span>
          </li>
        <?php else: ?>
          <?php foreach ($rankingInfinito as $player): ?>
            <li class="item-score">
              <span class="jogador-info">
                <strong class="posicao-rank">#<?= (int)$player['posicao'] ?></strong>
                <span class="nome-jogador" title="<?= htmlspecialchars($player['nome']) ?>"><?= htmlspecialchars($player['nome']) ?></span>
              </span>
              <span class="score score-infinito">
                <?= (int)$player['pontuacao'] ?> <?= (int)$player['pontuacao'] === 1 ? 'acerto' : 'acertos' ?> 🔥
              </span>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>

      </ul>

    </div>

  </div>

  <div class="modal-overlay" id="modalLogin">

    <div class="modal-content">

      <span class="fechar-modal"
        onclick="fecharModal('modalLogin')">
        &times;
      </span>

      <h2 id="modalTitulo">Login</h2>

      <input type="text"
        id="nome"
        placeholder="Nome"
        style="display:none;">

      <input type="email"
        id="email"
        placeholder="Email">

      <input type="password"
        id="senha"
        placeholder="Senha">

      <p id="erroLogin" class="mensagem-erro"></p>

      <button class="botao"
        id="btnAuthSubmit"
        onclick="enviarFormulario()">
        Entrar
      </button>

      <p id="toggleTexto"
        onclick="toggleModo()">
        Ainda não tem conta? <span class="destaque-link">Cadastre-se</span>
      </p>

    </div>

  </div>

  <div class="modal-overlay" id="modalOpcoes">

    <div class="modal-content">

      <span class="fechar-modal"
        onclick="fecharModal('modalOpcoes')">
        &times;
      </span>

      <h2>Opções</h2>

      <div class="container-opcoes">
        <p class="subtitulo-opcoes">Tema da Interface</p>
        <div class="grupo-botoes-tema">
          <button type="button" class="btn-opcao-tema <?= $temaAtual === 'escuro' ? 'selecionado' : '' ?>" id="btnTemaEscuro" onclick="alterarTema('escuro')">
            <span class="icone-tema">🌙</span> Escuro
          </button>
          <button type="button" class="btn-opcao-tema <?= $temaAtual === 'claro' ? 'selecionado' : '' ?>" id="btnTemaClaro" onclick="alterarTema('claro')">
            <span class="icone-tema">☀️</span> Claro
          </button>
        </div>
      </div>

    </div>

  </div>

  <script src="../assets/js/menu.js"></script>

</body>

</html>
