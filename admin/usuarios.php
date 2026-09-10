<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();
$adminLogadoId = (int) $_SESSION['id'];

$stmt = $db->query("
    SELECT u.id, u.nome, u.email, u.tipo, u.tema, u.criado_em,
           (SELECT COUNT(*) FROM historico h WHERE h.usuario_id = u.id) AS total_partidas,
           (SELECT MAX(pontuacao) FROM ranking_infinito ri WHERE ri.usuario_id = u.id) AS recorde_infinito,
           (SELECT MAX(pontuacao) FROM ranking_diario rd WHERE rd.usuario_id = u.id) AS recorde_diario
    FROM usuarios u 
    ORDER BY u.id ASC
");
$usuarios = $stmt->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Usuários & RBAC - Inteldle Admin</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="admin-body <?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <!-- Header Administrativo -->
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

  <!-- Subnav -->
  <nav class="admin-subnav">
    <a href="index.php" class="admin-nav-tab">Visão Geral</a>
    <a href="defeitos.php" class="admin-nav-tab">Defeitos</a>
    <a href="componentes.php" class="admin-nav-tab">Componentes</a>
    <a href="solucoes.php" class="admin-nav-tab">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab active">Usuários & Permissões</a>
  </nav>

  <!-- Container -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Usuários & Controle de Acesso (RBAC)</h1>
        <div class="admin-subtitulo-secao">
          Controle de contas, definição de administradores e gestão de credenciais.
        </div>
      </div>
      <button class="btn-admin-acao btn-admin-destaque" onclick="abrirModalCriar()">+ Novo Usuário</button>
    </div>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th>Nome do Jogador</th>
            <th>E-mail</th>
            <th style="width: 140px;">Nível de Acesso (RBAC)</th>
            <th style="width: 100px;">Partidas</th>
            <th style="width: 140px;">Recordes</th>
            <th style="width: 120px;">Criado em</th>
            <th style="width: 120px; text-align: center;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><strong>#<?= $u['id'] ?></strong></td>
              <td>
                <strong><?= htmlspecialchars($u['nome']) ?></strong>
                <?php if ((int)$u['id'] === $adminLogadoId): ?>
                  <span style="font-size: 11px; color: var(--cor-texto-principal); font-weight: bold;">(Você)</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge-status <?= $u['tipo'] === 'admin' ? 'badge-hardware' : '' ?>" style="border-color: <?= $u['tipo'] === 'admin' ? 'var(--cor-borda-principal)' : 'var(--cor-borda-score)' ?>;">
                  <?= strtoupper($u['tipo']) ?>
                </span>
              </td>
              <td><?= $u['total_partidas'] ?> jogos</td>
              <td style="font-size: 12px;">
                D: <?= (int)$u['recorde_diario'] ?> pts<br>
                I: <?= (int)$u['recorde_infinito'] ?> 🔥
              </td>
              <td style="font-size: 12px;"><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
              <td style="text-align: center;">
                <div class="acoes-tabela" style="justify-content: center;">
                  <button class="btn-acao-icone" 
                          onclick='abrirModalEditar(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                          title="Editar">Editar</button>
                  <?php if ((int)$u['id'] !== $adminLogadoId): ?>
                    <button class="btn-acao-icone excluir" 
                            onclick="excluirRegistro('../api/admin_usuarios.php', <?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['nome'])) ?>')" 
                            title="Excluir">Excluir</button>
                  <?php else: ?>
                    <button class="btn-acao-icone" disabled title="Não é permitido excluir a própria conta logada" style="opacity: 0.3; cursor: not-allowed;">—</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>

  <!-- Modal Adicionar / Editar Usuário -->
  <div class="modal-admin-overlay" id="modalUsuario">
    <div class="modal-admin-content">
      <span class="fechar-modal" onclick="fecharModalAdmin('modalUsuario')">&times;</span>
      <h2 class="modal-admin-titulo" id="modalUserTitulo">Novo Usuário</h2>

      <form id="formUsuario" onsubmit="event.preventDefault(); salvarRegistro('../api/admin_usuarios.php', 'formUsuario', 'modalUsuario');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
        <input type="hidden" name="id" id="user_id">

        <div class="form-grupo">
          <label class="form-label" for="nome">Nome Completo</label>
          <input type="text" name="nome" id="nome" class="form-input" placeholder="Ex: Roberto Carlos" required>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="email">E-mail</label>
          <input type="email" name="email" id="email" class="form-input" placeholder="Ex: usuario@inteldle.com" required>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="tipo">Papel no Sistema (RBAC)</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="usuario">Jogador Comum (usuario)</option>
            <option value="admin">Administrador Geral (admin)</option>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="senha">
            Senha <span id="labelSenhaObs" style="font-size: 11px; text-transform: none; opacity: 0.8;">(Mínimo 6 caracteres)</span>
          </label>
          <input type="password" name="senha" id="senha" class="form-input" placeholder="Digite a senha...">
        </div>

        <div class="form-rodape">
          <button type="button" class="btn-admin-acao" onclick="fecharModalAdmin('modalUsuario')">Cancelar</button>
          <button type="submit" class="btn-admin-acao btn-admin-destaque">Salvar Usuário</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    function abrirModalCriar() {
      document.getElementById('formUsuario').reset();
      document.getElementById('user_id').value = '';
      document.getElementById('senha').required = true;
      document.getElementById('labelSenhaObs').innerText = '(Obrigatório - Mínimo 6 caracteres)';
      document.getElementById('modalUserTitulo').innerText = 'Novo Usuário';
      abrirModalAdmin('modalUsuario');
    }

    function abrirModalEditar(u) {
      document.getElementById('formUsuario').reset();
      document.getElementById('user_id').value = u.id;
      document.getElementById('nome').value = u.nome;
      document.getElementById('email').value = u.email;
      document.getElementById('tipo').value = u.tipo;
      document.getElementById('senha').required = false;
      document.getElementById('labelSenhaObs').innerText = '(Deixe em branco para manter a senha atual)';
      document.getElementById('modalUserTitulo').innerText = 'Editar Usuário #' + u.id;
      abrirModalAdmin('modalUsuario');
    }
  </script>
</body>
</html>
