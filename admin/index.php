<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();

// Consultas de KPIs
$totalDefeitos = $db->query("SELECT COUNT(*) FROM defeitos")->fetchColumn();
$totalComponentes = $db->query("SELECT COUNT(*) FROM componentes")->fetchColumn();
$totalSolucoes = $db->query("SELECT COUNT(*) FROM solucoes")->fetchColumn();
$totalTestes = $db->query("SELECT COUNT(*) FROM componentes_testes")->fetchColumn();
$totalUsuarios = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalPartidas = $db->query("SELECT COUNT(*) FROM historico")->fetchColumn();

// Partidas Recentes
$ultimasPartidas = $db->query("
    SELECT h.*, u.nome AS usuario_nome, d.titulo AS defeito_titulo, c.nome AS componente_nome
    FROM historico h
    INNER JOIN usuarios u ON u.id = h.usuario_id
    INNER JOIN defeitos d ON d.id = h.defeito_id
    LEFT JOIN componentes c ON c.id = h.componente_id
    ORDER BY h.id DESC
    LIMIT 6
")->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Painel Administrativo - Inteldle</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="admin-body <?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <!-- Header Administrativo com estilo do Menu -->
  <header class="admin-navbar">
    <div class="admin-logo-area">
      <div class="admin-logo-texto">Inteldle</div>
      <span class="admin-badge-topo">Área Administrativa</span>
    </div>

    <div class="admin-user-nav">
      <button class="btn-toggle-tema" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
      <span class="admin-nome-user">Olá, <?= htmlspecialchars($_SESSION['nome']) ?></span>
      <a href="../views/menu.php" class="btn-admin-acao">← Voltar ao Jogo</a>
      <a href="../api/logout.php" class="btn-admin-acao btn-admin-perigo">Sair</a>
    </div>
  </header>

  <!-- Subnav de Abas estilo Inteldle -->
  <nav class="admin-subnav">
    <a href="index.php" class="admin-nav-tab active">Visão Geral</a>
    <a href="defeitos.php" class="admin-nav-tab">Defeitos</a>
    <a href="componentes.php" class="admin-nav-tab">Componentes</a>
    <a href="solucoes.php" class="admin-nav-tab">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab">Usuários & Permissões</a>
  </nav>

  <!-- Conteúdo Principal -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Painel de Controle</h1>
        <div class="admin-subtitulo-secao">
          Gerencie o banco de dados normalizado e acompanhe as estatísticas do sistema.
        </div>
      </div>
    </div>

    <!-- KPIs do Sistema -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-icone">⚠️</div>
        <div class="kpi-valor"><?= $totalDefeitos ?></div>
        <div class="kpi-rotulo">Defeitos Cadastrados</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">🧩</div>
        <div class="kpi-valor"><?= $totalComponentes ?></div>
        <div class="kpi-rotulo">Componentes</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">💡</div>
        <div class="kpi-valor"><?= $totalSolucoes ?></div>
        <div class="kpi-rotulo">Soluções Vinculadas</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">🔬</div>
        <div class="kpi-valor"><?= $totalTestes ?></div>
        <div class="kpi-rotulo">Testes & Sondas</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">👥</div>
        <div class="kpi-valor"><?= $totalUsuarios ?></div>
        <div class="kpi-rotulo">Usuários no Sistema</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icone">🎮</div>
        <div class="kpi-valor"><?= $totalPartidas ?></div>
        <div class="kpi-rotulo">Partidas no Histórico</div>
      </div>
    </div>

    <!-- Atalhos Rápidos para os CRUDs -->
    <h2 style="color: var(--cor-texto-principal); margin-top: 30px; margin-bottom: 15px; font-size: 22px;">
      Módulos de Gestão
    </h2>

    <div class="grid-atalhos-admin">
      <a href="defeitos.php" class="card-atalho">
        <div class="card-atalho-titulo">Defeitos</div>
        <div class="card-atalho-desc">
          Cadastre novos problemas, edite enunciados, vincule imagens e configure os cenários de diagnóstico.
        </div>
        <div class="card-atalho-link">Acessar Defeitos →</div>
      </a>

      <a href="componentes.php" class="card-atalho">
        <div class="card-atalho-titulo">Componentes</div>
        <div class="card-atalho-desc">
          Gerencie o catálogo de peças de hardware e ferramentas de software disponíveis para os técnicos.
        </div>
        <div class="card-atalho-link">Acessar Componentes →</div>
      </a>

      <a href="solucoes.php" class="card-atalho">
        <div class="card-atalho-titulo">Soluções</div>
        <div class="card-atalho-desc">
          Associe quais componentes resolvem cada defeito e defina as mensagens explicativas com o diagnóstico.
        </div>
        <div class="card-atalho-link">Acessar Soluções →</div>
      </a>

      <a href="diagnosticos.php" class="card-atalho">
        <div class="card-atalho-titulo">Diagnósticos & Testes</div>
        <div class="card-atalho-desc">
          Configure a suíte de testes de hardware, software, rede e segurança com comandos de sonda e telemetrias.
        </div>
        <div class="card-atalho-link">Acessar Diagnósticos →</div>
      </a>

      <a href="usuarios.php" class="card-atalho">
        <div class="card-atalho-titulo">Usuários & RBAC</div>
        <div class="card-atalho-desc">
          Administre contas de jogadores, promova administradores, redefina senhas e controle a segurança.
        </div>
        <div class="card-atalho-link">Acessar Usuários →</div>
      </a>
    </div>

    <!-- Atividade Recente no Jogo -->
    <h2 style="color: var(--cor-texto-principal); margin-top: 40px; margin-bottom: 15px; font-size: 22px;">
      Últimas Partidas Registradas no Histórico
    </h2>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th>Jogador</th>
            <th>Defeito</th>
            <th>Ação Testada</th>
            <th>Modo</th>
            <th>Resultado</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ultimasPartidas)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 25px; opacity: 0.7;">
                Nenhuma partida registrada até o momento.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($ultimasPartidas as $p): ?>
              <tr>
                <td><strong><?= htmlspecialchars($p['usuario_nome']) ?></strong></td>
                <td><?= htmlspecialchars($p['defeito_titulo']) ?></td>
                <td><?= htmlspecialchars($p['componente_nome'] ?? '—') ?></td>
                <td><span class="badge-status <?= $p['modo'] === 'diario' ? 'badge-hardware' : 'badge-software' ?>"><?= strtoupper($p['modo']) ?></span></td>
                <td>
                  <?php if ((int)$p['acertou'] === 1): ?>
                    <span class="badge-status badge-correto">✓ Acertou</span>
                  <?php else: ?>
                    <span class="badge-status badge-incorreto">✗ Errou</span>
                  <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($p['data_partida'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

  <script src="../assets/js/admin.js"></script>
</body>
</html>
